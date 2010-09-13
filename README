What is MiniCache?
==================
MiniCache is a small, lightweight, disk-based, open source cache system for PHP5.


When should I use MiniCache?
============================
Use MiniCache when you:

* Need a cache layer but...
* memecached isn't available (yet)

Sample use cases
================

* You are dynamically generating HTML strings, but it can be slow and hinder performance. You can use MiniCache to store the generated HTML string.
* You want to save the state of a PHP object. MiniCache can save the entire object for later reuse.
* You want to parse data from multiple sources into a PHP array. MiniCache can act as a central data storage location.


Getting Started
===============
### Step 1: Download
Download the MiniCache ZIP file and unzip the it. Copy the unzipped ~/src/web/include/lib/MiniCache folder to your project's ~/include/lib/ directory. When done, the path to dimage should be ~/include/lib/MiniCache/

### Step 2: Hello world!
Open one of your project's PHP templates and paste the following code:

	$mc = MiniCache::getInstance();
	$mc->set('myfirstkey', 'Hello world!');
	echo $mc->get('myfirstkey'); // Hello world!

The text 'Hello world!' should now appear in your webpage. MiniCache stores and retrieves data using simple set() and get() calls. You can store strings, arrays, objects, integers, booleans, and any type of serializable data (not PHP resources) using MiniCache.

Using the cache key you provide, 'myfirstkey' in this case, MiniCache generates a unique filename and path, then stores your cached data to disk. If you browse to ~/include/lib/MiniCache/cache/ and drill down through the subdirectories, you will see the cached file, typically with a '.cache' file extension.

### Step 3: Deleting items
MiniCache provides you several ways to delete items:

* delete() removes a single item from cache
* deleteExpired() removes only expired items from cache
* deleteAll() removes everything from cache

Let's try deleting the item we created in step 2:

	$mc = MiniCache::getInstance();
	echo $mc->get('myfirstkey'); // Hello world!
	$mc->delete('myfirstkey');
	echo $mc->get('myfirstkey'); // [empty]


Configuration
=============
MiniCache intentionally provides a limited set of configuration options in MiniCacheConfig.php:

	define('MINICACHE_DEPTH',	4); // Length of chars for subdirs. Integer 0 to 32.
	define('MINICACHE_DURATION',0); // Seconds
	define('MINICACHE_FEXT',	'.cache'); // Cache file extension including dot
	define('MINICACHE_PATH',	dirname(__FILE__).'/cache/'); // Path MUST exist, be writeable, and include trailing slash

### Configuring cache duration
The most common configuration setting is cache duration, the number of seconds until the item expires. MiniCache provides two levels of cache duration:

* Global duration setting via MINICACHE_DURATION (see above)
* Item level duration by passing a 3rd parameter to set()

__How to set cache duration__

	$mc = MiniCache::getInstance();
	$mc->set('akey', 'my data'); // expires based on MINICACHE_DURATION
	$mc->set('lastkey', 'last data', 0); // expires immediately
	$mc->set('mykey', 'my sample data', 3600); // expires in 1 hour
	$mc->set('anotherkey', 'more sample data', -1); // never expires


Extending MiniCache
===================
To apply custom functionality to MiniCache, you should extend the MiniCache class rather than modify it directly. Extending allows you to upgrade versions more smoothly.

Here's an example of how to extend MiniCache:

	class Cache extends MiniCache {

		// Same as MiniCache::$_instance
		private static $_instance = NULL;

		
		// Same as MiniCache::getInstance()
		public static function getInstance() {
			if( ! isset(self::$_instance)) {
				self::$_instance = new Cache;
			}
			return self::$_instance;
		}
		
		
		// Same as MiniCache::__construct()
		private function __construct() {}
		
		
		// Custom get() functionality to log get() requests
		// See MiniCache::get() for docs
		public function get($cacheKey) {
			$data = parent::get($cacheKey);
			
			$logData = array(
				time(),
				$cacheKey,
				(int) (((bool) $data) ? 'Hit' : 'Miss')
			);
			$logLine = join("\t", $logData)."\n";
			$logFpath = dirname(__FILE__).'/cache-requests.log';
			file_put_contents($logFpath, $logLine, FILE_APPEND);
			
			return $data;
		}
	}
