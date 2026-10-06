<?php
// Logger example — run: php examples/logger-example.php
require __DIR__ . '/../src/Logger.php';

$log = new Logger('app', sys_get_temp_dir() . '/myapp-logs');

// Only errors and above in production:
if (getenv('APP_ENV') === 'production') {
	$log->setMinLevel(Logger::ERROR);
}

$log->debug('Cache miss for {key}', array('key' => 'rates/usd'));
$log->info('User {user} logged in from {ip}', array('user' => 'azan', 'ip' => '127.0.0.1'));
$log->warning('Disk usage at {pct}%', array('pct' => 87));
$log->error('Payment failed for order {id}: {reason}', array('id' => 123, 'reason' => 'card declined'));

// String levels also work:
$log->log('critical', 'Database unreachable');

echo "Done — check " . sys_get_temp_dir() . "/myapp-logs/\n";
