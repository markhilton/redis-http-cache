<?php

class rediscache {

	public static $class  = 'updated'; // class of admin notice: array('updated' => green, 'error' => 'red', 'update-nag' => 'yellow')
	public static $notice = null;      // admin notice message
	public static $status = null;      // Redis connection status
	public static $redis  = null;      // Redis extension class object
	public static $config = null;      // Redis configuration array
	public static $info   = array();   // Redis statistic info array


	/**
	 * check if required code snippet has been installed in WordPress index.php
	 *
	 */
	public static function check_snippet() {
		if (defined('_REDIS_LIGHT_CACHE_PREPEND')) {
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
            'REDIS_HOST'    => $_ENV['REDIS_HOST']    ?? '127.0.0.1',
            'REDIS_PORT'    => $_ENV['REDIS_PORT']    ?? 6379,
            'REDIS_AUTH'    => $_ENV['REDIS_AUTH']    ?? '',
            'REDIS_WAIT'    => $_ENV['REDIS_WAIT']    ?? 1,
            'REDIS_QUERY'   => $_ENV['REDIS_QUERY']   ?? 0,
            'REDIS_EXCLUDE' => $_ENV['REDIS_EXCLUDE'] ?? [],
        ];

        $file    = $_SERVER['DOCUMENT_ROOT'] . ($_ENV['REDIS_CONFIG_PATH'] ?? '/wp-content/uploads/redis-config.json');
        $options = @json_decode(@self::simple_crypt(@file_get_contents($file, true), 'd'), true); 
        
        # echo '<pre>pre-load'; print_r($options); # die(); // DEBUG LINE

        if (is_array($options)) {
	        foreach ($options as $key => $val) {
	        	// overwrite config defaults only if environment variable is not set
	        	if (isset(self::$config[ $key ]) and empty($_ENV[ $key ])) {
	        		self::$config[ $key ] = is_array($val) ? $val : trim($val);
	        		# printf("key: [ %s ], val: [ %s ]\n", $key, $val); // DEBUG LINE
	        	}
	        }        	
        }

		# echo '<pre>post load'; print_r(self::$config); die(); // DEBUG LINE

        return self::$config;
	}



	/**
	 * connect to Redis
	 * 
	 */
	public static function connect()
	{
		self::$config = self::load_config();

		if (! self::$config['REDIS_STATUS']) {
	        rediscache::$class  = 'error';
	        rediscache::$notice = 'Redis status set to <b>OFF</b>. You can change it here: <a href="/wp-admin/admin.php?page=rediscache&tab=config">configuration</a>';

	        add_action('admin_notices', [ 'rediscache', 'admin_notice' ]);			

	        return false;
		}

        try {
            require_once 'predis/autoload.php';

            self::$redis = new Predis\Client([
                'scheme'  => 'tcp',
                'host'    => self::$config['REDIS_HOST'],
                'port'    => self::$config['REDIS_PORT'],
                'timeout' => self::$config['REDIS_WAIT'],
            ]);
			
            // cannot for the love of God catch this exception - please HELP!
			if (trim(self::$config['REDIS_AUTH']) != '') {
				# self::$redis->auth( self::$config['REDIS_AUTH'] );
			}

			self::$redis->connect();
            self::$config['REDIS_STATUS'] = 1;
        }

        catch (Predis\Connection\ConnectionException $exception) {
	        // throw message - cannot connect with redis
	        rediscache::$class  = 'error';
	        rediscache::$notice = 'Cannot connect to Redis storage engine. Please check your <a href="/wp-admin/admin.php?page=rediscache&tab=config">configuration</a>';

	        add_action('admin_notices', [ 'rediscache', 'admin_notice' ]);

	        self::$config['REDIS_STATUS'] = 0;
            self::logger($e);

            return false;
        }



	    // connect to Redis storage
        self::$redis->select(0);

        $domains = json_decode(self::$redis->get('domains'), true);

        // fetch redis database ID for current host
        if (isset($domains[ $_SERVER['HTTP_HOST'] ]['id'])) {
            $db = $domains[ $_SERVER['HTTP_HOST'] ]['id'];

            self::logger(sprintf('current domain [id: %d]: %s found in cache. Total hostname(s) stored: %d', 
            	$db, $_SERVER['HTTP_HOST'], count($domains)));
        }

        // create new redis database if current host does not have one
        else {
            if (! is_array($domains)) {
                $domains = [];
            }

            $db = count($domains) + 1;

            $domains[ $_SERVER['HTTP_HOST'] ]['id'] = $db;

            self::logger(sprintf('current domain: %s does not exist in cache - creating. Total hostname(s) stored: %d', 
            	$_SERVER['HTTP_HOST'], count($domains)));

            self::$redis->set('domains', json_encode($domains));
        }

        try {
            self::$redis->select($db);    
			self::$info['pages'] = 0;
	        self::$info          = self::$redis->info();
	        self::$status        = self::$config['status'];
	        self::$info['pages'] = (int) self::$redis->dbSize();
        } 

        catch (Exception $e) {
            self::logger(sprintf('ERROR: could not select database: %d for host: %s', $db, $_SERVER['HTTP_HOST']));
            return false;
        }
	}


    /*
     * system log
     *
     */
    public static function logger($message)
    {
        if (isset($_ENV['REDIS_LOG']) && $_ENV['REDIS_LOG'] == "true") {
            file_put_contents('php://stderr', sprintf("%s\n", $message), FILE_APPEND);    
        }
    }


	/**
	 * render admin notice
	 * 
	 */
	public static function admin_notice()
	{
	    if (trim(self::$notice) != '') {
	        printf('<div class="%s" style="margin: 20px 0 20px 0"><p>%s</p></div>', self::$class, self::$notice);
	    }
	}



	/**
	 * process user POST actions
	 * 
	 */
	public static function post_actions() {
        $file    = $_SERVER['DOCUMENT_ROOT'] . ($_ENV['REDIS_CONFIG_PATH'] ?? '/wp-content/uploads/redis-config.json');
        $options = @json_decode(@self::simple_crypt(@file_get_contents($file, true), 'd'), true);

		// ACTION: update configuration file
		//
	    if (isset($_POST['update'])) {
	        // create default options
	        foreach ($_POST as $key => $val) {
                $options[ $key ] = trim($val);
	        }

	        if (@file_put_contents($file, @self::simple_crypt(json_encode($options, JSON_PRETTY_PRINT), 'e'))) {
	            // throw message - redis config updated sucessfully
		        rediscache::$class  = 'updated';
		        rediscache::$notice = 'Configuration has been updated';

		        add_action('admin_notices', [ 'rediscache', 'admin_notice' ]);
	        } else {
	            // throw error - cannot save config file: redis-config.json
		        rediscache::$class  = 'error';
		        rediscache::$notice = 'Could not update configuration file. &nbsp;Please make sure this file is <b>writable</b>: &nbsp;' . $file;

		        add_action('admin_notices', [ 'rediscache', 'admin_notice' ]);
	        }
	    }
	

		// ACTION: update URL exclusions
		//
	    if (isset($_POST['REDIS_EXCLUDE'])) {
	        $options['REDIS_QUERY']   = $_POST['REDIS_QUERY'];
	        $options['REDIS_EXCLUDE'] = explode("\r\n", $_POST['REDIS_EXCLUDE']);

	        if (@file_put_contents($file, @self::simple_crypt(json_encode($options), 'e'))) {
	            // throw message - redis config updated sucessfully
		        rediscache::$class  = 'updated';
		        rediscache::$notice = 'Exclusion URL list has been updated';

		        add_action('admin_notices', [ 'rediscache', 'admin_notice' ]);
	        } else {
	            // throw error - cannot save config file: redis-config.json
		        rediscache::$class  = 'error';
		        rediscache::$notice = 'Could not update configuration file. &nbsp;Please make sure this file is <b>writable</b>: &nbsp;' . $file;

		        add_action('admin_notices', [ 'rediscache', 'admin_notice' ]);
	        }
	    }


		// ACTION: flush cache engine
		//
	    if (isset($_POST['flush'])) {
			if (self::$status != 'ON') {
		        rediscache::$class  = 'error';
		        rediscache::$notice = 'Redis connection error - cannot this operation';
			} else {
			    $domain = self::set_database();

				self::$redis->flushDb();

		        rediscache::$class  = 'updated';
		        rediscache::$notice = 'Domain cache flushed';
			}

	        add_action('admin_notices', [ 'rediscache', 'admin_notice' ]);
	    }


		// END of ACTIONS
		//
	}



	/**
	 * Flush page cache on post update action
	 *
	 */
	function flush_page($post_id)
	{
		global $wpdb;

		if (self::$status != 'ON') {
			return false;
		}


	    $permalink = get_permalink($post_id);
	    $domain    = self::set_database();
		$blog_id   = get_current_blog_id();
		$query     = 'SELECT path FROM '.TABLE_PREFIX.'blogs WHERE blog_id='.$blog_id;
		$path      = $wpdb->get_var($query);


	    // build URL key
	    $url = $domain.str_replace($path, '/', parse_url($permalink, PHP_URL_PATH));
	    $key = md5($url);

	    self::$redis->del($key); # die($key.' - '.$url); // DEBUG
	}



	/**
	 * Flush page cache on post update action
	 *
	 */
	function set_database()
	{
		global $wpdb;

		if (self::$status != 'ON') {
			return false;
		}

		// determine blog domain to flush
		$blog_id = get_current_blog_id();
		$query   = 'SELECT domain FROM '.TABLE_PREFIX.'domain_mapping WHERE blog_id='.$blog_id;
		$domain  = $wpdb->get_var($query);

		// fix for main blog domain flush domain selection
		if (trim($domain) == '')
		{
			$domain = $_SERVER['HTTP_HOST'];
		}

	    self::$redis->select(0);
	    $domains = json_decode(self::$redis->get('domains'), true);

	    // fetch redis database ID for current host
	    if (isset($domains[ $domain ]['id'])) {
	        $db = $domains[ $domain ]['id'];
	    }

	    // create new redis database if current host does not have one
	    else {
	        if (! is_array($domains)) {
	            $domains = [];
	        }

	        $db = count($domains) + 1;

	        $domains[ $domain ]['id'] = $db;

	        self::$redis->set('domains', json_encode($domains));
	    }

	    self::$redis->select($db);

	    return $domain;
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
	    $secret_key = $_ENV['REDIS_ENCRYPT_KEY']    ?? 'simple_secret_key';
	    $secret_iv  = $_ENV['REDIS_ENCRYPT_SECRET'] ?? 'simple_secret_iv';
	 
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
}
