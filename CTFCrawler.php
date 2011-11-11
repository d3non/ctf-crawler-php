<?php

require_once('TargetStore.php');

abstract class CTFCrawler {

	protected $store;
	protected $threads=array();
	protected $thread_limit=0;
	protected $cycle_time;
	protected $name="";

	public function __construct($targets,$threads=1,$cycle_time=5) {
		if(function_exists('sem_get'))
			$this->store = new TimedTargetStore($targets,$cycle_time,FALSE);
		else {
			$this->store = $targets;
			shuffle($this->store);
			$this->cycle_time = $cycle_time;
		}
		$this->thread_limit = $threads;
		declare(ticks=10);
	}

	abstract protected function _process($target);
	
	protected function connect_submitter() {
		
	}
	
	protected function submit($socket,$flag) {
	
	}

	protected function run() {
		while($target = $this->store->get())
			$this->_process($target);
	}
	
	protected function stopChilds() {
		foreach($this->threads as $t)
			;
	}
	
	public function start() {
		if(!function_exists('sem_get')) {
			$targets_cnt = count($this->store);
			$targets_per_thread = ceil($targets_cnt / $this->thread_limit);
		}else	$this->store->_start();
		while(($threads_cnt=count($this->threads)) < $this->thread_limit) {
			if(!function_exists('sem_get'))
				$targets=array_slice($this->store,$threads_cnt * $targets_per_thread, $targets_per_thread);
			switch($pid=pcntl_fork()) {
				case -1:
					die("cant fork!");
					break;
				case 0:
					echo "run..\n";
					$this->name = __CLASS__ . "-Thread#".($threads_cnt+1);
					if(!function_exists('sem_get')) {
						$this->store = new TimedTargetStore($targets,$this->cycle_time,FALSE);
						$this->store->_start();
					}
					$this->run();
					break 2;
				default:
					echo "parent..\n";
					$this->threads[]=$pid;
					break;
			}
		}
		while(fgets(STDIN) AND !feof(STDIN));
		$this->stopChilds();
	}
	
	public function isRunning() {
		return count($this->threads) > 0;
	}
}

#class CTFTelnetCrawler extends CTFCrawler {
#}

#class CTFHTTPCrawler extends CTFCrawler {
#}


