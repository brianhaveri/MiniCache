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
class MiniCache {

	/*
	 * ------------------------------------------------------
	 * Configuration variables
	 * You can change these (optional)
	 * ------------------------------------------------------
	 */
	private $_defaultDuration = 3600; // Seconds
	private $_path = '/cache/'; // Path MUST exist, be writeable, and include trailing slash
	private $_fext = '.cache'; // Cache file extension including dot

	
	/*
	 * ------------------------------------------------------
	 * Array keys used throughout class
	 * You probably won't change these (but still optional)
	 * ------------------------------------------------------
	 */
	const CACHEDATA		= 'data';
	const CACHEINFO		= 'info';
	const CACHEDURATION	= 'duration';
	const CACHEAGE			= 'age';

	
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
	function get($id) {
		$data = $this->_get($id);
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
	function getInfo($id) {
		$data = $this->_get($id);
		if(is_array($data) && array_key_exists(self::CACHEINFO, $data)) {

			// Age isn't directly stored in the cache file, so calculate it
			$data[self::CACHEINFO][self::CACHEAGE] = $this->_getAge($id);
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
	function set($id, $data, $duration = NULL) {
		$info = array(self::CACHEDURATION=>$this->_defaultDuration);
		if(is_int($duration)) {
			$info[self::CACHEDURATION] = $duration;
		}

		$file = array(self::CACHEDATA=>$data,
					  self::CACHEINFO=>$info);
		return file_put_contents($this->_fpath($id), serialize($file));
	}


	/**
	 * ------------------------------------------------------
	 * Deletes a cache item
	 * @param string $id
	 * @return bool
	 * ------------------------------------------------------
	 */
	function delete($id) {

		// Delete from instance vars
		$id = $this->_sanitizeID($id);
		if(is_array($this->_loaded) && array_key_exists($id, $this->_loaded)) {
			unset($this->_loaded[$id]);
		}

		// Delete from disk
		$fpath = $this->_fpath($id);
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
	function deleteAll() {
		$numDeleted = 0;
		$files = $this->listAll();
		if(is_array($files)) {
			foreach($files as $id) {
				$numDeleted += (int) $this->delete($id);
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
	function deleteExpired() {
		$numDeleted = 0;
		$files = $this->listAll();
		if(is_array($files)) {
			foreach($files as $id) {
				$info = $this->getInfo($id);
				if(is_array($info)) {
					if($this->_isExpired($info[self::CACHEAGE], $info[self::CACHEDURATION])) {
						$this->numDeleted += (int) $this->delete($id);
					}
				}
			}
		}
		return $numDeleted;
	}


	/**
	 * ------------------------------------------------------
	 * Returns IDs for all cache items
	 * Returns FALSE if an error occurred
	 * @return array|bool
	 * ------------------------------------------------------
	 */
	function listAll() {
		$files = scandir($this->_path);
		if($files && count($files) > 0) {
			foreach($files as $k=>$fname) {

				// Remove '.', '..', and any other subdirectories of _path
				$fpath = $this->_path.$fname;
				if(is_dir($fpath)) {
					unset($files[$k]);
					continue;
				}

				// We want the return array to contain IDs, not filenames
				// So replace filename with ID
				$files[$k] = $this->_extractID($fname);
			}

			// Sorting resets the array keys, which otherwise would be
			// offset by the previously existing subdirectories ('.', '..', etc.)
			sort($files);

			return $files;
		}
		return FALSE;
	}


	/**
	 * ------------------------------------------------------
	 * Returns all data (regular and metadata) for a cache item
	 * Returns FALSE if an error occurred
	 * @param string $id
	 * @return array
	 * ------------------------------------------------------
	 */
	private function _get($id) {
		
		// _get() is called by multiple methods
		// so we intermediately store and read the data using _loaded
		// to minimize disk reads
		
		// Use data in _loaded if possible
		$id = $this->_sanitizeID($id);
		if(is_array($this->_loaded) && array_key_exists($id, $this->_loaded)) {
			return $this->_loaded[$id];
		}
		
		// Data wasn't in _loaded, so let's read from disk
		$fpath = $this->_fpath($id);
		if(file_exists($fpath)) {
			$data = unserialize(file_get_contents($fpath));
			if(is_array($data)) {
				$this->_loaded[$id] = $data;
				return $this->_loaded[$id];
			}
		}
		return FALSE;
	}


	/**
	 * ------------------------------------------------------
	 * Returns a full path for a cache item
	 * @param string $id
	 * @return string
	 * ------------------------------------------------------
	 */
	private function _fpath($id) {
		return join('', array($this->_path,
							  $this->_sanitizeID($id),
							  $this->_fext));
	}


	/**
	 * ------------------------------------------------------
	 * Has the cache file expired?
	 * @param integer $age
	 * @param integer $duration
	 * @return bool
	 * ------------------------------------------------------
	 */
	private function _isExpired($age, $duration) {
		return $age > $duration;
	}


	/**
	 * ------------------------------------------------------
	 * Sanitizes an ID string
	 * @param string $id
	 * @return string
	 * ------------------------------------------------------
	 */
	private function _sanitizeID($id) {
		$replaceChars = array('/', '\\', ' ', '.');
		return str_replace($replaceChars, '-', strtolower($id));
	}


	/**
	 * ------------------------------------------------------
	 * Extracts an ID from the cache filename
	 * Ex: _extractID('my-saved-file.cache') => 'my-saved-file'
	 * @param string $fname
	 * @return string
	 * ------------------------------------------------------
	 */
	private function _extractID($fname) {
		return preg_replace("/$this->_fext$/", '', $fname);
	}


	/**
	 * ------------------------------------------------------
	 * Returns age of cache file (seconds)
	 * Returns FALSE if an error occurred
	 * @param string $id
	 * @return integer|bool
	 * ------------------------------------------------------
	 */
	private function _getAge($id) {
		$fpath = $this->_fpath($id);
		if(file_exists($fpath)) {
			return time() - filemtime($fpath);
		}
		return FALSE;
	}
}

?>
