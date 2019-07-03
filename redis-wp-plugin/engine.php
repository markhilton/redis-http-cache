<?php

/**
 * WordPress Redis Light Speed Caching Engine
 *
 * Redis caching system for WordPress. Inspired by Jim Westergren & Jeedo Aquino
 *
 * @author Mark Hilton
 * @see http://www.jeedo.net/lightning-fast-wordpress-with-nginx-redis/
 * @see http://www.jimwestergren.com/wordpress-with-redis-as-a-frontend-cache/
 */

if (extension_loaded('redis') and redis_light::cache()) {
    define('WP_USE_THEMES', true);

    ob_start([ 'redis_light', 'callback' ]);

    /** Loads the WordPress Environment and Template */
    require getcwd().'/wp-blog-header.php';

    ob_end_flush();

    /** terminate the script here **/
    die();
}




class redis_light
{
    public static $cc     = 0;    // logger step counter
    public static $key    = null; // cache key
    public static $redis  = null; // redis instance
    public static $config = null; // configuration array



    /* 
     * main Redis cache method
     *
     */
    public static function cache()
    {
        self::logger(str_repeat('-', 100));
        self::logger('Starting Redis caching engine connection...');

        //
        // do not run if explicitly requested
        #if (isset($_GET['nocache']) or (isset($_SERVER['HTTP_CACHE_CONTROL']) && $_SERVER['HTTP_CACHE_CONTROL'] == 'max-age=0')) {
        if (isset($_GET['NOCACHE'])) {
            self::logger('NOCACHE explicitly requested. terminating...');

            header('Redis-cache: no cach requested');

            return false;
        }

        //
        // do not run if request is a POST or user is logged into WordPress
        if ($_POST or preg_match("/wordpress_logged_in/", var_export($_COOKIE, true))) {
            self::logger('NOCACHE explicitly requested. terminating...');

            header('Redis-cache: cache disengaged');

            return false;
        }

        /**
         * start Redis cache processing
         *
         * 1. try to connect to redis server
         * 2. fetch or create domain host database
         * 3. define URL storage key
         *
         */
        try {
            $config = __DIR__.'/config.json';

            if ($config = @file_get_contents($config)) {
                self::$config = json_decode($config, true);
            }

            if (! is_array(self::$config)) {
                self::$config = self::defaults();
            }

            self::$redis = new Redis();
            $connect     = self::$redis->connect(self::$config['host'], self::$config['port'], self::$config['timeout']);

            if (trim(self::$config['security']) != '') {
                self::$redis->auth(self::$config['security']);
            }
        }
        // terminate script if cannot connect to Redis server
        // and gracefully fall back into regular WordPress
        catch (Exception $e) {
            self::logger('redis extension failed. terminating...');

            return false;
        }


        //
        // build URL key for Redis storage
        $url = (isset(self::$config['query']) and self::$config['query'] != "NO") ? $_SERVER['REQUEST_URI'] : strtok($_SERVER['REQUEST_URI'], '?');
        self::$key = md5($_SERVER['HTTP_HOST'].$url);

        self::logger(sprintf('requested URI: %s, key: %s', $url, self::$key));


        //
        // check engine should be engaged
        if (self::$config['status'] != 'ON') {
            self::logger('caching engine disengaged by admin. terminating...');

            return false;
        }


        //
        // check URL exceptions
        if (in_array($url, self::$config['exclude'])) {
            self::logger('requested URL listed as a no cache exeption. terminating...');

            return false;
        }


        //
        // connect to Redis
        if ($connect) {
            self::logger('connected to Redis cache OK. retrieving domains list');
        } else {
            self::logger('connection to Redis cache FAILED. terminating...');

            return false;
        }

        //
        // connect to domains database
        self::$redis->select(0);
        $domains = json_decode(self::$redis->get('domains'), true);

        // fetch redis database ID for current host
        if (isset($domains[ $_SERVER['HTTP_HOST'] ]['id'])) {
            $db = $domains[ $_SERVER['HTTP_HOST'] ]['id'];

            self::logger(sprintf('current domain [id: %d]: %s found in cache. Total domains stored: %d', $db, $_SERVER['HTTP_HOST'], count($domains)));
        }

        // create new redis database if current host does not have one
        else {
            if (! is_array($domains)) {
                $domains = [];
            }

            $db = count($domains) + 1;

            $domains[ $_SERVER['HTTP_HOST'] ]['id'] = $db;

            self::logger(sprintf('current domain: %s does not exist in cache - creating. Total domains stored: %d', $_SERVER['HTTP_HOST'], count($domains)));

            self::$redis->set('domains', json_encode($domains));
        }

        self::$redis->select($db);


        /**
         * cache requests and server cached content
         *
         * 1. serve cached content if url key found
         * 2. store content into cache if url key does not exist
         *
         */
        if (self::$redis->exists(self::$key)) {
            self::logger('fetching content from the cache. key: '.self::$key);

            header('Redis-cache: fetched from cache');

            die( self::$redis->get(self::$key) );
        }

        // cache the page
        else {
            self::logger('rendering page with WordPress');

            header('Redis-cache: storing new data');

            return true;
        }
    }

    /* 
     * default Redis conenction config
     *
     */
    public static function defaults()
    {
        self::$config = [
            'host'     => '127.0.0.1',
            'port'     => 6379,
            'security' => null,
            'timeout'  => 1,
        ];

        return self::$config;
    }

    /* 
     * system log
     *
     */
    public static function logger($message)
    {
        self::$cc++;

        if (file_exists('/tmp/.redis.log')) {
            openlog('Redis Cache Plugin', LOG_CONS | LOG_NDELAY | LOG_PID, LOG_USER | LOG_PERROR);
            syslog(LOG_INFO, sprintf('STEP %d: %s', self::$cc, $message));
            closelog();
        }
    }

    /* 
     * ob_start call back
     *
     */
    public static function callback($buffer)
    {
       // do not store output if empty
       // do not store output if starts with { - indicating json object
       // so we dont want to store it in Redis
        if (trim($buffer) == '' or substr(trim($buffer), 0, 1) == '{') {
            return $buffer;
        }


        // attempt to store content in the cache
        if (self::$redis->set(self::$key, $buffer)) {
            // log syslog message if cannot store objects in redis
            self::logger('storing content in the cache. page count: '.self::$redis->dbSize());
        } else {
            self::$redis->delete(self::$key);
            self::logger('Redis cannot store data. Memory: '.self::$redis->info('used_memory_human'));

            openlog('Redis Cache Plugin', LOG_CONS | LOG_NDELAY | LOG_PID, LOG_USER | LOG_PERROR);
            syslog(LOG_INFO, 'Redis cannot store data. Memory: '.self::$redis->info('used_memory_human'));
            closelog();
        }

        return $buffer;
    }

    /** **/
}
