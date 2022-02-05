<?php
namespace SharkyDog\Async;

class Results {
	private $onDone = [];
	private $rsPending = [];
	private $rsDone = [];
	private $clearOnDone = false;
	
	public function __construct(array $results=[], bool $clearOnDone=false) {
		foreach($results as $id => $result) $this->add($result,$id);
		$this->clearOnDone = $clearOnDone;
	}
	
	public function __destruct() {
		Debug::log(3, 'destruct: '.static::class);
	}
	
	public function clearOnDone(bool $clearOnDone=true) {
		$this->clearOnDone = $clearOnDone;
	}
	
	public function onDone(callable $onDone) {
		$this->onDone[] = $onDone;
	}
	
	private function _onDone($id) {
		if(!isset($this->rsPending[$id])) return;
		
		$this->rsDone[$id] = $this->rsPending[$id];
		unset($this->rsPending[$id]);
		
		if(empty($this->rsPending)) {
			foreach($this->onDone as $onDone) $onDone($this->rsDone,$this);
			if($this->clearOnDone) $this->clearDone();
		}
	}
	
	public function add(Result $result, $id=null) {
		if(is_null($id)) {
			$this->rsPending[] = $result;
			$id = array_key_last($this->rsPending);
		} else {
			$this->rsPending[$id] = $result;
		}
		
		$result->onDone(function() use($id) {
			$this->_onDone($id);
		});
		
		return $id;
	}
	
	public function clearDone() {
		foreach(array_keys($this->rsDone) as $id) unset($this->rsDone[$id]);
	}
	
	public function countPending() {
		return count($this->rsPending);
	}
	
	public function countDone() {
		return count($this->rsDone);
	}
	
	public function countAll() {
		return $this->countPending() + $this->countDone();
	}
	
	public function done() {
		if(empty($this->rsPending)) return true;
		foreach($this->rsPending as $result) $result->done();
		return empty($this->rsPending);
	}
	
	public function get(int $count=null, int $start=0) {
		$this->done();
		return array_slice($this->rsDone, $start, $count, true);
	}
	
	public function getId($id) {
		$this->done();
		return $this->rsDone[$id]??null;
	}
	
	public function pull(int $count=null) {
		$rsDone = $this->get($count);
		foreach(array_keys($rsDone) as $id) unset($this->rsDone[$id]);
		return $rsDone;
	}
	
	public function pullId($id) {
		$rs = $this->getId($id);
		if($rs) unset($this->rsDone[$id]);
		return $rs;
	}
	
	public function pullOne(&$id=null) {
		$pull = $this->pull(1);
		return is_null($id=key($pull)) ? null : $pull[$id];
	}
}