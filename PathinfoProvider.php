<?php

abstract class PathinfoProvider {

	abstract public function getVisitedElements($pathinfo,$strip=TRUE);

	protected static function _containsBase_callback($path,$base) {
		return strpos($path,$base) !== FALSE;
	}

	public static function filterBase($pathinfo,$base) {
		array_walk($pathinfo,array('static','_containsBase_callback'));
		return array_filter($pathinfo);
	}

	protected static function _stripBase_callback($path,$base) {
		if(strpos($path,$base)===0) return substr($path,strlen($base));
		else {
			trigger_error("base '{$base}' in pathinfo '{$path}' not found",E_USER_NOTICE);
			return $path;
		}
	}

	public static function stripBase($pathinfo,$base) {
		if(!$base) {
			trigger_error("no base to strip",E_USER_NOTICE);
			return $pathinfo;
		}
		array_walk($pathinfo,array('static','_stripBase_callback'));
	}

}

class DummyPiP extends PathinfoProvider {
	public function getVisitedElements($pathinfo,$strip=TRUE) {
		return array();
	}
}

class LocalPiP extends PathinfoProvider {

	protected $pipFile;

	public function __construct($pipFile) {
		$this->pipFile = $pipFile;
	}

	public function getVisitedElements($pathinfo,$strip=TRUE) {
		$fp = fopen($this->pipFile,'r');
		$lines=array();
		while(!feof($fp))
			$lines[] = fgets($fp);
		fclose($fp);
		$lines=$this->filterBase($lines,$pathinfo);
		if($strip)
			$lines=$this->stripBase($lines,$pathinfo);
		return $lines;
	}
	
	public function addVisited($pathinfo) {
		$fp = fopen($this->pipFile,'a');
		if($fp) {
			fputs($fp,$pathinfo);
			fclose($fp);
			return true;
		}
		return false;
	}
}

class MySQLPiP extends PathinfoProvider {

	/* MySQL conf */
	protected $conf;
	protected $res;

	protected $team;
	protected $service;

	function __construct($mysqlconf,$team,$service) {
		foreach(array('server','user','password','database','table') as $k)
			if(!array_key_exists($k,$mysqlconf))
				trigger_error("{$k} is missing in ".__CLASS__." constructor",E_USER_ERROR);
		$this->conf = $mysqlconf;
		$this->team = mysql_escape($team);
		$this->service = mysql_escape($service);
	}

	protected function connect() {
		$this->res = mysql_connect($this->conf['server'],$this->conf['user'],$this->conf['password']);
		if(!$this->res)
			trigger_error("could not connect to mysql",E_USER_ERROR);
		if(!mysql_select_db($this->conf['database'],$this->res))
			trigger_error("could not select database '{$this->conf['database']}'",E_USER_ERROR);
	}

	public function getVisitedElements($pathinfo,$strip=TRUE) {
		if(!$this->res) $this->connect();
		$pi = mysql_escape($pathinfo);
		$r = mysql_query("SELECT `pathinfo` FROM ´{$this->conf['table']}´ WHERE `team` = '{$this->team}' AND `service` = '{$this->service}' AND `pathinfo` LIKE '{$pi}%';");
		$e=array();
		while($x = mysql_fetch_assoc($r))
			if($strip) $e[] = static::stripBase($x,$pathinfo);
			else $e[] = $x;
		return $e;
	}


}
