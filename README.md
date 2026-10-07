# PHP Utilities

Small, dependency-free PHP helpers you can drop into any project. No framework, no Composer required.

## Contents

| File | What it does |
|---|---|
| `src/helpers.php` | `slugify()`, `time_ago()`, `format_bytes()`, `esc()` |
| `src/FileCache.php` | Tiny file-based cache class (`get`/`set`/`delete`) — no Redis needed |
| `src/SimplePaginator.php` | Framework-free pagination class (`offset()`, `render()`) |
| `src/SmtpMailer.php` | Dependency-free SMTP mailer — STARTTLS (587) + implicit TLS (465), no PHPMailer |
| `src/TokenBucketRateLimiter.php` | File-based token-bucket rate limiter (flock-safe, no Redis) for APIs/logins |
| `src/Logger.php` | PSR-3-style file logger — levels, daily rotation, `{context}` interpolation, flock-safe |
| `src/Dotenv.php` | Tiny `.env` loader (`load()`/`get()`), quoted values, inline comments, never clobbers real env |
| `examples/example.php` | Working usage examples for everything above |
| `examples/smtp-mailer-example.php` | SMTP send example (fill in your own credentials) |
| `examples/rate-limiter-example.php` | Token-bucket limiter demo (5 req/10s burst, 429 + Retry-After) |
| `examples/logger-example.php` | Logger demo — levels, min-level filtering, `{context}` placeholders |
| `examples/dotenv-example.php` | Dotenv demo — quotes, inline comments, defaults, real-env-wins |

## Usage

```php
require 'src/helpers.php';
require 'src/FileCache.php';

echo slugify('Hello World!');        // hello-world
echo time_ago(strtotime('-2 hours')); // 2 hours ago
echo format_bytes(1536);              // 1.5 KB

$cache = new FileCache(sys_get_temp_dir() . '/myapp-cache');
$cache->set('rates', $data, 3600);    // 1 hour TTL
$rates = $cache->get('rates');
```

Requires PHP 7.4+. MIT licensed.
