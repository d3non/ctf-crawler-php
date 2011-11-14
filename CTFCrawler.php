<?php

require_once('TargetStore.php');

abstract class CTFCrawler {

	/* MultiThreadingMode: in wich mode will we "share" the targets */
	const MTM_SPLIT = 0;
	const MTM_SHM = 1;

	protected $store;
	protected $threads=array();
	protected $thread_limit=0;
	protected $cycle_time;
	protected $name="";
	protected $MTMmode;

	public function __construct($targets,$threads=1,$cycle_time=5) {
		if(function_exists('shm_attach'))
			$this->MTMmode = self::MTM_SHM;
		else	$this->MTMmode = self::MTM_SPLIT;
		
		#TODO: remove this ;)
		$this->MTMmode = self::MTM_SPLIT;
		
		switch($this->MTMmode) {
			case self::MTM_SHM:
				$this->store = new TimedTargetStore($targets,$cycle_time,FALSE);
				break;
			case self::MTM_SPLIT:
				$this->store = $targets;
				shuffle($this->store);
				$this->cycle_time = $cycle_time;
				break;
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
		foreach($this->threads as $t) {
			echo "killing PID:$t\n";
			posix_kill($t,SIGTERM);
		}
	}
	
	public function start() {
		switch($this->MTMmode) {
			case self::MTM_SHM:
				$this->store->_start();
				break;
			case self::MTM_SPLIT:
				$targets_cnt = count($this->store);
				$targets_per_thread = ceil($targets_cnt / $this->thread_limit);
				break;
		}
		while(($threads_cnt=count($this->threads)) < $this->thread_limit) {
			if($this->MTMmode==self::MTM_SPLIT)
				$targets=array_slice($this->store,$threads_cnt * $targets_per_thread, $targets_per_thread);
			switch($pid=pcntl_fork()) {
				case -1:
					die("cant fork!");
					break;
				case 0:
					echo "run..\n";
					$this->name = __CLASS__ . "-Thread#".($threads_cnt+1);
					if($this->MTMmode==self::MTM_SPLIT) {
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
}

#class CTFTelnetCrawler extends CTFCrawler {
#}

#class CTFHTTPCrawler extends CTFCrawler {
#}


