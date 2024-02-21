<?php

class FormElements {

  static function keywords( $placeholder='What are you looking for?', $required=true ) {

    $f = new SimpleForm;

    $keywords = $f->search()
      ->name('search')
      ->class('keywords_search')
      ->placeholder( $placeholder );

    // skip autofocus if we are on the Entity page (ie, we have a Code)
    // although, we DO need some way to jump down to the search results if we are doing a search ...
    if ( !isset($_GET['code']) )
      $keywords->autofocus();

    if ($required)
      $keywords->required();

    $html = $keywords->getHTML();

    $html .= FormElements::button();

    return $html;

  } // end of keywords
  
  
  static function button($class="main", $text="Search!") {
    
    return '<button value="'.$text.'" class="'.$class.'" title="'.$text.'">
      <svg xmlns="http://www.w3.org/2000/svg" width="35" height="35" viewBox="0 0 512 512">
        <path class="icon-outline" d="M499.2,455.5L377.7,333.4c146-241.1-148.1-435.8-318.2-274c-165.1,185.9,41.6,460.6,273.4,319l121.5,118.8C489.5,535.8,534.4,490.8,499.2,455.5z M206.2,63.6c191.9,0,198.1,289,0,289C13.3,352.6,18.8,63.6,206.2,63.6z"></path>
      </svg>
      '.$text.'
    </button>'.PHP_EOL;
    
  }


  static function dates() {

    $f = new SimpleForm;

    $start = $f->number()
      ->label('Years')
      ->tooltip('Set a year range to filter this search (optional)')
      ->name('start_date')
      ->class('search_date')
      ->placeholder('1939')
      ->min('1700')
      ->max( date('Y') );

    if ( isset($_GET['start_date']) )
      $start->value( $_GET['start_date'] );

    $start = $start->getHTML();

    $end = $f->number()
      ->name('end_date')
      ->class('search_date')
      ->placeholder('1945')
      ->min('1700')
      ->max( date('Y') );

    if ( isset($_GET['end_date']) )
      $end->value( $_GET['end_date'] );

    $end = $end->getHTML();

    // wrap date fields together and return
    return $f->wrapInDiv( $start.'&mdash;'.$end );

  } // end of dates


  static function digital_select() {
    $f = new SimpleForm;

    $digital_options = array(
      '' => '',
      'Yes' => 'true',
      'No' => 'false'
    );

    return $f->select()
      ->label('Digital copy')
      ->tooltip('Filter results based on availability of a digital copy/scan')
      ->name('has_digital')
      ->options( $digital_options )
      ->wrap()
      ->getHTML();

  } // end of digital_select


  static function digital_checkbox() {
    $f = new SimpleForm;

    return $f->checkbox()
      ->label('Digital copy')
      ->tooltip('Show only results with a digital copy/scan')
      ->name('has_digital')
      ->value('true')
      ->wrap()
      ->getHTML();

  } // end of digital_checkbox


  static function digital_radio() {
    $f = new SimpleForm;

    return $f->radio()
      ->label('Digital copy')
      ->tooltip('Filter results based on availability of a digital copy/scan')
      ->name('has_digital')
      ->options([
        'Yes' => 'true',
        'No' => 'false',
        'Either' => '',
      ])
      ->default('')
      ->wrap()
      ->getHTML();

  } // end of digital_radio


  static function held_at() {
    $f = new SimpleForm;

    // this should not be duplicated ...
    $location_options = array(
      '' => '',
      'Auckland repository' => 'Auckland',
      'Christchurch repository' => 'Christchurch',
      'Dunedin repository' => 'Dunedin',
      'Wellington repository' => 'Wellington',
      'Alexander Turnbull' => 'Alexander Turnbull Library',
      'Digital Repository' => 'Digital Repository',
      'Agency' => 'Agency',
      'Not Held' => 'Not Held',
      // 'The Auckland War Memorial Museum Library' => 'Auckland War Memorial Museum Library',
      'The Hocken Library' => 'Hocken Library',
    );

    return $f->select()
      ->label('Held At')
      ->tooltip('Filter results by where the item is held')
      ->name('location')
      ->options( $location_options )
      ->wrap()
      ->getHTML();

  } // end of held_at


  static function access_radio() {
    $f = new SimpleForm;

    $access_options = array(
      // '' => '',
      'Open' => 'Open',
      'Restricted' => 'Restricted',
      'Any' => '',
    );

    return $f->radio()
      ->label('Access')
      ->tooltip('Filter results by access restriction')
      ->name('access')
      ->options( $access_options )
      ->default('')
      ->wrap()
      ->getHTML();

  } // end of access_radio


  static function access_select() {
    $f = new SimpleForm;

    $access_options = array(
      '' => '',
      'Open' => 'Open',
      'Restricted' => 'Restricted',
    );

    return $f->select()
      ->label('Access')
      ->tooltip('Filter results by access restriction')
      ->name('access')
      ->options( $access_options )
      ->wrap()
      ->getHTML();

  } // end of access_select


  static function format() {
    $f = new SimpleForm;

    $format_options = array(
      '' => '',
      'Text' => 'Text',
      'Map/Plan' => 'Map/Plan',
      'Moving Image' => 'Moving Image',
      'Sound Recording' => 'Sound Recording',
      'Artwork' => 'Artwork',
      'Photograph' => 'Photograph',
      'Object' => 'Object',
      'Other' => 'Not Determined',
      // probably others
    );

    return $f->select()
      ->label('Format')
      ->tooltip('Filter results by item format (text documents, artwork, maps, etc)')
      ->name('format')
      ->options( $format_options )
      ->wrap()
      ->getHTML();

  } // end of format

  static function entity_type() {
    $f = new SimpleForm;

    $entity_options = array(
      '' => '',
      'Series' => 'Series',
      'Agency' => 'Agency',
      'Accession' => 'Accession',
      'Organisation' => 'Organisation',
      'Item' => 'Item',
      'Disposal Authority' => 'Disposal Authority',
      'Access Authority' => 'Access Authority',
      'Function' => 'Function',
      'Jurisdiction' => 'Jurisdiction',
    );

    return $f->select()
      ->label('Entity type')
      ->tooltip('Restrict results to a specific record type, eg Series, Agency, Accession, Item, etc')
      ->name('entity_type')
      ->options( $entity_options )
      ->wrap()
      ->getHTML();

  } // end of entity_type


  static function group_results_radio() {
    $f = new SimpleForm;

    $grouping_options = array(
        'Series'  => '',             // default behaviour ... should we label this?
        'Agency'  => 'by_agency',
        'None'    => 'ungrouped',
    );

    return $f->radio()
      ->label('Group by')
      ->tooltip('Group search results into sections by type')
      ->name('group_results')
      ->options( $grouping_options )
      ->default('')
      ->wrap()
      ->getHTML();

  } // end of group_results_radio


    static function group_results_select() {
      $f = new SimpleForm;

      $grouping_options = array(
          'Series'  => '',             // default behaviour ... should we label this?
          'Agency'  => 'by_agency',
          'None'    => 'ungrouped',
      );

      return $f->select()
        ->label('Group by')
        ->tooltip('Group search results into sections by type')
        ->name('group_results')
        ->options( $grouping_options )
        ->wrap()
        ->getHTML();

    } // end of group_results_select


  static function sort_by() {
    $f = new SimpleForm;

    $sort_by_options = array(
      '' => '',                   // default is blank
      'Relevance' => 'score',
      'Title A-Z' => 'az',
      'Title Z-A' => 'za',
      'R-number 🔼' => 'id_asc',
      'R-number 🔽' => 'id_desc',
      'Series 🔼' => 'parent_id_desc',     // ACTUAL DEFAULT
      'Series 🔽' => 'parent_id_asc',
      'Recently Modified' => 'recently_modified',
      'Recently Created' => 'recently_created',
      'Recently Indexed' => 'recently_indexed',
    );

    return $f->select()
      ->label('Sort by')
      ->tooltip('Sort search results (works best with Group By set to none)')
      ->name('sort_by')
      ->options( $sort_by_options )
      ->wrap()
      ->getHTML();

  } // end of sort_by



} // end of class

 ?>
