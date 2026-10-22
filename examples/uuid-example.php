<?php

require __DIR__ . '/../src/Uuid.php';

use Azanumer\Utils\Uuid;

$v4 = Uuid::v4();
echo "v4: {$v4}  valid=" . var_export(Uuid::isValid($v4), true)
    . " version=" . var_export(Uuid::version($v4), true) . PHP_EOL;

$v7a = Uuid::v7();
usleep(2000);
$v7b = Uuid::v7();
echo "v7: {$v7a}" . PHP_EOL;
echo "v7: {$v7b}" . PHP_EOL;
echo 'v7 time-ordered (a < b): ' . var_export($v7a < $v7b, true) . PHP_EOL;

// Round-trip through 16-byte binary (BINARY(16) column storage).
$bin  = Uuid::toBytes($v4);
$back = Uuid::fromBytes($bin);
echo 'binary round-trip ok: ' . var_export($back === strtolower($v4), true) . PHP_EOL;

echo 'garbage valid: ' . var_export(Uuid::isValid('not-a-uuid'), true) . PHP_EOL;
