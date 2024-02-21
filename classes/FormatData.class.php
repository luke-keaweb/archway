<?php

class FormatData {

  static function display( string $heading=null, Array $results, bool $remove_blanks=true ) {

    // stop here if we have nothing
    if ( count($results) < 1)
      return false;

    $html = '<div class="result_set">'.PHP_EOL;

      if ($heading)
        $html .= '<h3 class="record_type">'.$heading.'</h3>'.PHP_EOL;

      $html .= FormatData::table($results, $remove_blanks);

    $html .= '</div>'.PHP_EOL;

    return $html;

  } // end of display



  static function table( Array $table, bool $remove_blanks=true ) {

    // input: an array where for each row, there are cells in the form
    // table heading label => cell value

    // output:  HTML string with nicely formatted table

    $tab = "\t";

    // clean out any blank columns
    if ($remove_blanks)
      $table = FormatData::removeBlankColumns( $table );

    $html = '<table class="result_table">'.PHP_EOL.PHP_EOL;

    $html .= $tab.'<thead>'.PHP_EOL;
      $html .= $tab.$tab.'<tr>'.PHP_EOL;
        foreach ($table[0] as $label => $value) {
          if ($label == '_class') continue;   // ignore _class values
          $html .= $tab.$tab.$tab.'<td title="Click to sort by this column">'.$label.'</td>'.PHP_EOL;
        }
      $html .= $tab.$tab.'</tr>'.PHP_EOL;
    $html .= $tab.'</thead>'.PHP_EOL.PHP_EOL;

    $html .= $tab.'<tbody>'.PHP_EOL;

      foreach ($table as $row) {

        $class = $row['_class'] ?? '';
        $html .= $tab.$tab.'<tr class="'.$class.'">'.PHP_EOL;
        foreach ($row as $label => $value) {
          if ($label == '_class') continue;   // ignore _class values
          $html .= $tab.$tab.$tab.'<td class="'.FormatData::classFromLabel($label).'">'.$value.'</td>'.PHP_EOL;
        }
        $html .= $tab.$tab.'</tr>'.PHP_EOL;
      }

      $html .= $tab.'</tbody>'.PHP_EOL.PHP_EOL;
    $html .= '</table>'.PHP_EOL.PHP_EOL;

    return $html;

  } // end of table


  static function classFromLabel($label) {
    $class = str_replace('&nbsp;', '', $label);
    $class = str_replace(' ', '', $class);

    return strtolower( 'tbl_'.$class );
  } // end of classFromLabel


  //
  // static function cards( Array $info ) {
  //
  //   // input: an array where for each row, there are label: value pairs
  //   // label => value
  //
  //   // output:  HTML string with nicely formatted divs representing this info
  //
  //   $tab = "\t";
  //
  //   $html = '<div class="result_card_group">'.PHP_EOL.PHP_EOL;
  //
  //     foreach ($info as $card) {
  //
  //       $class = $card['_class'] ?? '';
  //       $html .= $tab.'<div class="result_card '.$class.'">'.PHP_EOL;
  //
  //       if ( isset($card['Scan']) )
  //         $html .= $tab.$tab.$card['Scan'].PHP_EOL;
  //
  //       if ( isset($card['Flickr']) )
  //         $html .= $tab.$tab.$card['Flickr'].PHP_EOL;
  //
  //       foreach ($card as $label => $value) {
  //         if (
  //           $label == '_class'
  //           || $label == 'Code'
  //           || $label == 'Scan'   // moving scan to the first element
  //           || $label == 'Flickr'   // moving Flickr to the first element
  //         )
  //           continue; // for card view, skip the code
  //
  //         if ($value)
  //           $html .= $tab.$tab.'<p class="'.cssClass($label).'">'.$value.'</p>'.PHP_EOL;
  //       }
  //
  //       $html .= $tab.'</div>'.PHP_EOL.PHP_EOL;  // end of result_card
  //
  //     } // end of foreach card
  //
  //   $html .= '</div>'.PHP_EOL;    // end of result_card_group
  //
  //   return $html;
  //
  // } // end of cards




  static function removeBlankColumns( array $table ) {

    // first we initialise an array representing our table columns
    // we will assume that all columns are empty
    $empty_cols = array();
    foreach ($table[0] as $label => $value)
      $empty_cols[$label] = true;

    // now we work through the table and mark any columns as non-empty if there is data
    foreach($table as $row) {
      foreach ($row as $label => $value) {
        if ($value)
          $empty_cols[ $label ] = false;   // mark column as non-empty
      }
    }


    // rewrite our table array, omitting empty column fields
    $new_table = array();

    foreach($table as $row) {

      $new_row = array();
      foreach ($row as $label => $value) {

        if ( !$empty_cols[ $label ] )   // include only non-empty columns
          $new_row[$label] = $value;

      }

      $new_table[] = $new_row;

    } // end of rewriting new table foreach

    // OK, done!  Return our now clean table
    return $new_table;

  } // end of removeBlankColumns


}
