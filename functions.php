<?php

// show errors on local
if ($_SERVER['REMOTE_ADDR'] == '127.0.0.1') {
  ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
  error_reporting(E_ALL);
}

// this needs to run before autoload
setEnvironmentVariables();

// autoload classes
spl_autoload_register(function ($class) {
    include 'classes/'.$class.'.class.php';
});

// autoload Composer packages
require_once('vendor/autoload.php');



function setEnvironmentVariables() {
  
  // .env file is in the project root, this function is called inside /public
  
  if ( !file_exists('../.env') )
    die('.env file not found!');

  $env = parse_ini_file('../.env');

  foreach ($env as $var => $value) {
    // echo $var.' is '.$value.'<br />';
    define($var, $value);
  }

} // end of setEnvironmentVariables


function cleanString($string, $special_entities=true) {
  if (!$string) return false;

  // Trim whitespace
  $string = trim($string);

  // Strip tags to remove HTML and PHP tags
  $string = strip_tags($string);

  // Convert special characters to HTML entities
  if ($special_entities)
    $string = htmlspecialchars($string, ENT_QUOTES, 'UTF-8');

  // Additional sanitation can go here

  return $string;
}




function highlightKeywords($haystack, $keyword_string) {
  
    // there may not be a keyword to highlight at all
    if (!$keyword_string)
      return $haystack;
  
    // ignore blank keywords and titles
    if (strlen($haystack) < 1 || strlen($keyword_string) < 1) {
        return $haystack;
    }

    preg_match_all('#"([^"]+)"#', $keyword_string, $quoted);
    preg_match_all('#(?<!")\b\w{2,}\b(?!")#', $keyword_string, $non_quoted);

    $keywords = array_merge($quoted[1], $non_quoted[0]);

    $search = [];
    $replace = [];

    foreach ($keywords as $keyword) {
        preg_match_all("#$keyword+#i", $haystack, $matches);

        if (is_array($matches[0]) && count($matches[0]) >= 1) {
            foreach ($matches[0] as $match) {
                $search[] = $match;
                $replace[] = '<span class="highlight">' . $match . '</span>';
            }
        }
    }

    return stro_replace($search, $replace, $haystack);
} // end of highlightKeywords


function stro_replace($search, $replace, $subject) {
    return strtr( $subject, array_combine($search, $replace) );
}


function highlightAndTruncateTitle($title, $keyword_string) {

    // for really long titles we want to show just the keywords, with a bit of context

    // This is primarily for "Coroners Inquest unnecessary" which can be more than 400 words long, and tend to be pointless lists of names which are rife with false positives

    // We'll include the first few words by default, eg "Coroners Reports ..."
    // then we go through every word in the title and matching words are marked in the 'heatmap' array, along with surrounding context words
    // then we construct a truncated title using the heatmap array to guide which words are included

    // how many surrounding context words should we include?
    $context = 2;

     // return $haystack if there are no strings given, nothing to do.
    if (strlen($title) < 1 || strlen($keyword_string) < 1) {
        return $title;
    }

    // highlight should be case-insensitive, so we make search terms lowercase
    $keyword_string = strtolower($keyword_string);

    // get words from the keyword string, omitting any words less than 3 characters long
    preg_match_all('/(?<!")\b\w{3,}\b(?!")/', $keyword_string, $searchTerms);
    $searchTerms = $searchTerms[0];

    // split the title words by space
    $titleWords = explode(' ', $title);

    // set up our heatmap
    $heatmap = array();

    // we will include the first four words (eg, "Coroners Inquest unnecessary"
    $heatmap[0] = 1;
    $heatmap[1] = 1;
    $heatmap[2] = 1;
    $heatmap[3] = 1;

    foreach ($titleWords as $i => $word) {

        // get simple words (no punctuation etc) for matching against search terms
        preg_match_all('/\b\w+\b/u', $word, $plain_word);

        // ignore blank pieces
        if ( !isset($plain_word[0][0]) )
          continue;

        // highlight should be case-insensitive, so we make title words lowercase
        if ( in_array( strtolower($plain_word[0][0]), $searchTerms) ) {

            $heatmap[$i] = 100;

            for (
              $j = max(0, $i - $context );                            // start of highlight
              $j <= min(count($titleWords) - 1, $i + $context + 1);   // end of highlight
              $j++
            )
              $heatmap[$j] = 50;

        } else {

          // only update the heatmap if its not already set, to avoid setting previously highlighted words to zero
          if (!isset($heatmap[$i]))
            $heatmap[$i] = 0;

        }

    }

    // print_r($heatmap);

    // Loop through the heatmap array

    $highlighted = ''; // Initialize the highlighted string
    $prevHeat = null; // Store the previous value

    foreach ($heatmap as $i => $heat) {
        if ($heat == 0 && $prevHeat != 0) {
            $highlighted .= ' ... '; // Add ' ... ' to the highlighted string
        } elseif ($heat != 0) {
            $highlighted .= $titleWords[$i].' '; // Add a word to the highlighted string
        }
        $prevHeat = $heat; // Update the previous value
    }

    // echo $highlighted; // Output the highlighted string

    // remove any trailing spaces
    $highlighted = trim($highlighted);

    return highlightKeywords($highlighted, $keyword_string);

} // end of highlightAndTruncateTitle





function getArchDomain() {

  // retrives a FQDN from the .env file
  // eg https://archway.howison.co.nz

  // NOTE: should OMIT trailing slash

  if ( !defined('HTTP_ROOT') )
    throw new Exception('HTTP_ROOT is not defined in .env!');

  return HTTP_ROOT;

} // end of getArchDomain



function makeLink($url, $link_text, $title=false, $class=false) {

  $link = '<a target="_blank" ';

  if ($title)
    $link .= 'title="'.htmlspecialchars($title).'" ';

  if ($class)
    $link .= 'class="'.htmlspecialchars($class).'" ';

   $link .= 'href="'.htmlspecialchars($url).'">';

    $link .= $link_text;

  $link .= '</a>';

  return $link;

} // end of makeLink


function makeImage($src, $title, $class=false, $loading=false) {

  $img = '<img src="'.$src.'" title="'.htmlspecialchars($title).'" alt="'.htmlspecialchars($title).'" ';

  if ($class)
    $img .= 'class="'.htmlspecialchars($class).'" ';

  if ($loading)
    $img .= 'loading="'.$loading.'" ';

  $img .= '/>';

  return $img;

  // ideally we should also supply height="150" width="90"
}


function makeThumb($url, $title='', $class="thumb", $loading='') {

  // given a DPS image page, we can make a thumbnail

  // does the image URL already contain dps_func=thumbnail, or do we need to add it?
  if ( stristr($url, '&dps_func=thumbnail') ) {
    $thumb_url = $url;
    // $main_url = str_replace('&dps_func=thumbnail', '', $url);
  } else {
    $thumb_url = $url.'&dps_func=thumbnail';
    // $main_url = $url;
  }

  return makeImage($thumb_url, $title, $class, $loading);

  // once upon a time, we wrapped the image in a link to the page ...
  // return makeLink($main_url, $img, $title);

} // end of makeThumb



function archLink($url, $link_text, $title=false, $target=false, $class='', $hx='') {

  $link = '<a ';

  if ($target)
    $link .= 'target="'.$target.'" ';

  if ($title)
    $link .= 'title="'.$title.'" ';

  if ($class)
    $link .= 'class="'.$class.'" ';

  if ($hx)
    $link .= $hx.' ';


  $link .= 'href="'.$url.'">'.$link_text.'</a>';

  return $link;

} // end of archLink

function getCurrentURL() {
  // eg for form actions and detecting the current Nav item
  return $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'];
} // end of getCurrentURL


function getViewerURL( $pid ) {
  return getArchDomain().'/view/'.$pid;
}

function getEntityURL($code) {
  $type = getEntityType($code);
  return getArchDomain().'/'.$type.'/'.$code;
}

function entityLink($code, $link_text, $title='View Info', $target='') {
  return archLink( getEntityURL($code), $link_text, $title, $target, '', 'hx-boost="true" hx-indicator="body"');
} // end of entityLink


function getEntityType($code) {
    $patterns = [
        '/^\d{3,6}$/' => 'series',
        '/^[a-zA-Z]{4}$/' => 'agency',
        '/^R\d+$/' => 'item',
        '/^A00\d{2}$/' => 'organisation',
        '/^J\d{4}$/' => 'jurisdiction',
        '/^F\d{4}$/' => 'function',
        '/^DA\d{2,4}$/' => 'disposal-authority',
        '/^AA\d{1,4}$/' => 'access-authority',
        '/^[a-zA-Z]{2}(-[\d|a-zA-Z]+)+$/' => 'disposal-authority',
        '/^([WAD]|CH)\d{1,4}$/' => 'accession',
    ];

    foreach ($patterns as $pattern => $type) {
        if (preg_match($pattern, $code))
            return $type;
    }

    return 'entity';
} // end of getEntityType


function aimsLink($code, $link_text='', $title='View on Archives NZ', $class='') {

  $url = 'https://collections.archives.govt.nz/en-GB/web/arena/search#/entity/aims-archive/'.$code.'/';

  $link_text = '&#127744;&nbsp;'.$link_text;

  return makeLink($url, $link_text, $title, $class);

} // end of aimsLink




function linkSeries( string $possible_series_id ) {

  // only called from ImageViewer

  preg_match_all('/\b\d{3,5}\b/', $possible_series_id, $matches);

  if (!$matches[0])
    return false;

  $series_code = $matches[0][0];
  $series_link = entityLink($series_code, $series_code);

  $series_obj = Series::getInstance();
  $series_name = $series_obj->name( $series_code );

  if ($series_name)
    $series_link = entityLink( $series_code, $series_name );

  return $series_link;

} // end of linkSeries


function linkAgency( string $possible_agency_code ) {

  preg_match_all('/[A-Z]{4}/', $possible_agency_code, $matches);

  if (!$matches[0])
    return false;

  $agency_code = $matches[0][0];
  $agency_link = entityLink($agency_code, $agency_code);

  $agencies = Agency::getInstance();
  $agency_name = $agencies->name( $agency_code );

  if ($agency_name)
    $agency_link = entityLink($agency_code, $agency_name);

  return $agency_link;

} // end of linkAgency


function cssClass( $string ) {

    if (!$string)
      return false;

    // any string input should be transformed into a valid CSS class name
    // eg "Access Authority!" should be come "access-authority"
    $string = strtolower($string);  // Convert to lowercase
    $string = preg_replace('/[^a-z0-9]+/', '-', $string);  // Replace invalid characters with hyphens
    $string = trim($string, '-');  // Trim leading and trailing hyphens
    return $string;

} // end of cssClass


function extractDates( $result ) {

//   typical input might resemble
//   [hasBeginningDate] => Array
//     (
//         [type] => YearStart
//         [normalizedValue] => 1987-01-01T00:00:00.0Z
//         [dateQualifier] => Array
//             (
//                 [0] => exact
//                 [1] => yearOnly
//             )
//
//     )
//
// [hasEndDate] => Array
//     (
//         [type] => YearEnd
//         [normalizedValue] => 1997-01-01T00:00:00.0Z
//         [dateQualifier] => Array
//             (
//                 [0] => exact
//                 [1] => yearOnly
//             )
//
//     )


  $start_date = false;

  // TODO: handle 'normalizedValue' instead of textualValue

  // check what we should use for a beginning date
  if (isset($result['hasBeginningDate']['normalizedValue']) )
    $start_date = $result['hasBeginningDate']['normalizedValue'];
  else if (
    isset($result['hasBeginningDate']['dateQualifier'])
    && !is_array($result['hasBeginningDate']['dateQualifier'])
  )
    $start_date = $result['hasBeginningDate']['dateQualifier'];

  // stop here if we have nada
  if (!$start_date)
    return false;

  if (
    isset($result['hasEndDate']['normalizedValue'])
    && $result['hasEndDate']['normalizedValue']
  )
    $end_date = $result['hasEndDate']['normalizedValue'];
  else if (
    isset($result['hasEndDate']['dateQualifier'])
    && !is_array($result['hasEndDate']['dateQualifier'])
  )
    $end_date = $result['hasEndDate']['dateQualifier'];

  // wait, what is going on here???
  // hopefully this doesn't F anything up
  else if (
    isset($result['date'])
    && !is_array($result['date'])
    && $result['date']
  )
    $end_date = $result['date'];
  else
    $end_date = false;

  return cleanDates($start_date, $end_date);

} // end of extractDates






function extractHoldingsDate( $result ) {

  if (
    !isset($result['isAssociatedWithDate'])
    || !is_array($result['isAssociatedWithDate'])
    )
    return false;

  // assume we have nothing
  $hold_start_date = false;
  $hold_end_date = false;

  foreach( $result['isAssociatedWithDate'] as $date) {

    $hold_start_array = getAssocDate( $date, 'HoldingsYears', 'hasBeginningDate');
    if ($hold_start_array)
      $hold_start_date = getAssocDate( $hold_start_array, 'YearStartHoldings');

    $hold_end_array = getAssocDate( $date, 'HoldingsYears', 'hasEndDate');
    if ($hold_end_array)
      $hold_end_date = getAssocDate( $hold_end_array, 'YearEndHoldings');

  }

  return cleanDates( $hold_start_date, $hold_end_date );

  // [isAssociatedWithDate] => Array
      // (
      //     [0] => Array
      //       (
      //         [type] => HoldingsYears
      //         [hasBeginningDate] => Array
      //             (
      //                 [type] => YearStartHoldings
      //                 [textualValue] => 1890
      //                 [dateQualifier] => approximate
      //             )
      //
      //         [hasEndDate] => Array
      //             (
      //                 [type] => YearEndHoldings
      //                 [textualValue] => 1910
      //                 [dateQualifier] => approximate
      //             )

} // end of extractHoldingsDate



function getAssocDate( $date, $type, $target='textualValue' ) {

  if ( $date['type'] == $type) {

    // stop here if no target exists
    if ( !isset($date[ $target ]) )
      return false;

    // have we been asked for an array?  return it
    if (is_array($date[ $target ]))
      return $date[ $target ];

    // check if this is an exact date or a 'circa'
    $circa = ($date['dateQualifier'] ?? '') === 'approximate' ? 'c. ' : '';
    return $circa . $date[ $target ];

  }

  return false;
} // end of getAssocDate


function extractApprovedDate( $result ) {

  if (
    !isset($result['isAssociatedWithDate'])
    || !is_array($result['isAssociatedWithDate'])
  )
    return false;

  foreach( $result['isAssociatedWithDate'] as $date) {
    return getAssocDate( $date, 'DateApproved');
  }

  return false;

  // [isAssociatedWithDate] => Array(
  //     [0] => Array (
  //             [type] => DateApproved
  //             [textualValue] => 1999-02-25
  //         )
  // )

} // end of extractApprovedDate


function extractReviewedDate( $result ) {

  if (
    !isset($result['isAssociatedWithDate'])
    || !is_array($result['isAssociatedWithDate'])
    )
    return false;

  foreach( $result['isAssociatedWithDate'] as $date) {
    return getAssocDate( $date, 'DateReviewed');
  }

  return false;

  // [isAssociatedWithDate] => Array(
  //     [1] => Array (
  //             [type] => DateReviewed
  //             [textualValue] => 2009-02-25
  //         )

} // end of extractReviewedDate





function cleanDates($start, $end) {

  // if we have nothing, stop here
  if (!$start && !$end)
    return false;

  if ($start == 'unknown' && $end == 'unknown')
    return false;

  // clean up ISO 8601 format dates
  // TODO: we only return Year, but sometimes the dates are more exact
  // we need to distinguish between these using [dateQualifier] => Array
                                        // (
                                        //     [0] => exact
                                        //     [1] => yearOnly
                                        // )
  // ... in a previous step
  $start = date("Y", strtotime($start) );
  
  if ($end && $end != 'unknown' && $end != 'current')
    $end = date("Y", strtotime($end) );

  // clean up '1914 - 1914' to be just '1914'
  if ($start == $end)
    return $start;

  return $start.'&nbsp;-&nbsp;'.$end;

} // end of cleanDates




function genericInfo($label, $value, $is_list=false) {

  // we have a lot of label : info pairs to show, this is how we format the HTML

  // don't show blank values
  if (!$value)
    return false;

  $tab = "\t";

  // clean up any CamelCase labels
  $label = camelCaseToWords($label);

  // clean up text
  $value = cleanText( $value );

  // possibly hide long chunks of text into a toggleInfo
  // lists have separate logic and may already be in a toggleInfo
  if ( !$is_list && strlen( strip_tags($value) ) > 1200 )
    $value = toggleInfo( 'Show '.$label, $value, 'toggle_long_text' );

  // actually build the label / div pair
  $info = $tab.'<label class="info">'.$label.'</label>';
  $info .= '<div class="info">'.$value.'</div><br />'.PHP_EOL;

  return $info;

} // end of genericInfo


function makeList(array $info, $class='plain') {

  if ( empty($info) )
    return false;

  if ( count($info) < 13 )
    return makePlainList($info, $class);
  else
    return truncatedList($info, $class);

} // end of makeList

function makePlainList(array $info, $class='') {

    $html = '<ul class='.$class.'>'.PHP_EOL;

    foreach ($info as $item)
      $html .= "\t".'<li>'.$item.'</li>'.PHP_EOL;

    $html .= '</ul>'.PHP_EOL;
    return $html;
} // end of makePlainList


function truncatedList(array $info, $class='') {

  $html = '<ul class='.$class.'>'.PHP_EOL;

    $html .= "\t".'<li>'.array_shift($info).'</li>'.PHP_EOL;
    $html .= "\t".'<li>'.array_shift($info).'</li>'.PHP_EOL;
    $html .= "\t".'<li>'.array_shift($info).'</li>'.PHP_EOL;
    $html .= "\t".'<li>'.array_shift($info).'</li>'.PHP_EOL;

    $remaining_items = '';
    foreach ($info as $item)
      $remaining_items .= "\t".'<li>'.$item.'</li>'.PHP_EOL;

    // $id = md5( json_encode($remaining_items) );

    // $html .= toggleDiv($remaining_items, $id);
    $show_more = 'Show '.count($info).' items';
    $html .= "\t".toggleInfo($show_more, $remaining_items, 'toggle_long_text');

  $html .= '</ul>'.PHP_EOL;
  return $html;

} // end of truncatedList


function toggleInfo( $label, $info, $class='' ) {

  $html = '<details class="full_info '.$class.'">';
    $html .= '<summary class="toggle_info">'.$label.'</summary>';
    $html .= $info;
  $html .= '</details>';

  return $html;

}

function camelCaseToWords($camelCase) {

  // Split the string into an array of words separated by capital letters
  // This regex handles consecutive capitals, eg, ID

  $regex = '/(?#! splitCamelCase Rev:20140412)
      # Split camelCase "words". Two global alternatives. Either g1of2:
        (?<=[a-z])      # Position is after a lowercase,
        (?=[A-Z])       # and before an uppercase letter.
      | (?<=[A-Z])      # Or g2of2; Position is after uppercase,
        (?=[A-Z][a-z])  # and before upper-then-lower case.
      /x';

  $words = preg_split($regex, $camelCase);

  // Join the words back together, separated by spaces
  return implode(' ', $words);

} // end of camelCaseToWords


function cleanText( $text ) {

  // intended for cleaning up blocks of text which could contain newlines, HTML, etc

  // first, a trim
  $text = trim($text);

  // does this text contain any newlines?
  // this gets confusing ... real newlines, fake newlines, single/double quotes ...
  $has_newlines = preg_match("/\r|\n/", $text);

  // $has_newlines = true;
  // if (strpos($text, '\n') === false)
  //   $has_newlines = false;

  // does this text contain any tags?
  $has_tags = true;
  if (strpos($text, '<') === false && strpos($text, '>') === false)
    $has_tags = false;

  // if no tags + no newlines, we don't need to do any cleaning
  if (!$has_tags && !$has_newlines)
    return addLinks($text);

  // if we have no tags but some newlines, format newlines, add links and return
  if (!$has_tags && $has_newlines)
    return addLinks( nl2p($text) );

  // if we got this far, we have tags, let's remove any unnecessary ones
  $text = preg_replace('#</?(script|font|o:p)(.|\s)*?>#i', '', $text);

  // Remove all paragraphs that only contain whitespace, tabs, &nbsp; or <br />
  $text = preg_replace('/<p>\s*((&nbsp;|<br\s*\/?>)?\s*)*<\/p>/', '', $text);

  // Trim whitespace from the beginning and end of each paragraph
  $text = preg_replace('/<p>\s*/', '<p>', $text);
  $text = preg_replace('/\s*<\/p>/', '</p>', $text);

  // and now we can add links
  $text = addLinks($text);

  return $text;

} // end of cleanText


function csvCleanData($data) {

  if (!$data)
    return false;

  $data = str_replace('&#127744;', '', $data);
  $data = str_replace('&nbsp;', ' ', $data);
  $data = LinkToExcelHyperlink($data);
  $data = strip_tags($data);

  return trim($data);

} // end of csvCleanData


function LinkToExcelHyperlink($html) {

    // if the cell doesn't start and end with an anchor tag, stop here
    if (!(strpos($html, '<a') === 0 && substr($html, -4) === '</a>'))
       return $html;

    if (preg_match('/href="([^"]+)".*>([^<]+)<\/a>/s', $html, $matches)) {
        $url = $matches[1];
        $text = strip_tags(html_entity_decode($matches[2])); // Decode HTML entities and remove tags
        $text = str_replace('"', '""', $text); // Escape double quotes for Excel
        return '=HYPERLINK("'.$url.'", "'.$text.'")';
    }

    return $html; // Return original content if no link is found

} // end of LinkToExcelHyperlink


function stripTagsFromArray($value) {
    if (is_array($value)) {
        return array_map('stripTagsFromArray', $value);
    }

    $value = str_replace('&#127744;', '', $value);
    $value = str_replace('&nbsp;', ' ', $value);
    $value = strip_tags($value);
    return trim($value);
} // end of stripTagsFromArray


function extractFilenameFromURL($url) {
    $parsedUrl = parse_url($url);
    parse_str($parsedUrl['query'], $queryParams);

    return isset($queryParams['value']) ? $queryParams['value'] : null;
}


function addLinks($text) {

  //if the text has any existing URLs, skip URL replacement
  if ( stristr($text, 'href="') )
    return $text;
  // same for buttons
  if ( stristr($text, '</button>') )
    return $text;

  // replace URLs in text with actual links, omitting trailing fullstops
  return preg_replace("/(?i)\b((?:https?:(?:\/{1,3}|[a-z0-9%])|[a-z0-9.\-]+[.](?:com|net|org|edu|gov|mil|aero|asia|biz|cat|coop|info|int|jobs|mobi|museum|name|post|pro|tel|travel|xxx|ac|ad|ae|af|ag|ai|al|am|an|ao|aq|ar|as|at|au|aw|ax|az|ba|bb|bd|be|bf|bg|bh|bi|bj|bm|bn|bo|br|bs|bt|bv|bw|by|bz|ca|cc|cd|cf|cg|ch|ci|ck|cl|cm|cn|co|cr|cs|cu|cv|cx|cy|cz|dd|de|dj|dk|dm|do|dz|ec|ee|eg|eh|er|es|et|eu|fi|fj|fk|fm|fo|fr|ga|gb|gd|ge|gf|gg|gh|gi|gl|gm|gn|gp|gq|gr|gs|gt|gu|gw|gy|hk|hm|hn|hr|ht|hu|id|ie|il|im|in|io|iq|ir|is|it|je|jm|jo|jp|ke|kg|kh|ki|km|kn|kp|kr|kw|ky|kz|la|lb|lc|li|lk|lr|ls|lt|lu|lv|ly|ma|mc|md|me|mg|mh|mk|ml|mm|mn|mo|mp|mq|mr|ms|mt|mu|mv|mw|mx|my|mz|na|nc|ne|nf|ng|ni|nl|no|np|nr|nu|nz|om|pa|pe|pf|pg|ph|pk|pl|pm|pn|pr|ps|pt|pw|py|qa|re|ro|rs|ru|rw|sa|sb|sc|sd|se|sg|sh|si|sj|Ja|sk|sl|sm|sn|so|sr|ss|st|su|sv|sx|sy|sz|tc|td|tf|tg|th|tj|tk|tl|tm|tn|to|tp|tr|tt|tv|tw|tz|ua|ug|uk|us|uy|uz|va|vc|ve|vg|vi|vn|vu|wf|ws|ye|yt|yu|za|zm|zw)\/)(?:[^\s()<>{}\[\]]+|\([^\s()]*?\([^\s()]+\)[^\s()]*?\)|\([^\s]+?\))+(?:\([^\s()]*?\([^\s()]+\)[^\s()]*?\)|\([^\s]+?\)|[^\s`!()\[\]{};:'\".,<>?«»“”‘’])|(?:(?<!@)[a-z0-9]+(?:[.\-][a-z0-9]+)*[.](?:com|net|org|edu|gov|mil|aero|asia|biz|cat|coop|info|int|jobs|mobi|museum|name|post|pro|tel|travel|xxx|ac|ad|ae|af|ag|ai|al|am|an|ao|aq|ar|as|at|au|aw|ax|az|ba|bb|bd|be|bf|bg|bh|bi|bj|bm|bn|bo|br|bs|bt|bv|bw|by|bz|ca|cc|cd|cf|cg|ch|ci|ck|cl|cm|cn|co|cr|cs|cu|cv|cx|cy|cz|dd|de|dj|dk|dm|do|dz|ec|ee|eg|eh|er|es|et|eu|fi|fj|fk|fm|fo|fr|ga|gb|gd|ge|gf|gg|gh|gi|gl|gm|gn|gp|gq|gr|gs|gt|gu|gw|gy|hk|hm|hn|hr|ht|hu|id|ie|il|im|in|io|iq|ir|is|it|je|jm|jo|jp|ke|kg|kh|ki|km|kn|kp|kr|kw|ky|kz|la|lb|lc|li|lk|lr|ls|lt|lu|lv|ly|ma|mc|md|me|mg|mh|mk|ml|mm|mn|mo|mp|mq|mr|ms|mt|mu|mv|mw|mx|my|mz|na|nc|ne|nf|ng|ni|nl|no|np|nr|nu|nz|om|pa|pe|pf|pg|ph|pk|pl|pm|pn|pr|ps|pt|pw|py|qa|re|ro|rs|ru|rw|sa|sb|sc|sd|se|sg|sh|si|sj|Ja|sk|sl|sm|sn|so|sr|ss|st|su|sv|sx|sy|sz|tc|td|tf|tg|th|tj|tk|tl|tm|tn|to|tp|tr|tt|tv|tw|tz|ua|ug|uk|us|uy|uz|va|vc|ve|vg|vi|vn|vu|wf|ws|ye|yt|yu|za|zm|zw)\b\/?(?!@)))/", "<a class='break-word' target='_blank' href='$1'>$1</a>", $text);

}


function nl2p( $string ) {

  $paragraphs = '';
  foreach (explode("\n", $string) as $line) {
      if (trim($line))
          $paragraphs .= '<p>' . $line . '</p>'.PHP_EOL;  // if (trim()) skips blank lines
  }

  return $paragraphs;

} // end of nl2p


function viewCode( $array ) {

  echo '<pre>';
    print_r( $array );
  echo '</pre>';
  // exit;

} // end of viewCode



function entityIcon($type, $code='') {

  if (!$type)
    $type = ucfirst( getEntityType($code) );

  $icons = [
    'Item' => '📂',
    'Series' => '🗄',
    'Accession' => '🗃',
    'Agency' => '🏢',
    'Organisation' => '🏛',
    'Disposal Authority' => '🗑',
    'Access Authority' => '🔑',
    'Function' => '💼',
    'Jurisdiction' => '📜',
  ];

  $icon = $icons[$type] ?? false;

  if ($icon)
    return '<span title="'.$type.' '.$code.'">'.$icon.' </span> ';

  return false;   // no icon found!

  // return '<b>'.$entry['itemType'].'</b>';

} // end of entityIcon



function single_nl2br($string) {
  // mostly used for newlines in record titles

  // Convert newlines to <br> tags
  $string = nl2br($string);

  // Replace two or more consecutive <br> tags with a single <br>
  return preg_replace('/(<br\s*\/?>\s*){2,}/', '<br>', $string);

} // end of single_nl2br


// CSS Minifier => http://ideone.com/Q5USEF + improvement(s)
function inline_css( $css_path ) {

    // TO DO safety: need to constrain this ...
    $input = file_get_contents( $_SERVER['DOCUMENT_ROOT'].'/assets/styles/'.$css_path );

    if(trim($input) === "") return $input;

    $minified_css = preg_replace(
        array(
            // Remove comment(s)
            '#("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\')|\/\*(?!\!)(?>.*?\*\/)|^\s*|\s*$#s',
            // Remove unused white-space(s)
            '#("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\'|\/\*(?>.*?\*\/))|\s*+;\s*+(})\s*+|\s*+([*$~^|]?+=|[{};,>~]|\s(?![0-9\.])|!important\b)\s*+|([[(:])\s++|\s++([])])|\s++(:)\s*+(?!(?>[^{}"\']++|"(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\')*+{)|^\s++|\s++\z|(\s)\s+#si',
            // Replace `0(cm|em|ex|in|mm|pc|pt|px|vh|vw|%)` with `0`
            '#(?<=[\s:])(0)(cm|em|ex|in|mm|pc|pt|px|vh|vw|%)#si',
            // Replace `:0 0 0 0` with `:0`
            '#:(0\s+0|0\s+0\s+0\s+0)(?=[;\}]|\!important)#i',
            // Replace `background-position:0` with `background-position:0 0`
            '#(background-position):0(?=[;\}])#si',
            // Replace `0.6` with `.6`, but only when preceded by `:`, `,`, `-` or a white-space
            '#(?<=[\s:,\-])0+\.(\d+)#s',
            // Minify string value
            '#(\/\*(?>.*?\*\/))|(?<!content\:)([\'"])([a-z_][a-z0-9\-_]*?)\2(?=[\s\{\}\];,])#si',
            '#(\/\*(?>.*?\*\/))|(\burl\()([\'"])([^\s]+?)\3(\))#si',
            // Minify HEX color code
            '#(?<=[\s:,\-]\#)([a-f0-6]+)\1([a-f0-6]+)\2([a-f0-6]+)\3#i',
            // Replace `(border|outline):none` with `(border|outline):0`
            '#(?<=[\{;])(border|outline):none(?=[;\}\!])#',
            // Remove empty selector(s)
            '#(\/\*(?>.*?\*\/))|(^|[\{\}])(?:[^\s\{\}]+)\{\}#s'
        ),
        array(
            '$1',
            '$1$2$3$4$5$6$7',
            '$1',
            ':0',
            '$1:0 0',
            '.$1',
            '$1$3',
            '$1$2$4$5',
            '$1$2$3',
            '$1:0',
            '$1$2'
        ),
    $input);

    return '<style type="text/css">'.PHP_EOL.$minified_css.PHP_EOL.'</style>';

} // end of inline_css



function minify_and_inline_js( $js_path) {

    $input = file_get_contents( $_SERVER['DOCUMENT_ROOT'].'/assets/js/'.$js_path );

    if(trim($input) === "") return $input;

    $minified_js = preg_replace(
        array(
            // Remove comment(s)
            '#\s*("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\')\s*|\s*\/\*(?!\!|@cc_on)(?>[\s\S]*?\*\/)\s*|\s*(?<![\:\=])\/\/.*(?=[\n\r]|$)|^\s*|\s*$#',
            // Remove white-space(s) outside the string and regex
            '#("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\'|\/\*(?>.*?\*\/)|\/(?!\/)[^\n\r]*?\/(?=[\s.,;]|[gimuy]|$))|\s*([!%&*\(\)\-=+\[\]\{\}|;:,.<>?\/])\s*#s',
            // Remove the last semicolon
            '#;+\}#',
            // Minify object attribute(s) except JSON attribute(s). From `{'foo':'bar'}` to `{foo:'bar'}`
            '#([\{,])([\'])(\d+|[a-z_][a-z0-9_]*)\2(?=\:)#i',
            // --ibid. From `foo['bar']` to `foo.bar`
            '#([a-z0-9_\)\]])\[([\'"])([a-z_][a-z0-9_]*)\2\]#i'
        ),
        array(
            '$1',
            '$1$2',
            '}',
            '$1$3',
            '$1.$3'
        ),
    $input);

  return '<script type="text/javascript">'.$minified_js.PHP_EOL.'</script>'.PHP_EOL;

} // end of minify_and_inline_js



function inline_js( $js_path ) {
  $js = file_get_contents( $_SERVER['DOCUMENT_ROOT'].'/assets/js/'.$js_path );
  return '<script type="text/javascript">'.$js.PHP_EOL.'</script>'.PHP_EOL;
}


function flush_buffers() {

  ob_end_flush();
  ob_flush();
  flush();
  ob_start();

} // end of flush_buffers



function templateError( $message )  {
  $error_message = '<h2 class="error center" style="margin-top:1em;">'.$message.'</h2><br /><br />';
  return Template::build($error_message, 'Error');
}




function buildSiteTitle( $template_page_title=false) {

  $title = '';

  // we may have a partial page title provided
  if ( $template_page_title )
    $title .= strip_tags($template_page_title).' | ';

  $title .= 'Archway-style Collections Search';

  echo $title;

} // end of buildSiteTitle

?>
