<?php
namespace SharkyDog\Async;

abstract class Debug {
	protected static $level = 0;
	
	public static function init(int $level) {
		static::$level = $level;
	}
	
	public static function log(int $level, $data) {
		if($level > static::$level) return;
		DebugLogger::log($level, $data);
	}
}