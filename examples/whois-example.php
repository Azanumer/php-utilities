<?php
/**
 * WhoisClient demo — prints registrar, expiry date and days remaining
 * for each domain passed on the command line.
 *
 * Usage: php whois-example.php example.com example.net
 *
 * Needs outbound TCP/43. Some registrars rate-limit; don't loop aggressively.
 */

require __DIR__ . '/../src/WhoisClient.php';

$domains = array_slice($argv, 1);
if (empty($domains)) {
	fwrite(STDERR, "Usage: php whois-example.php example.com [example.net ...]\n");
	exit(2);
}

$whois = new WhoisClient(10);

foreach ($domains as $domain) {
	try {
		$expiry = $whois->getExpiry($domain);
		$days   = $whois->daysUntilExpiry($domain);
		printf(
			"%-24s registrar: %-28s expires: %s (%s)\n",
			$domain,
			$whois->getRegistrar() ?: 'unknown',
			$expiry ? $expiry->format('Y-m-d') : 'unknown',
			$days === null ? 'unknown' : ($days < 0 ? "EXPIRED {$days}d ago" : "$days days left")
		);
	} catch (Exception $e) {
		printf("%-24s error: %s\n", $domain, $e->getMessage());
	}
}
