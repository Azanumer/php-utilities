<?php
/**
 * ArrayDot demo. Run: php examples/array-dot-example.php
 */
require __DIR__ . '/../src/ArrayDot.php';

$config = array(
    'db' => array(
        'host'  => 'localhost',
        'ports' => array(3306, 3307),
        'user'  => null,
    ),
    'features' => array('cache', 'queue'),
);

// get() — nested reads with defaults, no notices on missing paths
echo ArrayDot::get($config, 'db.host'), "\n";              // localhost
echo ArrayDot::get($config, 'db.ports.1'), "\n";           // 3307
var_dump(ArrayDot::get($config, 'db.user'));               // NULL (key exists)
echo ArrayDot::get($config, 'db.pass', 'secret'), "\n";    // secret (default)

// has() — distinguishes "missing" from "null"
var_dump(ArrayDot::has($config, 'db.user'));               // true
var_dump(ArrayDot::has($config, 'db.pass'));               // false

// set() — builds intermediate arrays automatically
ArrayDot::set($config, 'cache.drivers.redis.ttl', 300);
echo ArrayDot::get($config, 'cache.drivers.redis.ttl'), "\n"; // 300

// forget() — safe removal
ArrayDot::forget($config, 'db.ports');
var_dump(ArrayDot::has($config, 'db.ports'));               // false

// pluck() — one nested column out of many rows
$users = array(
    array('id' => 1, 'profile' => array('email' => 'a@example.com')),
    array('id' => 2, 'profile' => array('email' => 'b@example.com')),
    array('id' => 3), // missing → default
);
print_r(ArrayDot::pluck($users, 'profile.email', 'n/a'));

// flatten() — nested → dot keys (handy for .env export / diffing configs)
print_r(ArrayDot::flatten(array('a' => array('b' => 1, 'c' => array(2, 3)))));

// only() / except() — top-level filtering
print_r(array_keys(ArrayDot::only($config, array('db', 'cache'))));
print_r(array_keys(ArrayDot::except($config, array('db'))));
