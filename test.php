<?php

/**
 * Redis Connection Test
 *
 * @author Mark Hilton
 */


// redis init
require 'init.php';

try {
    $redis->connect();
    die("\nRedis connection: OK\n\n");
}
catch (Predis\CommunicationException $exception) {
    die("\nRedis connection: ERROR\n\n");
}
