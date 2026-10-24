<?php
/**
 * Dependency-free exponential-backoff retry helper.
 *
 * Wraps flaky operations (API calls, SMTP sends, DB reconnects) so transient
 * failures retry with growing delays instead of failing the whole request.
 *
 * Usage:
 *   $result = Retry::run(function () {
 *       return $api->charge($order);   // throw on transient failure
 *   }, ['attempts' => 5, 'base_ms' => 200, 'on_retry' => $logger]);
 */

class Retry
{
    /**
     * Run $operation with retries.
     *
     * Options:
     *   attempts   int      total tries including the first (default 3)
     *   base_ms    int      delay before 2nd try in ms (default 250)
     *   max_ms     int      cap for any single delay (default 10000)
     *   factor     float    backoff multiplier (default 2.0)
     *   jitter     bool     add ±25% random jitter to avoid thundering herd (default true)
     *   retry_on   callable|string[]  which Throwables to retry (default: all)
     *   on_retry   callable invoked as on_retry($attempt, $delayMs, $throwable)
     *   sleep_fn   callable custom sleeper for tests, receives microseconds
     *
     * @param callable $operation
     * @param array    $options
     * @return mixed whatever $operation returns
     * @throws \Throwable the last error when attempts are exhausted
     */
    public static function run(callable $operation, array $options = array())
    {
        $attempts = max(1, (int) self::opt($options, 'attempts', 3));
        $baseMs   = max(0, (int) self::opt($options, 'base_ms', 250));
        $maxMs    = max(1, (int) self::opt($options, 'max_ms', 10000));
        $factor   = max(1.0, (float) self::opt($options, 'factor', 2.0));
        $jitter   = (bool) self::opt($options, 'jitter', true);
        $retryOn  = self::opt($options, 'retry_on', null);
        $onRetry  = self::opt($options, 'on_retry', null);
        $sleepFn  = self::opt($options, 'sleep_fn', null);

        $last = null;
        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                return call_user_func($operation);
            } catch (\Throwable $e) {
                $last = $e;
                if ($attempt === $attempts || !self::isRetryable($e, $retryOn)) {
                    throw $e;
                }
                $delayMs = self::delayFor($attempt, $baseMs, $maxMs, $factor, $jitter);
                if (is_callable($onRetry)) {
                    call_user_func($onRetry, $attempt, $delayMs, $e);
                }
                if (is_callable($sleepFn)) {
                    call_user_func($sleepFn, (int) ($delayMs * 1000));
                } else {
                    usleep((int) ($delayMs * 1000));
                }
            }
        }

        // Unreachable in practice (loop always returns or throws), kept for static analysers.
        throw $last;
    }

    /**
     * Delay in ms before the retry that follows attempt $attempt (1-based).
     * Attempt 1 fails -> base_ms; attempt 2 fails -> base_ms * factor; ...
     */
    public static function delayFor($attempt, $baseMs, $maxMs, $factor, $jitter)
    {
        $delay = $baseMs * pow($factor, $attempt - 1);
        $delay = min($delay, $maxMs);
        if ($jitter && $delay > 0) {
            // ±25% jitter.
            $spread = $delay * 0.25;
            $delay = $delay - $spread + (mt_rand() / mt_getrandmax()) * (2 * $spread);
        }
        return (int) round(max(0, $delay));
    }

    private static function opt(array $options, $key, $default)
    {
        return array_key_exists($key, $options) ? $options[$key] : $default;
    }

    private static function isRetryable(\Throwable $e, $retryOn)
    {
        if ($retryOn === null) {
            return true;
        }
        if (is_callable($retryOn)) {
            return (bool) call_user_func($retryOn, $e);
        }
        foreach ((array) $retryOn as $class) {
            if ($e instanceof $class) {
                return true;
            }
        }
        return false;
    }
}
