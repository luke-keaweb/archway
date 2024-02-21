<?php


$relative = '../';

require_once($relative.'functions.php');

echo Template::head( Template::title('Indexed Military Personnel Files') );
echo Template::header();
  
echo '<div class="main-content">';

try {

  echo '<h2><a href="/entity.php?code=18805">Military Personnel Files</a> indexed by Service Number</h2>';
  
  $more_info = '<p>This page consists of records from <a href="/entity.php?code=18805">Military Personnel Files (Series 18805)</a>, indexed so that they can be browsed, filtered and sorted by service number, conflict and name.</p>';
    
  $more_info .= '<p>Many records have been matched with '.makeLink('https://www.aucklandmuseum.com/war-memorial/online-cenotaph/', 'Auckland Museum Online Cenotaph').' entries, using WWI service numbers.</p>';
  
  $more_info .= '<p><b>Filtering</b></p>';
  
  $more_info .= '<ul>
      <li>Search for exact or partial matches to service number, surname and forename fields</li>
      <li>Optionally filter by conflict</li>
    </ul>';
    
  $more_info .= '<p><b>Results</b></p>';
  
  $more_info .= '<ul>
      <li>Results are sorted by service number by default</li>
      <li>Click column headings to sort by Surname, etc</li>
      <li>Hover your mouse over the R-number to see the original record title</li>
      <li>The Blue icon next to R-number takes you to the official Archives NZ record</li>
      <li>In the Scan column, you can either show the scanned record right on the page, or click the Blue icon to use the official Archives NZ media viewer.</li>
      <li>Up to 1000 records are shown at a time</li>
    </ul>';
      
  $more_info .= '<p><b>Repeated names / service numbers</b></p>';

  $more_info .= '<ul>
      <li>The indexed records are displayed with one service number per row.  You may see service numbers repeated when Archives NZ has multiple records referring to the same service number.</li>
      <li>You may also see the same name occurring multiple times with different service numbers.  Service members were often issued multiple service numbers relating to multiple wars, or even multiple service numbers for the same war.</li>
      <li>There may be a better way of presenting the data without the confusion of repeated entries.  Watch this space</li>
    </ul>';
      
  $more_info .= '<p>Indexing was done via '.makeLink('https://en.wikipedia.org/wiki/Regular_expression', 'regex').', relying on record title format, eg:</p>';
  
  $more_info .= '<ul>
      <li>Titles start with <b>SURNAME, forenames</b></li>
      <li>Parts of the title containing numeric digits are usually a service number</li>
      <li>South Africa service numbers start with SA</li>
      <li>Other service numbers are preceded by WWI or WWII</li>
    </ul>';
  
  $more_info .= '<p>With this method, a few indexing errors were inevitable.  Please <a href="mailto:archway@keaweb.co.nz">contact me</a> if you find repeated errors.</p>';

  $more_info .= '<p><b>Data Notes</b></p>';
  
  $more_info .= '<ul>
      <li>Archives NZ records blank service numbers as "N/N"; these are not indexed</li>
      <li>South African service numbers start with "SA", as per Archives NZ practise.  Note that the Online Cenotaph omits the SA from these service numbers.</li>
      <li>Archives NZ records were exported in late August 2023</li>
      <li>Online Cenotaph entries (for WWI servicemembers) were exported via the excellent API in February 2023</li>
    </ul>';  

    
  echo genericInfo('Note', '<p>You may see repeated people / service numbers. This is due to indexing and multiple Archives NZ records.</p>');
  
  echo genericInfo('More Info', $more_info);  
  
  $copyright = '<p>Archives NZ data <a href="https://www.archives.govt.nz/copyright" title="[W]here we have already ... made [archival material] digitally available on Collections search, it is covered by a Creative Commons BY 2.0 license, unless otherwise stated. You are then welcome to use it without seeking permission." target="_blank">is covered by</a> a <a href="https://creativecommons.org/licenses/by/2.0/" target="_blank">Creative Commons Attribution 2.0</a> license.  <a target="_blank" href="https://www.aucklandmuseum.com/war-memorial/online-cenotaph/">Online Cenotaph</a> data is covered by a <a target="_blank" href="https://creativecommons.org/licenses/by/4.0/">Creative Commons Attribution 4.0</a> license.</p>';
  
  echo genericInfo('Copyright', $copyright);  
      
  echo '<br />';    
        
  // set up initial state on pageload
  $_GET['mode'] = $_GET['mode'] ?? 'starts_with';

  $_GET['surname'] = $_GET['surname'] ?? null;
  $_GET['forenames'] = $_GET['forenames'] ?? null;
  $_GET['war'] = $_GET['war'] ?? null;
  
  if (isset($_GET['bulk_numbers']) && $_GET['bulk_numbers'] != '') {
    $service_numbers = explode("\n", trim($_GET['bulk_numbers']));
    // blank out the individual service number search if we are doing a bulk search
    $_GET['number'] = null;
  }
  
  $_GET['number'] = $_GET['number'] ?? null;
  
  $modes = array(
    'starts_with',
    'exact',
    'contains',
    'ends_with',
  );
  if ( !in_array($_GET['mode'], $modes) )
    throw new Exception('Unrecognised search filter mode');


  // OK now that our GET values are all set, show the form:
  echo searchForm();

  // Connect to DB
  $my_db = MySQL::getInstance();
  $db = $my_db->connect();
  $conditions = [
    'number' => $_GET['number'],
    'conflict' => $_GET['war'],   
    'surname' => $_GET['surname'],
    'forenames' => $_GET['forenames'],
  ];
  $where = [];
  $params = [];
  $types = '';
  
  $always_exact = array(
    'conflict',
  );
    
  $tempParams = [];
  foreach ($conditions as $field => $value) {
    
    if ( empty($value) || $value == '' )
      continue;
    
    if ( 
      $_GET['mode'] == 'exact'
      || in_array($field, $always_exact) 
    ) {

      $where[] = "$field = ?";
      $tempValue = $value;
      $tempParams[] = $tempValue;
      $types .= 's';  // assuming all are strings
      
    } else {
      
      $where[] = "$field LIKE ?";
      $tempValue = adjustValue($_GET['mode'], $value);
      $tempParams[] = $tempValue;
      $types .= 's';  // assuming all are strings
      
    }
    
  } // end of foreach conditions loop

  // optionally search for bulk service numbers
  if (!empty($service_numbers)) {
        
    if ($_GET['mode'] == 'exact') {

      $or_where = '(' . implode(' OR ', array_fill(0, count($service_numbers), 'service_numbers.number = ?')) . ')';
      $where[] = $or_where;

      foreach ($service_numbers as $num) {
        $tempParams[] = trim($num);
        $types .= 's';
      }
      
    } else {
      
      $or_where = '(' . implode(' OR ', array_fill(0, count($service_numbers), 'service_numbers.number LIKE ?')) . ')';
      $where[] = $or_where;

      foreach ($service_numbers as $num) {
        $tempValue = adjustValue($_GET['mode'], trim($num) );
        $tempParams[] = $tempValue;
        $types .= 's';
      }
      
    }
    
    // the below code just does an exact match
    // $or_where = '(' . implode(' OR ', array_fill(0, count($service_numbers), 'service_numbers.number = ?')) . ')';
    // $where[] = $or_where;
    // 
    // foreach ($service_numbers as $num) {
    //   $tempParams[] = trim($num);
    //   $types .= 's';
    // }
    
  } // end of service number handling
  
  

  $params = [];
  foreach ($tempParams as &$tempValue) {
    $params[] = &$tempValue;
    // echo '"'.$tempValue.'"<br />';
  }

  $whereSQL = false;
  if (!empty($where)) {    
    $whereSQL = implode(' AND ', $where);
  }
  
  $sql = "SELECT * FROM soldiers";
  $sql .= " LEFT JOIN service_numbers ON soldier = soldiers.id";
  
  if ($whereSQL)
    $sql .= " WHERE $whereSQL";
  else
    $sql .= " WHERE number != ''";   // backup WHERE clause, ignore blank numbers
    
  $sql .= " ORDER BY number";
  $sql .= " LIMIT 1000";
  // echo $sql;

  $stmt = $db->prepare($sql);

  if ($whereSQL) {
        
    $bindNames[] = $types;
    for ($i=0; $i<count($params);$i++) {
      $bindName = 'bind' . $i;
      $$bindName = $params[$i];
      $bindNames[] = &$$bindName;
    }

    // apparently this is necessary because in MySQLi binding parameters can't really be done dynamically?
    call_user_func_array([$stmt, 'bind_param'], $bindNames);
    
  } // end of if $whereSQL

  $result = $stmt->execute() or die($db->error);

  if ($result) {
    
    $res = $stmt->get_result();                                
    $d = array();
        
    $a = new AimsSearch;  // for creating View Online link
    
    while ($row = $res->fetch_assoc()) {
                
      $viewer = ($row['scan']) ? makeLink($row['scan'], '&#127744;', 'View scanned record in the Archives NZ online viewer') : '';
      $view_online = ($row['scan']) ? $a->getViewOnline($row['scan']) : '';
      $cenotaph = ($row['cenotaph_url']) ? makeLink($row['cenotaph_url'], 'Cenotaph Link') : '';
                      
      $d[] = array(
        // 'Original Title' => $row['title'],	
        'Service No.' => $row['number'],
        'Surname' => $row['surname'],
        'Forenames' => $row['forenames'],          
        'War' => $row['conflict'],
        'R-number' => aimsLink($row['r_number']).entityLink($row['r_number'], $row['r_number'], $row['title'], '_blank'),	
        'Scan' => $viewer.'&nbsp;'.$view_online,
        // 'Viewer' => $viewer,
        'AM Cenotaph Link' => $cenotaph,
        );

    } // end of while loop
    
    if ($res->num_rows == 0) {
      echo '<h3>No matching military records found!</h3>';
    }

    if ($res->num_rows == 1000)
      $heading = 'Showing first 1000 matching military records';
    else
      $heading = 'Found '.$res->num_rows.' matching military records';
    
    echo FormatData::display($heading, $d);
        
    
  } else { // end of if results
    
    // I think we only ever get here if there is an actual SQL error??
    echo '<h3>No matching military records found!</h3>';

  }


} catch (Exception $e) {

  echo '<h2 class="error center" style="margin-top:1em;">'.$e->getMessage().'</h2><br /><br />';

}



// finish the template    
echo '</div>';
echo Template::footer();  








function adjustValue($mode, $value) {
  
  switch ($mode) {
    
    case "starts_with":
      return $value.'%';    // Add % at the end for "starts with"
  
    case "ends_with":
      return '%'.$value;    // Add % at the start for "ends with"
    
    case "contains":
      return '%'.$value.'%';  // Add % around the string for "contains"
  
    default:  // we should never end up here
      throw new Exception('Unrecognised filter mode (in switch statement)');
  
  } // end of switch

} // end of adjustValue


function searchForm() {

  $f = new SimpleForm;

  $html = '';

    $html .= '<div class="search_box military">'.PHP_EOL;
    $html .= $f->start();


  $number = $f->text()
    ->label('Service No.')
    ->name('number');

    if ( isset($_GET['number']) )
      $number->value( cleanString($_GET['number']) );
      
  $html .= $number->getHTML().'<br />';
  
  $bulk_numbers = $f->textarea()
    ->label('Multiple Service Numbers (one per line, up to 400)<br /><span style="font-size:0.8em;">Most useful with Filter Mode = Exact Match</span>')
    ->class('long_label')
    ->name('bulk_numbers');

    if ( isset($_GET['bulk_numbers']) )
      $bulk_numbers->value( cleanString($_GET['bulk_numbers']) );
      
  // show the multiple service numbers box open if already filled in
  if ( isset($_GET['bulk_numbers']) && $_GET['bulk_numbers'] != '')
    $html .= $bulk_numbers->getHTML().'<br />';
  else
    $html .= toggleInfo( 'Add Multiple Service Numbers', $bulk_numbers->getHTML() );
  
    
  $surname = $f->text()
    ->label('Surname')
    ->name('surname');

    if ( isset($_GET['surname']) )
      $surname->value( cleanString($_GET['surname']) );
      
  $html .= $surname->getHTML();
  
  $forenames = $f->text()
    ->label('Forenames')
    ->name('forenames');

    if ( isset($_GET['forenames']) )
      $forenames->value( cleanString($_GET['forenames']) );
      
  $html .= $forenames->getHTML().'<br />';
  
  $wars = array(
    ''  => '',
    'South Africa'  => 'SA',
    'WWI'           => 'WWI',
    'WWII'          => 'WWII',
  );

  $war = $f->select()
    ->label('Conflict')
    ->name('war')
    ->options($wars);
    
  $html .= $war->getHTML().'<br />';
    
  $modes = array(
    'Starts With'  => 'starts_with',
    'Contains'     => 'contains',
    'Ends With'    => 'ends_with', 
    'Exact Match'  => 'exact',
  );

  $mode = $f->select()
    ->label('Filter Mode')
    ->name('mode')
    ->options($modes);
    
  $html .= $mode->getHTML().'<br />';
  
  $html .= '<label></label>';
  $html .= $f->button('🔍 Filter', 'military_filter');

  $html .= $f->end();
  $html .= '</div>';

  return $html;

} // end of searchForm
