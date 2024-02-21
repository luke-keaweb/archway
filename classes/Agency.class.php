<?php

class Agency {
  // Hold the class instance.
  private static $instance = null;
  private array $code_names = array();
   
  public static function getInstance() {
        
    // Singleton instance
    if(!self::$instance)
      self::$instance = new Agency;
   
    return self::$instance;
  }
   
  // private constructor ... ?
  private function __construct() {
    
    // load all codes and names into the array
    
    // connect to DB
    $my_db = MySQL::getInstance();
    $db = $my_db->connect();
    
    $result = $db->query( 'SELECT code, name FROM agencies;' ) or die( $db->error );
    
    while ( $row = $result->fetch_assoc() )
      $this->code_names[ $row['code']] = $row['name'];
    
  } // end of constructor
  
  
  
  public function name( string $code ) {
    
    // echo 'Agency was asked about '.$code.'<br />';
    
    if ( isset($this->code_names[$code]) )
      return $this->code_names[$code];
    
    return false;
    
    // TODO: if this code isn't in the database, we could ask Archives for it 
    // and then save the name into the DB
    
  } // end of getName
  
} // end of class
