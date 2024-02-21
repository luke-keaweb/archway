<?php

trait ResultParser {



  function translate( $raw_result ) {

    // print_r( $raw_result );

    // Search setting nativeQuery=true activates SolrQuery mode, which gives us more info
    // Tradeoff: SolrQuery obscures field names behind a partial MD5
    // Fix: we need to translate things ....

    $result = array();

    $translations = array(
      'code' => 'metadata.predicate.literal_s.0ab1078a',
      'name' => 'metadata.predicate.literal_s.28333a61',
      'series_id' => 'metadata.predicate.literal_s.43d92aa2',
      'series_name' => 'metadata.predicate.literal_s.d73b599a',
      'location' => 'metadata.predicate.literal_s.1e640512',

      // URL of media viewer, or sometimes the URL of an attached file (poss. an array)
      'view_online' => 'metadata.predicate.literal_s.95c8014a',

      // IE number of media view, or maybe the filename of an attached file (poss. an array)
      'reproduction' => 'metadata.predicate.literal_s.134351f0',

      'thumbnail' => 'metadata.predicate.literal_s.d434c708',         // URL of thumbnail

      'hasBeginningDate' => 'metadata.predicate.literal_s.47c35c78',
      'hasEndDate' => 'metadata.predicate.literal_s.2dd71a9e',

      'hasBeginningDate_unknown' => 'metadata.predicate.literal_s.8ad9da94',
      'hasEndDate_unknown' => 'metadata.predicate.literal_s.6d3924aa',

      'access' => 'metadata.predicate.literal_s.f2f21d0d',
      'item_type' => 'metadata.predicate.literal_s.93a3eb74',
      'format' => 'metadata.predicate.literal_s.9d499d9a',
      'format_description' => 'metadata.predicate.literal_s.538e0a78',
      'record_missing' => 'metadata.predicate.literal_s.ad5b4f96',
      'entity_type' => 'metadata.predicate.literal_s.4457f365',

      'record_number' => 'metadata.predicate.literal_s.e772234c',

      'accession' => 'metadata.predicate.literal_s.85149b66',
      'additional_description' => 'metadata.predicate.literal_s.490561a1',

      'additional_description2' => 'metadata.predicate.literal_s.6fbc640c',

      // watch out, here be dragons ... this also mixes in a bunch of other crap
      'summary' => 'metadata.predicate.literal_s.cfc04591',

      'agent_type' => 'metadata.predicate.literal_s.548863f7',
      'location2' => 'metadata.predicate.literal_s.eb1e45f5',

      'history' => 'metadata.predicate.literal_s.17eae5a7',

      // In Agency ABOJ this contains 'custom2', ie, Sources
      'sources' => 'metadata.predicate.literal_s.50aa0311',

      // In Jurisdiction J0428 this contains 'custom1', ie, Author Note
      'author_note' => 'metadata.predicate.literal_s.d190c98a',

      // TODO: this is not implemented yet
      // used in Organisations to store codes of agencies they Govern
      // 'governs_codes' => 'metadata.predicate.uri.efd25cad',
      // same but just names ... but they don't line up exactly ...
      // 'governs_names' => 'metadata.predicate.literal_s.26813aa4',

      // TODO: this is not implemented yet
      // used in Jurisdictions to store codes of 'Controlling Agency' and also 'Controlled by Organisation'
      // 'controlling_agency_codes' => 'metadata.predicate.uri.728aaae2',
      // same but just names ... but they don't line up exactly ...
      // 'controlling_agency_NAMES' => 'metadata.predicate.literal_s.26813aa4',
      // BUG; note the above is the same as governs_names


    );

    // TO DO:  make this work for multiple items, we just assume an array with one item
    foreach ($translations as $plain => $obscured) {

      // set any missing fields to null
      if ( !isset($raw_result[$obscured]) ) {
        $result[$plain] = null;
      } else {

        // aaargh, this is causing a ton of trouble
        $result[$plain] = $raw_result[$obscured][0];
        // the obscured view treats everything as an array, even single values
        // ideally we would have a newline here, but I can't seem to get it to work
        // $result[$plain] = implode('<br />', $raw_result[$obscured] );
      }

    }

    // Agent Mandate Other is often an array of multiples
    $result['agentMandateOther'] = '';
    $agentMandateOther = 'metadata.predicate.literal_s.76ff32be';
    if ( isset($raw_result[$agentMandateOther]) )
      $result['agentMandateOther'] = makeList($raw_result[$agentMandateOther]);

    // controlling_agency is an array of multiple agencies (eg J0428
    $result['ControllingAgencies'] = '';
    $ControllingAgencies = 'metadata.predicate.uri.728aaae2';
    if ( isset($raw_result[$ControllingAgencies]) )
      $result['ControllingAgencies'] = makeList($raw_result[$ControllingAgencies]);

    // in Disposal Authorities, View Online is actually an array
    $result['attachments'] = '';
    $attachments = 'metadata.predicate.literal_s.95c8014a';
    if ( isset($raw_result[$attachments]) && $result['entity_type'] == 'Disposal Authority' )
      $result['attachments'] = $this->makeAttachmentList($raw_result[$attachments], $result['entity_type']);
      
    // these two fields are values / labels of some 'Extended Info'
    $result['ei_values'] = $raw_result['metadata.predicate.literal_s.26813aa4'] ?? false;
    $result['ei_labels'] = $raw_result['metadata.predicate.literal_s.e7d8a866'] ?? false;

    // controlling_agency - ARRAY
    $agency_codes = $this->extractAgencyCodes( $raw_result );
    $result['agencies'] = $this->listAgencies( $agency_codes );

    // for sorting by agency, we'll use the first agency code
    if ($agency_codes) {
      $result['agency_code'] = $agency_codes[0];
      $agencies_obj = Agency::getInstance();
      $result['agency_name'] = $agencies_obj->name( $agency_codes[0] );
    } else {
      $result['agency_code'] = false;
      $result['agency_name'] = false;
    }

    // print_r($result);
    return $result;

  } // end of translate



  function makeAttachmentList($attachments, $entity_type) {
    
    // TO DO: this is an ugly hack for two different contexts lol
    
    // on Disposal Authorities, use a paperclip icon
    $icon = ($entity_type != 'Item') ? '📎 ' : '';
    
    $urls = array();

    foreach ($attachments as $u) {
      
      if (isset($u['url']))
        $url = $u['url'];
      else
        $url = $u;
      
      if (isset($u['linkText']))
        $link_text = $u['linkText'];
      else
        $link_text = '';
        
      $filename = extractFilenameFromURL($url) ?? $link_text;
      $urls[] = $icon.makeLink($url, $filename, 'Click to download '.$link_text);
    }

    if ( isset($attachments[0]['type']) )
      return genericInfo($attachments[0]['type'], makeList($urls) );
    else
      return makeList($urls);
        
  } // end of makeAttachmentList



  function processResults( $hits ) {

    if (!$hits)
      return false;

    $all_results = array();

    foreach ( $hits as $raw_result ) {

      $all_results[] = $this->processResult(
        $this->translate( $raw_result ),
        $raw_result
      );

    } // end of foreach

    return $all_results;

  } // end of processResults



  function processResult( $result, $raw_result ) {

    // NOTE assumes that $result has already been translated!

    $full_info = '';

    // CAREFUL: Tech Info adds a lot of time to render so much raw data
    if ( defined('TECH_INFO') ) {

      $show_tech_info = '<span class="technical_info" title="Show/Hide technical information about record '.$result['code'].'">&#x24d8;</span>';

      $tech_info = '<div class="technical_info">';
        $tech_info .= '<h2>Raw data '.makeLink($this->getSearchUrl(), 'from Axiell backend').'</h2><hr />';
        $tech_info .= '<pre>'. addLinks( print_r($raw_result, true) ).'</pre>';
      $tech_info .= '</div>'.PHP_EOL;

      $full_info .= $show_tech_info . $tech_info;

    } // end of tech info stff

    // combine the extended info labels + values
    $extended_info = $this->processExtendedInfo($result);

    $archives_link = aimsLink($result['code'], $result['code'], 'View item on Archives NZ');

    $archway_link = $result['code'] ? entityLink($result['code'], $result['code']) : $result['code'];
    
    // make series link
    $series_link = false;
    if ($result['series_id'])
      $series_link = entityLink($result['series_id'], $result['series_name']);

    // make Accession link
    $accession_link = false;
    if ($result['accession'])
      $accession_link = entityLink($result['accession'], $result['accession']);

    // include link to View Online if available
    // this currently includes BOTH thumbnail and a text link
    $view_online = $this->getViewOnline( $result['view_online'], $result['name'], $result['format'] );

    // just the text link
    $view_online_link_only = $this->getViewOnlineNoThumb( $result['view_online'], $result['name'], $result['format'] );

    // collect any related Flickr posts
    $result['flickr_post'] = false;
    if (!$view_online)
      $result['flickr_post'] = Flickr::get( $result['code'] );

    // use alternate values for unknown years
    if (!$result['hasBeginningDate'])
      $result['hasBeginningDate'] = $result['hasBeginningDate_unknown'];

    if (!$result['hasEndDate'])
      $result['hasEndDate'] = $result['hasEndDate_unknown'];

    // clean up start and end dates
    $years = cleanDates( $result['hasBeginningDate'], $result['hasEndDate'] );

    // clean up start and end dates
    $year_label = 'Years';
    if ( $years && strlen($years) == 4)
      $year_label = 'Year';

    // blank non-useful Summary fields
    if ($result['summary'] == 'No summary is currently available')
      $result['summary'] = false;

    // translate RecordisMissing into Missing or blank
    if ($result['record_missing'] == 'true')
      $result['record_missing'] = 'Missing';
    else
      $result['record_missing'] = false;

    // Make a handy link to search/browse in Entity (only non-items)
    $entity_link = false;
    $long_entity_link = false;
    if ( $result['entity_type'] != 'Item' ) {
      $entity_link = entityLink($result['code'], 'Browse', 'Browse / Search this '.$result['entity_type']);
      $long_entity_link = entityLink($result['code'], 'Browse results from this '.$result['entity_type'], 'Full information about '.$result['name'].'.  You may be able to browse and search related items');
    }

    // include full title if necessary
    $full_length_title = false;
    if (
      isset($_GET['simple_view'])
      && $_GET['simple_view'] == 1
      && strlen($result['name']) > 500
    )
      $full_length_title = $result['name'];

    $possible_fields = array(

      '🔍' => $long_entity_link,

      'Full Name' => $full_length_title,

      'Entity Type' => $result['entity_type'],    // eg Item
      'Code' => $archway_link,

      $year_label => $years,

      'Additional Description' => $result['additional_description'],

      'Description (Additional)' => $result['additional_description2'],

      'Series' => $series_link,
      'Agency' => $result['agencies'],
      'Accession' => $accession_link,

      'Held At'            => $result['location'],
      PHP_EOL.'Held At'    => $result['location2'],  // for Agency, Series, etc - note EOL to avoid overwriting prev Location

      'Item Type' => $result['item_type'],        // eg Physical
      'Agent Type' => $result['agent_type'],        // eg Agent

      'Summary' => $result['summary'],            // not usually for Items

      'Access' => $result['access'],              // eg Open
      'Digital Copy' => $view_online,

      'Attachments' => $result['attachments'],

      'Format' => $result['format'],              // eg Map/Plan
      'Series Format Description' => $result['format_description'],   // eg Split Pin Files

      'Other Mandates' => $result['agentMandateOther'],
      'Record Number' => $result['record_number'],
      // 'Prior Reference' => $extended_info['priref'],     // I don't think any Archives reference actually uses this?

      'Record Status' => $result['record_missing'],   // true / false

      'Related Flickr Post' => $result['flickr_post'],

      'Sources' => $result['sources'],
      'Author Note' => $result['author_note'],

      // need to process this some more
      // 'Related' => $result['ControllingAgencies'],

      // the formatting is often messed up on History, is it too ugly to show here?
      'History' => $result['history'],

    );

    foreach($possible_fields as $label => $value)
      $full_info .= genericInfo($label, $value);

    $ei_fields = array(
      'Box Number' => 'BoxNumber',
      'Part Number' => 'PartNumber',
      'Position Reference' => 'PositionReference',
      'Record Number Alt' => 'RecordNumberAlternative',
      'Former Archives Reference' => 'FormerArchivesReference',
    );

    foreach($ei_fields as $label => $name) {
      if ( isset($extended_info[$name]) )
        $full_info .= genericInfo($label, $extended_info[$name]);
    }


    // source attribution for Archives NZ
    $source_link = aimsLink($result['code'], 'Archives NZ', 'View '.$result['entity_type'].' on the official Archives New Zealand Collections Search website');
    $cc_link = makeLink('https://creativecommons.org/licenses/by/2.0/', 'CC BY 2.0', 'Creative Commons License: Attribution 2.0');

    $full_info .= genericInfo( 'Inquiry', $this->getInquiryLink($result) );
    $full_info .= genericInfo('Source', $source_link.' / '.$cc_link );

    // thats the end of the Full Info dropdown, from here we are processing stuff to show in the table view

    // for brevity, remove 'repository' from locations
    if ($result['location'])
      $result['location'] = str_ireplace('repository', '', $result['location']);

    // highlight keywords (and truncate super long titles)
    if (
      isset($_GET['simple_view'])
      && $_GET['simple_view'] == 1
      && strlen($result['name']) > 500
    )
      $main_label = highlightAndTruncateTitle( $result['name'], $this->keywords );
    else
      $main_label = highlightKeywords( single_nl2br($result['name']), $this->keywords );

    // Accessions, DAs and AAs deserve better names ...
    if (
      $result['entity_type'] == 'Accession'
      || $result['entity_type'] == 'Access Authority'
      || $result['entity_type'] == 'Disposal Authority'
    ) {
      // use the Summary field, or the first 275 characters of it, as name
      if ( strlen($result['summary']) < 275 )
        $main_label = $main_label .' - '. $result['summary'];
      else
        $main_label = $main_label .' - '. substr( $result['summary'], 0, 275 ).' ...';
    }

    // link main label to the entity record (if we have a code)
    $main_link = $result['code'] ? entityLink($result['code'], $result['code']) : false;

    // set the access span, if we have any access info
    $access_class = false;
    $access_info = false;
    if ( $result['access'] ) {
      // figure out the access class
      $access_class = $this->getAccessClass($result, $view_online);
      $result_string = str_replace(' ', '&nbsp;', $result['access']);  // don't linebreak on 'May be restricted'
      $access_info = '<span class="'.$access_class.'" title="Access: '.$result['access'].'">'.$result_string.'</span>';
    }

    // this gives us fine-grained control over individual table rows / cards
    // eg we can make a colour-coded border depending on access class
    $entity_class = 'entity-'.cssClass( $result['entity_type'] );
    $overall_class = 'access '.$access_class.' '.$entity_class;

    // on mobile, we shrink everything down to just show this TD
    $main_info = '';
    if ($view_online)
      $main_info .= $view_online;

    $main_info .= '<span class="above-title">'.$archway_link.'</span>';
    $main_info .= toggleInfo($main_label, $full_info);

    if ($entity_link)
      $main_info .= '<span class="below-title">'.$entity_link.'</span>';
    if ($years)
      $main_info .= '<span class="below-title">'.$years.'</span>';
    $main_info .= '<span class="below-title">'.$result['location'].'</span>';

    // OK, let's place things in the table array
    // QUESTION: now that we skip blank columns, are there any extra things we could insert here?
    $line = array();

    if ( isset($_GET['full_info_table']) && $_GET['full_info_table'] == 1 ) {

      $line['ID'] = $archway_link;
      $line['Name'] = $main_label;    // $main_info;
      $line['Date'] = $years;

      $line['Series'] = $series_link;
      $line['Accession'] = $accession_link;
      $line['Agency'] = $result['agencies'];

      $line['Browse'] = $entity_link;
      // $line['Flickr'] = $result['flickr_post'];
      $line['Scan'] = $view_online_link_only;    // $view_online;

      $line['Held At'] = $result['location'];

      $line['Record Number'] = $result['record_number'];

      if (isset($extended_info['RecordNumberAlternative']))
        $line['Record Number Alt'] = $extended_info['RecordNumberAlternative'];
      else
        $line['Record Number Alt'] = false;
        
      if (isset($extended_info['FormerArchivesReference']))
        $line['Former Archives Reference'] = $extended_info['FormerArchivesReference'];
      else
        $line['Former Archives Reference'] = false;
      
      if (isset($extended_info['BoxNumber']))
        $line['Box Number'] = $extended_info['BoxNumber'];
      else
        $line['Box Number'] = false;

      if (isset($extended_info['PartNumber']))
        $line['Part Number'] = $extended_info['PartNumber'];
      else
        $line['Part Number'] = false;

      if (isset($extended_info['PositionReference']))
        $line['Position Reference'] = $extended_info['PositionReference'];
      else
        $line['Position Reference'] = false;

      // TO DO: these should only appear if we are searching for Series / Agency
      // $line['Series Format Description'] = $result['format_description'];   // eg Split Pin Files
      // $line['Other Mandates'] = $result['agentMandateOther'];

      $line['Sources'] = $result['sources'];
      $line['Author Note'] = $result['author_note'];
      $line['Record Status'] = $result['record_missing'];   // true / false

      $line['Format'] = $result['format'];
      $line['Access'] = $access_info;

    } else if ($this->has_digital == 'true') {
    
      $line['_class'] = $overall_class;
      $line['Digital&nbsp;Media'] = $view_online;
      $line['Record'] = $main_info;
    
      $line['Date'] = $years;
      $line['Held At'] = $result['location'];

    } else if ( isset($_GET['simple_view']) && $_GET['simple_view'] == 1 ) {

      $line['Name'] = entityLink($result['code'], $main_label);
      $line['Scan'] = $view_online;
      $line['Year(s)'] = $years;
      $line['Held At'] = $result['location'];
      $line['Access'] = $access_info;

    } else {

      // this is the default 'table' view

      $line['_class'] = $overall_class;
      $line['ID'] = $archives_link;
      // $line['Series'] = $series_link;
      // $line['Agency'] = $result['agencies'];
      $line['Name'] = $main_info;
      $line['Browse'] = $entity_link;
      // $line['Format'] = $result['format'];
      // $line['Accession'] = $accession_link;
      $line['Flickr'] = $result['flickr_post'];
      $line['Scan'] = $view_online;    // $result['attachments'];
      // .'<br />'.$result[$more_info][0];
      $line['Date'] = $years;
      $line['Held At'] = $result['location'];
      // $line['Access'] = $access_info;

    }


    return $line;

  } // end of processResult


  function processExtendedInfo($result) {
    // combine the extended info labels + values
    // BEWARE, some of the extended_info values are observed to be broken/misleading
    // eg 'Physical' is actually the name of the record

    if (!$result['ei_values'])
      return false;

    // apparently we assume ei_labels will always be present?

    $extended_info = array();
    foreach( $result['ei_values'] as $c => $ei_value) {
      if ( isset($result['ei_labels'][$c]) ) {
        $ei_label = $result['ei_labels'][$c];
        $extended_info[$ei_label] = $ei_value;
      }
    }

    return $extended_info;

  } // end of processExtendedInfo



  function getViewOnlineNoThumb( $view_online, $title='', $format='' ) {

      return $this->makeViewerLinkGeneric(
        $view_online,
        $title,
        $format,
        false      // thumb
      );

  } // end of getViewOnlineNoThumb


  function getViewOnline( $view_online, $title='', $format='' ) {

      return $this->makeViewerLinkGeneric(
        $view_online,
        $title,
        $format,
        true      // thumb
      );

  } // end of getViewOnline


  function makeViewerLinkGeneric( $view_online, $title='', $format='', $thumb=false ) {

    if ( !$view_online )
      return false;

    // Ex Libris viewer links
    if ( stristr($view_online, 'dps_pid') ) {

      // grab the DPS PID from the viewer URL and slot it into our own Archway viewer
      $url_bits = parse_url($view_online);
      parse_str($url_bits['query'], $query);

      // get a format icon
      $icon = $this->getFormatIcon( $format );

      // make a label based on the format
      $label = $this->getLabel( $format );

      $html = '';

      $html .= makeLink( $view_online, $icon.$label, 'Official Archives NZ media viewer', 'view-online-link' );

      // should we include the thumbnail?
      if ($thumb) {
        $thumb = makeThumb( $view_online, $title, 'thumb '.cssClass($format), 'lazy' );
        // $html .='<div class="thumb-overlay"></div>';
        // $html .= $this->getHTMXViewerLink( $query['dps_pid'], $thumb, '' );
        $html .= makeLink( $view_online, $thumb, '');
      }
      
      // // by default, include just the text link
      // $html .= $this->getHTMXViewerLink( $query['dps_pid'], $icon.$label, 'view-online-link' );

      return $html;

    }

    // return makeLink($view_online, 'View&nbsp;Online');

    // PDF links
    if ( stristr($view_online, '.pdf') )
      return makeLink( $view_online, 'View&nbsp;PDF&nbsp;Online', '', 'view-online-link' );

    // RTF links
    if ( stristr($view_online, '.rtf') )
      return makeLink( $view_online, 'View&nbsp;RTF&nbsp;Online', '', 'view-online-link' );

    // generic document URLs ... I wonder if this will break ...
    return makeLink( $view_online, 'View&nbsp;Document&nbsp;Online', '', 'view-online-link' );

    // alternative error message
    // return '<span class="error">Unknown View Online reference</span><br />'.$view_online;

  } // end of getViewOnline



  function getHTMXViewerLink( $pid, $content, $class='' ) {

    return '<a href="'.getViewerURL($pid).'"
        class="add_row '.$class.'"
        hx-get="/ajax/viewer/'.$pid.'"
        hx-target="closest tr"
        hx-swap="afterend"
        hx-ext="toggle-viewer"
        data-id="viewer_'.$pid.'"
      >'.$content.'</a>';

  } // end of getHTMXViewerLink




  function getLabel( $format ) {

    $labels = [
      'Text' => 'Scan',
      'Map/Plan' => 'Map/Plan',
      'Moving Image' => 'Video',
      'Sound Recording' => 'Recording',
      'Artwork' => 'Artwork',
      'Photograph' => 'Photo',
      'Object' => 'Object',
      'Not Determined' => 'Media',
    ];

    $label = $labels[$format] ?? 'Media';
    return 'View&nbsp;'.$label;

  } // end of getLabel


  function getFormatIcon( $format ) {

    $icons = [
      'Text' => '📃',
      'Map/Plan' => '🗺',
      'Moving Image' => '🎞️',
      'Sound Recording' => '📻',
      'Artwork' => '🖼',
      'Photograph' => '📷',
      'Object' => '🗿',
      'Not Determined' => '❓',
    ];

    $icon = $icons[$format] ?? false;

    if ($icon)
      return $icon.'&nbsp;';

    return false;   // no icon found!

  } // end of getFormatIcon



  function getAccessClass($result, $view_online) {

    // LOL:  an item can be Restricted and also available online
    if ($view_online)
      return 'view-online';

    if ($result['access'] == 'Open')
      return 'open-access';

    if ($result['access'] == 'Restricted')
      return 'restricted-access';

    if ($result['access'] == 'May be restricted')
      return 'partial-access';

    return '';

  } // end of getAccessClass


  function getInquiryLink($result) {

    if ($result['entity_type'] != 'Item')
      return false;

    // "The 'Item copy or information request' page looks pretty janky, but I assure you this is the actual system that Archives NZ uses"
    $url = 'https://research.archivesnz.info/reft100.aspx?key=Item&bbttl='.$result['code'].'&bbpn='.$result['agency_code'].'&bbpp='.$result['series_id'].'&bbcll='.$result['accession'];

    return makeLink($url, 'Request item from Archives NZ', '', 'always_highlight');

  } // end of getInquiryLink


  function extractAgencyCodes( $result ) {

    // stop here if the agency URIs field is absent
    if ( !isset($result['metadata.predicate.uri.fae65ac5']) )
      return false;

    $agency_uris = $result['metadata.predicate.uri.fae65ac5'];

    $codes = array();
    foreach($agency_uris as $agency_uri) {

      // https://common.aims.axiellhosting.com/api/etl-entrystore/latest/aims/resource/ABWN
      $chunks = explode('/', $agency_uri);
      $possible_code = end($chunks);

      // We want to grab last four letters of the URI because it is likely an agency code
      if (preg_match('/^[a-zA-Z]{4}$/', $possible_code))
        $codes[] = $possible_code;

    }

    // stop here if we have nothing
    if (!$codes)
      return false;

    return $codes;

  } // end of extractAgencyCodes


  function listAgencies( $agency_codes ) {

    // stop here if we have nothing
    if (!$agency_codes)
      return false;

    $list = array();

    // to collect agency name from DB
    $agencies_obj = Agency::getInstance();

    foreach ($agency_codes as $code)
      $list[] = entityLink( $code, $agencies_obj->name($code) );

    if (count($list) == 1)
      return $list[0];
    else
      return makeList($list);

  } // end of listAgencies


} // end of class
