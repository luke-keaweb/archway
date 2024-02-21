<?php

abstract class Template {

    // allows for the replacement of DefaultTemplate with another of our choice
  
    public static function __callStatic($method, $arguments) {
      
      // have we got a custom Template class to use, or should we use DefaultTemplate?
      $templateClass = defined('CUSTOM_TEMPLATE') ? CUSTOM_TEMPLATE : 'DefaultTemplate';
        
      return forward_static_call_array([$templateClass, $method], $arguments);

    } // end of __callStatic


} // end of class