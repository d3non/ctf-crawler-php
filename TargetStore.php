<?php

/*

you realy want to use this class with sysvsem and sysvshm enabled!

*/

class TargetStore {

	const NONE = -1;
	const SEMAPHORE = 0;

	protected $semaphore;
	protected $SHM;
	protected $lock_type;
	protected $targets;
	protected $target_len = 0;
	protected $target_ptr = 0;
	
	protected $shared_vars = array('instant_cnt','target_ptr','targets');
	private $instant_cnt = 0;

	public function __construct($target_list,$flock_file="") {
		if(function_exists('sem_get') AND function_exists('shm_attach'))
			$this->lock_type = self::SEMAPHORE;
		else	$this->lock_type = self::NONE;
		$this->targets = array_values($target_list);
		$this->target_len = count($target_list);
		$this->instant_cnt++;
		switch($this->lock_type) {
			case self::SEMAPHORE:
				$semID = hexdec(md5("TargetStore"+time()));
				$this->semaphore = sem_get($semID);
				$this->SHM = shm_attach($semID,1024*500,0660);
				foreach($this->shared_vars as $i => $var) #copy vars from local mem to shared mem
					shm_put_var($this->SHM,$i,$this->$var);
				break;
			default: /* aka self::NONE */
				
		}
	}

	public function _copyed() { #could be called if we (aka process) are copyed (aka forked), and ONLY then!
		if($this->getLock()) {
			$this->instant_cnt++;
			$this->freeLock();
		}
	}

	public function __destruct() {
		if($this->getLock()) {
			$this->instant_cnt--;
			$this->freeLock();
			shm_detach($this->SHM);
			if($this->instant_cnt<=0)
				shm_remove($this->SHM);
		}
	}

	protected function getLock() {
		switch($this->lock_type) {
			case self::SEMAPHORE:
				if(sem_acquire($this->semaphore)) {
					foreach($this->shared_vars as $i => $var) #copy vars from shared mem to local
						$this->$var = shm_get_var($this->SHM,$i);
					return TRUE;
				} else return FALSE;
				break;
			case self::NONE:
				return true;
				break;
		}
	}

	protected function freeLock() {
		switch($this->lock_type) {
			case self::SEMAPHORE:
				if(sem_release($this->semaphore)) {
					foreach($this->shared_vars as $i => $var) #copy vars from local mem to shared mem
						shm_put_var($this->SHM,$i,$this->$var);
					return TRUE;
				} else return FALSE;
				break;
			case self::NONE:
				return true;
				break;
		}
	}

	protected function _cycle() {
		$this->target_ptr=0;
	}

	public function get() {
		if($this->getLock()) {
			if($this->target_ptr>=$this->target_len)
				$this->_cycle();
			$next = $this->targets[$this->target_ptr];
			$this->target_ptr++;
			$this->freeLock();
			return $next;
		}
	}

}

class TimedTargetStore extends TargetStore {

/*
* this class makes shure the list is not called more often than $cycle_time (in seconds)
* it uses microtime und usleep so you can specify fractions of seconds as floats
*/

	protected $cycle_start=0;
	protected $cycle_time=0;

	public function __construct($target_list,$cycle_time,$autostart=FALSE,$flock_file="") {
		parent::__construct($target_list,$flock_file);
		$this->cycle_time=$cycle_time;
		if($autostart)
			$this->_start();
	}

	protected function _cycle() {
		parent::_cycle();
		$wakeup = $this->cycle_start + $this->cycle_time;
		$now = microtime(TRUE);
		while($now<$wakeup) {
			usleep(1000000*($wakeup-$now));
			$now=microtime(TRUE);
		}
		$this->cycle_start = $now;
	}

	public function _start() {
	#if there is a long delay between instanting and running, you should create this store with autostart=FALSE and call this function before you start
		if($this->getLock()) {
			if($this->cycle_start == 0)
				$this->cycle_start = microtime(TRUE);
			$this->freeLock();
		}
	}

}
