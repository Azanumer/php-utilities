<?php
/**
 * SecurityHeaders — framework-agnostic security header builder for PHP.
 *
 * Builds a modern, sane header set (HSTS, CSP with nonces, X-Frame-Options,
 * Referrer-Policy, Permissions-Policy, COOP/COEP/CORP) and sends it with
 * correct handling for CLI, already-sent headers, and report-only mode.
 *
 * Usage:
 *   $h = SecurityHeaders::defaultSecure();
 *   $h->nonce();                       // returns a fresh CSP nonce for inline <script nonce="...">
 *   $h->apply();                       // sends headers
 */

class SecurityHeaders {

	/** @var array Header name => value. */
	private $headers = array();

	/** @var array CSP directive => sources. */
	private $csp = array();

	/** @var bool Report-only mode for CSP. */
	private $reportOnly = false;

	/** @var string|null Current request nonce. */
	private $nonce = null;

	/**
	 * Private constructor — use the factory methods.
	 */
	private function __construct() {}

	/**
	 * Sensible hardened defaults for a typical dynamic site.
	 *
	 * CSP: self + inline scripts/styles allowed only with a nonce,
	 * images/fonts/media from self + https + data:. Adjust to your site.
	 *
	 * @return SecurityHeaders
	 */
	public static function defaultSecure() {
		$h = new self();
		$h->header( 'X-Content-Type-Options', 'nosniff' );
		$h->header( 'X-Frame-Options', 'SAMEORIGIN' );
		$h->header( 'Referrer-Policy', 'strict-origin-when-cross-origin' );
		$h->header( 'Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()' );
		$h->header( 'Cross-Origin-Opener-Policy', 'same-origin' );
		$h->header( 'Cross-Origin-Resource-Policy', 'same-origin' );
		$h->csp( 'default-src', array( "'self'" ) );
		$h->csp( 'script-src', array( "'self'", "'nonce'" ) );
		$h->csp( 'style-src', array( "'self'", "'nonce'" ) );
		$h->csp( 'img-src', array( "'self'", 'https:', 'data:' ) );
		$h->csp( 'font-src', array( "'self'", 'https:', 'data:' ) );
		$h->csp( 'connect-src', array( "'self'" ) );
		$h->csp( 'frame-ancestors', array( "'self'" ) );
		$h->csp( 'base-uri', array( "'self'" ) );
		$h->csp( 'form-action', array( "'self'" ) );
		return $h;
	}

	/**
	 * Set an arbitrary header (overwrites same-name headers).
	 *
	 * @param string $name  Header name.
	 * @param string $value Header value.
	 * @return $this
	 */
	public function header( $name, $value ) {
		$this->headers[ $name ] = $value;
		return $this;
	}

	/**
	 * Enable HTTP Strict Transport Security.
	 *
	 * @param int  $maxAge          Seconds browsers remember (default 1 year).
	 * @param bool $includeSubDomains Include subdomains.
	 * @param bool $preload         Add preload token (only after submitting to hstspreload.org).
	 * @return $this
	 */
	public function hsts( $maxAge = 31536000, $includeSubDomains = true, $preload = false ) {
		$value = 'max-age=' . (int) $maxAge;
		if ( $includeSubDomains ) {
			$value .= '; includeSubDomains';
		}
		if ( $preload ) {
			$value .= '; preload';
		}
		return $this->header( 'Strict-Transport-Security', $value );
	}

	/**
	 * Set sources for a Content-Security-Policy directive.
	 *
	 * The special source "'nonce'" is replaced with the request nonce
	 * (generated lazily) when the header is built.
	 *
	 * @param string $directive e.g. 'script-src'.
	 * @param array  $sources   Source list.
	 * @return $this
	 */
	public function csp( $directive, array $sources ) {
		$this->csp[ $directive ] = $sources;
		return $this;
	}

	/**
	 * Add a CSP report endpoint and switch CSP to report-only mode
	 * (useful when rolling out a policy without breaking the site).
	 *
	 * @param string $reportUri Report endpoint URL.
	 * @return $this
	 */
	public function cspReportOnly( $reportUri ) {
		$this->reportOnly = true;
		return $this->csp( 'report-uri', array( $reportUri ) );
	}

	/**
	 * Get (creating if needed) the per-request CSP nonce.
	 * Use it in your templates: <script nonce="<?php echo $h->nonce(); ?>">
	 *
	 * @return string Base64 nonce.
	 */
	public function nonce() {
		if ( $this->nonce === null ) {
			$this->nonce = rtrim( strtr( base64_encode( random_bytes( 16 ) ), '+/', '-_' ), '=' );
		}
		return $this->nonce;
	}

	/**
	 * Build the Content-Security-Policy value from directives.
	 *
	 * @return string
	 */
	private function buildCsp() {
		$parts = array();
		foreach ( $this->csp as $directive => $sources ) {
			$sources = array_map(
				function ( $s ) {
					return $s === "'nonce'" ? "'nonce-{$this->nonce()}'" : $s;
				},
				$sources
			);
			$parts[] = $directive . ' ' . implode( ' ', $sources );
		}
		return implode( '; ', $parts );
	}

	/**
	 * Send all headers. No-ops on CLI or when headers were already sent
	 * (returns false in that case so callers can log it).
	 *
	 * @return bool True if headers were sent.
	 */
	public function apply() {
		if ( PHP_SAPI === 'cli' || headers_sent() ) {
			return false;
		}
		foreach ( $this->headers as $name => $value ) {
			header( $name . ': ' . $value );
		}
		if ( ! empty( $this->csp ) ) {
			$cspName = $this->reportOnly ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy';
			header( $cspName . ': ' . $this->buildCsp() );
		}
		return true;
	}

	/**
	 * Return headers as an array (handy for frameworks / testing).
	 *
	 * @return array Header name => value, including the CSP header.
	 */
	public function toArray() {
		$out = $this->headers;
		if ( ! empty( $this->csp ) ) {
			$cspName        = $this->reportOnly ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy';
			$out[ $cspName ] = $this->buildCsp();
		}
		return $out;
	}
}
