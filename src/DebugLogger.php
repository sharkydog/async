<?php
namespace SharkyDog\Async;

abstract class DebugLogger {
	protected static $levels = [
		1 => 'error',
		2 => 'warn',
		3 => 'info'
	];
	protected static $lvlpad;
	protected static $logger;
	
	public static function init(callable $logger) {
		static::$logger = $logger;
	}
	
	public static function log(int $level, $data) {
		if(!static::$logger) static::$logger = static::class.'::logger_stdout';
		if(!static::$lvlpad) static::$lvlpad = max(array_map('strlen',static::$levels));
		call_user_func(static::$logger, $data, static::$levels[$level]??$level);
	}
	
	protected static function logger_stdout($data, $slevel) {
		echo "[".date('d.m H:i:s').": ".str_pad($slevel,static::$lvlpad)."]: ";
		echo print_r($data,true)."\n";
	}
}