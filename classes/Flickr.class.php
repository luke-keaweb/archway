<?php

class Flickr {
  // Hold the class instance.
  private static $instance = null;
  // private array $code_names = array();

  public static function getInstance() {
    // Singleton instance
    if(!self::$instance)
      self::$instance = new Flickr;

    return self::$instance;
  }




  public static function get( $code ) {

    // connect to DB
    $my_db = MySQL::getInstance();
    $db = $my_db->connect();

    $result = $db->query( 'SELECT * FROM flickr WHERE r_numbers = "'.$code.'" LIMIT 1;' ) or die( $db->error );

    // stop here if we found nothing
    if ( !$result->num_rows)
      return false;

    $row = $result->fetch_assoc();

    $flickr_url = 'https://www.flickr.com/photos/archivesnz/'.$row['flickr_photo_id'];
    $title = 'Related Archives NZ Flickr post: '.$row['flickr_photo_title'];

    $thumb = makeImage(
      $row['flickr_thumbnail_url'],
      $title,
      'thumb'
    );

    $linked_thumb = makeLink($flickr_url, $thumb);

    $linked_label = makeLink(
      $flickr_url,    // URL of link
      'Flickr',       // text of link
      $title,         // title / mouseover text
      'flickr-link'   // class
    );

    return $linked_thumb.$linked_label;

  } // end of get



} // end of class
