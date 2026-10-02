# PHP Utilities

Small, dependency-free PHP helpers you can drop into any project. No framework, no Composer required.

## Contents

| File | What it does |
|---|---|
| `src/helpers.php` | `slugify()`, `time_ago()`, `format_bytes()`, `esc()` |
| `src/FileCache.php` | Tiny file-based cache class (`get`/`set`/`delete`) — no Redis needed |
| `examples/example.php` | Working usage examples for everything above |

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
