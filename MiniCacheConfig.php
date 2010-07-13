<?php

/**
 *----------------------------------------
 * Configure MiniCache
 *----------------------------------------
 */
define('MINICACHE_DEPTH',	4); // Length of chars for subdirs. Integer 0 to 32.
define('MINICACHE_DURATION',	0); // Seconds
define('MINICACHE_FEXT',		'.cache'); // Cache file extension including dot
define('MINICACHE_PATH',		'/cache/'); // Path MUST exist, be writeable, and include trailing slash
	
?>
