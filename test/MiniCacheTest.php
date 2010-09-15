<?php

require_once('PHPUnit/Framework.php');
require_once(dirname(__FILE__).'/../MiniCache.php');

class MiniCacheTest extends PHPUnit_Framework_TestCase {
	
	public function testSetGet() {
		$mc = MiniCache::getInstance();
		$tests = array(
			'string'	=> 'my test string',
			'array'		=> array('my', 'test', 'array'),
			'boolean'	=> TRUE,
			'integer'	=> 35,
			'double'	=> 3.14,
			'object'	=> (object) 'my object'
		);
		foreach($tests as $k=>$v) {
			$cacheKey = __CLASS__.__FUNCTION__.$k;
			$mc->set($cacheKey, $v);
		}
		$mc->destroyInstance();
		
		$mc = MiniCache::getInstance();
		foreach($tests as $k=>$v) {
			$cacheKey = __CLASS__.__FUNCTION__.$k;
			$getResult = $mc->get($cacheKey);
			$this->assertEquals($getResult, $v);
			$this->assertEquals(gettype($getResult), $k);
		}
	} // end testSetGet()
	
	
	public function testDelete() {
		$mc = MiniCache::getInstance();
		$tests = array(
			'string'	=> 'my test string',
			'array'		=> array('my', 'test', 'array'),
			'boolean'	=> TRUE,
			'integer'	=> 35,
			'double'	=> 3.14,
			'object'	=> (object) 'my object'
		);
		foreach($tests as $k=>$v) {
			$cacheKey = __CLASS__.__FUNCTION__.$k;
			$mc->set($cacheKey, $v);
			$mc->delete($cacheKey);
		}
		$mc->destroyInstance();
		
		$mc = MiniCache::getInstance();
		foreach($tests as $k=>$v) {
			$this->assertEquals($mc->get($cacheKey), FALSE);
		}
	} // end testDelete()
	
	
	public function testIsExpired() {
		$this->assertEquals(MiniCache::isExpired(2, 1), TRUE);
	}
	
	public function testCacheKey() {
		$this->assertEquals(is_string(MiniCache::cacheKey('my test key')), TRUE);
		$this->assertEquals(strlen(MiniCache::cacheKey('my new test key')), 32);
	}
	
	public function testListAll() {
		$mc = MiniCache::getInstance();
		$mc->deleteAll();
		$tests = array(
			'string'	=> 'my test string',
			'array'		=> array('my', 'test', 'array'),
			'boolean'	=> TRUE,
			'integer'	=> 35,
			'double'	=> 3.14,
			'object'	=> (object) 'my object'
		);
		$cacheKeys = array();
		foreach($tests as $k=>$v) {
			$cacheKey = __CLASS__.__FUNCTION__.$k;
			$cacheKeys[] = MiniCache::cacheKey($cacheKey);
			$mc->set($cacheKey, $v);
		}
		$mc->destroyInstance();
		
		$mc = MiniCache::getInstance();
		$listAllResultKeys = array_keys($mc->listAll());
		sort($listAllResultKeys);
		sort($cacheKeys);
		$this->assertEquals($listAllResultKeys, $cacheKeys);
	}
	
	public function testExpiration() {
		$durations = array(-1, 0, 1);
		foreach($durations as $duration) {
			$cacheKey = 'exp'.$duration;
			$mc = MiniCache::getInstance();
			$mc->set($cacheKey, 'my test string', $duration);
			$beforeExpiration = $mc->get($cacheKey);
			if($duration >= 0) { sleep($duration + 1); }
			$mc->deleteExpired();
			
			$afterExpiration = $mc->get($cacheKey);
			if($duration >= 0) {
				$this->assertEquals($afterExpiration, FALSE);
			}
			else {
				$this->assertEquals($afterExpiration, $beforeExpiration);
			}
		}
		$mc = MiniCache::getInstance();
		$mc->delete('exp-1');
	} // end testExpiration()
}

?>