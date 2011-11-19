<?php

require_once 'CTFCrawler.php';

require_once 'PathinfoProvider.php';

define('VERBOSE',true);

class TestCrawler extends CTFHTTPCrawler {

	protected function _process($target) {
		echo $this->name.") processing target: ".print_r($target,true)."\n";
		$resph=array();
		$cont = $this->ht_get($target,'/',NULL,NULL,&$resph);
		echo "hier kommen die header:\n";
		print_r($resph);
#		echo "und nun der content:\n";
#		echo $cont;
		echo "\n\n\n FERTIG!";

/*		$s = $this->connect($target);
		if($s) {
			$this->write($s,"give me please\n");
			$flag = trim($this->read($s,20));
			$this->disconnect($s);
			if($flag) $this->submit($flag,array('team'=>$this->_extractTeamID($target)));
			echo $this->name . " processed ".$this->_extractTeamID($target)." and got flag {$flag}\n";
		}else echo $this->name . ": could not connect to ".$this->_extractTeamID($target)."\n";
*/
	}

}

date_default_timezone_set('Europe/Berlin');

$submitConf = array('server'=>'127.0.0.1','port'=>50001,'service'=>'test');

$targets = array(TestCrawler::mkTargetArray('www.heise.de',80,3),TestCrawler::mkTargetArray('www.google.de',80,3),TestCrawler::mkTargetArray('www.golem.de',80,3),
TestCrawler::mkTargetArray('blog.fefe.de',80,3),TestCrawler::mkTargetArray('www.heise.de',80,3));

$crawler = new TestCrawler($submitConf,$targets,new DummyPiP(),2,45);

$crawler->start();
