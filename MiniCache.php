<?php

/**
 * ------------------------------------------------------
 * MiniCache
 *
 * A small, lightweight cache system for PHP5 or newer
 *
 * @author Brian Haveri
 * @link http://github.com/brianhaveri/MiniCache
 * @license MIT License http://en.wikipedia.org/wiki/MIT_License
 * ------------------------------------------------------
 */
include(dirname(__FILE__).'/MiniCacheConfig.php');
class MiniCache {

	/*
	 * ------------------------------------------------------
	 * Array keys used throughout class
	 * You probably won't change these (but still optional)
	 * ------------------------------------------------------
	 */
	const CACHEAGE		= 'age';
	const CACHEDATA		= 'data';
	const CACHEDURATION	= 'duration';
	const CACHEID		= 'id';
	const CACHEINFO		= 'info';
	const CACHEKEY		= 'key';

	
	/*
	 * ------------------------------------------------------
	 * Do not change these
	 * ------------------------------------------------------
	 */
	private static $_instance;	// Singleton instance
	private $_loaded = array();	// Intermediate storage of loaded cache items


	/**
	 * ------------------------------------------------------
	 * Returns a singleton
	 * This is the instantiation method
	 * @return object
	 * ------------------------------------------------------
	 */
	static function getInstance() {
		if( ! isset(self::$_instance)) {
			$c = __CLASS__;
			self::$_instance = new $c;
		}
		return self::$_instance;
	}
	
	
	/**
	 * ------------------------------------------------------
	 * Destroy the instance
	 * ------------------------------------------------------
	 */
	public function destroyInstance() {
		self::$_instance->_loaded = array();
		self::$_instance = NULL;
	}


	/**
	 * ------------------------------------------------------
	 * Nothing actually happens in the constructor, but we
	 * need to declare it with private visibility to
	 * force instantiation through getInstance().
	 *
	 * If you try to instantiate through the constructor,
	 * you will receive a PHP error.
	 * ------------------------------------------------------
	 */
	private function __construct() {}


	/**
	 * ------------------------------------------------------
	 * Returns data for a cache item.
	 * Return datatype will match datatype saved using set()
	 * Returns FALSE if an error occurred
	 * @param string $id
	 * @return mixed|bool
	 * ------------------------------------------------------
	 */
	public function get($id) {
		$cacheKey = self::cacheKey($id);
		$data = $this->_get($cacheKey);
		if(is_array($data) && array_key_exists(self::CACHEDATA, $data)) {
			return $data[self::CACHEDATA];
		}
		return FALSE;
	}


	/**
	 * ------------------------------------------------------
	 * Returns metadata for a cache item
	 * Returns FALSE if an error occurred
	 * @param string $id
	 * @return array|bool
	 * ------------------------------------------------------
	 */
	public function getInfo($id) {
		$cacheKey = self::cacheKey($id);
		$data = $this->_get($cacheKey);
		if(is_array($data) && array_key_exists(self::CACHEINFO, $data)) {

			// Age isn't directly stored in the cache file, so calculate it
			$data[self::CACHEINFO][self::CACHEAGE] = $this->_getAge($cacheKey);
			return $data[self::CACHEINFO];
		}
		return FALSE;
	}


	/**
	 * ------------------------------------------------------
	 * Saves a cache item to disk
	 * The data provided may be of any datatype including
	 * object, array, string, etc.
	 *
	 * The saved data and corresponding metadata is
	 * saved to disk as a serialized PHP multidimensional array.
	 *
	 * @param string $id
	 * @param mixed $data
	 * @param integer $duration
	 * @return bool
	 * ------------------------------------------------------
	 */
	public function set($id, $data, $duration = NULL) {
		if(! is_int($duration)) { $info[self::CACHEDURATION] = MINICACHE_DURATION; }

		$cacheKey = self::cacheKey($id);
		$fpath = $this->_fpath($cacheKey);
		$cacheData = array(
			self::CACHEDATA	=> $data,
			self::CACHEINFO	=> array(
				self::CACHEDURATION	=> $duration,
				self::CACHEID		=> $id,
				self::CACHEKEY		=> $cacheKey
			)
		);
		$serializedData = serialize($cacheData);
		if(file_put_contents($fpath, $serializedData, LOCK_EX)) {
			chmod($fpath, 0777);
			self::getInstance()->_loaded[$cacheKey] = $cacheData;
			return TRUE;
		}
		return FALSE;
	}


	/**
	 * ------------------------------------------------------
	 * Deletes a cache item
	 * @param string $id
	 * @return bool
	 * ------------------------------------------------------
	 */
	public function delete($id) {
		$cacheKey = self::cacheKey($id);

		// Delete from instance vars
		if(is_array(self::getInstance()->_loaded) && array_key_exists($cacheKey, self::getInstance()->_loaded)) {
			unset(self::getInstance()->_loaded[$cacheKey]);
		}

		// Delete from disk
		$fpath = $this->_fpath($cacheKey);
		if(file_exists($fpath)) {
			return unlink($fpath);
		}

		return FALSE;
	}


	/**
	 * ------------------------------------------------------
	 * Deletes all cache items
	 * Returns number of items deleted
	 * @return integer
	 * ------------------------------------------------------
	 */
	public function deleteAll() {
		$numDeleted = 0;
		$items = $this->listAll();
		if(is_array($items)) {
			foreach($items as $cacheKey=>$item) {
				$info =& $item[self::CACHEINFO];
				$numDeleted += (int) $this->delete($info[self::CACHEID]);
			}
		}
		return $numDeleted;
	}


	/**
	 * ------------------------------------------------------
	 * Deletes only expired cache items
	 * Returns number of items deleted
	 * @return integer
	 * ------------------------------------------------------
	 */
	public function deleteExpired() {
		$numDeleted = 0;
		$items = $this->listAll();
		if(is_array($items)) {
			foreach($items as $cacheKey=>$item) {
				$info =& $item[self::CACHEINFO];
				if(is_array($info)) {
					if(self::isExpired($this->_getAge($info[self::CACHEKEY]), $info[self::CACHEDURATION])) {
						$numDeleted += (int) $this->delete($info[self::CACHEID]);
					}
				}
			}
		}
		return $numDeleted;
	}


	/**
	 * ------------------------------------------------------
	 * Returns keys and info for all items
	 * @return array
	 * ------------------------------------------------------
	 */
	public function listAll($startDir=NULL) {
		if(is_null($startDir)) { $startDir = MINICACHE_PATH; }
		
		$files = scandir($startDir);
		$items = array();
		if($files && count($files) > 0) {
			foreach($files as $k=>$fname) {
				if(in_array($fname, array('.', '..', '.svn'))) { continue; }
				
				if(is_dir($startDir.'/'.$fname)) {
					$items = array_merge($items, $this->listAll($startDir.'/'.$fname));
					continue;
				}
				
				$data = $this->_get(basename($fname, MINICACHE_FEXT));
				$cacheKey = $data[self::CACHEINFO][self::CACHEKEY];
				unset(self::getInstance()->_loaded[$cacheKey]);
				unset($data[self::CACHEDATA]);
				$items[$cacheKey] = $data;
			}
			ksort($items);
			return $items;
		}
		return array();
	}


	/**
	 * ------------------------------------------------------
	 * Returns all data (regular and metadata) for a cache item
	 * Returns FALSE if an error occurred
	 * @param string $cacheKey
	 * @return array
	 * ------------------------------------------------------
	 */
	private function _get($cacheKey) {
		
		// _get() is called by multiple methods
		// so we intermediately store and read the data using _loaded
		// to minimize disk reads
		
		// Use data in _loaded if possible
		if(is_array(self::getInstance()->_loaded) && array_key_exists($cacheKey, self::getInstance()->_loaded)) {
			return self::getInstance()->_loaded[$cacheKey];
		}
		
		// Data wasn't in _loaded, so let's read from disk
		$fpath = $this->_fpath($cacheKey);
		if(file_exists($fpath)) {
			$data = unserialize(file_get_contents($fpath));
			if(is_array($data)) {
				self::getInstance()->_loaded[$cacheKey] = $data;
				return $data;
			}
		}
		return FALSE;
	}


	/**
	 * ------------------------------------------------------
	 * Returns a full path for a cache item
	 * @param string $cacheKey
	 * @return string
	 * ------------------------------------------------------
	 */
	private function _fpath($cacheKey) {
		$path = MINICACHE_PATH;
		if(MINICACHE_DEPTH > 0) {
			$segments = array_slice(str_split($cacheKey), 0, MINICACHE_DEPTH);
			$path .= join('/', $segments);
			if(! file_exists($path)) {
				mkdir($path, 0777, TRUE);
			}
		}
		
		return join('', array(
			$path.'/',
			$cacheKey,
			MINICACHE_FEXT
		));
	}


	/**
	 * ------------------------------------------------------
	 * Has the cache file expired?
	 * @param integer $age
	 * @param integer $duration
	 * @return bool
	 * ------------------------------------------------------
	 */
	public static function isExpired($age, $duration) {
		return ($duration >= 0 && $age > $duration);
	}


	/**
	 * ------------------------------------------------------
	 * Generate a cache key
	 * @param string $id
	 * @return string
	 * ------------------------------------------------------
	 */
	public static function cacheKey($id) {
		return md5($id);
	}


	/**
	 * ------------------------------------------------------
	 * Returns age of cache file (seconds)
	 * Returns FALSE if an error occurred
	 * @param string $cacheKey
	 * @return integer|bool
	 * ------------------------------------------------------
	 */
	private function _getAge($cacheKey) {
		$fpath = $this->_fpath($cacheKey);
		if(file_exists($fpath)) {
			return time() - filemtime($fpath);
		}
		return FALSE;
	}
}

?>
