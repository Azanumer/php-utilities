<?php
/**
 * FileCache — a tiny file-based cache with TTL.
 *
 * Use it when you don't have Redis/Memcached but want to avoid
 * repeating expensive calls (API responses, heavy queries).
 *
 *     $cache = new FileCache('/tmp/myapp-cache');
 *     $data = $cache->remember('exchange_rates', 3600, function () {
 *         return fetch_rates_from_api(); // only runs on cache miss
 *     });
 */
class FileCache
{
    private string $dir;

    public function __construct(string $dir) {
        $this->dir = rtrim($dir, '/');
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0755, true);
        }
    }

    private function path(string $key): string {
        return $this->dir . '/' . sha1($key) . '.cache';
    }

    public function set(string $key, $value, int $ttl = 3600): bool {
        $payload = json_encode([
            'expires' => time() + $ttl,
            'data'    => $value,
        ]);
        return file_put_contents($this->path($key), $payload, LOCK_EX) !== false;
    }

    public function get(string $key, $default = null) {
        $file = $this->path($key);
        if (!is_file($file)) return $default;
        $payload = json_decode(@file_get_contents($file), true);
        if (!is_array($payload) || $payload['expires'] < time()) {
            @unlink($file);
            return $default;
        }
        return $payload['data'];
    }

    public function delete(string $key): bool {
        $file = $this->path($key);
        return !is_file($file) || unlink($file);
    }

    public function remember(string $key, int $ttl, callable $callback) {
        $value = $this->get($key);
        if ($value !== null) return $value;
        $value = $callback();
        $this->set($key, $value, $ttl);
        return $value;
    }

    /** Remove all expired entries. Run from cron daily. */
    public function prune(): int {
        $removed = 0;
        foreach (glob($this->dir . '/*.cache') ?: [] as $file) {
            $payload = json_decode(@file_get_contents($file), true);
            if (!is_array($payload) || $payload['expires'] < time()) {
                @unlink($file);
                $removed++;
            }
        }
        return $removed;
    }
}
