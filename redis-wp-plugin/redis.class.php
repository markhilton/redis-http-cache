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

        $check   = false;
	    $index   = @file('../index.php');
        $snippet = "@include 'wp-content/plugins/redis-light-speed-cache/engine.php';";

        foreach ($index as $key => $val) {
            if (trim($val) == $snippet) {
                $check = true;
            }
        }

        if ($check === false) {
            self::$class  = 'error';
            self::$notice = 'Redis cache snippet not detected! &nbsp; Please install <a href="/wp-admin/admin.php?page=rediscache&tab=setup">code snippet</a> to start leveraging Redis cache engine.';

            add_action('admin_notices', [ 'rediscache', 'admin_notice' ]);
        }

        return $check;
	}


	/**
	 * load config
	 *
	 */
	public static function load_config()
	{
        $config = __DIR__.'/config.json';

        if ($config = @file_get_contents($config)) {
            self::$config = json_decode($config, true);
        }

        if (! is_array(self::$config)) {
            self::$config = self::defaults();
        }
	}



	/**
	 * load config
	 *
	 */
	public static function defaults()
	{
        return [
        	'host'    => 'redis.host',
        	'port'    => 6379,
        	'timeout' => 1,
        ];
	}



	/**
	 * connect to Redis
	 *
	 */
	public static function connect()
	{
	    try {
	    	if (extension_loaded('redis')) {
		    	self::load_config();
		        self::$redis = new Redis();

		        $connect = @self::$redis->connect(self::$config['host'], self::$config['port'], self::$config['timeout']);

		        if (trim(self::$config['security']) != '') {
		            self::$redis->auth(self::$config['security']);
		        }
		    } else {
		    	self::$status = 'OFF';
		    }
	    }
	    // terminate script if cannot connect to Redis server
	    // and gracefully fall back into regular WordPress
	    catch (Exception $e) {
	        self::$status = 'OFF';
	    }


	    // connect to Redis storage
	    if ($connect) {
	        self::$redis->select(0);

	        $domains = json_decode(self::$redis->get('domains'), true);

	        // make sure $domains is an array even if empty
	        if (! is_array($domains)) {
	        	$domains = [];
	        }

			self::$info['pages'] = 0;
	        self::$info          = self::$redis->info();
	        self::$status        = self::$config['status'];

			if (defined('SUBDOMAIN_INSTALL') && SUBDOMAIN_INSTALL !== true) {
				global $wpdb;

				$multisite = $wpdb->get_col('SELECT domain FROM ' . $wpdb->prefix . 'domain_mapping WHERE blog_id='.get_current_blog_id());

				if (is_array($multisite) and count($multisite) < 1) {
					$multisite = [ $_SERVER['HTTP_HOST'] ];
				}

				foreach ($multisite as $site) {
					if (array_key_exists($site, $domains)) {
						$db = $domains[ $site ]['id'];

			            self::$redis->select($db);
			            self::$info['pages'] = (int) self::$redis->dbSize();
					}
				}
			} else if (isset($domains[ $domain ]['id'])) {
	            $db = $domains[ $domain ]['id'];

	            self::$redis->select($db);
	            self::$info['pages'] = (int) self::$redis->dbSize();
	        }
	    } else {
	        // throw message - cannot connect with redis
	        rediscache::$class  = 'error';
	        rediscache::$notice = 'Cannot connect to Redis storage engine. Please check your <a href="/wp-admin/admin.php?page=rediscache&tab=config">configuration</a>';

	        add_action('admin_notices', [ 'rediscache', 'admin_notice' ]);

	        self::$status = 'OFF';
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

		// ACTION: update configuration file
		//
	    if (isset($_POST['update'])) {
	        $options = [
	            'host' => null, 'port' => null, 'timeout' => null, 'security' => null, 'status' => 'OFF', 'query' => 'YES', 'exclude' => [],
	        ];

	        // create default options
	        foreach ($_POST as $key => $val) {
	            if (array_key_exists($key, $options)) {
	                $options[$key] = trim($val);
	            }
	        }

	        if (@file_put_contents(__DIR__.'/config.json', json_encode($options, JSON_PRETTY_PRINT))) {
	            // throw message - redis config updated sucessfully
		        rediscache::$class  = 'updated';
		        rediscache::$notice = 'Configuration has been updated';

		        add_action('admin_notices', [ 'rediscache', 'admin_notice' ]);
	        } else {
	            // throw error - cannot save config file: config.json
		        rediscache::$class  = 'error';
		        rediscache::$notice = 'Could not update configuration file. &nbsp;Please make sure this file is <b>writable</b>: &nbsp;'.__DIR__.'/config.json';

		        add_action('admin_notices', [ 'rediscache', 'admin_notice' ]);
	        }
	    }


		// ACTION: update URL exclusions
		//
	    if (isset($_POST['exclude'])) {

	        $options = json_decode(@file_get_contents(__DIR__.'/config.json'), true);

	        $options['query']   = $_POST['query'];
	        $options['exclude'] = explode("\r\n", $_POST['exceptions']);

	        if (@file_put_contents(__DIR__.'/config.json', json_encode($options, JSON_PRETTY_PRINT))) {
	            // throw message - redis config updated sucessfully
		        rediscache::$class  = 'updated';
		        rediscache::$notice = 'Exclusion URL list has been updated';

		        add_action('admin_notices', [ 'rediscache', 'admin_notice' ]);
	        } else {
	            // throw error - cannot save config file: config.json
		        rediscache::$class  = 'error';
		        rediscache::$notice = 'Could not update configuration file. &nbsp;Please make sure this file is <b>writable</b>: &nbsp;'.__DIR__.'/config.json';

		        add_action('admin_notices', [ 'rediscache', 'admin_notice' ]);
	        }
	    }


		// ACTION: install code snippet
		//
	    if (isset($_POST['install']) and $_POST['install'] == 'Insert Snippet') {
	        self::install_snippet();
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
	 * Install plugin snippet
	 *
	 */
	function install_snippet()
	{
        if (is_writable('../index.php')) {
            $output  = array();
            $content = @file('../index.php');

            foreach ($content as $line) {
                $output[] = $line;

                if (substr(trim($line), 0, 5) == '<?php') {
                    $output[] = "\r\n@include 'wp-content/plugins/redis-light-speed-cache/engine.php';\r\n\r\n";
                }
            }

            if (rename('../index.php', __DIR__.'/index.orig') ) {

                if (file_put_contents('../index.php', join("", $output))) {
			        rediscache::$class  = 'updated';
			        rediscache::$notice = 'Redis cache code snippet has been sucessfully inserted';

			        add_action('admin_notices', [ 'rediscache', 'admin_notice' ]);
                } else {
			        rediscache::$class  = 'error';
			        rediscache::$notice = 'Could not update WordPress index.php';

			        add_action('admin_notices', [ 'rediscache', 'admin_notice' ]);
                }

            } else {
		        rediscache::$class  = 'error';
		        rediscache::$notice = 'Could not create index.php backup';

		        add_action('admin_notices', [ 'rediscache', 'admin_notice' ]);
            }

        } else {
	        rediscache::$class  = 'error';
	        rediscache::$notice = 'WordPress index.php is not writable';

	        add_action('admin_notices', [ 'rediscache', 'admin_notice' ]);
        }
	}


	/**
	 * Install plugin snippet
	 *
	 */
	function remove_snippet()
	{
        if (is_writable('../index.php')) {
            if (rename(__DIR__.'/index.orig', '../index.php')) {
		        rediscache::$class  = 'updated';
		        rediscache::$notice = 'Original index.php has been restored';

		        add_action('admin_notices', [ 'rediscache', 'admin_notice' ]);
            }

            elseif ($content = @file_get_contents('../index.php')) {

	            $snippet = "\r\n@include 'wp-content/plugins/redis-light-speed-cache/engine.php';\r\n\r\n";
	            $content = str_replace($snippet, '', $content);

		        rediscache::$class  = 'updated';
		        rediscache::$notice = 'Redis cache code snippet has been sucessfully removed';

		        add_action('admin_notices', [ 'rediscache', 'admin_notice' ]);
            }

            else {
		        rediscache::$class  = 'error';
		        rediscache::$notice = 'Could not automatically restore original index.php';

		        add_action('admin_notices', [ 'rediscache', 'admin_notice' ]);
            }

        } else {
	        rediscache::$class  = 'error';
	        rediscache::$notice = 'WordPress index.php is not writable';

	        add_action('admin_notices', [ 'rediscache', 'admin_notice' ]);
        }
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

	    self::$redis->delete($key); # die($key.' - '.$url); // DEBUG
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
}
