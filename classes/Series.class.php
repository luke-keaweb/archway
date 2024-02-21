<?php

class Series {

  private static $instance = null;    // Hold the class instance

  private array $store = array();     // an array of series IDs and names, filled in by ->hint()



  public static function getInstance() {

    // Singleton instance
    if(!self::$instance)
      self::$instance = new Series;

    return self::$instance;
  }



  public function name( int $series_id ) {

    // echo 'Series->name was asked about '.$series_id.'<br />';

    // first, check the stored array for a cached id / name pair
    if ( array_key_exists($series_id, $this->store) )
      return $this->store[ $series_id ];

    // connect to DB
    $my_db = MySQL::getInstance();
    $db = $my_db->connect();

    $result = $db->query( 'SELECT name FROM series WHERE series_id = '.$series_id.' LIMIT 1;' ) or die( $db->error );

    $row = $result->fetch_assoc();

    // if this code isn't in the database, ask Archives for its info
    return $row['name'] ?? $this->getUnknownName($series_id);

  } // end of name



  private function getUnknownName( int $series_id ) {

    $url = 'https://common.aims.axiellhosting.com/api/federation/latest/customers/61e1427f3d7155799faa2e4c/sources/aims-archive/entities/'.$series_id;

    $spider = new Spider;
    try {
      $json = $spider->fetch( $url, 'entity' );
    } catch (Exception $e) {
      // if we ran into some problem with JSON collection, just return the series ID
      // its not critical to have a series name
      return $series_id.' (unknown)';
    }

    $raw_info = json_decode( $json, true);

    // viewCode( $raw_info['entity'] );

    // let's double-check we have Entity info, and this is actually a Series
    if (
      !isset($raw_info['entity'])
      || $raw_info['entity']['itemType'] != 'Series'
    )
      return $series_id.' (unknown)';

    // OK, it's definitely a Series, let's save it into the database
    $this->insertSeries( $raw_info['entity'] );

    // ... and return the name
    return $raw_info['entity']['name'];

  } // end of retrieveName





  function insertSeries( array $series ) {

    $my_db = MySQL::getInstance();
    $db = $my_db->connect();
    $last_id = 0;

    // Prepare the SQL query with placeholders
    $sql = "INSERT INTO series (
      series_id,
      name,
      description,
      location,
      start_date,
      end_date
    ) VALUES (?, ?, ?, ?, ?, ?)";

    // Create a prepared statement
    $statement = $db->prepare($sql);

    // Check if prepare was successful
    if ($statement === false) {
      // echo "Error: " . $db->error . '<br />';
      return false;
    }

    // some of these values could be blank, let's fix that
    $desc = $series['description'][0] ?? '';
    $location = $series['isAssociatedWithPlace'][0]['name'] ?? '';
    $start_date = $series['hasBeginningDate']['textualValue'] ?? '';
    $end_date = $series['hasEndDate']['textualValue'] ?? '';

    // restrict data length to fit varchars
    $location = $this->truncate($location, 15);
    $start_date = $this->truncate($start_date, 8);
    $end_date = $this->truncate($end_date, 8);

    // Bind variables to the prepared statement as parameters
    $statement->bind_param("ssssss",
      $series['id'],
      $series['name'],
      $desc,
      $location,
      $start_date,
      $end_date,
    );

    // Execute the prepared statement
    if ($statement->execute()) {
      $last_id = $db->insert_id;
    } else {
      // echo "Error: " . $statement->error . '<br />';
      $last_id = 0;
    }

    // Close the statement and the database connection
    $statement->close();

    // return $last_id;
    return true;

  } // end of insertSeries



  function truncate($string, $length) {
    return substr($string, 0, $length);
  }





public function hint( array $bulk_series_ids ) {

  // an array of series IDs which we can look up in one query, and refer to as needed

  // first, let's skip any series ID which we already have stored
  $bulk_series_ids = array_diff($bulk_series_ids, array_keys($this->store));

  // stop here if have nothing to search for
  if (count($bulk_series_ids) < 1)
    return false;

  // Prepare the SQL query
  $my_db = MySQL::getInstance();
  $db = $my_db->connect();

  // Convert the array into a comma-separated string of placeholders like ?,?,?
  $placeholders = implode(',', array_fill(0, count($bulk_series_ids), '?'));

  $query = "SELECT series_id, name FROM series WHERE series_id IN ($placeholders)";
  $stmt = $db->prepare($query);

  // Dynamically bind the parameters
  $params = array_fill(0, count($bulk_series_ids), 'i');  // An array of 'i's
  array_unshift($params, str_repeat('i', count($bulk_series_ids)));  // Add the types as the first element

  // Convert the array values to references
  $refs = [];
  foreach ($bulk_series_ids as $key => $value) {
      $refs[$key] = &$bulk_series_ids[$key];
  }
  array_unshift($refs, $params[0]);

  call_user_func_array([$stmt, 'bind_param'], $refs);

  // Execute the query and fetch results
  $stmt->execute();
  $result = $stmt->get_result();

  // Store results
  while ($row = $result->fetch_assoc()) {
      $this->store[ $row['series_id'] ] = $row['name'];
  }

  // Don't forget to close the statement
  $stmt->close();

}  // end of hint


function searchID( int $search_id, int $limit=10 ) {
  // $search_id is effectively sanitised because it must be an integer

  // an exact match on the start of the Series name

  // connect to DB
  $my_db = MySQL::getInstance();
  $db = $my_db->connect();

  $sql = "SELECT series_id, name FROM series WHERE series_id LIKE '$search_id%' LIMIT $limit";

  $result = $db->query( $sql ) or die( $db->error );

  // stop here if we found nothing
  if ( !$result->num_rows)
    return [];

  $matches = array();

  while ($row = $result->fetch_assoc() )
    $matches += $this->getSeriesLabel($row, $search_id);

  return $matches;

} // end of searchID


function searchNameExact(string $search, int $limit = 10) {
    // An exact match on the START of the Series name

    // Connect to DB
    $my_db = MySQL::getInstance();
    $db = $my_db->connect();

    // Prepare the SQL statement
    $stmt = $db->prepare("SELECT series_id, name FROM series WHERE name LIKE CONCAT(?, '%') LIMIT ?");
    if (!$stmt) {
        // Handle error appropriately
        die($db->error);
    }

    // Bind parameters and execute
    $stmt->bind_param("si", $search, $limit);
    $stmt->execute();

    $result = $stmt->get_result();
    
    // Close the prepared statement
    $stmt->close();

    // Stop here if we found nothing
    if ($result->num_rows == 0) {
        return [];
    }

    $matches = array();
    while ($row = $result->fetch_assoc())
      $matches += $this->getSeriesLabel($row, $search);

    return $matches;
} // end of searchNameExact



function searchNamePartial(string $search, int $limit = 10) {
  
    $terms = explode(' ', $search);
    $name_conditions = [];
    $params = []; // Array to hold the parameters for binding

    foreach ($terms as $term) {
        $term = trim($term);
        if (strlen($term) < 2)
            continue;
        $name_conditions[] = "name LIKE ?";
        $params[] = "%$term%"; // Add the term to the params array
    }

    if (!$name_conditions)
        return [];

    $name_whereCondition = implode(' AND ', $name_conditions);

    // Connect to DB
    $my_db = MySQL::getInstance();
    $db = $my_db->connect();

    $sql = "SELECT series_id, name FROM series WHERE $name_whereCondition LIMIT ?";
    $stmt = $db->prepare($sql);
    if (!$stmt) die($db->error);

    $params[] = $limit; // Add limit to the params array
    $types = str_repeat('s', count($params) - 1) . 'i'; // String types for each term, integer for limit
    $stmt->bind_param($types, ...$params);
    $stmt->execute();

    $result = $stmt->get_result();
    $stmt->close();
    
    $matches = [];

    if ($result->num_rows == 0)
        return [];

    while ($row = $result->fetch_assoc())
        $matches += $this->getSeriesLabel($row, $search);

    return $matches;
    
} // end of searchNamePartial



function getSeriesLabel($row, $search) {
  
  $label = $row['series_id'].' - '.$row['name'];
  $label = highlightKeywords($label, $search);
  
  return [ 
    $label => $row['series_id'], 
  ];

} // end of getSeriesLabel



 // private constructor ... ?
 // private function __construct() {
 //
 //   // load all Series IDs and names into the array
 //   // uh, is this actually a good idea??


       // memory usage is about 6 MB


 //   // connect to DB
 //   $my_db = MySQL::getInstance();
 //   $db = $my_db->connect();
 //
 //   $result = $db->query( 'SELECT series_id, name FROM series;' ) or die( $db->error );
 //
 //   while ( $row = $result->fetch_assoc() )
 //     $this->code_names[ $row['series_id']] = $row['name'];
 //
 // } // end of constructor



} // end of class
