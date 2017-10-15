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

if (redis_light::cache()) {
    define('WP_USE_THEMES', true);

    ob_start([ 'redis_light', 'callback' ]);

    /** Loads the WordPress Environment and Template */
    require getcwd() . $_SERVER['SCRIPT_NAME'];

    ob_end_flush();

    /** terminate the script here **/
    die();
}

class redis_light
{
    public static $key    = null; // cache key
    public static $redis  = null; // redis instance
    public static $config = null; // configuration array
    public static $db     = 0;    // current redis db
    public static $cookie = '';   // unique user cookie


    /*
     * main Redis cache method
     *
     */
    public static function cache()
    {
        self::$config = self::defaults();

        self::logger('connecting to redis [' . self::$config['host'] . ']');

        //
        // do not run if explicitly requested
        #if (isset($_GET['NOCACHE']) or (isset($_SERVER['HTTP_CACHE_CONTROL']) && $_SERVER['HTTP_CACHE_CONTROL'] == 'max-age=0')) {
        if (isset($_ENV['PAGECACHE_NOCACHE']) && isset($_GET[ $_ENV['PAGECACHE_NOCACHE'] ])) {
            self::logger('nocache explicitly requested. terminating...');

            header('Cache: skipping');

            return false;
        }

        //
        // do not run if request is a POST or user is logged into WordPress
        if (preg_match("/wordpress_logged_in/", var_export($_COOKIE, true))) {
            self::logger('POST request - terminating...');

            header('Cache: disengaged');

            return false;
        }

        //
        // build URL key for Redis storage
        self::$cookie = isset($_COOKIE['pagecache'])    ? $_COOKIE['pagecache']   : '';
        $url    = self::$config['query'] == false ? $_SERVER['REQUEST_URI'] : strtok($_SERVER['REQUEST_URI'], '?');

        // post request will set user unique cache data set
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            self::$cookie = uniqid();
            setcookie('pagecache', self::$cookie, time() + 60 * 30, '/');
            self::logger(sprintf('post detected, generating new key cookie: [%s]', self::$cookie));

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

        // self::$redis = new Redis();
        // $connect     = self::$redis->connect(self::$config['host'], self::$config['port'], self::$config['timeout']);

        if (stream_resolve_include_path("predis/autoload.php") === false) {
            self::logger('Cannot locate PREDIS class, Redis cache will not work');
            return false;
        }

        require 'predis/autoload.php';

        self::$redis = new Predis\Client([
            'scheme'  => 'tcp',
            'host'    => self::$config['host'],
            'port'    => self::$config['port'],
            'timeout' => self::$config['timeout'],
        ], [ 'profile' => '3.2' ]);


        if (! is_null(self::$config['security'])) {
            self::$redis->auth(self::$config['security']);
        }


        self::$key = md5($_SERVER['HTTP_HOST'] . $url . self::$cookie);
        self::logger(sprintf('requested URI: [%s], COOKIE: [%s], KEY: [%s]', $url, self::$cookie, self::$key));

        // woo commerce exceptions
        // self::$config['exclude'][] = '/map/*';
        // self::$config['exclude'][] = '/cart/*';
        // self::$config['exclude'][] = '/orders/*';
        // self::$config['exclude'][] = '/checkout/*';
        // self::$config['exclude'][] = '/my-account/*';

        //
        // check URL exceptions
        foreach (self::$config['exclude'] as $exclude) {
            if (trim($exclude) == '') {
                continue;
            }

            $pattern = sprintf('/%s/', str_replace('/', '\/', $exclude));

            if (preg_match($pattern, $url)) {
                self::logger('requested URL listed as a no cache exeption. terminating...');

                header('Cache: page excluded');

                return false;
            }
        }

        //
        // connect to Redis
        // if ($connect) {
        //     self::logger('connected to Redis cache OK. retrieving domains list');
        // } else {
        //     self::logger('connection to Redis cache FAILED. terminating...');

        //     return false;
        // }

        //
        // connect to domains database
        try {
            self::$redis->select(0);
            self::logger('connected to Redis cache OK. retrieving domains list');
        } catch (Exception $e) {
            self::logger('connection to Redis cache FAILED. terminating because of: '.$e->getMessage()."\n");

            return false;
        }

        $domains = json_decode(self::$redis->get('domains'), true);

        // fetch redis database ID for current host
        if (isset($domains[ $_SERVER['HTTP_HOST'] ]['id'])) {
            self::$db = $domains[ $_SERVER['HTTP_HOST'] ]['id'];

            self::logger(sprintf('current domain [id: %d]: %s found in cache. Total hostname(s) stored: %d', self::$db, $_SERVER['HTTP_HOST'], count($domains)));
        }

        // create new redis database if current host does not have one
        else {
            if (! is_array($domains)) {
                $domains = [];
            }

            self::$db = count($domains) + 1;

            $domains[ $_SERVER['HTTP_HOST'] ]['id'] = self::$db;

            self::logger(sprintf('current domain: %s does not exist in cache - creating. Total hostname(s) stored: %d', $_SERVER['HTTP_HOST'], count($domains)));

            self::$redis->set('domains', json_encode($domains));
        }

        try {
            self::$redis->select(self::$db);    
        } catch (Exception $e) {
            self::logger(sprintf('ERROR: could not select database: [%d] for host: [%s]', self::$db, $_SERVER['HTTP_HOST']));
            return false;
        }
        

        /**
         * cache requests and server cached content
         *
         * 1. serve cached content if url key found
         * 2. store content into cache if url key does not exist
         *
         */
        if (self::$redis->exists(self::$key)) {
            self::logger('cached page found! fetching content, key: [' . self::$key . ']');

            http_response_code(self::$redis->get(self::$key.'-CODE'));

            $headers = json_decode(self::$redis->get(self::$key.'-HEAD'), true);

            if (is_array($headers)) {
                foreach ($headers as $header) {
                    header($header);
                }
            }

            header('Cache: fetched from cache');

            die(self::$redis->get(self::$key));
        }

        // cache the page
        else {
            self::logger('cached page not found! [' . $_SERVER['REQUEST_URI'] . ']');

            header('Cache: storing new data');

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
            'host'     => isset($_ENV['PAGECACHE_HOST'])     ? $_ENV['PAGECACHE_HOST']         : 'redis',
            'port'     => isset($_ENV['PAGECACHE_PORT'])     ? $_ENV['PAGECACHE_PORT']         : 6379,
            'timeout'  => isset($_ENV['PAGECACHE_TTIMEOUT']) ? $_ENV['PAGECACHE_TTIMEOUT']     : 0.5,
            'query'    => isset($_ENV['PAGECACHE_QUERY'])    ? (bool) $_ENV['PAGECACHE_QUERY'] : false,
            'debug'    => isset($_ENV['PAGECACHE_DEBUG'])    ? (bool) $_ENV['PAGECACHE_DEBUG'] : false,
            'security' => isset($_ENV['PAGECACHE_AUTH']) && trim($_ENV['PAGECACHE_AUTH']) != '' ? $_ENV['PAGECACHE_AUTH'] : null,
        ];

        self::$config['exclude'] = [];

        return self::$config;
    }

    /*
     * system log
     *
     */
    public static function logger($message)
    {
        if (self::$config['debug']) {
            file_put_contents('php://stdout', 'PAGECACHE: ' . $message);
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
        $response_code = http_response_code();

        self::$redis->select(self::$db); 

        if (in_array($response_code, [ '200', '404' ]) and self::$redis->set(self::$key, $buffer)) {
            self::$redis->set(self::$key.'-CODE', $response_code);
            self::$redis->set(self::$key.'-HEAD', json_encode(headers_list()));

            // set key expiration
            $ttl = self::$cookie == '' ? "expire in 1 week" : "expire in 30 minutes";

            self::$redis->ttl($ttl);

            // log syslog message if cannot store objects in redis
            self::logger(sprintf('caching content for [%s]. database size: [%d]', $ttl, self::$redis->dbSize()));
        } else {
            # self::$redis->delete(self::$key);
            self::logger('Redis cannot store data. Memory: '.self::$redis->info('used_memory_human'));
        }

        return $buffer;
    }

    /** **/
}
