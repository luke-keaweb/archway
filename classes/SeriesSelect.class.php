<?php

class SeriesSelect {

    function renderAjax() {

      // do we actually have something to search for
      if (
        !isset($_GET['series_search'])
        || strlen($_GET['series_search']) < 2
      )
        return false;

// TO DO: sanitise input for SQL

      $series_obj = Series::getInstance();

      // if the user has given us a number, do an ID match search
      $matches_id = [];
      if ( is_numeric($_GET['series_search']) )
        $matches_id = $series_obj->searchID( $_GET['series_search'], 5 );

      // an exact match on the start of the name
      // this is only required to bring exact matches to the top of the list ...
      $matches_exact = $series_obj->searchNameExact( $_GET['series_search'] );

      // a more forgiving match with partial matches on every word in the query
      $matches_partial = $series_obj->searchNamePartial( $_GET['series_search'] );

      // using + instead of array_merge so series_ids are preserved
      $matches = $matches_id + $matches_exact + $matches_partial;

      if (!$matches)
        return '<label></label> <span>No Series matching \''.cleanString($_GET['series_search']).'\'</span>';

      $f = new SimpleForm;
      $series_radio = $f->radio()
        ->label('Series')
        ->tooltip('Show only results which belong to a specific series')
        ->name('series')
        ->options($matches)
        ->radioNewlines()
        ->getHTML();

      echo $series_radio;

    } // end of renderAjax
    
    
    
    static function formElement( $selected_series=null ) {
      
      $html = '<hr />';

      if (!$selected_series) {

        $html .= '<div class="form-section">';
          $html .= '<label for="form_series_search">Series Search</label>';
          $html .= '<input type="search" id="form_series_search" name="series_search" hx-get="/ajax/series_search/" hx-target="#series_search_results" hx-trigger="keyup changed delay:200ms, search" hx-push-url="false" placeholder="Type to search for a Series" />';
          $html .= '<br /><div id="series_search_results"></div>';    
          // $html .= '<br /> <input type="text" name="series" value="'.$this->series.'" />';
        $html .= '</div>';
        
      } else {
        $html .= '<p class="center">Series <b>'.$selected_series.' - '.linkSeries($selected_series).'</b> is selected</p>';
      }
      
      return $html;
      
    } // end of formElement


} // end of class
