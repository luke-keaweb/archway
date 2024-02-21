<?php

class ExtraInfo {
  
  public string $code; 
  
  function __construct( $code ) {
    // eg R6584629
      $this->code = $code;
  }
  
  function getUrl() {
    return 'https://common.aims.axiellhosting.com/api/transaction/latest/aims-collections/111156555655658/'.$this->code;
  }

  function getInfo() {
  
    $spider = new Spider;
    $spider->setTimeoutMS( 500 );
    
    // some items timeout without returning anything, don't worry about it bro
    try {
      $output = $spider->fetch( $this->getUrl(), 'extra_info' );      
      $json = json_decode( trim( $output ), true);
  
      // print_r( $json );
  
      if ( isset($json['message']) )
        throw new Exception('Collections Search returned the following message:<br /><br /> '.$json['message']);

      if ( isset($json['apierror']['status']) )
        throw new Exception('Collections Search returned the following error:<br /><br /> '.$json['apierror']['status']);

      return $json;

    } catch (Exception $e) {
      // throw new Exception( 'No response from extra info' );
      return false;  
    } 

  
  } // end of getInfo
  
  
} // end of class

