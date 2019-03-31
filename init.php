<?php

/**
 * Redis Connection Initiator
 * for PHP include_path shared WordPress cache engine component
 *
 * @author Mark Hilton
 */

// init predis
require 'predis/autoload.php';

$json = $_SERVER['DOCUMENT_ROOT'] . '/wp-content/plugins/redis-light-speed-cache/config.json';

if ($config = @file_get_contents($json) or $config = @file_get_contents(basename($json)))
{
    $config = json_decode($config, true);
}

if (! is_array($config))
{
    $config = [];
}

$redis = new Predis\Client($config);
