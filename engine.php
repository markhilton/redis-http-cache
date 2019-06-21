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
    require $_SERVER['DOCUMENT_ROOT'] . '/wp-blog-header.php';

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
        #if (isset($_GET['NOCACHE']) or (isset($_SERVER['HTTP_CACHE_CONTROL']) && $_SERVER['HTTP_CACHE_CONTROL'] == 'max-age=0')) {
        if (isset($_GET['nocache'])) {
            self::logger('NOCACHE explicitly requested. terminating...');

            header('Cache: skipping');

            return false;
        }

        //
        // do not run if request is a POST or user is logged into WordPress
        if ($_POST or preg_match("/wordpress_logged_in/", var_export($_COOKIE, true))) {
            self::logger('NOCACHE explicitly requested. terminating...');

            header('Cache: disengaged');

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
        self::$config = self::load_config();

        try {
            require_once 'predis/autoload.php';

            self::$redis = new Predis\Client([
                'scheme'  => 'tcp',
                'host'    => self::$config['REDIS_HOST'],
                'port'    => self::$config['REDIS_PORT'],
                'timeout' => self::$config['REDIS_WAIT'],
            ]);

            self::$redis->connect();
            self::$config['REDIS_STATUS'] = 1;
        }

        catch (Predis\Connection\ConnectionException $exception) {
            self::$config['REDIS_STATUS'] = 0;
            self::logger($e);
            return false;
        }

        if (trim(self::$config['REDIS_AUTH']) != '') {
            self::$redis->auth(self::$config['REDIS_AUTH']);
        }

        //
        // build URL key for Redis storage
        $url = (isset(self::$config['REDIS_QUERY']) and ! self::$config['REDIS_QUERY'])
            ? $_SERVER['REQUEST_URI'] : strtok($_SERVER['REQUEST_URI'], '?');

        self::$key = md5($_SERVER['HTTP_HOST'] . $url);

        self::logger(sprintf('requested URI: %s, key: %s', $url, self::$key));

        // woo commerce exceptions
        self::$config['REDIS_EXCLUDE'][] = '/map/*';
        self::$config['REDIS_EXCLUDE'][] = '/cart/*';
        self::$config['REDIS_EXCLUDE'][] = '/orders/*';
        self::$config['REDIS_EXCLUDE'][] = '/checkout/*';
        self::$config['REDIS_EXCLUDE'][] = '/my-account/*';

        //
        // check URL exceptions
        foreach (self::$config['REDIS_EXCLUDE'] as $exclude) {
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
            $db = $domains[ $_SERVER['HTTP_HOST'] ]['id'];

            self::logger(sprintf('current domain [id: %d]: %s found in cache. Total hostname(s) stored: %d', $db, $_SERVER['HTTP_HOST'], count($domains)));
        }

        // create new redis database if current host does not have one
        else {
            if (! is_array($domains)) {
                $domains = [];
            }

            $db = count($domains) + 1;

            $domains[ $_SERVER['HTTP_HOST'] ]['id'] = $db;

            self::logger(sprintf('current domain: %s does not exist in cache - creating. Total hostname(s) stored: %d', $_SERVER['HTTP_HOST'], count($domains)));

            self::$redis->set('domains', json_encode($domains));
        }

        try {
            self::$redis->select($db);
        } catch (Exception $e) {
            self::logger(sprintf('ERROR: could not select database: %d for host: %s', $db, $_SERVER['HTTP_HOST']));
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
            self::logger('fetching content from the cache. key: '.self::$key);

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
            self::logger('rendering page with WordPress');

            header('Cache: storing new data');

            return true;
        }
    }

    /**
     * load config
     *
     */
    public static function load_config()
    {
        self::$config = [
            'REDIS_STATUS'  => true,
            'REDIS_HOST'    => $_SERVER['REDIS_HOST']    ?? '127.0.0.1',
            'REDIS_PORT'    => $_SERVER['REDIS_PORT']    ?? 6379,
            'REDIS_AUTH'    => $_SERVER['REDIS_AUTH']    ?? '',
            'REDIS_WAIT'    => $_SERVER['REDIS_WAIT']    ?? 1,
            'REDIS_QUERY'   => $_SERVER['REDIS_QUERY']   ?? 0,
            'REDIS_EXCLUDE' => $_SERVER['REDIS_EXCLUDE'] ?? [],
        ];

        $file    = $_SERVER['DOCUMENT_ROOT'] . ($_SERVER['REDIS_CONFIG_PATH'] ?? '/wp-content/uploads/redis-config.json');
        $options = @json_decode(@self::simple_crypt(@file_get_contents($file, true), 'd'), true);

        # echo '<pre>pre-load'; print_r($options); # die(); // DEBUG LINE

        if (is_array($options)) {
            foreach ($options as $key => $val) {
                // overwrite config defaults only if environment variable is not set
                if (isset(self::$config[ $key ]) and empty($_SERVER[ $key ])) {
                    self::$config[ $key ] = is_array($val) ? $val : trim($val);
                    # printf("key: [ %s ], val: [ %s ]\n", $key, $val); // DEBUG LINE
                }
            }
        }

        # echo '<pre>post load'; print_r(self::$config); die(); // DEBUG LINE

        return self::$config;
    }

    /*
     * system log
     *
     */
    public static function logger($message)
    {
        self::$cc++;

        if (isset($_SERVER['REDIS_LOG']) && $_SERVER['REDIS_LOG'] == "true") {
            file_put_contents('php://stderr', sprintf("STEP %d: %s\n", self::$cc, $message), FILE_APPEND);
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

        if (in_array($response_code, [ '200', '404' ]) and self::$redis->set(self::$key, $buffer)) {
            self::$redis->set(self::$key.'-CODE', $response_code);
            self::$redis->set(self::$key.'-HEAD', json_encode(headers_list()));

            // log syslog message if cannot store objects in redis
            self::logger('storing content in the cache. page count: '.self::$redis->dbSize());
        } else {
            self::$redis->del(self::$key);
            self::logger('Redis cannot store data. Memory: '.self::$redis->info('used_memory_human'));
        }

        return $buffer;
    }

    /**
     * Encrypt and decrypt
     *
     * @author Nazmul Ahsan <n.mukto@gmail.com>
     * @link http://nazmulahsan.me/simple-two-way-function-encrypt-decrypt-string/
     *
     * @param string $string string to be encrypted/decrypted
     * @param string $action what to do with this? e for encrypt, d for decrypt
     */
    public static function simple_crypt( $string, $action = 'e' ) {
        // you may change these values to your own
        $secret_key = $_SERVER['REDIS_ENCRYPT_KEY']    ?? 'simple_secret_key';
        $secret_iv  = $_SERVER['REDIS_ENCRYPT_SECRET'] ?? 'simple_secret_iv';

        $output         = false;
        $encrypt_method = "AES-256-CBC";
        $key            = hash( 'sha256', $secret_key );
        $iv             = substr( hash( 'sha256', $secret_iv ), 0, 16 );

        if ( $action == 'e' ) {
            $output = base64_encode( openssl_encrypt( $string, $encrypt_method, $key, 0, $iv ) );
        }
        else if( $action == 'd' ){
            $output = openssl_decrypt( base64_decode( $string ), $encrypt_method, $key, 0, $iv );
        }

        return $output;
    }

    /** **/
}
