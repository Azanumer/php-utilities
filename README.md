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
| `src/CsrfGuard.php` | Session-based CSRF protection — single-use rotating tokens, `field()`/`validate()`, no dependencies |
| `src/SecurityHeaders.php` | Security header builder — HSTS, CSP with nonces, Permissions-Policy/COOP/COEP, report-only mode, `apply()`/`toArray()` |
| `src/WhoisClient.php` | Dependency-free WHOIS client (port 43) — referral-following, `getExpiry()`, `daysUntilExpiry()`, `getRegistrar()` |
| `src/UploadedFile.php` | Secure upload handler — finfo MIME sniffing vs extension whitelist, size limit, random filenames, path confinement |
| `src/Encryption.php` | Authenticated encryption (XChaCha20-Poly1305/secretbox) — HKDF key derivation, `kid` key rotation, tamper-proof packed format |
| `src/IpAddress.php` | Client-IP detection behind trusted proxies (X-Forwarded-For right-to-left walk, CF-Connecting-IP), CIDR/range checks IPv4+IPv6, `isPrivate()`, Cloudflare ranges helper |
| `src/SessionGuard.php` | Session hardening — strict cookie params (Secure/HttpOnly/SameSite), idle + absolute timeouts, IP/UA binding, probabilistic ID rotation |
| `src/PasswordPolicy.php` | Entropy-based password strength — pool entropy minus repeat/sequence penalties, common-password blocklist, `analyze()`/`passes()`/`suggest()` |
| `src/HtmlSanitizer.php` | Allow-list HTML sanitizer — drops script/iframe, strips event handlers + `javascript:` URLs, unwraps unknown tags, `allowTags()`/`allowAttributes()` policies |
| `examples/example.php` | Working usage examples for everything above |
| `examples/smtp-mailer-example.php` | SMTP send example (fill in your own credentials) |
| `examples/rate-limiter-example.php` | Token-bucket limiter demo (5 req/10s burst, 429 + Retry-After) |
| `examples/logger-example.php` | Logger demo — levels, min-level filtering, `{context}` placeholders |
| `examples/dotenv-example.php` | Dotenv demo — quotes, inline comments, defaults, real-env-wins |
| `examples/csrf-guard-example.php` | CSRF demo — protected form, single-use token, double-submit rejected |
| `examples/security-headers-example.php` | SecurityHeaders demo — hardened defaults, HSTS, CSP nonce in an inline script, `toArray()` inspection |
| `examples/whois-example.php` | WHOIS demo — registrar, expiry date, days-until-expiry for domains on the command line |
| `examples/upload-example.php` | Upload demo — browser form + CLI mode that validates a local file without HTTP |
| `examples/encryption-example.php` | Encryption demo — basic round-trip, tamper rejection, context binding, key rotation |
| `examples/ip-address-example.php` | IpAddress demo — CIDR checks, simulated proxy headers, Cloudflare mode |
| `examples/session-guard-example.php` | SessionGuard flow — start, validate, touch, rotate after login (browser demo) |
| `examples/password-policy-example.php` | PasswordPolicy demo — weak vs strong passwords scored, quick `passes()` check, `suggest()` generator |
| `examples/html-sanitizer-example.php` | HtmlSanitizer demo — hostile input cleaned under default and strict policies |
| `src/ArrayDot.php` | Dot-notation access for nested arrays — `get`/`set`/`has`/`forget`, `pluck`, `flatten`, `only`/`except` |
| `examples/array-dot-example.php` | ArrayDot demo — nested reads with defaults, safe removal, column pluck, flatten |
| `src/Uuid.php` | RFC 4122 UUIDs — `v4()` random, `v7()` time-ordered (DB-index friendly), `isValid()`, `version()`, `toBytes()`/`fromBytes()` for `BINARY(16)` columns |
| `examples/uuid-example.php` | Uuid demo — v4/v7 generation, time-ordering check, binary round-trip, invalid input |
| `src/HttpClient.php` | Dependency-free cURL HTTP client — `get`/`post`/`postJson`/`put`/`delete`/`bearer`, JSON body decoding, redirect + SSL options, strict `throw` mode |
| `examples/http-client-example.php` | HttpClient demo — GET with query, JSON POST, bearer auth, strict-mode exception on 404 |
| `src/Retry.php` | Exponential-backoff retry helper — `Retry::run()`, jitter, `retry_on` filter, `on_retry` hook, injectable sleeper |
| `examples/retry-example.php` | Retry demo — backoff schedule, flaky-call recovery, fail-fast on non-retryable errors |
| `src/Money.php` | Immutable money value object — integer minor units (no float errors), fromMajor/fromMinor, add/subtract/multiply, zero-decimal currencies, same-currency guard |
| `examples/money-example.php` | Money demo — exact 19.99 + 0.01 = 20.00, PKR multiply/subtract, JPY zero-decimal, cross-currency rejection |

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
