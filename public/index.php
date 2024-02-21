<?php 

try {
  
  $pageTimerStart = microtime(true);
  
  require_once('../functions.php');

  $router = new Router;
  echo $router->getPage();
  
} catch (Exception $e) {

  echo templateError( $e->getMessage() );
  
}

?>