<?php

require_once('TargetStore.php');

abstract class CTFCrawler {

	/* MultiThreadingMode: in wich mode will we "share" the targets and PiP */
	const MTM_SPLIT = 0;
	const MTM_SHM = 1;

	protected $threads=array();
	protected $thread_limit=0;
	protected $cycle_time;
	protected $MTMmode;
	protected $conf = array();

	protected $name="";
	protected $store;
	protected $f_submitter;

	public function __construct($conf,$targets,$pip,$threads=1,$cycle_time=5) {
		if(function_exists('shm_attach') AND function_exists('sem_get'))
			$this->MTMmode = self::MTM_SHM;
		else	$this->MTMmode = self::MTM_SPLIT;
		
		#TODO: remove this ;) (if MTM_SHM is ready)
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
		foreach(array('server','port','service') as $a)
			if(!array_key_exists($a,$conf))
				trigger_error("\"$a\" is needed in the first argument for ".__CLASS__." constructor!",E_USER_ERROR);
		$this->conf = $conf;
		$this->thread_limit = $threads;
		declare(ticks=10);
	}


	abstract protected function _process($target);
	abstract protected function _extractTeamID($target);


	protected function connect_submitter() {
		$this->f_submitter = fsockopen($this->conf['server'],$this->conf['port'],$errno,$errstr,10);
		if($this->f_submitter === FALSE)
			die("could not connect to ({$this->conf['server']}:{$this->conf['port']})!
{$errno}: $errstr\n");
		stream_set_blocking($this->f_submitter,0);
		fwrite($this->f_submitter,"?service={$this->conf['service']}");
		while(""==fgets($this->f_submitter));
	}

	protected function disconnect_submitter() {
		fclose($this->f_submitter);
	}

	protected function submit($flag,$options=array(),$echo=FALSE) {
		$opts=array('');
		foreach($options as $k => $v)
			$opts[] = "?{$k}={$v}";
		fwrite($this->f_submitter,"{$flag}".implode(' ',$opts));
		while(""==($r=fgets($this->f_submitter)))
			if($echo) echo $r;
	}


	protected function run() {
		$this->connect_submitter();
		while($target = $this->store->get())
			$this->_process($target);
		$this->disconnect_submitter();
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

abstract class CTFTelnetCrawler extends CTFCrawler {

	protected function _extractTeamID($target) {
		return $target['id'];
	}

	protected function read($sock,$timeout=0,$all=TRUE) {
		$s="";
		if($timeout>0) {
			stream_set_blocking($sock,1);
			stream_set_timeout($sock,$timeout);
			$s=fgets($sock);
			stream_set_blocking($sock,0);
			if($all) while($r=fgets($sock)) $s.=$r;
		}
		else while($r=fgets($sock)) $s.=$r;
		return $s;
	}
	
	protected function write($sock,$msg,$timeout=0,$all=TRUE) {
		if($timeout>0) {
			stream_set_blocking($sock,1);
			stream_set_timeout($sock,$timeout);
		}
		
		if($all) {
			$sent = 0;
			while($ret = fwrite($sock,substr($msg,$sent),strlen($msg)-$sent))
				if($ret) $sent += $ret;
				else {
					stream_set_blocking($sock,0);
					trigger_error("write failed to send all the data",E_USER_WARNING);
					return FALSE;
				}
			$ret = $sent;
		}else $ret = fwrite($sock,$msg,strlen($msg));
		stream_set_blocking($sock,0);
		return $ret == strlen($msg);
	}

	protected function connect($target) {
		$sock = fsockopen($target['host'],$target['port'],$errno,$errstr);
		if($sock===FALSE) trigger_error($errno.": ".$errstr,E_USER_WARNING);
		stream_set_blocking($sock,0);
		return $sock;
	}
	
	protected function disconnect($sock) {
		fclose($sock);
	}

	static public function mkTargetArray($host,$port,$teamID) {
		return array('host' => $host, 'port' => $port, 'id' => $teamID);
	}

}

#class CTFHTTPCrawler extends CTFCrawler {
#}


