<?php

class ImageViewer {

  public string $pid;

  public bool $plain=false;

  function __construct( string $pid ) {

    if (!$pid)
      throw new Exception('No PID (National Library image ID) provided');

    $this->pid = $pid;

  }

  function renderAjax() {
    $this->plain = true;

    // to avoid HTMX showing whole-template errors, we catch them here
    try {

      // colspan should be provided by an htmx extension
      $colspan = $_GET['colspan'] ?? 5;

      $html = '<tr id="viewer_'.$this->pid.'" class="viewer"><td colspan="'.$colspan.'">';
        $html .= $this->render();
      $html .= '</td></tr>';

      return $html;

    } catch (Exception $e) {

      $html = '<tr id="viewer_'.$this->pid.'" class="viewer"><td colspan="'.$colspan.'">';
        $html .= $e->getMessage();
      $html .= '</td></tr>';

      return $html;

    }

  }

  function renderPage() {
    return Template::build( $this->render(), $this->page_title );
  }



  function render() {

    $html = '';  
    
    // $html .= '<iframe src="https://ndhadeliver.natlib.govt.nz/delivery/DeliveryManagerServlet?dps_func=thumbnail&dps_pid=IE19454691"></iframe>';    

    $this->spider = new Spider;

    // Given a DPS PID, collect the raw HTML for the viewer
    $original_url = 'https://ndhadeliver.natlib.govt.nz/delivery/DeliveryManagerServlet?dps_pid='.$this->pid;
    $raw_html = $this->spider->fetch( $original_url, 'viewer_raw' );
    // print_r( $this->spider->curl_info );
    // echo $raw_html;
    
    // The raw HTML contains an iFrame, we need to find its URL
    preg_match('/<iframe src="([^"]+)"/', $raw_html, $iframe);
    $iframe_url = html_entity_decode( $iframe[1] );

    // stop here if we didn't find an iframe URL (imPerva / incapsula blocked)
    if (!$iframe_url)
      return '<h3 class="error">Sorry, media can no longer be embedded directly.  Visit the '.makeLink($original_url, 'Archives NZ media page').' instead.<h3>';

    // Stop here if the iframe points to an error page, eg, IE77279384
    if ( stristr($iframe_url, 'deliveryError.jsp') )
      return '<h3 class="error">Sorry, this record cannot be viewed due to an error in the Archives NZ viewer</h3><p>Visit the '.makeLink($original_url, 'Archives NZ viewer page').' for more information</p>';

    // Stop here if the iframe points to an access / login page
    if ( stristr($iframe_url, 'deliveryNotAllow.jsp') )
      return '<h3 class="error">Sorry, this record cannot be viewed due to access restrictions</h3><p>Visit the '.makeLink($original_url, 'Archives NZ viewer page').' for more information</p>';

    // it's handy to extract the value of dps_dvs, a millisecond unix epoch used for caching
    $dps_dvs = $this->extractDpsDvs( $iframe_url );
    // echo $dps_dvs.'<br />';

    // now we can collect the iframe contents and extract the image IDs
    $viewer_html = $this->spider->fetch( $iframe_url, 'viewer_iframe' );
    // viewCode( $viewer_html );

    // get the title of the viewer page
    preg_match('/<title>(.*?)<\/title>/', $viewer_html, $matches);
    $original_title = $matches[1];

    // record this for passing to template()
    $this->page_title = 'Media Viewer: '.$original_title;

    if ( !$this->plain )
      $html .= '<h2>Media '.makeLink( getViewerURL($this->pid), $original_title).'</h2>';

    // we may have multiple image trees (ie, table of contents / derivative copies like a PDF)
    // eg http://archway/viewer/?IE78515056
    $image_trees = $this->extractTree( $viewer_html );
    // print_r( $image_trees );

    // these part seems overly complex ....
    $pdf_embed = '';
    $gallery_size = 0;
    $gallery_embed = '';
    $generic_viewer = '';

    $tree_counter = 0;

    foreach($image_trees as $image_tree) {
      // print_r( $image_tree );

      if ( !$image_tree || empty($image_tree[0]) )
        return false;   // this should never happen

      // right now we have no metadata so we don't know what kind of files we are dealing with here
      $metadata = false;
      $metadata = $this->checkMetaData( $dps_dvs, $image_tree[0] );

      // sometimes we just get no metadata
      if (!$metadata) {

        // so without metadata, we just ... kind of assume its a set of images?  Seems odd
        $gallery_size = count($image_tree);
        $gallery_embed .= $this->generateGallery($image_tree);

      } else {

        $file_type = $this->extractMetadata($metadata, 'File Extension');
        $file_name = $this->extractMetadata($metadata, 'File Original Name');
        $file_size = $this->extractMetadata($metadata, 'File Size');

        if (!$file_name)
          $file_name = $original_title;

        $ndha = 'https://ndhadeliver.natlib.govt.nz/delivery/';
        $stream_url = $ndha.'StreamGate?dps_dvs='.$dps_dvs.'&dps_pid='.$image_tree[0];
        $download_url = $ndha.'DeliveryManagerServlet?dps_pid='.$image_tree[0].'&dps_func=download';

        switch ($file_type) {

          case 'pdf':
            $pdf_embed .= $this->embedPDF( $file_name, $download_url, $file_size );
            break;

          case 'jpg':
            $gallery_size = count($image_tree);
            $gallery_embed .= $this->generateGallery($image_tree);
            break;

          case 'mp4':
            $generic_viewer .= '<video controls><source src="'.$stream_url.'"></video><br />';
            break;

          case 'mp3':
            $generic_viewer .= '<audio controls class="center"><source src="'.$stream_url.'"></audio>';
            break;

          case 'epub':
            $generic_viewer .= genericInfo('<h3>Epub Viewer</h3>', '<h3>'.makeLink($original_url, $file_name).'</h3>' );
            break;

          case 'jp2':
            $generic_viewer .= genericInfo('<h3>Image Viewer</h3>', '<h3>'.makeLink($original_url, $file_name, 'This image is a JPEG 2000 file, which cannot be viewed directly in most browsers.  Click here to view the image on the Archives NZ file viewer').'</h3>' );
            break;

          // remainder should be mostly DOCuments and RTF files etc
          default:
            $label = '<h3>'.makeLink($download_url, $file_name).'</h3>';
            if ($file_size)
              $label .='('.$file_size.')';
            $generic_viewer .= genericInfo('<h3>Download</h3>', $label );

        } // end of switch

      } // end of metadata if/else

      $tree_counter++;

    } // end of foreach image trees


    // Gallery vs PDF!

    // the problem: some items have hundreds of scanned pages, and this overwhelms the light gallery and makes the thumbnail server cry.
    // The solution: if we have a PDF version, omit the gallery

    // if there is no PDF version, or there are less than 30 images, show the gallery
    if (!$pdf_embed || $gallery_size < 30)
      $html .= $gallery_embed;

    // always show PDF embed button if available
    $html .= $pdf_embed;

    // and always show other image formats
    $html .= $generic_viewer;

    // Download link for Zipped Images
    // TODO: is there a download All for other things, like PDFs?
    // this turns out to be quite fragile, heavily dependent on dps_dvs
    // possibly confused by cookies containing the dps_dvs???
    
    // WAIT - turns out NatLib sends a new dps_dvs specifically for the FL id, when the viewer hits Seadragon to get the first image, eg see set-cookie in the header here:
    // https://ndhadeliver.natlib.govt.nz/delivery/DeliveryManagerServlet?embedded=true&toolbar=false&from_mets=true&dps_pid=FL62696808&dps_dvs=1700098947564~43
    if ($_SERVER['REMOTE_ADDR'] == '127.0.0.1') {
    
      $zip_url = $this->getZipURL($viewer_html, $dps_dvs);
      $html .= genericInfo('Download', makeLink($zip_url, 'All files (ZIP)', 'Download Zip file of all files') );
          
      $pdf_download = '<a href="/ajax/pdf/'.$this->pid.'?zip_url='.urlencode($zip_url).'" hx="boost">Test PDF download</a>';    
          
      $html .= genericInfo('TEST', $pdf_download);    
                          
    }

    // we also want some other info from this IE record
    // We'll use a regular expression to match the metadata div element and its contents
    preg_match('/<div class="ieMetadata metaData">(.*?)<\/div>/s', $viewer_html, $matches);

    // skip this part if we are in 'plain' mode (ie, an AJAX insert)
    if (!$this->plain) {
        // grab the item code (R-number), series ID, agency code, etc
        $item_code = $this->extractMetadata( $matches[1], 'Item Code' );
        $series_link = linkSeries( $this->extractMetadata( $matches[1], 'Series Code' ) );
        $agency_link = linkAgency( $this->extractMetadata( $matches[1], 'Provenance' ) );

    if ($item_code)
      $html .= genericInfo('Associated Item Code', entityLink($item_code, $item_code) );

    if ($series_link)
      $html .= genericInfo('Series', $series_link );

    if ($agency_link)
      $html .= genericInfo('Agency', $agency_link );

    } // end of not-is-plain if

    // source attribution
    $source_link = makeLink($original_url, '&#127744;&nbsp;Archives NZ', 'Te Rua Mahara o te Kāwanatanga | Archives New Zealand Collection Search');
    $cc_link = makeLink('https://creativecommons.org/licenses/by/2.0/', 'CC BY', 'Creative Commons Attribution 2.0 Generic');
    $html .= genericInfo('Source', $source_link.' / '.$cc_link);

    // ISSUE: Too generic to be useful!
    // $usage = "<p>USAGE AND COPYRIGHT</p>
    // <p>The item you are viewing is made available for personal research purposes only. It must not be reproduced, downloaded, printed, adapted, distributed or published without the permission of Archives New Zealand and, where applicable, the copyright owner.
    // You are responsible for clearing copyright and meeting any other requirements that apply to your proposed use of an item.</p>
    // <p>Under the ".makeLink('https://www.legislation.govt.nz/act/public/1994/0143/latest/DLM345634.html', 'New Zealand Copyright Act 1994')." you may be able to make some limited use of items available from this site (for example, under the fair dealing provisions) without having to obtain the permission of the copyright owner.</p>
    // <p>Please note that other conditions may also apply to the use of some items.</p>
    // <p>More information about copyright and usage can be found on the Archives New Zealand web site. You can also email enquiries to <a href=\"mailto:research.archives@dia.govt.nz\">research.archives@dia.govt.nz</a> or by calling +64-4-4995595.</p>";
    //
    // $html .= genericInfo('Terms of Use', $usage);


    $more_quality_options = '<br /><br />
    <a
      href="/mets/'.$this->pid.'"
      hx-get="/ajax/mets/'.$this->pid.'"
      hx-swap="outerHTML"
    >
      Alternative File Formats / High Quality Options
    </a>';

    if ($_SERVER['REMOTE_ADDR'] == '127.0.0.1')
      $html .= $more_quality_options;

    return $html;

  } // end of render



  function generateGallery( $image_tree ) {

    $html = '';

    $thumb = 'https://ndhadeliver.natlib.govt.nz/delivery/DeliveryManagerServlet?dps_func=thumbnail&dps_pid=';
    $large = 'https://ndhadeliver.natlib.govt.nz/delivery/DeliveryManagerServlet?dps_func=download&dps_pid=';

    // if there is only one image, just show it full size
    if ( count($image_tree) == 1) {

      $html .= '<div class="lightgallery">';
        $html .= '<a data-gallery="'.$this->pid.'" href="'.$large.$image_tree[0].'">';
          $html .= makeImage($large.$image_tree[0], $image_tree[0], 'full-image');
        $html .= '</a>';
      $html .= '</div>';

    // otherwise, we show a grid of thumbnail images
    } else {

      $html .= '<div class="thumb-gallery lightgallery">';

      $counter = 0;
      foreach ($image_tree as $img) {
        $html .= '<a data-gallery="'.$this->pid.'" href="'.$large.$img.'">';

          if ($counter < 30)
            $html .= makeImage($thumb.$img, $img);
          else
            $html .= makeImage('', $img);

        $html .= '</a>';

        $counter++;
      }

      $html .= '</div>';

    }

    return $html;

  } // end of generateGallery



  function embedPDF( string $pdf_filename, string $download_pdf_url, $file_size ) {

    $stream_pdf_url = 'https://ndhadeliver.natlib.govt.nz/NLNZStreamGate/get?dps_pid='.$this->pid;

    $pdf_button = '<button data-iframe="true" data-src="'.$stream_pdf_url.'" class="pdf lightgallery-pdf">'.$pdf_filename.' ('.$file_size.')</button>';

    $html = genericInfo('<b>View</b>', $pdf_button );
    $html .= genericInfo('Download', makeLink($download_pdf_url, $pdf_filename.' ('.$file_size.')', 'Download PDF') );
    $html .= '<hr />';

    return $html;

  } // end of embedPDF




  function extractTree( $html ) {

    $bits = explode("var JSONObject = ", $html);
    $juicy_bits = explode('var jsonObj = JSON.parse(JSONObject)', $bits[1]);

    $trimmed = trim($juicy_bits[0]);      // trim whitespace
    $trimmed = substr($trimmed, 0, -2);   // remove last two characters ';
    $trimmed = substr( $trimmed, 1);      // remove first character '

    $tree_json = json_decode( $trimmed, true );  // make JSON into a PHP array

    $trees = array();
    foreach($tree_json as $tree)
      $trees[] = explode(',', $tree);

    return $trees;

  } // end of extractTree


  function extractDpsDvs( $url ) {

    $url_components = parse_url($url);

    // Extract the query string from the URL
    $query_string = $url_components['query'];

    // Parse the query string into an associative array
    parse_str($query_string, $query_params);

    // Extract the value of the dps_dvs parameter
    return $query_params['dps_dvs'];

  } // end of extractDpsDvs


  function checkMetaData( $dps_dvs, $item_pid ) {

    if (!$item_pid)
      return false;  // should I throw an error here?

    $metadata_url = 'https://ndhadeliver.natlib.govt.nz/delivery/DeliveryManagerServlet?dps_func=metadataHTML&dps_dvs='.$dps_dvs.'&metadata=xsl&dps_pid='.$item_pid;

    // some items timeout without returning anything, don't worry about it bro
    try {

      $metadata = $this->spider->fetch( $metadata_url, 'viewer_metadata' );
      return str_replace('<br/>', '', $metadata);  // strip <br>'s and return

    } catch (Exception $e) {
      return false;
    }

  } // end of checkMetaData


  function getZipURL(string $html, string $dps_dvs) {
            
    $dom = new DOMDocument;
    libxml_use_internal_errors(true); // Suppress parsing errors/warnings
    $dom->loadHTML($html);
    libxml_clear_errors();

    $accordion = $dom->getElementById('accordion');
    // stop here if we don't have an accordion
    if (!$accordion)
      return false;

    $rep = false;
    $title = false;
    
    foreach ($accordion->childNodes as $child) {
        if ($child->nodeName === 'h3') {
            $rep = $child->getAttribute('name');
            $title = $child->getAttribute('title');
        }
    }

    // stop here if we have no Representation (eg REP67208190 ), in this context with a -1
    if (!$rep)
      return false;
    
    $zip_url = 'https://ndhadeliver.natlib.govt.nz/delivery/DeliveryManagerServlet?dps_func=downloadAll&dps_dvs='.$dps_dvs.'&curSM='.$rep.'&curLabel='.urlencode($title);
    
    return $zip_url;

  } // end of getZipURL


  function extractMetadata( $metadata, $title ) {

    if (!$metadata)
      return false;

    // echo $metadata;
    // exit;

    // Load the HTML into a DOMDocument object
    $doc = new DOMDocument();

    // suppress errors from ill-formed HTML
    libxml_use_internal_errors(true);
      $doc->loadHTML($metadata);
    libxml_clear_errors();

    // Create a DOMXPath object
    $xpath = new DOMXPath($doc);

    // Use the DOMXPath object to search for the span element with, eg, the "File Extension" title
    $span_elements = $xpath->query("//span[@title='".$title."']");

    // Extract the file extension from the immediately following span element
    if ( isset($span_elements[0]) ) {
      $info_span = $span_elements[0]->nextSibling;
      return $info_span->textContent;   // Outputs, eg "mp4"
    }

    return false;

  } // end of extractMetadata


} // end of class
