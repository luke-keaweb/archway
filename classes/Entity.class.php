<?php

class Entity {
  
  use ResultParser;
  
  public string $code;
  public array $info;
  public ?array $request_info;
    
  function __construct( $code ) {

    // Code should be a string that contains only alphanumeric characters, plus hyphens.
    if (preg_match('/[^a-zA-Z0-9-]/', $code)) 
      throw new Exception('Oops, the code provided ('.$code.') contains invalid characters');

    $this->code = $code;
  
    $this->info = $this->collect( $this->getUrl() );
  
  } // end of construcor

  
  function showInfo() {
    
    // viewCode( $this->info );
    
    $code = $this->info('id');
    $icon = entityIcon( $this->info('itemType'), $code );
    $name = single_nl2br( $this->info('name') );
    $heading_link = entityLink($code, $name, 'About this '.$this->info('itemType'));

    $html = '';  
    
    $html .= '<h2>'.$icon.$heading_link.'</h2>';
    
    // link to indexed Military Personnel Files
    if ($code == '18805')
      $html .= '<div class="search_box"><h3 class="center" style="margin-bottom:0.5em;">
          Military Personnel Files now <a href="/military.php">indexed by Service Number and conflict</a> for advanced searching
        </h3></div>';
                  
    // for my local copy, insert some technical info
    if ( defined('TECH_INFO') )
      $html .= $this->getTechInfo();
    
    $description = false;
    if ( isset($this->info['entity']['description']) )  // is an array, for no reason
      $description = implode('<br />', $this->info['entity']['description']);
    
    // blank non-useful Summary fields
    if ($description == 'No summary is currently available')
      $description = false;
      
    // if Summary and History are identical, blank History
    // eg http://archway/entity.php?code=12502
    if ( 
      $this->info('history') && $description
      && strcmp(strip_tags($description), strip_tags($this->info['entity']['history']) ) === 0
    )
      $this->info['entity']['history'] = '';
      
    
    $location1 = $this->info['entity']['isAssociatedWithPlace'][0]['name'] ?? false;
    $location2 = $this->info['entity']['isAssociatedWithPlace'][0]['location'] ?? false; // Alternative location field in eg BACR
    
    $main_dates = extractDates( $this->info['entity'] );
    $main_date_label = (strlen($main_dates) == 4) ? 'Year' : 'Years';
    
    $ric_classification = false;
    if ( $this->info('ricClassification') )
      $ric_classification = ucwords( strtolower( $this->info('ricClassification') ) );
    
    // note you cannot have multiple labels the same!
    $fields = array(
      'Entity Type'     =>    $this->info('itemType'),
      'Code'              =>    $this->info['entity']['id'],
      $main_date_label    =>    $main_dates,
      
      // Individual Items
      'Item Level'        =>    $this->info('ricType'), 
      'Access'            =>    $this->info('conditionsOfAccess'), 
      
      // Series holdings dates
      'Holdings Years'    =>    extractHoldingsDate( $this->info['entity'] ),
            
      // these appear on Disposal Authorities, eg, DA203
      'Date approved'     =>    extractApprovedDate( $this->info['entity'] ),
      'Date reviewed'     =>    extractReviewedDate( $this->info['entity'] ),
      'Disposal Type'     =>    $this->info('ruleType'), 
      'Status'            =>    $this->info('ruleState'),    
                      
      'Agent Type'        =>    $this->info('agentType'),
      'Established By'    =>    $this->info('agentMandateEstablishing'),

      'Summary'           =>    $description,
      
      'Additional Description'   => $this->info('additionalDescriptionItem'),
      
      'Held At'          =>    $location1,     
      PHP_EOL.'Held At'  =>    $location2,    // Alternative location field in eg BACR
      'Authenticity Note'            =>    $this->info('authenticityNote'),
      'System of Classification'     =>    $ric_classification,  

    );
    foreach ($fields as $label => $value)
      $html .= genericInfo($label, $value);
      
    
    // image / DPS link
    if ( $this->info('image') ) {
      
      // try to collect the format of the scanned digital media
      // this ... doesn't seem quite right ... 
      $format = $this->info['entity']['hasOrHadCategory'][0]['name'] ?? false;
      
      $imgs = array();
      foreach ($this->info['entity']['image'] as $image_url)
        $imgs[] = $this->getViewOnline( $image_url['url'], $this->info['entity']['name'], $format );
    
      $img_html = '<table class="plain"><tr><td>'.makeList($imgs).'</td></tr></table>';
    
      $html .= genericInfo('Digital Copy', $img_html);
    }
    
    // Flickr posts
    $html .= genericInfo('Related Flickr post', Flickr::get( $code ) );
    
    // URLs / attached documents
    if ( $this->info('url') )
      $html .= $this->makeAttachmentList($this->info['entity']['url'], $this->info('itemType') );
          
    if ( $this->info('hasOrHadCategory') ) {
      $categories = $this->singleCategories( $this->info['entity']['hasOrHadCategory'] );
      foreach($categories as $cat)
        $html .= genericInfo($cat['type'], $cat['name']);
    }      

    if ( $this->info('identifier') ) {
      foreach($this->info['entity']['identifier'] as $label => $identifier_array)
       foreach($identifier_array as $value) {
         if ($label == 'priref') 
          continue;
        $html .= genericInfo($label, $value);
      }
    }
    
    
    if ($this->info('recordIsMissing') == '1')
      $html .= genericInfo( 'Record Status', 'Missing');
    
    
    // experimental
    // Content access statement can be IDENTICAL to the Series Access Statement seen below
    // example: http://archway/entity.php?code=R20551228
    if ($this->info('itemType') == 'Item') {
    
      $extra_info = new ExtraInfo( $this->code );
      $extra = $extra_info->getInfo();
      
      // print_r( $extra );
      
      if ( isset($extra['partOf']['restrictionReason']) )
        $html .= genericInfo('Series Access Statement', $extra['partOf']['restrictionReason']);   
        
      if ( isset($extra['partOf']['accessContacts']) )
        $html .= genericInfo('Series Access Contacts', $extra['partOf']['accessContacts']);   
            
    } // end of extra info
    
    
    // these only appear on Series entries I think
    if ($this->info('seriesAccessContacts') )
      $html .= genericInfo('Series Access Contacts', $this->info['entity']['seriesAccessContacts'] );

    if ($this->info('seriesRestrictionReason') )
      $html .= genericInfo('Series Restriction Reason', $this->info['entity']['seriesRestrictionReason'] );
            

    if ( $this->info('history') )
      $html .= genericInfo('History', $this->info('history') );
    
    
    // sometimes there are intriguing notes stored in, eg, ['custom']['custom2']
    if ( $this->info('custom') ) {
      
      // Found in https://collections.archives.govt.nz/o/common-services/v1.0/groups/384226/config
      $custom_labels = array(
        "custom1" => "Author Note",
        "custom2" => "Sources",        
        "custom5" => "Series Format Description",
        "custom7" => "Additional Description",
      );
      
      foreach ($this->info['entity']['custom'] as $custom_number => $custom_value) {
        $custom_label = $custom_labels[ $custom_number ] ?? 'Note';
        $html .= genericInfo($custom_label, $custom_value);
      }
    }
    
    if ($this->info('isOrWasIncludedIn')) {
      foreach ($this->info('isOrWasIncludedIn') as $included_in) {
        // gah ...
        if ( is_numeric($included_in) ) {
          $series_obj = Series::getInstance();
          $html .= genericInfo('Series', entityIcon('Series').entityLink($included_in, $series_obj->name($included_in) ) );
        } else {
          $html .= genericInfo('Accession', entityIcon('Accession').entityLink($included_in, $included_in)  );
        }
      }
    }

    if ($this->info('agentMandateOther'))
      $html .= genericInfo('Other Mandates', makeList( $this->info('agentMandateOther')));
      
    // print_r( $included_in );
    
    // if ($this->info('series') ) {
    // 
    //   // conditions of access appear to be inherited from the Series info
    // 
    //   if (
    //     isset( $this->info['entity']['series']['seriesRestrictionReason'] )
    //     && trim(strip_tags($this->info['entity']['series']['seriesRestrictionReason'])) != 'No restrictions'
    //   )
    //     $html .= genericInfo('Series access statement', $this->info['entity']['series']['seriesRestrictionReason'] );
    // 
    //   if (
    //     isset( $this->info['entity']['series']['seriesAccessContacts'] )
    //     && trim($this->info['entity']['series']['seriesAccessContacts']) != 'Not applicable'
    //   )
    //     $html .= genericInfo('Series access contacts', $this->info['entity']['series']['seriesAccessContacts'] );    
    // 
    //   $html .= genericInfo('Series', entityLink($this->info['entity']['series']['id'], $this->info['entity']['series']['name']) );
    // 
    // }
    
    // Items have an Accession array which has tons of info
    if ($this->info('accession') ) {

      // conditions of access appear to be inherited from the Accession info
      $html .= genericInfo('Accession access statement', $this->info['entity']['accession']['unserialisedAccessStatement'] );
      $html .= genericInfo('Accession access contacts', $this->info['entity']['accession']['unserialisedAccessContacts'] );    

      $html .= genericInfo('Accession', entityLink($this->info['entity']['accession']['id'], $this->info['entity']['accession']['name'].' - '.$this->info['entity']['accession']['description']) );
      
    }
  
    // we go through all the possible Relations and show them

    // QUESTION
    // The correct label for each relation sometimes changes depending on the entityType

    if ($this->info('sequentialRelation') )
      $html .= $this->extractRelations( $this->info['entity']['sequentialRelation'] );
  
    if ($this->info('managementRelation') )
      $html .= $this->extractRelations( $this->info['entity']['managementRelation'] );
    

    
    if (
      $this->info('itemType') == 'Function'
      || $this->info('itemType') == 'Jurisdiction'
    )
      $authority_controlling = 'Agencies Responsible';
    else
      $authority_controlling = 'Responsible For';
    
    // // if the entity is an Access Authority, we want this to just say 'Controlled By'
    if ($this->info('itemType') == 'Agency')
      $authority_controlled = 'Functions / Jurisdictions';
    else
      $authority_controlled = 'Controlled By';
    
    $authority_label_list = array(
      'controlling'     =>  $authority_controlling,    // usually Series
      'controlled_by'   =>  $authority_controlled,
    );
    $html .= $this->extractRelations( $this->info('authorityRelation'), $authority_label_list );



    // yes, yes, it's all backwards
    $ahc_label_list = array(
      'controlling'     =>  'Controlled By',
      'controlled_by'   =>  'Controlling',
    );
    $html .= $this->extractRelations( $this->info('agentHierarchicalRelation'), $ahc_label_list );

     
    $acr_label_list = array(
      'controlling'     =>  'Governs',
      'controlled_by'   =>  'Governed by',
    );
    $html .= $this->extractRelations( $this->info('agentControlRelation'), $acr_label_list );
    

    $rule_label_list = array(
      'controlling'     =>  'Rules',
      'controlled_by'   =>  'Ruled By',
    );
    $html .= $this->extractRelations( $this->info('ruleRelation'), $rule_label_list );
    
    
    
    $mandate_label_list = array(
      'controlling'     =>  'Mandates',
    );
    $html .= $this->extractRelations( $this->info('mandateRelation'), $mandate_label_list );
    
    // recordResourceRelation can sometimes be just a bare array like series 18805
    $recordResourceRelation = $this->info('recordResourceRelation');
    if (isset($recordResourceRelation[0]['type']) ) {
      $html .= $this->extractRelations( $this->info('recordResourceRelation') );
    } else if (is_array($recordResourceRelation)) {
      
      $rrr_list = array();
      
      foreach ($recordResourceRelation as $rrr) {
        $label = $rrr;
        $type = getEntityType($rrr);
        if ($type == 'series') {
          $series_obj = Series::getInstance();
          $label = $series_obj->name($rrr);
        }
        $rrr_list[] = entityIcon('', $rrr).entityLink($rrr, $label);
      }
      
      $html .= genericInfo('Related records', makeList($rrr_list) );
    }
    
    // inquiry link
    $inquiry_agency = $this->info['entity']['managementRelation']['0']['relationHasSource'] ?? false;
    $inquiry_accession = $this->info['entity']['isOrWasIncludedIn'][0] ?? false;
    if ( is_numeric($inquiry_accession) )
      $inquiry_accession = false;
    $inquiry_request = [
      'entity_type' => $this->info('itemType'),
      'code' => $this->code,
      'agency_code' => $inquiry_agency,
      'series_id' => $this->info('parentId'),
      'accession' => $inquiry_accession,
    ];
    $html .= genericInfo( 'Inquiry', $this->getInquiryLink($inquiry_request) );  
    
    // source attribution
    $source_link = aimsLink($this->info['entity']['id'], 'Archives NZ', 'Te Rua Mahara o te Kāwanatanga | Archives New Zealand Collection Search');
    $cc_link = makeLink('https://creativecommons.org/licenses/by/2.0/', 'CC BY', 'Creative Commons License: Attribution 2.0');
        
    $html .= genericInfo('Source', $source_link.' / '.$cc_link);
        
        
        

    // OK, now we'll show the Searching Within This Entity sections
    
    // Search items from an Agency's worth of Series 
    // TODO:  there is now a better way to do this, which includes Accessions!
    if ( $this->info('authorityRelation') ) {

      $series_array = array();
      foreach ( $this->info['entity']['authorityRelation'] as $entry ) {
        $code = $this->getCode($entry);
        if ( is_numeric($code) )
          $series_array[$code] = $code;
      }
            
      if ( $series_array)
        $html .= '<br />'.$this->showSeriesItems( $series_array );
      
    } // end of if authorityRelation
    
    if ( $this->info('itemType') == 'Series') {
      $html .= $this->showSeries();
    }
    
    
    if ( $this->info('itemType') == 'Accession')
      $html .= $this->showAccession();



    // this part is only used by Accessions and Access Authorities I think? ...
    if ( $this->info('isRelatedTo') ) {
      
      $html .= '<h3>This '.$this->info('itemType').' is related to:</h3>';
      $html .= $this->showRelatedSeries( $this->info['entity']['isRelatedTo'] );
            
      // skip showSeriesItems for Accessions so we don't double-up the search / items stuff
      if ($this->info('itemType') != 'Accession')
        $html .= $this->showSeriesItems( $this->info['entity']['isRelatedTo'] );
      
    }
    
    
    // ok, if none of the Series / Related Series browse functionality is available, apologise
    if ( 
      !isset($series_array)
      && !isset($agency_results)
      && !$this->info('isRelatedTo')
      && $this->info('itemType') != 'Series'
      && $this->info('itemType') != 'Accession'
      && $this->info('itemType') != 'Item'
    )
      $html .= '<h4 class="center"><span class="error">Note:</span> This '.$this->info('itemType').' does not have any related Series, so we cannot search for related items</h4>';

    // TODO: under test ... competes with main search results a bit ...
    if ( $this->info('itemType') == 'Agency') {
      $agency_results = $this->showAgency( $this->info['entity']['name'] );
      if ($agency_results) {
        $html .= '<hr /><h3>This Agency is responsible for:</h3>';
        $html .= $agency_results;
      }
    }

    return Template::build( $html, $this->makePageTitle( $this->info('itemType'), $this->info('name') ) );

  } // end of showInfo
  
  function makePageTitle($type, $name) {
    
    // accessions have their name and code identical, lets check
    if ($this->code != $name)
      return $type.' '.$this->code.' | '.$name;

    return $type.' '.$this->code;
  }
  
  
  
  function info( $value ) {
    // mostly to suppress Undefined Index errors
    return $this->info ['entity'] [$value] ?? null;
  }


  function extractRelations(?Array $list, Array $custom=array() ) {

    if (!$list) return false;

    $generic = array(
      'transferring'     =>  'Transferring Agency',  
   
      'controlled'       =>  'Controlled',
      'controlling'      =>  'Controlling',    
      'controlled_by'    =>  'Controlled By',    
      
      'successor'        =>  'Succeeded By',    
      'predecessor'      =>  'Preceded By',
      
      'succeeding'       =>  'Succeeding',
      'preceding'        =>  'Preceding',    
      
      'superseded_by'    =>  'Superseded By',     
      'supersedes'       =>  'Supersedes',  
      
      'covered_by'       =>  'Covered By',    
      'responsible'      =>  'Responsible',     
    );

    $relation_list = array();
    
    // ok, now we sort the list by type
    foreach ( $list as $entry ) {
            
      // print_r( $entry );
            
      // collect dates, add brackets
      $dates = extractDates( $entry );
      if ($dates) 
        $dates = ' ('.$dates.')';
      
      $code = $this->getCode($entry);

      if (!$code)
        continue;

      $icon = entityIcon($entry['itemType'], $code);
      
      foreach($generic as $type => $label) {
        if ($entry['type'] != $type) continue;
        $relation_list[ $type ][] = $icon.entityLink( $code, $entry['name'], $this->entityTitle($entry) ).$dates;
      }
        
    } // end of foreach


    $html = '';
    
    // OK, now we cycle through the lists and put the custom labels on
    foreach ($relation_list as $r_type => $relations) {

      // skip this if we didn't find anything to show
      if (!$relation_list)
        continue;

      // label overrides happen here
      $r_label = $custom[$r_type] ?? $generic[$r_type];
        
      $html .= genericInfo($r_label, makeList($relations), true );  // we tell genericInfo this is already a truncated list
      
    }
    
    return $html;

  } // end of extractRelations


  

  function entityTitle($entry) {

    $code = $this->getCode($entry);
    
    // if the name and the code are identical, skip the name
    $name = '';
    if ($entry['name'] != $code)
      $name = ' - '.$entry['name'];
    
    return 'More information about '.$entry['itemType'].' '.$code.$name;
  } // end of entityTitle


  function getCode($entry) {
    
    // figure out which field holds the code we will use for linking
    if ( isset($entry['relationHasTarget']) ) 
      return $entry['relationHasTarget'];
    
    if ( isset($entry['relationHasSource']) ) 
      return $entry['relationHasSource'];

    if ( isset($entry['relationConnects']) )       
      return $entry['relationConnects'];      

    // we should never get here ...
    return false;
    // throw new Exception('Oops, tried to find a Code for a Relation with no clear Target or Source');
      
  } // end of getCode


  

  function showRelatedSeries( $series_array ) {
  
    if ( !is_array($series_array) )
      return false;

    $search = new AimsSearch;
    $search->setSeriesList( $series_array );
    return $search->simpleResults();
    
  } // end of showRelatedSeries 
  
  
  
  function showSeriesItems( array $series_array ) {

    // remove any non-integer items (eg, Accessions, Access Authorities)
    $series_array = array_filter($series_array, 'is_numeric');
    $c = count($series_array);
  
    if ( $c < 1 )
      return false;

    $heading = 'Searching within '.$this->info('itemType').' '.entityLink($this->info['entity']['id'], $this->info['entity']['name']).' ('.$c.' Related Series)';
    $placeholder = 'Keywords to find in '.$this->info('itemType');

    $warning = false;
    if ( count($series_array) > 250 ) {
      $count = count($series_array);
      $series_array = array_slice($series_array, 0, 250);
      $warning = '<h3 class="error center">NOTE: Related Series list has '.$count.' Series, which is too many to search at once.  Searching within first 250</h3>';
    }

    $search = new AimsSearch;
    $search->setParameters();
    $search->setMultipleSeries( $series_array, $placeholder );

    // this is required to set the facets correctly
    $_GET['multiple_series'] = implode(',', $series_array);

    $html = '';

    $html .= $search->getAdvancedForm( $heading );
    
    $html .= $warning;  // optional
    
    $html .= $search->getResults();
    
    return $html;
    
  } // end of showSeriesItems 
  

  
  function showSeries() {

    // we need to do this to set values in series_form
    $_GET['series'] = intval( $this->code );

    $heading = 'Searching within '.$this->info('itemType').' '.entityLink($this->info['entity']['id'], $this->info['entity']['name']);
    $placeholder = 'Keywords to find in '.$this->info('itemType');

    $search = new AimsSearch();    
    $search->setParameters();
    $html = '<br />'.$search->getAdvancedForm( $heading, $placeholder );
    $html .= $search->getResults();
    
    return $html;
    
  } // end of showSeries
  
  
  
  
  function showAccession() {

    // we need to do this to set values in series_form ... and it also triggers setParameters
    $_GET['accession'] = $this->code;
    
    $heading = 'Searching within '.$this->info('itemType').' '.entityLink($this->info['entity']['id'], $this->info['entity']['name']);
    $placeholder = 'Keywords to find in '.$this->info('itemType');

    $search = new AimsSearch();
    $search->setParameters();
    $search->SetGroupResults('ungrouped');// this removes the extraneous title
    $html = '<br />'.$search->getAdvancedForm( $heading, $placeholder );
    $html .= $search->getResults();
    
    return $html;
    
  } // end of showAccession
  
  
  function showAgency( $agency_name ) {
    
    $search = new AimsSearch;
    $search->setManagingAgency( $agency_name );
    $search->setEntityType( 'Accession' );
    return $search->simpleResults();
        
  } // end of showAgency
  
  
  
  function singleCategories( array $array ) {
    // hasOrHadCategory sometimes contains repeated info like this:
    // Series Format => Photograph Slides
    // Series Format => Photographic Negatives
    // Series Format => Photographic Positives
    $consolidated = [];
    $typeMap = [];

    foreach ($array as $item) {
        $type = $item['type'];
        $name = $item['name'];

        if (!isset($typeMap[$type]))
            $typeMap[$type] = [];
        
        $typeMap[$type][] = $name;
    }

    foreach ($typeMap as $type => $names) {
        $consolidated[] = [
            'type' => $type,
            'name' => implode('<br />', $names)
        ];
    }

    return $consolidated;
    
  } // end of singleCategories

  
  
  function getTechInfo() {
    // Technical information about this record (ie, full array of info)
    
    $show_tech_info = '<span class="technical_info" title="Show/Hide technical information">&#x24d8;</span>';
    
    $tech_info = '<div class="technical_info">';
    
      if ( isset($this->request_info) ) {
        
        $r = $this->request_info;
        $tech_info .= '<h2>cURL request stats</h2>';
        // $tech_info .= '<pre>'.print_r( $r, true ).'</pre>';
        
        $tech_info .= 'Status: '.$r['http_code'].'<br />';
        $tech_info .= 'Connected to '.$r['primary_ip'].' via '.$r['scheme'].' in '.round($r['connect_time'], 4).' seconds<br />';
        
        $dl_size = $r['size_download'] / 1024;
        // $dl_speed = $r['speed_download'] / (1024 * 1024);
        // ('.$dl_speed.' MB/s)
        $tech_info .= 'Downloaded '.round($dl_size, 4).' kB<br />';
        $tech_info .= 'Done in '.round($r['total_time'], 4).' seconds<br />';
        
        if ($r['redirect_count'])
          $tech_info .= 'Redirected '.$r['redirect_count'].' times';

        $tech_info .= '<hr />';
        
      } else {
        $tech_info .= '<p>Collected from local cache</p>';
      }
    
      $tech_info .= '<h2>Raw data '.makeLink($this->getUrl(), 'from Axiell backend').'</h2><hr />';
      $tech_info .= '<pre>'. addLinks( print_r($this->info, true) ).'</pre>';
    $tech_info .= '</div>';
            
    return $show_tech_info . $tech_info;  
    
  } 
  
    
    
  function getUrl() {
    
    return 'https://common.aims.axiellhosting.com/api/federation/latest/customers/61e1427f3d7155799faa2e4c/sources/aims-archive/entities/'.$this->code;
    
    // return 'https://common.aims.axiellhosting.com/api/federation/latest/customers/61e1427f3d7155799faa2e4c/sources/aims-archive/items/'.$this->code;
  }

  
  function collect() {

    // annoyingly this is subtly different from the version in AimsSearch
    // This endpoint outputs plain JSON, we don't need to strip anything off
    // TO DO:  detect valid JSON and skip data stripping?

    $spider = new Spider;
    $json = $spider->fetch( $this->getUrl(), 'entity' );
    
    $this->request_info = $spider->curl_info;
    
    // we run this here to catch any errors that have been cached (eg 502 errors)
    $spider->checkForJSONErrors( $json );
    
    $raw_info = json_decode( $json, true);

      // viewCode( $raw_info );
      // exit;
      
    return $raw_info;
    
  } // end of actuallyGetJSON
  
}