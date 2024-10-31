<?php
/**
 * Normalize URLs
 * 
 * Copyright 2010 by hakre <hakre.wordpress.com>, some rights reserved.
 *
 * Wordpress Plugin Header:
 * 
 *   Plugin Name:    Normalize URLs
 *   Plugin URI:     http://hakre.wordpress.com/plugins/normalize-urls/
 *   Description:    Normalize URLs makes Wordpress to play more nicely regarding URLs in HTTP Requests.
 *   Version:        1.2-beta-2
 *   Stable tag:     1.1
 *   Min WP Version: 2.9
 *   Author:         hakre
 *   Author URI:     http://hakre.wordpress.com/
 *   Donate link:    http://www.prisonradio.org/donate.htm
 *   Tags:           HTTP, redirect
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */
return normalizeUrlsPlugin::bootstrap();

class normalizeUrlsPlugin {
	/** @var string original request uri */
	private $_original_request_uri;
	/** @var normalizeUrlsPlugin */
	static $__instance;
	final public static function bootstrap() {
		if (null==normalizeUrlsPlugin::$__instance)
			normalizeUrlsPlugin::$__instance = new normalizeUrlsPlugin();
		return normalizeUrlsPlugin::$__instance;
	}
	private function __construct() {
		$this->normalizeRequest();
	}
	
	public function normalizeRequest() {
		if ( null !== $this->_original_request_uri ) {
			throw new BadMethodCallException(sprintf('Request has been already normalized (Original Request: %s)!', $this->_original_request_uri));
		}

		$this->_original_request_uri = $_SERVER['REQUEST_URI'];
		$_SERVER['REQUEST_URI'] = $this->url_normalize($_SERVER['REQUEST_URI']);
	}

### {section:funcset-removedotsegments:start}
/**
 * remove dot segments
 *
 * @param string $path
 * @return string path w/o dot segments
 */
function remove_dot_segments( $path ) {
	$input  = (string) $path;
	$output = '';

	while(strlen($input))
	{
		// A.1
		$in3 = substr($input, 0, 3);
		if ('../' === $in3) {
				$input = substr($input, 3);
				continue;
		}

		// A.2
		$in2 = substr($input, 0, 2);
		if ('./' === $in2) {
				$input = substr($input, 2);
				continue;				
		}

		// B.1
		if ('/./' === $in3) {
			$input = substr($input, 2);
			continue;
		}

		// B.2
		if ('/.' === $input) {
			$input = '/';
			continue;
		}

		// C.1
		$in4 = substr($input, 0, 4);
		if ( ('/../' === $in4 && ($input = substr($input, 3))) || ( ('/..' === $input) && ($input = '/')) ) {
			$output = substr($output, 0, (int) strrpos($output, '/'));
			continue;
		}

		// D.1
		if ( '.' === $input || '..' === $input ) {
			$input = '';
			continue;
		}

		// E.1
		if ('/' === $input[0]) {
			$output .= '/';
			$input   = substr($input, 1);
		}

		// E.2
		$p = strpos($input, '/');
		if ( false === $p ) {
			$output .= $input;
			$input   = '';
		} else {
			$output .= substr($input, 0, $p);
			$input   = substr($input, $p);
		}
	}

	return $output;
}
### {section:funcset-removedotsegments:end}

### {section:funcset-14292.6:start}
/**
 * compare two or more URLs with each other
 * 
 * @since 3.0.1
 * @see RFC 2612 section 3.2.3 {@link http://www.ietf.org/rfc/rfc2616.txt}
 * 
 * @param  string $url1 first URL
 * @param  string $url2 other URL
 * @return int number of different URLs (0 for no difference)
 */
function url_compare( $url1, $url2 ) {
	$urls = func_get_args();
	$urls = array_map( 'url_normalize', $urls );
	$urls = array_unique( $urls );
	return count( $urls ) - 1;
}

/**
 * normalize a URL
 * 
 * some basic protocols are supported like HTTP, HTTPS, FTP
 * 
 * @since 3.0.1
 * @param string $url URL to normalize
 * @param array $default_ports (optional) sheme-keyed ([a-z]+) array of default ports (integer)
 * @param string normalized URL, empty string if URL was invalid 
 */
function url_normalize( $url ) {	
	// check for invalid characters, an invalid URL is "normalized" as empty string
	$nurl   = (string) $url;
	$result = preg_match('([\x00-\x20\x7F-\xFF])', $nurl);
	if ( $result )
		return '';
	$result = null;

	// normalize triplets
	if ( false !== strpos( $nurl, '%' ) ) {
		// normalize triplets case to lowercase
		$nurl = preg_replace_callback( '(%[a-f0-9]{2})i', array( $this, 'url_normalize_triplets' ), $nurl );
		
		// normalize unreserved triplets which might but are not to be encoded in a normlaized URL
		// unreserved  = ALPHA / DIGIT / "-" / "." / "_" / "~"  (not: "*" / "'" / "(" / ")" )
		$unreserved = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-_.!~";
		
		$i = 0;	
		$m = strlen( $unreserved );
		while ( $i < $m )
			$nurl = str_replace( '%' . dechex( ord( $c = $unreserved[$i++] ) ), $c, $nurl );
		$unreserved = $i = $m = $c = null;
	}

	// malformed invalid URL is "normalized" as emtpy string
	$parts = @parse_url( $nurl );
	if ( false === $parts )
		return '';

	// normalize sheme, host, port, (abs_)path and query 
	$count = extract( $parts );

	// normalize host
	isset( $host ) && $host = strtolower( $host );

	// normalize scheme and it's according default port
	isset( $scheme ) && ( $scheme = strtolower( $scheme ) ) 
	&& isset( $port ) && ( getservbyname( $scheme, 'tcp' ) === $port ) && ( $port = null );

	// normalize empty port
	isset( $port ) && empty( $port ) && $port = null;

	// normalize path segment (RFC 3986, section 6.2.2.3)
	!isset( $path ) && $path = '/';

	$path = $this->remove_dot_segments( $path );

	isset( $host ) && '' == $path && $path = '/';

	// normalize query (sort and filter out empty entries)
	isset( $query ) && ( $query = explode( '&', $query ) ) && asort( $query )
		&& $query = implode( '&', array_filter( $query ) )
		;

	// build normalized URL
	$nurl = '';

	isset( $user ) && $nurl .= $user;
	isset( $pass ) && $nurl .= ':' . $pass;
	$nurl && $nurl .= '@';
	
	isset( $scheme ) && $nurl = $scheme . '://' . $nurl;
	
	isset( $host ) && $nurl .= $host;
	isset( $port ) && $nurl .= ':' . $port;
	isset( $path ) && $nurl .= $path;
	isset( $query ) && $nurl .= '?' . $query;
	isset( $fragment ) && $nurl .= '#' . $fragment;

	return $nurl;
}

/**
 * callback to lowercase first match
 * 
 * @since 3.0.1
 * @note only to be used by url_normalize
 * @param array $matches matches
 * @return string lowercase first match
 */
function url_normalize_triplets( $matches ) {#
	return strtolower( $matches[0] );
}
### {section:funcset-14292.6:end}
}

# EOF;