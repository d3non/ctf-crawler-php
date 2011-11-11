<?php

require_once 'CTFCrawler.php';


class TestCrawler extends CTFCrawler {

	protected function _process($target) {
		echo $this->name . ": it is now ".date("c")." and we are processing $target\n";
	}

}

date_default_timezone_set('Europe/Berlin');

$crawler = new TestCrawler(array("foo","bar","baz","test","lol","echo","42","rofl","23"),2);

$crawler->start();
