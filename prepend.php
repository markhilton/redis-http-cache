<?php

/**
 * Redis Cache Engine
 *
 * this is intent to be used as PHP auto prepend script
 * for WordPress installations
 *
 * script checks if WordPress index.php from vhost root folder has been called
 * if so, then run URL request through Redis Cache Engine first
 *
 */

if (isset($_ENV['REDIS_LIGHT_CACHE_PREPEND']) and isset($_SERVER['SCRIPT_FILENAME']) and isset($_SERVER['DOCUMENT_ROOT'])) 
{
	if (in_array($_ENV['REDIS_LIGHT_CACHE_PREPEND'], [ '1', 'true' ])) {
		include_once('engine.php');		
	}
}
