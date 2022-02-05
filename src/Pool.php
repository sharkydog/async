<?php
namespace SharkyDog\Async;

class Pool {
	private $wClass;
	private $wArgv;
	private $pSizeMax = 1;
	private $pSizeMin = 0;
	private $onRun = [];
	private $onDone = [];
	private $workers = [];
	
	public function __construct(string $wClass, $pSizeMax=1, ...$wArgv) {
		if(!is_subclass_of($wClass,Worker::class)) {
			throw new \Exception('Pool: class '.$wClass.' is not '.Worker::class);
		}
		
		if($pSizeMax <= 0) $pSizeMax = 1;
		
		$this->wClass = $wClass;
		$this->wArgv = $wArgv;
		$this->pSizeMax = $pSizeMax;
	}
	
	public function __destruct() {
		Debug::log(3, 'destruct: '.static::class);
	}
	
	public function close() {
		$this->setSize(1);
		$this->pSizeMax = 0;
	}
	
	public function setSize(int $pSizeMax, int $pSizeMin=0) {
		if($pSizeMin < 0) $pSizeMin = 0;
		if($pSizeMax <= 0) $pSizeMax = 1;
		if($pSizeMax < $pSizeMin) $pSizeMax = $pSizeMin;
		
		$this->pSizeMax = $pSizeMax;
		$this->pSizeMin = $pSizeMin;
		
		$countWorkers = count($this->workers);
		
		if($pSizeMin > $countWorkers) {
			$countWorkers = $pSizeMin - $countWorkers;
			while($countWorkers--) $this->worker();
			return;
		}
		
		$freeWorkers = array_filter(array_keys($this->workers), function($idxW){
			return !$this->workers[$idxW]['results']->countPending();
		});
		
		if(!empty($freeWorkers)) {
			$countWorkersRemove = max($countWorkers-$pSizeMin, $pSizeMax-$countWorkers);
			while(!empty($freeWorkers) && $countWorkersRemove>0 && $countWorkersRemove--) {
				$this->_remove(array_shift($freeWorkers));
			}
		}
	}
	
	public function onRun(callable $onRun) {
		$this->onRun[] = $onRun;
	}
	
	public function onDone(callable $onDone) {
		$this->onDone[] = $onDone;
	}
	
	public function worker(&$idxW=null) {
		if(($countWorkers=count($this->workers)) > 0) {
			$workersResultsCount = array_map(function($arrWorker){
				return $arrWorker['results']->countPending();
			}, $this->workers);
			
			asort($workersResultsCount);
			$idxW = array_key_first($workersResultsCount);
			
			if($countWorkers >= $this->pSizeMin) {
				if(!$workersResultsCount[$idxW] || $countWorkers==$this->pSizeMax) {
					return $this->workers[$idxW]['worker'];
				}
			}
		}
		
		if(!$this->pSizeMax) throw new \Exception(static::class.' is closed');
		
		$this->workers[] = [
			'worker' => new $this->wClass(...$this->wArgv),
			'results' => new Results,
			'keep' => false
		];
		$idxW = array_key_last($this->workers);
		Debug::log(3, 'new worker: ('.$idxW.') '.$this->wClass);
		
		$this->workers[$idxW]['worker']->onRun(function($result) use($idxW) {
			$this->_onRun($idxW,$result);
		});
		$this->workers[$idxW]['results']->onDone(function() use($idxW) {
			$this->_onDone($idxW);
		});
		
		return $this->workers[$idxW]['worker'];
	}
	
	public function done() {
		$done = true;
		foreach(array_keys($this->workers) as $idxW) {
			if(!$this->workers[$idxW]['results']->done()) $done = false;
			$this->_remove($idxW);
		}
		return $done;
	}
	
	private function _remove($idxW) {
		if(!isset($this->workers[$idxW])) return;
		if($this->workers[$idxW]['keep']) return;
		$this->remove($idxW);
	}
	
	public function remove($idxW) {
		if(!isset($this->workers[$idxW])) return;
		if($this->workers[$idxW]['results']->countPending()) return;
		
		$this->workers[$idxW]['keep'] = false;
		if(count($this->workers) <= $this->pSizeMin) return;
		
		if($this->workers[$idxW]['results']->countDone()) {
			$this->workers[$idxW]['results']->clearDone();
		}
		
		Debug::log(3, 'remove worker: ('.$idxW.') '.$this->wClass);
		unset($this->workers[$idxW]);
	}
	
	private function _onRun($idxW,$result) {
		if(!empty($this->onRun)) {
			foreach($this->onRun as $onRun) {
				$onRun($result,$this->workers[$idxW]['worker'],$idxW,$this);
			}
		}
		
		$this->workers[$idxW]['results']->add($result);
		$this->workers[$idxW]['keep'] = false;
	}
	
	private function _onDone($idxW) {
		$results = $this->workers[$idxW]['results']->pull();
		
		if(!empty($this->onDone)) {
			foreach($this->onDone as $onDone) {
				$r = $onDone($results,$this->workers[$idxW]['worker'],$idxW,$this);
				if($r) $this->workers[$idxW]['keep'] = true;
			}
		}
		
		$this->_remove($idxW);
	}
}