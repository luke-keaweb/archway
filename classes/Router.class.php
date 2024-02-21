<?php

use FastRoute\Dispatcher as Dispatcher;
use FastRoute\RouteCollector as RouteCollector;

class Router {

  function routes(RouteCollector $r) {

    // search routes
    $r->get('/', 'AimsSearch@simpleSearch' );
    $r->get('/index.php', 'AimsSearch@simpleSearch' );
    $r->get('/advanced/', 'AimsSearch@advancedSearch' );

    $r->get('/advanced_series/', 'AimsSearch@advancedSearchWithSeries' );

    $r->get('/experiments/', 'AimsSearch@experimental' );

    // CSV export
    $r->get('/spreadsheet/', 'AimsSearch@spreadsheetExport' );

    // API
    $r->get('/api/1.0/', 'AimsSearch@jsonExport' );

    // facets for lazy loading
    $r->get('/ajax/facets', 'AimsSearch@ajaxFacets');

    // ASH simple search ... update this
    $r->get('/ajax/simple.php', 'AimsSearch@ajaxSearch');

    // entities (generic, should never get used)
    $r->get('/entity/{code}', 'Entity@showInfo');

    // entities (specific)
    $r->get('/item/{code}', 'Entity@showInfo');
    $r->get('/agency/{code}', 'Entity@showInfo');
    $r->get('/function/{code}', 'Entity@showInfo');
    $r->get('/accession/{code}', 'Entity@showInfo');
    $r->get('/jurisdiction/{code}', 'Entity@showInfo');
    $r->get('/organisation/{code}',   'Entity@showInfo');
    $r->get('/access-authority/{code}', 'Entity@showInfo');
    $r->get('/disposal-authority/{code}', 'Entity@showInfo');

    // For Series, {id} must be a number (\d+)
    $r->get('/series/{code:\d+}', 'Entity@showInfo');

    // media viewer pages
    $r->get('/view/{code}', 'ImageViewer@renderPage');
    $r->get('/mets/{code}', 'METSViewer@renderPage');

    // series select combobox
    $r->get('/ajax/series_search/', 'SeriesSelect@renderAjax');

    // ajax viewers
    $r->get('/ajax/viewer/{code}', 'ImageViewer@renderAjax');
    $r->get('/ajax/mets/{code}', 'METSViewer@renderAjax');
    $r->get('/ajax/pdf/{code}', 'ZipToPdf@renderAjax');

    // preserving old routes
    $r->get('/entity.php', 'Entity@showInfo');
    $r->get('/viewer/', 'ImageViewer@renderPage');
    $r->get('/viewer/mets.php', 'METSViewer@renderPage');

  } // end of routes



  public function getPage() {

    $d = FastRoute\simpleDispatcher([$this, 'routes']);

    // Fetch URI
    $uri = $_SERVER['REQUEST_URI'];

    // Strip query string (?foo=bar)
    $query_pos = strpos($uri, '?');
    if ($query_pos !== false) {
        $uri = substr($uri, 0, $query_pos);
    }

    // now we'll match our current route with the route list
    $route_info = $d->dispatch(
      $_SERVER['REQUEST_METHOD'],
      rawurldecode($uri)           // decodes the URI eg Hello%20World
    );

    switch ($route_info[0]) {

      case Dispatcher::NOT_FOUND:
          throw new Exception('Page not found!');   // should we return a proper 404 error here?

      case Dispatcher::METHOD_NOT_ALLOWED:
          throw new Exception('Incorrect HTTP method for this URL');

      case Dispatcher::FOUND:
          return $this->handleRoute( $route_info );

    } // end of switch

    // we should never get here I think?
    throw new Exception('General page route error');

  } // end of getPage



  function handleRoute( $route_info ) {

    // we expect the class and render method in the form 'ClassName@methodName'
    list($class, $render) = explode('@', $route_info[1]);

    // get the vars ... only one at this point
    $vars = $route_info[2];
    $input = $vars['code'] ?? null;

    if ($input && !isset($_GET['code']) )
      $_GET['code'] = $input;

    if (!$input && isset($_GET['code']) )
      $input = $_GET['code'];

    if (!$input && isset($_GET['pid']) )
      $input = $_GET['pid'];

    // some pages like AJAX viewer use the whole query string, eg, /ajax/viewer/?IE45678345
    if (!$input && isset($_SERVER['QUERY_STRING']) )
      $input = $_SERVER['QUERY_STRING'];

    // and here we actually create the controller object and echo the HTML returned by render_method
    $obj = new $class( $input );
    return $obj->$render();

  } // end of handleRoute



   public static function getPrettyUrl() {
        $uri = $_SERVER['REQUEST_URI'];
        $query_pos = strpos($uri, '?');
        if ($query_pos !== false) {
            $uri = substr($uri, 0, $query_pos);
        }
        return $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['SERVER_NAME'] . rawurldecode($uri);
    }


} // end of class
