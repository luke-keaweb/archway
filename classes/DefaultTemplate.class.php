<?php

class DefaultTemplate {



  public static function build( $content, $partial_title=false ) {

    $html = Template::head( Template::title($partial_title) );
      $html .= Template::header();

        $html .= '<div class="main-content">'.$content.'</div>';

      // should we include some custom HTML above the footer?
      if (defined('CUSTOM_FOOTER_HTML') )  
        $html .= file_get_contents('../html/'.CUSTOM_FOOTER_HTML);

    $html .= Template::footer();

    return $html;

  } // end of buildPage


  static function title( $partial_title=false ) {

    $site_title = 'Archway-style Collections Search';

    // we may have a partial page title provided
    if ( $partial_title )
      return strip_tags($partial_title).' | '.$site_title;

    return $site_title;

  } // end of title


  static function head( $title ) {

    $html = '<!DOCTYPE html><html lang="en"><head>';
    $html .= '<title>'.$title.'</title>';

    $html .= '<meta name="viewport" content="width=device-width,initial-scale=1">'.PHP_EOL;
    $html .= '<meta name="robots" content="index, nofollow">'.PHP_EOL;

    // are we using a custom CSS file, or just the default?
    $css = defined('CUSTOM_CSS') ? CUSTOM_CSS : 'main.css';
    $html .= '<link rel="stylesheet" type="text/css" href="/assets/styles/'.$css.'?5">'.PHP_EOL;

    $html .= '<script src="/assets/js/htmx1.9.5.min.js?1" defer></script>'.PHP_EOL;
    $html .= '<script src="/assets/js/main.js?8" defer></script>'.PHP_EOL;
    $html .= '<script src="/assets/js/sort_table.js?1" defer></script>'.PHP_EOL;

    // experimental
    // $html .= '<script src="https://unpkg.com/jspdf@latest/dist/jspdf.umd.min.js"></script>'.PHP_EOL;
    // $html .= '<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>'.PHP_EOL;


    // lightgallery plugin - CSS is inserted by the lightgallery JS when it loads
    $html .= '<script src="/assets/js/lightgallery_combined.min.js" defer></script>';

    // Favicons for everyone! -->
    $html .= '<link rel="apple-touch-icon" sizes="180x180" href="/assets/styles/favicon/apple-touch-icon.png">';
    $html .= '<link rel="icon" type="image/png" sizes="32x32" href="/assets/styles/favicon/favicon-32x32.png">';
    $html .= '<link rel="icon" type="image/png" sizes="16x16" href="/assets/styles/favicon/favicon-16x16.png">';
    $html .= '<link rel="manifest" href="/assets/styles/favicon/site.webmanifest">';
    $html .= '<link rel="mask-icon" href="/assets/styles/favicon/safari-pinned-tab.svg" color="#5bbad5">';
    $html .= '<meta name="msapplication-TileColor" content="#da532c">';
    $html .= '<meta name="theme-color" content="#000000">';

    $html .= '<meta name="description" content="A free-to-use alternative search for Archives New Zealand\'s online records, inspired by the old Archway system. Advanced searching, browsing by Series and many more features missing from the official website.">';

    $html .= '<meta property="og:title" content="'.Template::title().'" />';
    $html .= '<meta property="og:description" content="An alternative search system for Archives NZ, inspired by the beautiful simplicity of the beloved Archway system" />';
    $html .= '<meta property="og:image" content="https://archway.howison.co.nz/assets/styles/favicon/android-chrome-512x512.png" />';

    $html .= '</head>';

    return $html;

  } // end of head


  static function header() {

    $html = '<body>'.PHP_EOL;
    $html .= '<header>'.PHP_EOL;
      $html .= '<h1 class="header"><a href="/">🔍 Archway-style Collections Search</a></h1>'.PHP_EOL;

    $html .= '<p class="header">Your <b>unofficial</b> gateway to Archives NZ</p>'.PHP_EOL;

    $html .= '<!--div class="search_box">

      <h3 class="center">
        <span class="error">Wednesday 22 November</span>
        <br />
        Adjusted the site to work with today\'s Collections Search updates - please <a href="mailto:archway@keaweb.co.nz">let me know</a> if you notice any bugs!
      </h3>

    </div-->';

    $html .= Template::menu();

    $html .= '</header>';

    return $html;

  } // end of header



  static function menu() {

    $menu = array(
      'Search' => '/',
      'Advanced' => '/advanced/',

      'Explore' => array(
        'Artwork' => '/advanced/?search=*&has_digital=true&access=Open&format=Artwork#results',
        'Photos' => '/advanced/?search=*&has_digital=true&access=Open&format=Photograph#results',
        'Video' => '/advanced/?search=*&has_digital=true&access=Open&format=Moving+Image#results',
        'Audio' => '/advanced/?search=*&has_digital=true&access=Open&format=Sound+Recording#results',
        'Maps' => '/advanced/?search=*&has_digital=true&access=Open&format=Map/Plan#results',
      ),

      'Browse' => array(
        'Agencies' => '/advanced/?entity_type=Agency',
        'Series' => '/advanced/?entity_type=Series',
        'Accessions' => '/advanced/?entity_type=Accession',
      ),

    );

    return Template::buildMenu($menu, 'nav');

  }


  static function buildMenu(array $menu, $class='') {

    $html = $class === 'nav' ? '<nav class="'.$class.'" hx-boost="true" hx-indicator="body">'.PHP_EOL : '<div class="submenu">'.PHP_EOL;

    foreach($menu as $label => $url)
      $html .= Template::showMenuItem($label, $url);

    $html .= $class === 'nav' ? '</nav>' : '</div>';

    return $html.PHP_EOL;

  }


  static function showMenuItem(string $label, $url_or_submenu) {

    if ( is_array($url_or_submenu) ) {
      $url = false;
      $submenu = $url_or_submenu;
    } else {
      $url = $url_or_submenu;
      $submenu = false;
    }

    $link_url = getArchDomain().$url;
    // remove any hash anchors for matching purposes
    $match_link_url = preg_replace('/#.*$/', '', $link_url);

    $class = '';

    if ($url && getCurrentURL() == $match_link_url) {
      $class = 'active';
      $label = '🔍 '.$label;
    }

    $html = '';

    $linked_label = $url ? archLink($link_url, $label, $label, '', $class) : '<span>'.$label.' ▼</span>';

    $tab = "\t";

    if ($submenu) {
      $html .= '<div class="dropdown">'.$linked_label.PHP_EOL;
        $html .= Template::buildMenu($submenu);
      $html .= '</div>'.PHP_EOL;
    } else {
      $html .= $tab.$linked_label.PHP_EOL;
    }

    return $html;
  }





  static function footer() {

    $html = '<footer>';

      $html .= '<p><a href="mailto:archway@keaweb.co.nz">Email</a> feedback and suggestions</p>';

      // how long did it take to render our page?
      global $pageTimerStart;     // this is set at top of index.php
      $elapsed = microtime(true) - $pageTimerStart;
      $html .= '<span class="rendered">Page rendered in '.round($elapsed, 4).' seconds</span>';

    $html .= '</footer></body></html>';

    return $html;

  }  // end of footer

} // end of class
