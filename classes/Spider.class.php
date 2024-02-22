<?php

class Spider {

	var $ch; 		// cURL handle used to hold our cURL instance
	var $info; 	// cURL debug headers info

	public ?string $url = null;

	public ?string $fetch_source = null;

	public ?array $curl_info = null;

	private string $cache_context = '';

  private bool $cache_encryption = true;

	var $timeout_ms=15000;	// fifteen seconds, gross

	function __construct() {
		$this->ch = curl_init(); /// open a cURL instance
	}
	
	function setTimeoutMS( $milliseconds ) {
		$this->timeout_ms = $milliseconds;
	}


	function fetch( string $url, string $context, bool $skip_cache=false ) {

		// sanity check - if URL is blank, stop here 
		if ( $url == '' )
			return false;
			// throw new Exception('No URL provided to singleSpider!');

		// sanity check - if context string is blank, stop here
		// TODO:  should we handle-ise this string?  eg changes spaces to underscores, / lowercase the string, etc
		// plus there is a max length presumably
		if ($context == '')
			throw new Exception('No context provided to Spider fetch class!');

		// reset fetch source
		$this->fetch_source = null;
	
		$this->url = $url;
		$this->cache_context = $context;

		// should we skip the cache?
		if ($skip_cache)
			return $this->fetchFromWeb( $url );
		 
		// collect cached JSON if available, otherwise collect the actual JSON from this URL
		return $this->fetchFromCache() ?? $this->fetchFromWeb( $url );
		
	} // end of fetch
	
	

	function fetchFromWeb( $url ) {
	
		$this->setup( $this->ch, $url );
		$html = curl_exec($this->ch); // pulls the webpage from the internet
		
		// keep track of technical info
		$this->curl_info = curl_getinfo($this->ch);
		// print_r($info).'<br /><br />';
		// 
		// $headers = curl_getinfo($this->ch, CURLINFO_HEADER_OUT );
		// print_r($headers);
		// 
		// global $dns_time;
		// $dns_time += $info['namelookup_time'];
		// 
		// $domain_bits = parse_url( $url );  
		// echo "'".$domain_bits['host'].':'.$info['primary_port'].':'.$info['primary_ip']."',<br />";
		// 
		// echo '<b>URL: '.$info['url'].'</b><br />';
		// echo 'IP Address: '.$info['primary_ip'].'<br />';
    // echo 'Total cURL time: '.$info['total_time'].'<br />';
    // echo 'Namelookup_time: '.$info['namelookup_time'].'<br />';
    // echo 'Connect_time: '.$info['connect_time'].'<br />';
    // echo 'Pretransfer_time: '.$info['pretransfer_time'].'<br /><br /><br />';

		if (!empty(curl_error($this->ch)))
			throw new Exception( 'Oops, while collecting information the following error occurred:<br /><br />'.curl_error($this->ch) );

		if ($this->curl_info['size_download'] == 0)
			throw new Exception( "Oops, there was no response from URL ".$url );

		// check for common 'apierror' messages from the Collections Search API / NatLib media viewer
		// at this point it should be raw or unprocessed JSON
		$this->checkForJSONErrors( $html );
		
		$this->fetch_source = 'web';
		
		// OK, load this into the cache
   	$this->AddToCache( $html );
				
		return $html;

	} // end of fetchFromWeb


	function curlClose() {
				
		curl_close($this->ch); /// closes the connection
			
	}


	function setup( $spider, $url ) {

			curl_setopt($spider, CURLOPT_URL, $url); /// set the URL to download
			curl_setopt($spider, CURLOPT_RETURNTRANSFER, 1); // tell cURL to return the data
			
			curl_setopt($spider, CURLOPT_CONNECTTIMEOUT, 3);
			curl_setopt($spider, CURLOPT_TIMEOUT_MS, $this->timeout_ms);	// NOTE: this is now milliseconds
									
			// use cached IP addresses to speed things up even more
			curl_setopt($spider, CURLOPT_RESOLVE, $this->cachedHostIP($url) );
			curl_setopt($spider, CURLOPT_DNS_USE_GLOBAL_CACHE, false);
			
			// use HTTP2 
			curl_setopt($spider, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2);
			
			curl_setopt($spider, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($spider, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($spider, CURLOPT_HTTPAUTH, CURLAUTH_ANY);

			$relative = '../';
			$cookie = $relative.'cookie.txt';
			curl_setopt($spider, CURLOPT_COOKIEJAR, $cookie );	
			curl_setopt($spider, CURLOPT_COOKIEFILE, $cookie );
			// Archives returns an error if we build up more than 200 cookies in here
			// should we have kind of check to clear out the cookie file sometimes??

			curl_setopt($spider, CURLOPT_FOLLOWLOCATION, true); // Follow any redirects
			curl_setopt($spider, CURLOPT_ENCODING, ''); // set gzip, deflate or keep empty for server to detect and set supported encoding.

			curl_setopt($spider, CURLOPT_USERAGENT, $this->getRandomUserAgent() );
	
			// for debugging		
			curl_setopt($spider, CURLOPT_VERBOSE, true);	
			curl_setopt($spider, CURLINFO_HEADER_OUT, true);
		
	} // end of setupSpider


	function getRandomUserAgent() {

		$user_agents = array(
			'Mozilla/5.0 (iPhone; CPU iPhone OS 15_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/101.0.4951.44 Mobile/15E148 Safari/604.1',
			
			'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/101.0.4951.67 Safari/537.36',
			
			'Mozilla/5.0 (Linux; Android 10; SM-A205U) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/101.0.4951.61 Mobile Safari/537.36',
			
			'Mozilla/5.0 (Linux; Android 10; LM-Q710(FGN)) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/101.0.4951.61 Mobile Safari/537.36',
		
			'Mozilla/5.0 (Macintosh; Intel Mac OS X 12_3_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/101.0.4951.67 Safari/537.36',
			
			'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/101.0.4951.67 Safari/537.36',
		);
		
		// pick a random UA string and return it
		return array_rand( array_flip($user_agents) );
		
	} // end of getRandomUserAgent


	function cachedHostIP( $url ) {
		
		// to speed things up we use a cached IP address that cURL can use to contact each domain
		// this skips a lot of nslookup() calls which can take up to 250ms
		
		if (!$url) return array();
				
		// let's set up a cached function call to get the IP address, or a cached version
		$getCachedHostByName = $this->ip_cache()->wrap(
			'gethostbyname',    // this function will be called if there is no cached IP
			[$this->ip_cache()::EXPIRE => '1 week']
		);

		// parse the URL to get the host (eg example.co.nz)
		$parts = parse_url( $url );
				
		$host = $parts['host'];
		
		try {
			$ip = $getCachedHostByName( $host );
		} catch (TypeError $ex) {
	    return array();
	  }
		

		if ($parts['scheme'] == 'http')
			$port = 80;
		
		if ($parts['scheme'] == 'https')
			$port = 443;

		return array( $host.':'.$port.':'.$ip );
		
	} // end of cachedHostIP


	function ip_cache() {

		$relative = '../';
		$storage = new Nette\Caching\Storages\FileStorage( $relative.'cache' );
		
		return new Nette\Caching\Cache($storage, 'ip_addresses' );
						
	} // end of IP address cache




  function cache() {

		$relative = '../';
		
		$base_cache_dir = $relative.'cache/';
		if (!is_dir($base_cache_dir))
		  mkdir($base_cache_dir, 0777, true);	
		
  	$cache_dir = $relative.'cache/'.$this->cache_context;
		if (!is_dir($cache_dir))
		  mkdir($cache_dir, 0777, true);
	
		$storage = new Nette\Caching\Storages\FileStorage( $cache_dir );
							
    // should we use the real cache, or skip caching?					
		// $storage = new Nette\Caching\Storages\DevNullStorage;
    
    // NOTE:  you may need to change owner so Apache can write to the cache dir, eg 
    // chown www-data:www-data cache
    
    return new Nette\Caching\Cache( $storage );
                
  } // end of cache
	
	
    
    function key() {
      
      if (!$this->url)
        throw new Exception('Oops, no key specified for URL caching');
      
			// TO DO:  if we bring back POST arrays, we need to use that as part of the key
			
      return $this->url;
      
    } // end of key


	function fetchFromCache() {
		
		$cached_html = $this->cache()->load( $this->key() );
		
		if ($cached_html) {
			$this->fetch_source = 'cache';
			return $this->decrypt($cached_html);
		}
		
		// otherwise, return null
		return null;

	} // end of fetchFromCache



	function AddToCache( $html ) {
		
		// echo 'Caching some results with key: '.$this->key();
					
		// $this->catchJSONErrors( $results );            
			
		$this->cache()->save( 
			$this->key(),
			$this->encrypt( $html ), 
			[$this->cache()::EXPIRE => '1 week']
		);
		
	} // end of AddToCache


	function encrypt( $html ) {
	          
	  if ( !$this->cache_encryption )
	    return $html;
	                    
	  $encrypted = openssl_encrypt(
	    base64_encode( json_encode($html) ),
	    'aes-128-cbc',
	    base64_encode( $this->key() ),
	    0,
	    'AF7E822CA58EA557'
	  );
	  
	  return $encrypted;
	  
	} // end of encrypt


	function decrypt( $encrypted_string ) {
	  
	  if ( !$this->cache_encryption )
	    return $encrypted_string;
	                
	  return 
	    json_decode( 
	      base64_decode( 
	        openssl_decrypt(
	        $encrypted_string,
	        'aes-128-cbc',
	        base64_encode( $this->key() ),
	        0,
	        'AF7E822CA58EA557'
	      )
	    ), true
	  );

	} // end of decrypt





	
	function checkForJSONErrors( $raw_output ) {
	
		if ($raw_output === null)
			throw new Exception('Sorry, there was no response from Archives NZ for this request');
	
		if ( stristr($raw_output, 'event:error') !== FALSE)
	     throw new Exception('Collections Search returned an error');
	
	 	if ( stristr($raw_output, '<h1>502 Bad Gateway</h1>') !== FALSE)
	     throw new Exception('Sorry, Collections Search returned an error (502 Bad Gateway)');

		// sometimes returned by NatLib media viewer
		if ( stristr($raw_output, 'Error in Delivery') !== FALSE)
	     throw new Exception('Sorry, there was a problem retrieving the media viewer information (Error in Delivery)');
		
	 	// if ( stristr($raw_output, '<h1>HTTP Status 400') !== FALSE)
	  //    throw new Exception('Sorry, Collections Search returned an error (HTTP Status 400)');


	
		// at this point our string should be raw or unprocessed JSON
		// POSSIBLY with some garbage at the beginning / end, will that F things up??  Let's find out
    $json = json_decode( $raw_output, true);

	  if ( isset($json['message']) )
	    throw new Exception('Collections Search returned the following message:<br /><br /> '.$json['message']);

	  if ( isset($json['apierror']['status']) )
	    throw new Exception('Collections Search returned the following error:<br /><br /> '.$json['apierror']['status'].'<br />'.$json['apierror']['message']);
	
	  return true;
	
	} // end of checkForJSONErrors

			

}	// end of singleSpider class
