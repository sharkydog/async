<?php
namespace SharkyDog\Async;

abstract class Result {
	private $onDone = [];
	private $value;
	
	abstract protected function _done(): bool;
	abstract protected function _value();
	
	public function __destruct() {
		Debug::log(3, 'destruct: '.static::class);
	}
	
	final public function onDone(callable $onDone) {
		$this->onDone[] = $onDone;
	}
	
	final public function done() {
		$done = $this->_done();
		
		if($done && !empty($this->onDone)) {
			foreach($this->onDone as $onDone) $onDone($this);
		}
		
		return $done;
	}
	
	private function _val() {
		$value = $this->value;
		
		if(!$value) {
			if(($value=$this->_value()) && ($value instanceOf \stdClass)) {
				$this->value = $value;
			} else {
				$value = (object)[];
			}
			
			if(!property_exists($value,'ret')) $value->ret = null;
			if(!property_exists($value,'out')) $value->out = null;
			if(!property_exists($value,'err')) $value->err = null;
		}
		
		return $value;
	}
	
	final public function ret() {
		return $this->_val()->ret;
	}
	final public function out() {
		return $this->_val()->out;
	}
	final public function err() {
		return $this->_val()->err;
	}
}