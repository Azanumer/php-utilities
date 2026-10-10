<?php
/**
 * WhoisClient — dependency-free WHOIS lookups over raw port-43 sockets.
 *
 * Follows registrar referrals automatically, so querying a .com/.net
 * domain returns the thin registrar record (with expiry date) instead
 * of the registry stub. Expiry dates are normalised to DateTime.
 *
 * Usage:
 *   $whois = new WhoisClient();
 *   $expiry = $whois->getExpiry('example.com');   // DateTime|null
 *   echo $whois->query('example.com');            // raw text
 */

class WhoisClient {

	/** Known registry servers for common TLDs (used as the first hop). */
	private const REGISTRY = array(
		'com'  => 'whois.verisign-grs.com',
		'net'  => 'whois.verisign-grs.com',
		'org'  => 'whois.pir.org',
		'info' => 'whois.afilias.net',
		'biz'  => 'whois.biz',
		'us'   => 'whois.nic.us',
		'uk'   => 'whois.nic.uk',
		'co'   => 'whois.nic.co',
		'io'   => 'whois.nic.io',
		'me'   => 'whois.nic.me',
		'ai'   => 'whois.nic.ai',
		'pk'   => 'whois.pknic.net.pk',
		'in'   => 'whois.registry.in',
	);

	/** @var int seconds to wait for each socket operation */
	private $timeout;

	/** @var string raw text of the last query */
	private $raw = '';

	public function __construct(int $timeout = 10) {
		$this->timeout = max(1, $timeout);
	}

	/**
	 * Run a full WHOIS query with referral-following.
	 *
	 * @return string raw WHOIS response (registrar record when available)
	 * @throws RuntimeException on network failure
	 */
	public function query(string $domain): string {
		$domain = strtolower(trim($domain));
		$domain = preg_replace('/^https?:\/\//', '', $domain);
		$domain = rtrim(explode('/', $domain)[0], '.');

		if (!preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)+$/', $domain)) {
			throw new InvalidArgumentException("Not a valid domain: $domain");
		}

		$tld    = strtolower(substr($domain, strrpos($domain, '.') + 1));
		$server = self::REGISTRY[$tld] ?? $this->ianaServer($tld);

		$response = $this->ask($server, $domain);

		// Follow one referral to the registrar's WHOIS server for the full record.
		$referral = $this->findReferral($response);
		if ($referral !== null && strcasecmp($referral, $server) !== 0) {
			$response = $this->ask($referral, $domain);
		}

		$this->raw = $response;
		return $response;
	}

	/** Raw text of the most recent query ('' before any query). */
	public function getRaw(): string {
		return $this->raw;
	}

	/**
	 * Expiry date of a domain, or null when unavailable/unparseable.
	 */
	public function getExpiry(string $domain): ?DateTime {
		$text  = $this->query($domain);
		$match = $this->findExpiry($text);
		if ($match === null) {
			return null;
		}
		try {
			return new DateTime($match);
		} catch (Exception $e) {
			return null;
		}
	}

	/**
	 * Days until expiry (negative when already expired), or null.
	 */
	public function daysUntilExpiry(string $domain): ?int {
		$expiry = $this->getExpiry($domain);
		if ($expiry === null) {
			return null;
		}
		$now  = new DateTime('now', $expiry->getTimezone());
		$diff = $now->diff($expiry);
		return $diff->invert ? -$diff->days : $diff->days;
	}

	/**
	 * Registrar name from the last query, or ''.
	 */
	public function getRegistrar(): string {
		if (preg_match('/^registrar:\s*(.+)$/mi', $this->raw, $m)) {
			return trim($m[1]);
		}
		return '';
	}

	/**
	 * Ask one WHOIS server. Verisign-style registries want "domain example.com".
	 */
	private function ask(string $server, string $domain): string {
		$errno = 0; $errstr = '';
		$fp = @fsockopen($server, 43, $errno, $errstr, $this->timeout);
		if ($fp === false) {
			throw new RuntimeException("WHOIS connect to $server failed: $errstr ($errno)");
		}
		stream_set_timeout($fp, $this->timeout);

		$query = (stripos($server, 'verisign') !== false) ? "domain $domain" : $domain;
		fwrite($fp, $query . "\r\n");

		$out = '';
		while (!feof($fp)) {
			$chunk = fread($fp, 8192);
			if ($chunk === false) {
				break;
			}
			$out .= $chunk;
		}
		fclose($fp);
		return $out;
	}

	/** Find the registrar WHOIS server hinted inside a registry response. */
	private function findReferral(string $text): ?string {
		foreach (array('/^whois server:\s*(\S+)/mi', '/^referralserver:\s*whois:\/\/(\S+)/mi') as $pattern) {
			if (preg_match($pattern, $text, $m)) {
				return rtrim(trim($m[1]), '/');
			}
		}
		return null;
	}

	/** Locate an expiry-date line; returns the date string or null. */
	private function findExpiry(string $text): ?string {
		$labels = array(
			'registry expiry date', 'registrar registration expiration date',
			'expiry date', 'expiration date', 'expires on', 'expire date',
			'domain expiration date', 'validity', 'paid-till',
		);
		foreach (explode("\n", $text) as $line) {
			$line = trim($line);
			if ($line === '' || $line[0] === '#' || $line[0] === '%') {
				continue;
			}
			foreach ($labels as $label) {
				if (stripos($line, $label . ':') === 0) {
					$date = trim(substr($line, strlen($label) + 1));
					if ($date !== '' && $date !== 'not defined') {
						return $date;
					}
				}
			}
		}
		return null;
	}

	/** Ask IANA which WHOIS server owns a TLD we don't have mapped. */
	private function ianaServer(string $tld): string {
		try {
			$resp = $this->ask('whois.iana.org', $tld);
			if (preg_match('/^whois:\s*(\S+)/mi', $resp, $m)) {
				return trim($m[1]);
			}
		} catch (RuntimeException $e) {
			// fall through to generic fallback below
		}
		return 'whois.iana.org';
	}
}
