<?php

/*

harhar - nice shit of code this class is! butem, can we use it? NOOOO!
why? we need sysvsem enabled, what can only be done on compile time with --enable-sysvsem
and nearly none distribution has packages with it.

so I added an alternative "locking" mechanism: NONE. (aka stupid) it does no locking at all.
so you can use this store, but must take care that an instance of this store is used by one thread only!

*/

class TargetStore {

	const NONE = -1;
	const SEMAPHORE = 0;

	protected $semaphore;
	protected $lock_type;
	protected $targets;
	protected $target_len = 0;
	protected $target_ptr = 0;

	public function __construct($target_list,$flock_file="") {
		if(function_exists('sem_get'))
			$this->lock_type = self::SEMAPHORE;
		else	$this->lock_type = self::NONE;
		switch($this->lock_type) {
			case self::SEMAPHORE:
				$this->semaphore = sem_get(hexdec(md5("TargetStore"+time())));
				break;
			default: /* aka self::NONE */
				
		}
		$this->targets = array_values($target_list);
		$this->target_len = count($target_list);
	}

	protected function getLock() {
		switch($this->lock_type) {
			case self::SEMAPHORE:
				return sem_acquire($this->semaphore);
				break;
			case self::NONE:
				return true;
				break;
		}
	}

	protected function freeLock() {
		switch($this->lock_type) {
			case self::SEMAPHORE:
				return sem_release($this->semaphore);
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

	public function __construct($target_list,$cycle_time,$autostart=TRUE,$flock_file="") {
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
	#if there is a long delay between instanting and running, you should create this store with autocstart=FALSE and call this function before you start
		if($this->getLock()) {
			if($this->cycle_start != 0)
				$this->cycle_start = microtime(TRUE);
			$this->freeLock();
		}
	}

}
