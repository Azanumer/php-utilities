<?php
/**
 * TokenBucketRateLimiter — simple file-based token-bucket rate limiter.
 *
 * Throttle APIs, login attempts, or form submissions without Redis:
 * tokens refill over time, each action costs tokens, and a request is
 * allowed only while the bucket has enough. Uses flock() so it is safe
 * under concurrent PHP-FPM processes.
 *
 * Example: 100 requests per hour per API key
 *   $limiter = new TokenBucketRateLimiter(sys_get_temp_dir() . '/rl', $apiKey, 100, 3600);
 *   if (!$limiter->allow()) {
 *       http_response_code(429);
 *       header('Retry-After: ' . $limiter->getRetryAfter());
 *       exit('Too many requests');
 *   }
 */

class TokenBucketRateLimiter
{
    private string $storageDir;
    private string $identifier;
    private float $capacity;
    private float $refillPerSecond;

    /**
     * @param string $storageDir  Writable directory for bucket state files.
     * @param string $identifier  Who is being limited (API key, IP, user id…).
     * @param float  $capacity    Max tokens in the bucket (= max burst).
     * @param int    $refillSeconds Seconds to fully refill an empty bucket.
     */
    public function __construct(string $storageDir, string $identifier, float $capacity, int $refillSeconds)
    {
        if ($capacity <= 0) {
            throw new InvalidArgumentException('capacity must be > 0');
        }
        if ($refillSeconds <= 0) {
            throw new InvalidArgumentException('refillSeconds must be > 0');
        }
        if (!is_dir($storageDir) && !mkdir($storageDir, 0755, true) && !is_dir($storageDir)) {
            throw new RuntimeException("cannot create storage dir: $storageDir");
        }

        $this->storageDir     = rtrim($storageDir, '/');
        // Hash the identifier: state filenames stay filesystem-safe and don't leak keys/IPs.
        $this->identifier     = hash('sha256', $identifier);
        $this->capacity       = $capacity;
        $this->refillPerSecond = $capacity / $refillSeconds;
    }

    private function stateFile(): string
    {
        return $this->storageDir . '/' . $this->identifier . '.json';
    }

    /**
     * Try to consume $cost tokens. Returns true when the action is allowed.
     * The bucket starts full, so a fresh identifier gets the full burst.
     */
    public function allow(float $cost = 1.0): bool
    {
        if ($cost <= 0) {
            throw new InvalidArgumentException('cost must be > 0');
        }
        if ($cost > $this->capacity) {
            return false; // can never be afforded — deny rather than wait forever
        }

        $file = $this->stateFile();
        $fp   = fopen($file, 'c+');
        if ($fp === false) {
            throw new RuntimeException("cannot open state file: $file");
        }

        try {
            if (!flock($fp, LOCK_EX)) {
                throw new RuntimeException('cannot lock state file');
            }

            $now   = microtime(true);
            $state = $this->readState($fp, $now);

            // Refill based on elapsed time, capped at bucket capacity.
            $state['tokens'] = min(
                $this->capacity,
                $state['tokens'] + ($now - $state['updated']) * $this->refillPerSecond
            );
            $state['updated'] = $now;

            if ($state['tokens'] < $cost) {
                $this->writeState($fp, $state); // persist refill progress even on deny
                return false;
            }

            $state['tokens'] -= $cost;
            $this->writeState($fp, $state);
            return true;
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    /**
     * Seconds until $cost tokens are available (0 when allowed right now).
     * Useful for the Retry-After header on a 429 response.
     */
    public function getRetryAfter(float $cost = 1.0): int
    {
        $file = $this->stateFile();
        if (!is_file($file)) {
            return 0; // fresh bucket — full
        }
        $state = json_decode(@file_get_contents($file), true);
        if (!is_array($state) || !isset($state['tokens'], $state['updated'])) {
            return 0;
        }
        $now    = microtime(true);
        $tokens = min(
            $this->capacity,
            (float) $state['tokens'] + ($now - (float) $state['updated']) * $this->refillPerSecond
        );
        if ($tokens >= $cost) {
            return 0;
        }
        return (int) ceil(($cost - $tokens) / $this->refillPerSecond);
    }

    /**
     * Reset the bucket to full (e.g. after a successful payment or captcha).
     */
    public function reset(): void
    {
        @unlink($this->stateFile());
    }

    private function readState($fp, float $now): array
    {
        rewind($fp);
        $raw   = stream_get_contents($fp);
        $state = $raw ? json_decode($raw, true) : null;
        if (!is_array($state) || !isset($state['tokens'], $state['updated'])) {
            return ['tokens' => $this->capacity, 'updated' => $now]; // fresh bucket: full
        }
        return ['tokens' => (float) $state['tokens'], 'updated' => (float) $state['updated']];
    }

    private function writeState($fp, array $state): void
    {
        rewind($fp);
        ftruncate($fp, 0);
        fwrite($fp, json_encode(['tokens' => $state['tokens'], 'updated' => $state['updated']]));
        fflush($fp);
    }
}
