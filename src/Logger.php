<?php
/**
 * Logger — PSR-3-style file logger with levels, daily rotation and {context} interpolation.
 *
 * One file per day per channel: <dir>/<channel>-YYYY-MM-DD.log
 *
 *     $log = new Logger('app', '/var/log/myapp');
 *     $log->info('User {user} logged in', array('user' => 'azan'));
 *     $log->error('Payment failed for order {id}', array('id' => 123));
 *
 * Append-only writes use flock(), so it is safe for parallel PHP-FPM workers.
 * No dependencies, PHP 7.4+.
 */
class Logger {

	const DEBUG     = 100;
	const INFO      = 200;
	const NOTICE    = 250;
	const WARNING   = 300;
	const ERROR     = 400;
	const CRITICAL  = 500;
	const ALERT     = 550;
	const EMERGENCY = 600;

	/** @var array int => name */
	private static $names = array(
		self::DEBUG     => 'DEBUG',
		self::INFO      => 'INFO',
		self::NOTICE    => 'NOTICE',
		self::WARNING   => 'WARNING',
		self::ERROR     => 'ERROR',
		self::CRITICAL  => 'CRITICAL',
		self::ALERT     => 'ALERT',
		self::EMERGENCY => 'EMERGENCY',
	);

	private $channel;
	private $dir;
	private $minLevel;

	/**
	 * @param string $channel  Log channel, becomes part of the filename.
	 * @param string $dir      Writable directory for log files (created if missing).
	 * @param int    $minLevel Minimum numeric level to record (default: everything).
	 */
	public function __construct($channel, $dir, $minLevel = self::DEBUG) {
		$this->channel  = preg_replace('/[^a-zA-Z0-9_-]/', '', $channel);
		$this->dir      = rtrim($dir, '/');
		$this->minLevel = (int) $minLevel;
		if (!is_dir($this->dir)) {
			mkdir($this->dir, 0755, true);
		}
	}

	public function setMinLevel($level) {
		$this->minLevel = (int) $level;
	}

	/** @param int|string $level Numeric level or lowercase name ('error'). */
	public function log($level, $message, array $context = array()) {
		$num = is_int($level) ? $level : $this->nameToLevel((string) $level);
		if ($num < $this->minLevel) {
			return;
		}
		$line = sprintf(
			"[%s] %s: %s\n",
			date('Y-m-d H:i:s'),
			isset(self::$names[$num]) ? self::$names[$num] : 'UNKNOWN',
			$this->interpolate($message, $context)
		);
		file_put_contents($this->path(), $line, FILE_APPEND | LOCK_EX);
	}

	private function interpolate($message, array $context) {
		foreach ($context as $key => $value) {
			if (is_array($value) || is_object($value)) {
				$value = json_encode($value);
			}
			$message = str_replace('{' . $key . '}', (string) $value, $message);
		}
		return $message;
	}

	private function path() {
		return $this->dir . '/' . $this->channel . '-' . date('Y-m-d') . '.log';
	}

	private function nameToLevel($name) {
		$map = array_change_key_case(array_flip(self::$names), CASE_LOWER);
		$name = strtolower($name);
		return isset($map[$name]) ? $map[$name] : self::DEBUG;
	}

	/* Shorthands */
	public function debug($m, array $c = array())     { $this->log(self::DEBUG, $m, $c); }
	public function info($m, array $c = array())      { $this->log(self::INFO, $m, $c); }
	public function notice($m, array $c = array())     { $this->log(self::NOTICE, $m, $c); }
	public function warning($m, array $c = array())   { $this->log(self::WARNING, $m, $c); }
	public function error($m, array $c = array())      { $this->log(self::ERROR, $m, $c); }
	public function critical($m, array $c = array())   { $this->log(self::CRITICAL, $m, $c); }
	public function alert($m, array $c = array())      { $this->log(self::ALERT, $m, $c); }
	public function emergency($m, array $c = array())  { $this->log(self::EMERGENCY, $m, $c); }
}
