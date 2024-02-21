<?php

class MySQL {
  // Holds the single class instance.
  private static $instance = null;
  private $conn;
   
  public static function getInstance() {
        
    // Singleton instance
    if(!self::$instance)
      self::$instance = new MySQL;
   
    return self::$instance;
  } 
   
  private function __construct() {
        
    // do we have what we need to connect?
    if (
      !defined('DB_SERVER')
      || !defined('DB_USER')
      || !defined('DB_PASSWORD')
      || !defined('DB_DATABASE')
    )
      throw new Exception('MySQL connection details not provided!');
    
    // Create db connection from .env variables
    $conn = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_DATABASE);

    // Check connection
    if ($conn->connect_error) 
      die('Connection failed: ' . $conn->connect_error);
    
    $this->conn = $conn;
  
  }
    
  public function connect() {
    return $this->conn;
  }
  
} // end of class