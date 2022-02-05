<?php
namespace SharkyDog\Async;

abstract class Worker {
	private $onRun = [];
	private $killed = false;
	
	abstract protected function _run(\Closure $task, array $argv=[]): Result;
	abstract protected function _kill(): void;
	
	public function __destruct() {
		Debug::log(3, 'destruct: '.static::class);
	}
	
	final public function onRun(callable $onRun) {
		if($this->killed) throw new \Exception(static::class.' is killed');
		$this->onRun[] = $onRun;
	}
	
	final public function run(\Closure $task, ...$argv): Result {
		if($this->killed) throw new \Exception(static::class.' is killed');
		
		$result = $this->_run((function($task,$argv) {
			set_error_handler(function($errno, $errstr, $errfile, $errline) {
				throw new \ErrorException($errstr, $errno, $errno, $errfile, $errline);
			},E_ALL);
			
			ob_start();
			$ret = (object)['ret'=>null,'out'=>null,'err'=>null];
			
			try {
				$ret->ret = $task(...$argv);
			} catch(\Throwable $e) {
				$ret->err = (object)[
					'code' => $e->getCode(),
					'mesg' => $e->getMessage(),
					'file' => $e->getFile(),
					'line' => $e->getLine()
				];
			}
			
			$ret->out = ob_get_clean();
			restore_error_handler();
			
			return $ret;
		})->bindTo(null), [$task->bindTo(null),$argv]);
		
		if(!empty($this->onRun)) {
			foreach($this->onRun as $onRun) $onRun($result,$this);
		}
		
		return $result;
	}
	
	final public function kill() {
		$this->onRun = [];
		$this->killed = true;
		$this->_kill();
	}
}