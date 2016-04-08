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

if (isset($_SERVER['REDIS']) and $_SERVER['REDIS'] == 'on' and isset($_SERVER['SCRIPT_FILENAME']) and isset($_SERVER['DOCUMENT_ROOT'])) 
{
	// declare this constant to avoid WP plugin throw error of not detected snippet
	define('_REDIS_LIGHT_CACHE_PREPEND', true);

	if (str_replace($_SERVER['DOCUMENT_ROOT'], '', $_SERVER['SCRIPT_FILENAME']) == '/index.php') 
	{
		@include 'engine.php';
	}
}
