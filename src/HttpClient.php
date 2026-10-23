<?php
/**
 * HttpClient — a dependency-free HTTP client built on ext-curl.
 *
 *   $client = new HttpClient( array( 'timeout' => 10 ) );
 *   $res    = $client->get( 'https://api.example.com/users', array( 'page' => 1 ) );
 *   if ( $res->ok() ) {
 *       $users = $res->json();
 *   }
 *
 * All requests return an HttpResponse value object; network failures
 * return a response with status 0 and the cURL error in ->error().
 * For stricter code, enable 'throw' => true to get HttpClientException.
 */

class HttpClientException extends RuntimeException {}

/**
 * Value object for an HTTP response.
 */
class HttpResponse {
	/** @var int HTTP status code (0 when the request never completed). */
	public $status;
	/** @var string Response body. */
	public $body;
	/** @var array<string,string> Lower-cased response headers. */
	public $headers;
	/** @var string cURL error message when $status is 0. */
	public $error;

	public function __construct( $status, $body, $headers = array(), $error = '' ) {
		$this->status  = (int) $status;
		$this->body    = (string) $body;
		$this->headers = $headers;
		$this->error   = (string) $error;
	}

	/** 2xx? */
	public function ok() {
		return $this->status >= 200 && $this->status < 300;
	}

	/** Network failure (no HTTP response received)? */
	public function failed() {
		return 0 === $this->status;
	}

	/**
	 * Decode a JSON body.
	 *
	 * @return mixed Decoded value, or null when the body is not valid JSON.
	 */
	public function json() {
		$data = json_decode( $this->body, true );
		return ( JSON_ERROR_NONE === json_last_error() ) ? $data : null;
	}

	/** Case-insensitive header lookup. */
	public function header( $name ) {
		$name = strtolower( $name );
		return isset( $this->headers[ $name ] ) ? $this->headers[ $name ] : null;
	}
}

/**
 * Simple cURL-based HTTP client.
 */
class HttpClient {
	/** @var array Default options. */
	protected $defaults;

	/**
	 * @param array $options timeout (s), connect_timeout (s), user_agent,
	 *                       headers (array), verify_ssl (bool),
	 *                       throw (bool: raise on network/4xx/5xx),
	 *                       follow_redirects (bool), max_redirects (int).
	 */
	public function __construct( array $options = array() ) {
		$this->defaults = array_merge( array(
			'timeout'          => 15,
			'connect_timeout'  => 10,
			'user_agent'       => 'HttpClient/1.0 (+PHP)',
			'headers'          => array(),
			'verify_ssl'       => true,
			'throw'            => false,
			'follow_redirects' => true,
			'max_redirects'    => 5,
		), $options );

		if ( ! extension_loaded( 'curl' ) ) {
			throw new HttpClientException( 'ext-curl is required by HttpClient.' );
		}
	}

	/** GET with optional query parameters. */
	public function get( $url, array $query = array(), array $headers = array() ) {
		if ( ! empty( $query ) ) {
			$url .= ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $query );
		}
		return $this->request( 'GET', $url, array( 'headers' => $headers ) );
	}

	/** POST a form-encoded body. */
	public function post( $url, array $data = array(), array $headers = array() ) {
		return $this->request( 'POST', $url, array(
			'body'    => http_build_query( $data ),
			'headers' => array_merge(
				array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
				$headers
			),
		) );
	}

	/** POST a JSON body. */
	public function postJson( $url, $data, array $headers = array() ) {
		return $this->request( 'POST', $url, array(
			'body'    => json_encode( $data ),
			'headers' => array_merge(
				array( 'Content-Type' => 'application/json' ),
				$headers
			),
		) );
	}

	public function put( $url, $body = '', array $headers = array() ) {
		return $this->request( 'PUT', $url, compact( 'body', 'headers' ) );
	}

	public function delete( $url, array $headers = array() ) {
		return $this->request( 'DELETE', $url, array( 'headers' => $headers ) );
	}

	/**
	 * Send an authenticated request with a Bearer token.
	 *
	 * @param string $method  HTTP method.
	 * @param string $url     URL.
	 * @param string $token   Bearer token.
	 * @param mixed  $body    Optional body.
	 * @param array  $headers Extra headers.
	 */
	public function bearer( $method, $url, $token, $body = null, array $headers = array() ) {
		$headers['Authorization'] = 'Bearer ' . $token;
		return $this->request( $method, $url, array( 'body' => $body, 'headers' => $headers ) );
	}

	/**
	 * Core request sender.
	 *
	 * @param string $method  HTTP method.
	 * @param string $url     URL.
	 * @param array  $options body (string|null), headers (array), timeout, verify_ssl…
	 * @return HttpResponse
	 * @throws HttpClientException When 'throw' is enabled and the request fails.
	 */
	public function request( $method, $url, array $options = array() ) {
		$opt = array_merge( $this->defaults, $options );

		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_HEADER, true );
		curl_setopt( $ch, CURLOPT_TIMEOUT, (int) $opt['timeout'] );
		curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, (int) $opt['connect_timeout'] );
		curl_setopt( $ch, CURLOPT_USERAGENT, $opt['user_agent'] );
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, (bool) $opt['follow_redirects'] );
		curl_setopt( $ch, CURLOPT_MAXREDIRS, (int) $opt['max_redirects'] );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, (bool) $opt['verify_ssl'] );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, $opt['verify_ssl'] ? 2 : 0 );

		$method = strtoupper( $method );
		if ( 'GET' !== $method ) {
			curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $method );
		}
		if ( null !== $opt['body'] && '' !== $opt['body'] ) {
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $opt['body'] );
		}

		$headers = array_merge( $this->defaults['headers'], isset( $opt['headers'] ) ? $opt['headers'] : array() );
		$curl_headers = array();
		foreach ( $headers as $name => $value ) {
			$curl_headers[] = $name . ': ' . $value;
		}
		if ( ! empty( $curl_headers ) ) {
			curl_setopt( $ch, CURLOPT_HTTPHEADER, $curl_headers );
		}

		$raw      = curl_exec( $ch );
		$errno    = curl_errno( $ch );
		$errmsg   = curl_error( $ch );
		$status   = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		$hdr_size = (int) curl_getinfo( $ch, CURLINFO_HEADER_SIZE );
		curl_close( $ch );

		if ( false === $raw || 0 !== $errno ) {
			$response = new HttpResponse( 0, '', array(), $errmsg ? $errmsg : 'cURL error ' . $errno );
		} else {
			$response = new HttpResponse(
				$status,
				substr( $raw, $hdr_size ),
				self::parseHeaders( substr( $raw, 0, $hdr_size ) )
			);
		}

		if ( $opt['throw'] && ( $response->failed() || $response->status >= 400 ) ) {
			throw new HttpClientException(
				$response->failed()
					? 'Request failed: ' . $response->error
					: 'HTTP error ' . $response->status . ' for ' . $method . ' ' . $url
			);
		}

		return $response;
	}

	/**
	 * Parse a raw header block into a lower-cased name => value map.
	 * Uses the LAST header block (redirect chains prepend earlier ones).
	 */
	protected static function parseHeaders( $raw_headers ) {
		$headers = array();
		$blocks  = preg_split( "/\r\n\r\n/", trim( $raw_headers ) );
		$block   = end( $blocks );
		foreach ( explode( "\r\n", $block ) as $line ) {
			if ( false === strpos( $line, ':' ) ) {
				continue; // Status line.
			}
			list( $name, $value ) = explode( ':', $line, 2 );
			$headers[ strtolower( trim( $name ) ) ] = trim( $value );
		}
		return $headers;
	}
}
