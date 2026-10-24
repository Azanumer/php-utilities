<?php
/**
 * Retry helper demo: flaky API call retried with exponential backoff.
 *
 * Usage: php examples/retry-example.php
 */

require __DIR__ . '/../src/Retry.php';

// 1) Backoff schedule preview (no sleeping).
echo "Backoff schedule (base 250ms, factor 2):\n";
for ($attempt = 1; $attempt <= 4; $attempt++) {
    printf("  after attempt %d -> %d ms\n", $attempt, Retry::delayFor($attempt, 250, 10000, 2.0, false));
}

// 2) Flaky operation that fails twice, then succeeds — with a fake sleeper
//    so the demo runs instantly while recording the delays.
$failures = 0;
$sleeps = array();

$result = Retry::run(
    function () use (&$failures) {
        $failures++;
        if ($failures < 3) {
            throw new RuntimeException('transient 502 from payment API');
        }
        return array('status' => 'charged', 'id' => 'ch_123');
    },
    array(
        'attempts' => 5,
        'base_ms'  => 200,
        'jitter'   => false,
        'sleep_fn' => function ($microseconds) use (&$sleeps) {
            $sleeps[] = $microseconds / 1000;
        },
        'on_retry' => function ($attempt, $delayMs, $e) {
            printf("  retry %d after %d ms (%s)\n", $attempt, $delayMs, $e->getMessage());
        },
    )
);

echo 'Result: ' . json_encode($result) . "\n";
echo 'Sleeps (ms): ' . implode(', ', $sleeps) . "\n";

// 3) retry_on filter: only retry RuntimeException, fail fast on others.
try {
    Retry::run(
        function () { throw new InvalidArgumentException('bad input — do not retry'); },
        array('retry_on' => array('RuntimeException'), 'sleep_fn' => function () {})
    );
} catch (InvalidArgumentException $e) {
    echo "Correctly failed fast: " . $e->getMessage() . "\n";
}
