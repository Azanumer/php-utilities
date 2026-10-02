<?php
require __DIR__ . '/../src/helpers.php';
require __DIR__ . '/../src/FileCache.php';

// --- helpers ---
echo slugify('My Awesome Blog Post!') . PHP_EOL;   // my-awesome-blog-post
echo time_ago(strtotime('-3 hours')) . PHP_EOL;     // 3 hours ago
echo format_bytes(2048576) . PHP_EOL;              // 2 MB
echo esc('<script>alert("xss")</script>') . PHP_EOL;

// --- file cache ---
$cache = new FileCache(sys_get_temp_dir() . '/demo-cache');

$weather = $cache->remember('weather_lahore', 600, function () {
    // expensive call goes here; runs only on cache miss
    return ['temp' => 32, 'condition' => 'sunny'];
});
print_r($weather);

echo "Pruned: " . $cache->prune() . " expired entries" . PHP_EOL;
