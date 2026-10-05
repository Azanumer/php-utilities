<?php
/**
 * TokenBucketRateLimiter usage example.
 *
 * Run: php examples/rate-limiter-example.php
 */

require __DIR__ . '/../src/TokenBucketRateLimiter.php';

$dir = sys_get_temp_dir() . '/rate-limiter-demo';
@mkdir($dir, 0755, true);

// 5 requests per 10 seconds for this "client".
$limiter = new TokenBucketRateLimiter($dir, 'demo-client', 5, 10);

echo "Firing 8 requests (bucket holds 5):\n";
for ($i = 1; $i <= 8; $i++) {
    if ($limiter->allow()) {
        echo "  request $i: ALLOWED\n";
    } else {
        echo "  request $i: DENIED — retry in {$limiter->getRetryAfter()}s\n";
    }
}

// Burst action costing 2 tokens at once (e.g. a bulk endpoint).
$limiter->reset();
echo "\nAfter reset, one bulk request costing 2 tokens: ";
echo $limiter->allow(2) ? "ALLOWED\n" : "DENIED\n";

// Cleanup.
array_map('unlink', glob($dir . '/*.json'));
@rmdir($dir);
