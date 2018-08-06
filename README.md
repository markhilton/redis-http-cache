# Redis HTTP cache

Redis cache engine for WordPress sites its two components HTTP caching system that speeds up WordPress page serving by leveraging Redis memory cache storage.

After installation HTML content of website pages are being stored in Redis memory engine and served directly from memory on consequential HTTP requests. 

## Redis PHP prepend component

If you manage multiple WordPress vhosts on single server this component is to be installed in php.ini defined "include_path" to be auto prepend to every php request. In case of WordPress front end most of requests are routed to /index.php.

After altering php configuration, which can be done in php.ini or individually per vhost in .htaccess for Apache web servers or php-fpm.conf as follows: 

```bash
php_admin_value[auto_prepend_file] = "redis-http-cache/prepend.php"
```

This will trigger prepend.php script every PHP request. Prepend.php will then determine if required REDIS environment variable is set to "ON" and if the request is routed to /index.php file. If both of those conditions are met then engine.php is triggered in order to either store or serve content to/from Redis memory cache. 

### Environment variables

If defined, will render WordPress configuration variable read-only.

```env
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_AUTH=null
REDIS_WAIT=1
REDIS_LOG=0
REDIS_QUERY=1
REDIS_EXCLUDE=base64_encoded_json_array_of_urls
REDIS_CONFIG_PATH=/wp-content/uploads/redis-config.json
REDIS_ENCRYPT_KEY=simple_secret_key
REDIS_ENCRYPT_SECRET=simple_secret_iv
```

### Installation

Redis HTTP cache WordPress plugin component is not required to incorporate cache engine into WordPress site. The PHP auto prepend component will capture website HTML content output, automatically store it into memory and serve it on all consequential requests.

However WordPress plugin is required in order to manage no cache page exceptions per individual site as well as flushing cache storage for individual site.

## Redis PHP WordPress component

Redis HTTP cache WordPress component is an optional WordPress plugin that expands cache management via WordPress admin backend in order to allow to set up URL exceptions to disable caching functionality for certain URLs and ability flush cache for individual site.

### Installation

1. Copy redis-wp-plugin to your WordPress /wp-content/plugins/ directory.

2. Activate plugin

### Screenshot

![image alt text](https://github.com/markhilton/redis-http-cache/blob/master/redis-wp-plugin/screenshot-1.png)

### Debug

Redis HTTP cache engine appends HTTP headers to broadcast engine activity:

* cache disengaged

* no cache request

* page excluded

* storing new data

* fetched from cache

You can see those messages when you open browser inspector and investigate response headers as on attached image:

 ![image alt text](https://raw.githubusercontent.com/markhilton/redis-http-cache/master/redis-wp-plugin/screenshot-2.png)
