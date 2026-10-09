<?php
require __DIR__ . '/../src/SecurityHeaders.php';

$headers = SecurityHeaders::defaultSecure()
	->hsts()                       // Strict-Transport-Security: max-age=1y, includeSubDomains
	->header( 'X-Powered-By', '' ); // strip leaky header

// Grab the nonce BEFORE sending headers, so inline scripts can use it.
$nonce = $headers->nonce();

// Send (no-op on CLI / after output started).
$sent = $headers->apply();
echo $sent ? "Headers sent.\n" : "Not sent (CLI or headers already sent).\n";

// Inspect what would be sent (great for tests / frameworks):
foreach ( $headers->toArray() as $name => $value ) {
	echo $name, ': ', substr( $value, 0, 90 ), ( strlen( $value ) > 90 ? '...' : '' ), "\n";
}
?>
<!doctype html>
<html>
<head><title>SecurityHeaders demo</title></head>
<body>
	<script nonce="<?php echo htmlspecialchars( $nonce, ENT_QUOTES ); ?>">
		console.log('inline script allowed by nonce');
	</script>
</body>
</html>
