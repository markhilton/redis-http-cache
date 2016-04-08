---
title: Redis HTTP cache
layout: post
author: markhilton
permalink: /redis-http-cache/
source-id: 1606Hd-aKJTyRyBtq1bXnrdyFh8Yv9pj2fD2LiE5u_l0
published: true
---
Redis HTTP cache

Redis cache engine for WordPress sites its two components HTTP caching system that speeds up WordPress page serving by leveraging Redis memory cache storage.

After installation HTML content of website pages are being stored in Redis memory engine and served directly from memory on consequential HTTP requests. 

## Redis PHP prepend component

If you manage multiple WordPress vhosts on single server this component is to be installed in php.ini defined "include_path" to be auto prepend to every php request. In case of WordPress front end most of requests are routed to /index.php.

After altering php configuration, which can be done in php.ini or individually per vhost in .htaccess for Apache web servers or php-fpm.conf as follows: 

php_admin_value[auto_prepend_file] = "redis-http-cache/prepend.php"

This will trigger prepend.php script every PHP request. Prepend.php will then determine if required REDIS environment variable is set to "ON" and if the request is routed to /index.php file. If both of those conditions are met then engine.php is triggered in order to either store or serve content to/from Redis memory cache. 

### Installation

Redis HTTP cache WordPress plugin component is not required to incorporate cache engine into WordPress site. The PHP auto prepend component will capture website HTML content output, automatically store it into memory and serve it on all consequential requests.

However WordPress plugin is required in order to manage no cache page exceptions per individual site as well as flushing cache storage for individual site.

### Debug

Redis HTTP cache will log its activity into syslog if detects presence of /tmp/.redis.log file. The log information is extensive and may eat up hard drive space quickly, so it is only intended for problem debugging purposes.

#### Start debugging 

touch /tmp/.redis.log 

to start logging Redis HTTP cache messages into syslog. To preview messages run:

tail -f /var/log/messages or tail -f /var/log/syslog

#### Stop debugging 

rm /tmp/.redis.log 

to remove Redis HTTP cache log trigger and stop logging messages into syslog. 

## Redis PHP WordPress component

Redis HTTP cache WordPress component is an optional WordPress plugin that expands cache management via WordPress admin backend in order to allow to set up URL exceptions to disable caching functionality for certain URLs and ability flush cache for individual site.

### Installation

1. Copy redis-wp-plugin to your WordPress /wp-content/plugins/ directory.

2. Activate plugin

### Screenshot

![image alt text]({{ site.url }}/public/BMbpD6rCZ1qoniF20u7H2A_img_0.png)

### Debug

Redis HTTP cache engine appends HTTP headers to broadcast engine activity:

* cache disengaged

* no cache request

* page excluded

* storing new data

* fetched from cache

You can see those messages when you open browser inspector and investigate response headers as on attached image:

 ![image alt text]({{ site.url }}/public/BMbpD6rCZ1qoniF20u7H2A_img_1.png)

