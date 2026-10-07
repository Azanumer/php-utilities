<?php
// Dotenv demo: loads .env.example-style values, shows defaults + env-wins behaviour.
require __DIR__ . '/../src/Dotenv.php';

// Pretend the real environment already defines DB_HOST (e.g. from the web server).
putenv('DB_HOST=from-real-env');

$tmp = sys_get_temp_dir() . '/dotenv-demo.env';
file_put_contents($tmp, <<<'ENV'
# Demo .env
export DB_HOST=from-dotenv-file
DB_NAME="my app # db"
DB_USER='root'
DB_PASS=secret123  # inline comment stripped
EMPTY=
ENV
);

$loaded = Dotenv::load($tmp);
echo "Loaded: $loaded vars\n";
echo 'DB_HOST: ' . Dotenv::get('DB_HOST') . " (real env wins)\n";
echo 'DB_NAME: ' . Dotenv::get('DB_NAME') . " (# kept inside quotes)\n";
echo 'DB_USER: ' . Dotenv::get('DB_USER') . "\n";
echo 'DB_PASS: ' . Dotenv::get('DB_PASS') . "\n";
echo 'MISSING: ' . var_export(Dotenv::get('NOPE', 'fallback'), true) . " (default)\n";

unlink($tmp);
