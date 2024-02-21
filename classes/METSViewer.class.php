<?php

class METSViewer {

  public string $pid;
  
  function __construct( string $pid ) {

    if (!$pid)
      throw new Exception('No PID (National Library image ID) provided');

    $this->pid = $pid;
  }
  
  function renderPage() {
    return Template::build( $this->main(), $this->pid.' METS data - Alternative formats/quality options' ); 
  }
  
  function renderAjax() {
    
    // to avoid HTMX showing whole-template errors, we catch them here
    try {
      return $this->main();
    } catch (Exception $e) {
      return $e->getMessage();
    }
        
  } // end of renderAjax

  function main() {

    // Given a DPS PID, collect the METS info we can use to view the digital media
    // in this file, the focus is on extracting the Representation IDs and File IDs of the high quality preservation masters (eg, TIF for scans, WAV, MOV, etc)
  
    $original_url = 'https://ndhadeliver.natlib.govt.nz/delivery/DeliveryManagerServlet?dps_pid='.$this->pid;
    
    $html = '';
  
    $this->spider = new Spider;

    $metadata_url = 'https://ndhadeliver.natlib.govt.nz/delivery/DeliveryManagerServlet?dps_func=mets&dps_pid='.$this->pid;
    $raw_metadata = $this->spider->fetch( $metadata_url, 'viewer_mets' );
    
    // viewCode( $raw_metadata );

    // detect access-not-permitted messages
    if ( stristr($raw_metadata, '<title>Access is not permitted</title>') )
      return '<h3 class="error">Sorry, this record cannot be viewed due to access restrictions</h3><p>Visit the '.makeLink($original_url, 'Archives NZ viewer page').' for more information</p>';

    // fix an annoying namespace XML thing
    $raw_metadata = str_replace('http://www.loc.gov/mods/v3 ', '', $raw_metadata);

    $dom = new DOMDocument();
    $dom->loadXML( $raw_metadata );
    $xpath = new DOMXPath($dom);

    // collect some basic info about this record
    // $title = $this->xInfo($xpath, 'title');
    // $title_link = makeLink('MAKE_THIS_FAKE_ROUTE/'.$this->pid, $title);
    
    $code = $this->xInfo($xpath, 'identifier');
    // $code_link = entityLink($code, $code);
    
    // $series_link = linkSeries( $this->xInfo($xpath, 'isPartOf') );
    // $agency_link = linkAgency( $this->xInfo($xpath, 'provenance') );
    // $archives_link = makeLink($original_url, '&#127744;&nbsp;Archives New Zealand Te Rua Mahara o te Kāwanatanga');

    // $METS_info = makeLink('https://en.wikipedia.org/wiki/Metadata_Encoding_and_Transmission_Standard', 'What is METS?');      
    // 
    
    // $html .= '<h2>Media Details '.$title_link.'</h2>';
    
    // $html .= genericInfo( 'Title', '<h4>'.$title_link.'</h4>' );

    // $html .= genericInfo( 'Associated Item', $code_link );
    // $html .= genericInfo( 'Series', $series_link ); 
    // $html .= genericInfo( 'Agency', $agency_link );      
    // $html .= genericInfo('Online Viewer', $archives_link );
        
    // $html .= genericInfo('Data Source', $METS_link );        
        
        
    // $html .= '<hr />';  
            
    // OK, now for the crazy bit.  Fetching what we need from this complex format.  Hold onto your butts.
    
    foreach ($xpath->query("//mets:fileSec") as $file_section) {
      // print_r( $file_section ).'<br /><br /><br />';

      $file_groups = $file_section->getElementsByTagName('fileGrp');

      // an array to hold the table we will build
      $list = array();
      
      foreach ($file_groups as $group) {

        $rep = $group->getAttribute('ID');

        // collect file IDs for this group
        $file_ids = array();
        $file_ids_elements = $group->getElementsByTagName('file');
        foreach ($file_ids_elements as $file_id_el)
          $file_ids[] = $file_id_el->getAttribute('ID');
        
        // $thumb = 'https://ndhadeliver.natlib.govt.nz/delivery/DeliveryManagerServlet?dps_func=thumbnail&dps_pid='; 
        // $large = 'https://ndhadeliver.natlib.govt.nz/delivery/DeliveryManagerServlet?dps_func=download&dps_pid=';
        // 
        // foreach($file_ids as $fl) {
        //   $html .= makeThumb( $thumb.$fl, $fl );  // thumbnails, just as a test
        //   // $html .= makeThumb( $large.$fl, $fl ); 
        // }

        $file_locations = $group->getElementsByTagName('FLocat');
        // there should only be one ... do we need the foreach here?
        foreach ($file_locations as $file)
          $ext = pathinfo($file->getAttribute('xlin:href'), PATHINFO_EXTENSION);
          
          $ndha_url = 'https://ndhadeliver.natlib.govt.nz/delivery/DeliveryManagerServlet?dps_pid=';
          $archives_link = makeLink($ndha_url.$rep, '&#127744;&nbsp;Archives NZ Viewer');

          if ($ext == 'jpg' ) {
            // $html .= genericInfo( 'Link', makeLink($ndha_url.$rep, '&#127744;&nbsp;View at Archives NZ (Standard Quality)') );
            
            $list[] = array( 
              'Format' => strtoupper($ext),
              'Quality' => 'Standard',
              'Online Viewer' => $archives_link,
              'Direct File Links' => $this->fileLinkList($file_ids, $ndha_url, $xpath)
            );
            
          } else if ( $ext == 'mp3' || $ext == 'mp4') {
            // $html .= genericInfo( 'Link', makeLink($ndha_url.$rep, '&#127744;&nbsp;View at Archives NZ (Standard Quality)') );

            $list[] = array( 
              'Format' => strtoupper($ext),
              'Quality' => 'Standard',
              'Online Viewer' => $archives_link,
              'Direct File Links' => $this->fileLinkList($file_ids, $ndha_url, $xpath)
            );

          } else if ($ext == 'tif' || $ext == 'jp2' || $ext == 'wav' || $ext == 'mov') {
            
            // $html .= genericInfo( 'Link', makeLink($ndha_url.$rep, '&#127744;&nbsp;View at Archives NZ (Very High Quality '.strtoupper($ext).')').' - use with caution!');

            $list[] = array( 
              'Format' => strtoupper($ext),
              'Quality' => 'Very High Quality',
              'Online Viewer' => $archives_link,
              'Direct File Links' => $this->fileLinkList($file_ids, $ndha_url, $xpath)
            );
                        
          } else {
          
            // $html .= genericInfo( 'Link', makeLink($ndha_url.$rep, '&#127744;&nbsp;View at Archives NZ ('.strtoupper($ext).')' ) );

            $list[] = array( 
              'Format' => strtoupper($ext),
              'Quality' => 'Standard',
              'Online Viewer' => $archives_link,
              'Direct File Links' => $this->fileLinkList($file_ids, $ndha_url, $xpath)
            );
            
          }

      } // end of file groups foreach
    } // end of xpath foreach
    
    
    // we really only need this if everything above failed somehow
    // $original_url = 'https://ndhadeliver.natlib.govt.nz/delivery/DeliveryManagerServlet?dps_pid='.$this->pid;
    // $link = makeLink($original_url, '&#127744;&nbsp;View at Archives NZ (default)');
    // $html .= genericInfo('Link', $link);

    $html .= FormatData::display( 'Alternative File Formats / High Quality Options', $list );

    $METS_link = makeLink($metadata_url, 'raw METS data'); 
    $METS_info = makeLink('https://en.wikipedia.org/wiki/Metadata_Encoding_and_Transmission_Standard', 'More info');  
    
    // IE = Intellectual Entities
    
    // $html .= '<p>This information was extracted from the '.$METS_link.' for this record.  '.$METS_info.' about METS.</p>';

    // source attribution
    $source_link = aimsLink($code, 'Archives NZ', 'Te Rua Mahara o te Kāwanatanga | Archives New Zealand Collection Search');
    $cc_link = makeLink('https://creativecommons.org/licenses/by/2.0/', 'CC BY', 'Creative Commons License: Attribution 2.0');
    
    $METS_link = makeLink($metadata_url, 'METS data', 'Metadata Encoding and Transmission Standard'); 
        
    $html .= '<p>'.$source_link.' / '.$METS_link.' / '.$cc_link.'</p>';

    return $html;
    
  } // end of main
  
  
  
  function fileLinkList(array $file_ids, $ndha_url, $xpath) {
    
    $file_links = '';
    
    // $thumb = 'https://ndhadeliver.natlib.govt.nz/delivery/DeliveryManagerServlet?dps_func=thumbnail&dps_pid=';
    // makeThumb( $thumb.$fl, $fl ).
    
    foreach ($file_ids as $fl)
      $file_links .= 'Download&nbsp;'.makeLink($ndha_url.$fl.'&dps_func=download', $fl).'&nbsp;('.$this->getFileSize($xpath, $fl).')<br />';
    
    // if ($file_links)
    //   $file_links = '<ul>'.$file_links.'</ul>';
    
    return $file_links;
      
  } // end of fileLinkList

  
  function xInfo(DOMXPath $x, $node_name) {

    // Query the XML for the given node using local-name
    $nodes = $x->query('//*[local-name()="'.$node_name.'"]');

    $node_text = '';

    // collect all the text from nodes with this name
    if ($nodes->length > 0) {
      $node_text .= $nodes->item(0)->nodeValue;
    }
    
    return $node_text;

  } // end of xInfo



  function getFileSize(DOMXPath $x, $fileId) {
    
      $x->registerNamespace('mets', 'http://www.loc.gov/METS/');
      $entries = $x->query("//mets:techMD[@ID='{$fileId}-amd-tech']");

      foreach ($entries as $entry) {
        
        $records = $entry->getElementsByTagName('record');
        foreach ($records as $record) {

          $keys = $record->getElementsByTagName('key');
          foreach ($keys as $key) {
            
            // echo $key->getAttribute('id').' is <b>'.$key->nodeValue.'</b><br />';
            
            if ($key->getAttribute('id') == 'fileSizeBytes')
              return $this->humanFileSize( $key->nodeValue );
              
          } // end of keys foreach
    
        } // end of record foreach

      }

      return null; // Return null if the file size is not found
      
  } // end of getFileSize



  function humanFileSize($size, $precision=2) {
      $units = array('B', 'KB', 'MB', 'GB', 'TB');

      $bytes = max($size, 0);
      $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
      $pow = min($pow, count($units) - 1);

      $bytes /= pow(1024, $pow);

      return round($bytes, $precision).'&nbsp;'.$units[$pow];
  }

  
  
  // What a typical METS fileSection looks like, hopefully
  
  // <mets:fileSec>
  // <mets:fileGrp ID="REP25256337" ADMID="REP25256337-amd">
  //   <mets:file ID="FL25256338" ADMID="FL25256338-amd">
  //     <mets:FLocat LOCTYPE="URL" xlin:href="/exlibris1/access_storage/anz_access_01/2015/08/20/ac_1/V1-FL25256338.jpg" xmlns:xlin="http://www.w3.org/1999/xlink"/>
  //   </mets:file>
  // </mets:fileGrp>
  // <mets:fileGrp ID="REP25256282" ADMID="REP25256282-amd">
  //   <mets:file ID="FL25256283" ADMID="FL25256283-amd">
  //     <mets:FLocat LOCTYPE="URL" xlin:href="/exlibris1/permanent_storage/anz_file_01/2015/08/20/file_1/V1-FL25256283.tif" xmlns:xlin="http://www.w3.org/1999/xlink"/>
  //   </mets:file>
  // </mets:fileGrp>
    


  
  
} // end of class
