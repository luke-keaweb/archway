<?php

class AimsSearch {

  use ResultParser;

  public ?string $keywords = null;

  public ?int $start_date = null;
  public ?int $end_date = null;

  public ?int $series = null;
  public ?string $accession = null;

  public ?string $managing_agency_by_name = null;
  public ?string $series_by_name = null;

  public ?array $series_list = null;
  public ?array $search_series_list = null;

  public ?string $entity_type = null;

  public ?string $has_digital = null;

  public ?string $location = null;

  public ?string $access = null;

  public ?string $format = null;

  public ?string $record_number = null;
  public ?string $record_number_alt = null;
  public ?string $former_archives_ref = null;

  public ?string $group_results = null;
  public ?string $sort_by = null;

  public int $size = 500;
  public int $start = 0;

  public $page_title=false;


  function simpleSearch() {

    $this->setParameters();

    $require_keywords = true;
    // several reasons to not require keywords
    if (
      !$this->entity_type
      && !$this->accession
      && !$this->series
      && !$this->series_list
      && !$this->search_series_list
    )
      $require_keywords = false;

    // if has_digital is No, use the radio buttons, otherwise the checkbox
    if ($this->has_digital === "false")
      $has_digital = FormElements::digital_radio();
    else
      $has_digital = FormElements::digital_checkbox();

    $html = $this->buildForm([
      FormElements::keywords('What are you looking for?', $require_keywords),
      FormElements::dates(),
      $has_digital,
    ]);
    
    $html .= $this->getResults();

    return Template::build( $html, $this->page_title );

  } // end of simpleSearch


  function advancedSearch($heading=false) {

    $this->setParameters();

    $html = $this->getadvancedForm($heading);
    $html .= $this->getResults();

    return Template::build( $html, $this->page_title );

  } // end of advancedSearch
  
  
  
  function advancedSearchWithSeries() {

    $this->setParameters();

    $html = $this->buildForm([
      FormElements::keywords(),
      FormElements::dates(),
      FormElements::digital_radio(),
      FormElements::access_radio(),
      FormElements::format(),
      FormElements::held_at(),
      FormElements::entity_type(),
      
      FormElements::group_results_radio(),
      FormElements::sort_by(),
      
      SeriesSelect::formElement( $this->series ),
      
    ]);

    $html .= $this->getResults();

    return Template::build( $html, $this->page_title );

  } // end of advancedSearchWithSeries
  


  function experimental() {

    $this->setParameters();

    $search_fields = array(
      FormElements::keywords(),
      FormElements::dates(),
      FormElements::digital_select(),
      FormElements::access_select(),
      FormElements::format(),
      FormElements::held_at(),
      FormElements::entity_type(),
    );

    $html = $this->buildForm($search_fields, 'Form with select elements');

    $html .= '<br /><br /><br /><hr /><br /><br /><br />';

    $search_fields_radio = array(
      FormElements::keywords(),
      FormElements::dates(),
      FormElements::digital_radio(),
      FormElements::access_radio(),
      FormElements::format(),
      FormElements::held_at(),
      FormElements::entity_type(),
    );

    $html .= $this->buildForm($search_fields_radio, 'Form with some radio elements');

    $html .= '<br /><br /><br /><hr /><br /><br /><br />';

    $html .= $this->buildForm($search_fields, 'Form with stacked labels, and three columns', 'experiment');

    $html .= '<br /><br /><br /><hr /><br /><br /><br />';

    $html .= $this->buildForm($search_fields_radio, 'Form with radio elements, stacked labels, and three columns', 'experiment');

    $html .= $this->getResults();

    return Template::build( $html, $this->page_title );

  } // end of experimental
  

  function csvExportLink() {
    $csv_url = '/spreadsheet/?'.$this->currentQueryString();
    return makeLink($csv_url, 'CSV Export', 'Download these results as a CSV spreadsheet', 'csv-export');
  }

  function spreadsheetExport() {

    $this->setSize(1000);
    $this->setGroupResults('ungrouped');
    $_GET['full_info_table'] = 1;

    $this->setParameters();
    $json = $this->collect( $this->getSearchUrl() );

    if ($json['totalHits'] < 1)
      return false;

    $results = $this->processResults( $json['hits'] );

    return $this->arrayToCSVDownload( $results );

  } // end of spreadsheetExport



  function arrayToCSVDownload($data, $filename = 'data.csv') {

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="'.$filename.'"');

    $output = fopen('php://output', 'w');

    // Add headers
    if (!empty($data)) {
        fputcsv($output, array_keys(reset($data)));
    }

    // Add rows
    foreach ($data as $row) {
        $cleanRow = array_map('csvCleanData', $row);
        fputcsv($output, $cleanRow);
    }

    fclose($output);

  } // end of arrayToCSVDownload


  function jsonExport() {

    // TODO: a blank keyword search defaults to *, can we stop doing that?

    $this->setSize(1000);
    $this->setGroupResults('ungrouped');
    $_GET['full_info_table'] = 1;

    $this->setParameters();
    $json = $this->collect( $this->getSearchUrl() );

    $search_array = array();
    $search_array['totalHits'] = $json['totalHits'];
    if ($json['totalHits'] > 0) {
      $results = $this->processResults( $json['hits'] );

      // remove any HTML tags
      // TODO: separate titles of, eg, Series, from Series URLs / codes
      $results = stripTagsFromArray($results);
      $search_array['results'] = $results;

    }




    echo json_encode( $search_array );

    // return $this->arrayToCSVDownload( $results );

  } // end of jsonExport



  function ajaxFacets() {

    $this->setParameters();
    return $this->showFacets();

  } // end of ajaxFacets

  function ajaxSearch() {

    // called by ASH
    $this->setParameters();
    return $this->getResults();

  } // end of ajaxSearch


  function setParameters() {

    $parameters = array(
      'setKeywords'       =>  'search',
      'setSeries'         =>  'series',
      'setAccession'      =>  'accession',
      'setManagingAgency' =>  'managing_agency',
      'setMultipleSeries' =>  'multiple_series',
      'setSeriesName'     =>  'series_name',
      'setEntityType'     =>  'entity_type',
      'setStart'          =>  'start',
      'setSize'           =>  'size',
      'setStartDate'      =>  'start_date',
      'setEndDate'        =>  'end_date',
      'setHasDigital'     =>  'has_digital',
      'setLocation'       =>  'location',
      'setAccess'         =>  'access',
      'setFormat'         =>  'format',

      'setRecordNumber'   =>  'record_number',
      'setRecordNumberAlt'    =>  'record_number_alt',
      'setFormerArchivesRef'  =>  'former_archives_ref',

      'setGroupResults'   =>  'group_results',
      'setSortBy'         =>  'sort_by',
    );

    foreach ($parameters as $setter => $get) {
      if ( isset($_GET[ $get ]) && $_GET[ $get ] != '' ) {
        $this->$setter( $_GET[ $get ] );
      }
    }

  } // end of setParameters


  function setKeywords( string $keywords ) {
    $this->keywords = cleanString( $keywords, false );  // false skips htmlspecialentities
  }

  function setStartDate( int $start_date ) {
    $this->start_date = $start_date;
  }

  function setEndDate( int $end_date ) {
    $this->end_date = $end_date;
  }

  function setSeries( int $series ) {
    $this->series = $series;
  }

  function setAccession( string $accession ) {
    // do we want to check this string is really an accession ... ?
    $this->accession = $accession;
  }

  function setManagingAgency( string $managing_agency_by_name ) {
    // returns a list of Series and Accessions managed by the agency
    // CANNOT be used to find items related to the agency!
    $this->managing_agency_by_name = $managing_agency_by_name;
  }

  function setSeriesName( string $series_by_name ) {
    // does this actually ever get used?
    // does it still work ... ?
    $this->series_by_name = $series_by_name;
  }

  function setSeriesList( array $series_list ) {
    $this->series_list = $series_list;
  }

  function setMultipleSeries( $search_series_list ) {
    // $search_series_list should be either an array or a comma-delimited list
    if ( is_array($search_series_list) )
      $this->search_series_list = $search_series_list;
    else
      $this->search_series_list = explode(',', $search_series_list);
  }

  function setEntityType( string $entity_type) {
    $this->entity_type = $entity_type;
  }

  function setHasDigital( string $has_digital) {
    // must be a string containing 'true' or 'false'
    if ($has_digital != 'true' && $has_digital != 'false')
      throw new Exception('has_digital condition not recognised!');

    // a string containing 'true' or 'false'
    $this->has_digital = $has_digital;

    // we don't want to overload the poor old NatLib thumbnail servers now, do we?
    if ($has_digital == 'true')
      $this->setSize(100);

  } // end of setHasDigital

  function setLocation( string $location) {
    $this->location = $location;
  }

  function setAccess( string $access) {
    $this->access = $access;
  }

  function setFormat( string $format) {
    $this->format = $format;
  }

  function setRecordNumber( string $record_number) {
    $this->record_number = $record_number;
  }

  function setRecordNumberAlt( string $record_number_alt) {
    $this->record_number_alt = $record_number_alt;
  }

  function setFormerArchivesRef( string $former_archives_ref) {
    $this->former_archives_ref = $former_archives_ref;
  }

  function setGroupResults( string $group_results ) {
    $this->group_results = $group_results;
  }

  function setSortBy( string $sort_by ) {
    $this->sort_by = $sort_by;
  }

  function setSize( int $size ) {
    $this->size = $size;
  }

  function setStart( int $start ) {
    $this->start = $start;
  }



  function getCollectionsUrl() {
    // for replicating the search on the official Collections site
    $collections_url = 'https://collections.archives.govt.nz/en-GB/web/arena/search#/?';
    return $collections_url . $this->searchParams();
  }

  function getSearchUrl() {

    // for actually collecting the info we need

    // we can only get the facet stuff returned if nativeQuery is false ...

    $api_url = 'https://common.aims.axiellhosting.com/api/federation/latest/customers/61e1427f3d7155799faa2e4c/search';
    $api_url .= '?nativeQuery=true';
    $api_url .= '&size='.$this->size;
    $api_url .= '&facetSize=0';
    $api_url .= '&start='.$this->start;

    return $api_url . $this->searchParams();

  } // end of getSearchUrl


  function getFacetUrl() {

    $facet_url = 'https://common.aims.axiellhosting.com/api/federation/latest/customers/61e1427f3d7155799faa2e4c/search';
    $facet_url .= '?nativeQuery=false';
    $facet_url .= '&facetSize=40';
    $facet_url .= $this->searchParams();

    foreach ($this->facetList() as $facet)
      $facet_url .= '&facetField='.urlencode( '"field":"'.$facet.'","sourceId":"aims-archive"');

    return $facet_url;

  } // end of getFacetUrl


  function searchParams() {

    $url = '';

    // This is the default search
    // it searches for each keyword separately
    if ($this->keywords)
      $url .= '&q='.urlencode($this->keywords);
    else
      $url .= '&q=*';

    // now we can add in any other search parameters we have
    $conditions = array(
      'digitalRecordInArchive' => $this->has_digital,  // a string, "true" or "false"
      'parentId' => $this->series,
      'isOrWasIncludedIn_accession' => $this->accession,
      'managementRelation_agency' => $this->managing_agency_by_name,
      'id' => $this->series_list,               // an array of series IDs
      'itemType' => $this->entity_type,              // Item, Series, Agency, etc
      'isAssociatedWithPlace' => $this->location,     // repository location
      'conditionsOfAccess' => $this->access,          // open vs restricted access
      'hasOrHadCategory_contentType' => $this->format,            // text, video, audio, etc

      'recordNumber_search' => $this->record_number,
      'recordNumberAlternative_search' => $this->record_number_alt,
      'formerArchivesReference_search' => $this->former_archives_ref,
    );

    // loop through our generic conditions and add to the URL query string
    foreach ($conditions as $field => $value) {
      if ( $value )
        $url .= $this->condition($field, $value);
    }

    // for searching INSIDE an array of series
    // this has to be separate from above because the array can only hold one instance of 'parentId'
    if ($this->search_series_list)
      $url .= $this->condition('parentId', $this->search_series_list);

    // Date ranges are a bit different.  Need a start + end year, eg:
    // &c="field":"hasBeginningDate_facet","gte":"1970","lte":"1975","type":"in"
    // &c="field":"hasEndDate_facet","gte":"1970","lte":"1975","type":"in"

    // if we have a start date but no end date, use the present as the end date
    if ( $this->start_date && !$this->end_date )
      $this->end_date = date('Y');

    if ( $this->start_date && $this->end_date ) {

      $start_date_condition = '"field":"hasBeginningDate_facet","gte":"'.$this->start_date.'","lte":"'.$this->end_date.'","type":"in"';
      $end_date_condition = '"field":"hasEndDate_facet","gte":"'.$this->start_date.'","lte":"'.$this->end_date.'","type":"in"';

      $url .= '&c='.urlencode( $start_date_condition ).'&c='.urlencode( $end_date_condition );

    }

    // Filtering by Series name (eg for facet filter)
    // turns out there is a HORRIFIC bug where this filter can encompass dozens of unrelated series which happen to have the same name
    if ($this->series_by_name) {
      // is this facetField part necessary?
      $url .= '&facetField='.urlencode( '"field":"isOrWasIncludedIn_series","sourceId":"aims-archive"');
      $url .= '&fc='.urlencode( '"field":"isOrWasIncludedIn_series","type":"in","values":["\"'.$this->series_by_name.'\""]' );
    }

    // sortBy has moved to a separate function
    $url .= $this->getSortCondition();

    $url .= '&sourceId=aims-archive';

    return $url;

  } // end of searchParams



  function condition(string $field, $value, $c='c') {
    // $c should be either 'c' or 'fc'

    // $value could be a string or an array (eg, a list of Related Series IDs to search)
    // here we implode any arrays into a string of values to be searched
    if (is_array($value) ) {

      // we need to truncate very large arrays
      if (count($value) > 250)
        $value = array_slice($value, 0, 250);

      $value = implode('","', $value);
    } // end of array handling

    // if our value contains spaces, wrap it in extra quotes
    $value = stristr($value, ' ') ? '\"'.$value.'\"' : $value;

    return '&'.$c.'='.urlencode( '"field":"'.$field.'","type":"in","values":["'.$value.'"]' );
  } // end of condition


  function getSortCondition() {

      // set default - sort by Series, to work with our group-by-Series default
      if (!$this->sort_by)
        $this->sort_by = 'parent_id_desc';

      $sort_by_options = array(
        'Relevance' => 'score',
        'Title A-Z' => 'az',
        'Title Z-A' => 'za',
        'R-number (ascending)' => 'id_asc',
        'R-number (descending)' => 'id_desc',
        'Series (descending)' => 'parent_id_desc',           // DEFAULT
        'Series (ascending)' => 'parent_id_asc',
        'Recently Modified' => 'recently_modified',
        'Recently Created' => 'recently_created',
        'Recently Indexed' => 'recently_indexed',
      );

      // sanity check
      if ( !in_array($this->sort_by, array_values($sort_by_options)) )
        throw new Exception('Invalid sort by option!');

      switch($this->sort_by) {

        case 'score':
          return $this->sort('score', 'desc');

        case 'id_asc':
          return $this->sort('id', 'asc');

        case 'id_desc':
          return $this->sort('id', 'desc');

        case 'az':
          return $this->sort('name_sort', 'asc');

        case 'za':
          return $this->sort('name_sort', 'desc');

        case 'parent_id_desc':
          return $this->sort('parentId', 'desc');

        case 'parent_id_asc':
          return $this->sort('parentId', 'asc');

        case 'recently_modified':
          return $this->sort('modified', 'desc');

        case 'recently_created':
          return $this->sort('created', 'desc');

        case 'recently_indexed':
          return $this->sort('indexedAt', 'desc');

        default:
          return $this->sort('parentId', 'desc');

      } // end of switch

  } // end of getSortCondition


  function sort($field, $order) {
    return '&sort='.urlencode( '"field":"'.$field.'","order":"'.$order.'","sourceId":"aims-archive"');
  }


  // function getSimpleForm
  
  function getAdvancedForm($heading=false) {
    
    return $this->buildForm(
      [
        FormElements::keywords(),
        FormElements::dates(),
        FormElements::digital_radio(),
        FormElements::access_radio(),
        FormElements::format(),
        FormElements::held_at(),
        FormElements::entity_type(),
        FormElements::group_results_radio(),
        FormElements::sort_by(),      
      ],
      $heading
    );
  
  } // end of getAdvancedForm
  
  function buildForm(
      $search_fields,
      $heading=false,
      $class='',
    ) {

    $html = '<div class="search_box '.$class.'">'.PHP_EOL;

    if ($heading)
      $html .= '<h3 class="center">'.$heading.'</h3>'.PHP_EOL;

    $html .= '<form method="GET" id="search-form" action="'.Router::getPrettyUrl().'#search-form" hx-get="'.Router::getPrettyUrl().'#search-form" hx-push-url="true" hx-target="body" hx-indicator="body">'.PHP_EOL;

    foreach ($search_fields as $field)
      $html .= $field;

    // a handy button just for mobile (under the long list of fields)
    $html .= '<label></label>'.FormElements::button('mobile_search_button');

    $html .= '</form>';
    $html .= '</div>';

    return $html;

  } // end of experimentalForm


  function getResults() {

    // Step One: check we have something to search for ...
    if (
      !$this->keywords
      && !$this->series
      && !$this->accession
      && !$this->managing_agency_by_name    // find Series and Accessions related to this Agency
      && !$this->series_list                // search for the specific Series Numbers in the list
      && !$this->search_series_list   // searches for items WITHIN the list of Series numbers
      && !$this->entity_type

      && !$this->record_number
      && !$this->record_number_alt
      && !$this->former_archives_ref
    )
      return '<h2 class="error center" style="margin-top:1em;">
                  Type some text in the form to search Archives NZ
              </h2><br /><br />';

    // ok, so we have some criteria to do a search with

    $json = $this->collect( $this->getSearchUrl() );
    // viewCode( $json );

    $html = $this->describeResults( $json['totalHits'] );

    // if we have any results, show them:
    if ($json['totalHits'] > 0) {

      $html .= $this->showLazyFacets();

      $html .= $this->showPagination( $json['totalHits'], $this->size, $this->start );

      $html .= $this->csvExportLink();

      $html .= $this->presentResults( $json );

      $html .= $this->showPagination( $json['totalHits'], $this->size, $this->start );

    }

    $html .= $this->getAttribution();

    return $html;

  } // end of getResults method


  function countResults() {
    $json = $this->collect( $this->getSearchUrl() );
    return $json['totalHits'];
  }



  function presentResults($json) {

    // how should we group these results?

    // if we are showing a Series, we don't need to group things into sections
    if ( $this->series )
      return $this->unGroupedResults($json);

    if ( $this->group_results == 'ungrouped')
      return $this->unGroupedResults($json);

    if ( $this->group_results == 'by_agency' )
      return $this->groupResultsByAgency($json);

    // else, the default grouping option:
    return $this->groupResultsBySeries($json);

  } // end of presentResults


  function getAttribution() {

    $archives_nz = makeLink( $this->getCollectionsUrl(), 'Archives NZ results', 'View this search on the official Archives NZ Collections Search website');

    $api = makeLink( $this->getSearchUrl(), 'API', 'View raw API data from Axiell backend server');

    $quote = '"[W]here we have already ... made [archival material] digitally available on Collections search, it is covered by a Creative Commons BY 2.0 license, unless otherwise stated. You are then welcome to use it without seeking permission."';

    $cc = makeLink('https://www.archives.govt.nz/copyright', 'CC BY 2.0', $quote);

    $attribution = '<p class="attribution">';
      $attribution .= $archives_nz.' via '.$api.' under '.$cc.' license';
    $attribution .= '</p>';

    return $attribution;

  } // end of getAttribution




  function facetList() {
    // this list of facets determines which facets we request from the server
    // and also the order in which facets appear in the UI
    return array(
      "parentId",                     // actual Series owner
      "isOrWasIncludedIn_accession",  // accession
      "hasOrHadCategory_contentType",             // format eg Text, Map/Plan, Audio, Video
      "isAssociatedWithPlace",        // Location / repository
      "conditionsOfAccess",           // Open, Restricted, etc
      "itemType",                     // Item, Series, Agency, etc

      // turned off for the moment as it conflicts with the existing Digitised checkbox
      // tryinng various ways to reintegrate
      "digitalRecordInArchive",       // digitised

      // serious issues with how these are implemented
      // "isOrWasIncludedIn_series",     // literally uses a non-unique name, lol
      // "managementRelation_agency",    // only applies to Series results, super dumb

      // pointless, ignored:
      // "hasEndDate",
      // "hasBeginningDate",

      // not implemented:
      // "authorityRelation_function",    // Function ... might be nice with their name
      // "ricType",                       // Physical / Digital

      // tested, do NOT work
      // "authorityRelation_jurisdiction",
      // "managementRelation_jurisdiction",
      // "authorityRelation_organisation",
      // "managementRelation_organisation",

    );
    // is there some way to request a list of this info from the server?

  } // end of facetList



  function showLazyFacets() {

    $html = '<div class="facets" hx-get="/ajax/facets?'.$this->currentQueryString().'&facets_action='.Router::getPrettyUrl().'" hx-trigger="load" hx-swap="outerHTML">';
      $html .= '<span class="facet_label">Filters</span> ';
    $html .= '</div>';

    return $html;

  } // end of showLazyFacets

  function currentQueryString() {

    // Parse existing QUERY_STRING into an array
    parse_str($_SERVER['QUERY_STRING'], $queryArray);

    // Merge with $_GET, $_GET values will take precedence
    $combined = array_merge($queryArray, $_GET);

    // Build the new query string
    return http_build_query($combined);

  } // end of currentQueryString

  function showFacets() {

    // which facets show up are under our control in facetList()
    try {
      $json = $this->collect( $this->getFacetUrl() );
    } catch (Exception $e) {
      return false;
    }

    // stop here if we have no facets to show
    if ( !isset($json['aggregations']) )
     return false;

    // $facet_chosen = '';     // facets the user has already filtered on
    $facet_selects = '';    // available facets which the user can filter by

    // sort the facets so they appear in the correct order
    $aggregations = $this->sortFacets( $json['aggregations'] );

    foreach ($aggregations as $facet) {

      // skip facets with no options
      if ( !isset($facet['buckets']) )
        continue;

      $facet_selects .= $this->facetSelect( $facet );

    } // end of foreach

    // stop here if we have no facets available to show the user
    if (!$facet_selects)
      return false;

    $f = new SimpleForm();

    $html = '<div class="facets">';

      $action = $_GET['facets_action'] ?? null;

      $html .= $f->start('GET', $action);

      $html .= '<span class="facet_label">Filters</span> ';
      $html .= $facet_selects;

      // just need a few hidden variables here to hold the already-existing search parameters
      $html .= $f->hidden()->name('search')->getHTML();
      $html .= $f->hidden()->name('start_date')->getHTML();
      $html .= $f->hidden()->name('end_date')->getHTML();
      // $html .= $f->hidden()->name('has_digital')->getHTML();

      $html .= $f->end();

    $html .= '</div>';

    return $html;

  } // end of showFacets


  function sortFacets( $raw_facets ) {

    $facet_list = $this->facetList();

    usort($raw_facets, function ($a, $b) use ($facet_list) {
        $posA = array_search($a['name'], $facet_list);
        $posB = array_search($b['name'], $facet_list);
        return $posA - $posB;
    });

    return $raw_facets;

  } // end of sortFacets


  function facetSelect( $facet ) {

    // single-option facets are handled a bit differently
    if (count( $facet['buckets'] ) == 1)
      return $this->facetChosen( $facet );

    $label = $this->translateFacetLabel($facet['name']);

    $options = array($label => '');

    foreach ($facet['buckets'] as $bucket) {
      $b_label = $this->bucketLabel($bucket, $facet) . ' ('.number_format( $bucket['count'] ).')';
      $options[ $b_label ] = $bucket['key'];
    }

    $f = new SimpleForm;

    $facet_select = $f->select()
      ->name( $this->translateFacetName($facet['name']) )
      ->tooltip('Refine results by '.$label)
      ->options( $options )
      ->class( 'facet_select' )
      ->getHTML();

    return $facet_select;

  } // end of facetSelect


  function facetChosen( $facet ) {

    // there should only be one bucket option
    if ( count($facet['buckets']) != 1)
      throw new Exception('incorrect number of buckets in facetChosen');

    $name = $this->translateFacetName($facet['name']);

    // has this single-option facet been chosen already?
    if (
      isset($_GET[$name])
      && $_GET[$name] == $facet['buckets'][0]['key']
    ) {

      $label = $this->translateFacetLabel($facet['name']);

      $options = array();

      // again, should only be one bucket here
      foreach ($facet['buckets'] as $bucket) {
        $bucket_name = $this->bucketLabel( $bucket, $facet );
        $bucket_label = $label.': '.$bucket_name;
        $options[ $bucket_label ] = $bucket['key'];
      }

      // the user can remove the filter by choosing this blank option
      $options['(Reset '.$label.' filter)'] = '';

      $f = new SimpleForm;
      $facet_selected = $f->select()
        ->name( $name )
        ->tooltip('To remove, click (Reset '.$label.' filter)')
        ->options( $options )
        ->class( 'facet_chosen' )
        ->getHTML();

      return $facet_selected;

    }

    // otherwise, return false
    return false;

  } // end of facetChosen


  function bucketLabel( $bucket, $facet ) {

    if ($facet['name'] == 'parentId') {
      $series_obj = Series::getInstance();
        // we can help out Series by passing the whole set of series IDs
        $series_obj->hint( array_column($facet['buckets'], 'key') );
      return $series_obj->name( $bucket['key'] );
    }

    if ($bucket['key'] == 'true')
      return 'Yes';

    if ($bucket['key'] == 'false')
      return 'No';

    // open / restricted access icons
    // TO DO: clean this up, we should have centralised icons
    if ($bucket['key'] == 'Open')
      $bucket['key'] = '👌 '.$bucket['key'];
    if ($bucket['key'] == 'Restricted')
      $bucket['key'] = '🔒 '.$bucket['key'];
    if ($bucket['key'] == 'May be restricted')
      $bucket['key'] = '❓ '.$bucket['key'];


    // remove 'repository' for brevity
    return trim( str_replace('repository', '', EntityIcon( $bucket['key'] ).$this->getFormatIcon( $bucket['key'] ).$bucket['key']) );

  } // end of translateBucketLabel

  function translateFacetLabel( $name ) {

    $facet_labels = array(
      "itemType" => "Entity Type",
      "hasOrHadCategory_contentType" => "Format",
      "digitalRecordInArchive" => "Digitised",
      "isOrWasIncludedIn_accession" => "Accession",
      "isAssociatedWithPlace" => "Held At",
      "parentId" => "Series",
      // "isOrWasIncludedIn_series" => "Series",
      // "managementRelation_agency" => "Agency",
      "conditionsOfAccess" => "Access",
    );

    return $facet_labels[ $name ] ?? '';

  } // end of translateFacetLabel


  function translateFacetName( $name ) {

    $facet_labels = array(
      "itemType" => "entity_type",
      "hasOrHadCategory_contentType" => "format",
      "digitalRecordInArchive" => "has_digital",
      "isOrWasIncludedIn_accession" => "accession",
      "isAssociatedWithPlace" => "location",
      "parentId" => "series",
      // "isOrWasIncludedIn_series" => "series_name",
      // "managementRelation_agency" => "managing_agency",
      "conditionsOfAccess" => "access",
    );

    // should we integrate these with our Advanced Search or separate them from our usual approach?
    return $facet_labels[ $name ] ?? '';

  } // end of translateFacetLabel



  function describeResults( $hits ) {

    // 2,588 digitised restricted-access maps and plans matching 'Ohura'

    $html = '';

    $entity_type = $this->entity_type.' ' ?? '';

    $digital = '';
    if ( $this->has_digital == 'true' )
      $digital = 'digitised ';
    if ( $this->has_digital == 'false' )
      $digital = 'non-digitised ';

    $access = '';
    if ($this->access)
      $access = strtolower($this->access).'-access ';

    $result_label = 'result';
    if ( $this->format )
      $result_label = $this->simpleFormat($this->format);

    if ( $this->entity_type )
      $result_label = $this->entity_type;

    if ( $this->format && $this->entity_type )
      $result_label = $this->simpleFormat($this->format).' '.$this->entity_type;

    if ( $this->search_series_list )
      $result_label = 'related item';

    $main_label = $digital.$access.$result_label;

    if ($hits == 0)
      $html .= 'No '.$main_label.'s found';
    else if ($hits == 1)
      $html .= 'One '.$main_label;
    else
      $html .= '<span class="keywords">'.number_format( $hits ).'</span> '.$main_label.'s';

    // tidy up plurals
    $html = str_replace('Seriess', 'Series', $html);
    $html = str_replace('Agencys', 'Agencies', $html);
    $html = str_replace('Authoritys', 'Authorities', $html);

    if ( $this->keywords != '' && $this->keywords != '*' )
      $html .= ' matching <span class="keywords toggle_highlight" title="Highlight keywords in result titles">'.$this->keywords.'</span>';

    // there should be at most one of these
    // TODO: this certainly needs tidying up
    if ($this->record_number != '')
      $html .= ' where Record Number matches <span class="keywords">'.$this->record_number.'</span>';

    if ($this->record_number_alt != '')
      $html .= ' where Alternative Record Number matches  <span class="keywords">'.$this->record_number_alt.'</span>';

    if ($this->former_archives_ref != '')
      $html .= ' where Former Archives Reference matches <span class="keywords">'.$this->former_archives_ref.'</span>';

    // series name
    if ( $this->series ) {
      $series_obj = Series::getInstance();
      $series_name = $series_obj->name( $this->series );
      $html .= ' in '.entityLink( $this->series, $series_name );
    }

    // series name (from facet search)
    if ( isset($_GET['series_name']) && $_GET['series_name'] != '' )
      $html .= ' in '.$_GET['series_name'];

    if ( $this->accession )
      $html .= ' in '.entityLink($this->accession, $this->accession);

    if ( $this->start_date && $this->end_date )
      $html .= ' between '.$this->start_date.'-'.$this->end_date;

    if ( $this->location )
      $html .= ', at '.$this->location;

    // for use in template
    $this->page_title = $html;

    return '<h2 class="result_description" id="results">'.$html.'</h2>';

  } // end of describeResults


  function simpleFormat(string $format) {
    // takes a formal string like "Sound Recording" and returns a simple name like 'audio'
    $translate = array(
      'Text' => 'document',
      'Map/Plan' => 'map/plan',
      'Moving Image' => 'video',
      'Sound Recording' => 'audio',
      'Artwork' => 'artwork',
      'Photograph' => 'photo',
      'Object' => 'object',
      // 'Not Determined' => 'result'
    );
    // if the string isn't in the array, default to 'result'
    $simple_format = isset($translate[$format]) ? $translate[$format] : 'result';
    return $simple_format;
  } // end of simpleFormat


  function groupResultsBySeries( $json ) {

    $html = '';

    $labels = array();
    $sections = array();

    // go through the results and organise them into Series, Accession, etc sections
    foreach ( $json['hits'] as $raw_result ) {

      $r = $this->translate( $raw_result );

      // if ($r['entity_type'] == 'Agency')
      //   $this->saveAgency( $r );

      // Items that belong to a Series
      if ( $r['series_id'] != '') {

        $series_link = entityLink(
          $r['series_id'],
          $r['series_name'],
          'View more information about '.$r['series_name'].' (Series '.$r['series_id'].')'
        );

        // do we also have an agency to display in the section label?
        if (isset($r['agency_code']) ) {
          $agency_link = entityLink(
            $r['agency_code'],
            $r['agency_name'],
            'View more information about '.$r['agency_name'].' (Agency '.$r['agency_code'].')'
          );

          $labels[ $r['series_id'] ] = $agency_link.'&nbsp;&nbsp;>&nbsp;&nbsp;'.$series_link;

        } else {
          $labels[ $r['series_id'] ] = $series_link;
        }

        $sections[ $r['series_id'] ][] = $raw_result;  // save record into a chunk

      // Items that only belong to an Accession (no Series)
      } else if ( $r['accession'] != '') {

        $labels[ $r['accession'] ] = entityLink(
          $r['accession'],
          'Accession '.$r['accession'],
          'View more information about Accession '.$r['accession']
        );

        $sections[ $r['accession'] ][] = $raw_result;  // save record into a chunk

      // Other entries, sorted by entity type (Agency, etc
      } else if ( $r['entity_type'] != '') {

        $labels[ $r['entity_type'] ] = entityIcon($r['entity_type']).$r['entity_type'];   // need to make this plural
        $sections[ $r['entity_type'] ][] = $raw_result;  // save record into a 0 chunk

      // I don't think we should ever end up here?
      } else {

        $labels[0] = '<span class="error">Miscellaneous / Uncategoried</span>';
        $sections[0][] = $raw_result;  // save record into a 0 chunk

      }

    } // end of JSON section chunking


    // for each chunk of results, process the array and make it into a nice link table entry
    foreach ($sections as $code => $section)
      $html .= FormatData::display(
        $labels[ $code ],
        $this->processResults( $section )
      );

    return $html;

  } // end of groupResultsBySeries


  function groupResultsByAgency( $json ) {

    $html = '';

    $labels = array();
    $sections = array();

    // go through the results and organise them into Series, Accession, etc sections
    foreach ( $json['hits'] as $raw_result ) {

      $r = $this->translate( $raw_result );

      // Items that belong to an Agency
      if ( $r['agency_code'] != '') {

        $labels[ $r['agency_code'] ] = entityLink( $r['agency_code'], $r['agency_name'], 'View more information about '.$r['agency_name'].' (Agency '.$r['agency_code'].')');

        $sections[ $r['agency_code'] ][] = $raw_result;  // save record into a chunk

      // Other entries, sorted by entity type (Organisation, etc
      } else if ( $r['entity_type'] != '') {

        $labels[ $r['entity_type'] ] = $r['entity_type'];   // need to make this plural
        $sections[ $r['entity_type'] ][] = $raw_result;  // save record into a 0 chunk

      } else {

        $labels[0] = '<span class="error">Miscellaneous (no Agency recorded)</span>';
        $sections[0][] = $raw_result;  // save record into a 0 chunk

      }

    } // end of JSON section chunking


    // for each chunk of results, process the array and make it into a nice link table entry
    foreach ($sections as $code => $section)
      $html .= FormatData::display(
        $labels[ $code ],
        $this->processResults( $section )
      );

    return $html;

  } // end of groupResultsBySeries


  function unGroupedResults( $json ) {

    $results = $this->processResults( $json['hits'] );

    return FormatData::display( null, $results );

  } // end of unGroupedResults


  function arrayOfResults() {

    $json = $this->collect( $this->getSearchUrl() );

    $translated_results = array();
    $i = 0;

    foreach ( $json['hits'] as $raw ) {

        $translated_results[$i] = $this->translate( $raw );
        $translated_results[$i]['extended_info'] = $this->processExtendedInfo( $translated_results[$i] );
        $i++;
    }

    return $translated_results;

  } // end of arrayOfResults


  function simpleResults() {

    $json = $this->collect( $this->getSearchUrl() );

    // stop here if we have nothing
    if ($json['totalHits'] < 1)
      return false;

    return $this->unGroupedResults( $json );
    // return $this->groupResultsBySeries( $json );

  } // end of runActualSearch method







   function collect( $url ) {

     $spider = new Spider;
     $output = $spider->fetch( $url, 'search' );

     // for debugging how much time it takes to fetch search + facets
     // echo $url;
     // print_r( $spider->curl_info );
     // echo '<br />';

     // process the stream to remove unncessary non-JSON guff
     $event_search_output = explode('event:search', $output);
     // print_r( $event_search_output );

     $event_complete_output = explode('event:complete', $event_search_output[1]);
     // print_r( $event_complete_output );

     // now we need to remove the last 40 characters
     // id:baf58deb-a68a-430e-b922-7ad2743480d0
     $data_output = substr($event_complete_output[0], 0, -40);
     // print_r( $data_output );

     // now we need to remove the first 6 characters
     // data:
     $actual_json_data = substr($data_output, 6);
     // print_r( $actual_json_data );

     $json = json_decode( trim( $actual_json_data ), true);
     // print_r( $json );

     // viewCode( $json );

     return $json;

   } // end of collect




   function showPagination(
     int $result_size,
     int $size,
     int $start
   ) {

     // stop here if we only have one page of pagination
     if ($result_size < $size)
       return false;

     $html = '<div class="pagination_outer">'.PHP_EOL;
     $html .= '<div class="pagination">'.PHP_EOL;
     $html .= '<span class="pagination">View</span>&nbsp;&nbsp;'.PHP_EOL;

     // Logic for pagination
     $pages = $result_size / $size;
     for (
       $page=0;
       $page <= $pages;
       $page++
     ) {

       $start = $page * $size;
       $end = $start + $size;

       // truncate end if we are on the last page
       $display_end = $end;
       if ($end > $result_size)
         $display_end = $result_size;

       // adjust things to be non-zero-indexed
       $display_start = $start + 1;
       $display_page = $page + 1;

       $link = $this->getPaginationLink( $this->size, $start );

       // highlight the current pagination we are on
       $get_start = $_GET['start'] ?? 0;
       $class = ($start == $get_start) ? ' live' : '';

       // TODO: would be nice to get the current pagination in the page title maybe?

       $title = 'View results '.$display_start.'-'.$display_end;

       $html .= '<a href="'.$link.'" title="'.$title.'" class="pagination'.$class.'">'.$display_start.'-'.$display_end.'</a>'.PHP_EOL;

     }

     $html .= '</div>'.PHP_EOL;   // end of pagination
     $html .= '</div>'.PHP_EOL;   // end of pagination_outer

     return $html;

   } // end of showPagination;


   function getPaginationLink(int $size, int $start) {

     $queries = array();
     parse_str($_SERVER['QUERY_STRING'], $queries);

     $queries['size'] = $size;
     $queries['start'] = $start;

     $query_string = http_build_query($queries);

     $url_chunk = parse_url($_SERVER["REQUEST_URI"]);
     //  $url_chunk['host']
     $base_url = getArchDomain().$url_chunk['path'];

     return $base_url.'?'.$query_string;

   } // getPaginationLink





   // function saveAgency( $r ) {
   //
   // // connect to DB
   // $my_db = new MySQL;
   // $db = $my_db->connect();
   //
   // $sql = 'INSERT INTO agencies (code, name) VALUES (';
   //
   // $sql .= '"'.mysqli_real_escape_string($db, $r['code']).'", ';
   // $sql .= '"'.mysqli_real_escape_string($db, $r['name']).'"';
   // $sql .= ');';
   //
   // // echo $sql.'<br />';
   //
   // $db->query($sql) or die( $db->error );
   //
   // // echo '<p>Saved agency '.$r['name'].'</p>';
   //
   // } // end of saveAgency



} // end of class
