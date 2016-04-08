<?php
/*
Plugin Name: Redis Light Speed Cache
Plugin URI: http://wordpress.org/extend/plugins/redis-light-speed-cache/
Description: Redis Light Speed Cache Engine for WordPress puts ultra fast Redis Memory cache engine in front of WordPress installation. Cached pages are served directly from the memory without engagine WordPress. Typical cached page serving speeds below 100ms!
Version: 1.0
Author: Mark Hilton
Author URI: http://www.crunchgeek.com/
*/


// add page cache flush on post save / update
add_action('save_post', [ 'rediscache', 'flush_page']);

register_activation_hook(  __FILE__, 'activate_rediscache');
register_deactivation_hook(__FILE__, 'deactive_rediscache');

if (is_admin()) {
    add_action('admin_init', 'admin_init_rediscache');
    add_action('admin_menu', 'admin_menu_rediscache');
}




/** 
 * activate plugin - add snippet to index.php
 *
 */
function activate_rediscache()
{
    require_once 'redis.class.php';

    rediscache::install_snippet();
}

/** 
 * de-activate plugin - remove snippet to index.php
 *
 */
function deactive_rediscache()
{
    require_once 'redis.class.php';

    rediscache::remove_snippet();
}

/** 
 * admin interface init
 *
 */
function admin_init_rediscache()
{
    require_once 'redis.class.php';

    rediscache::check_snippet();
    rediscache::connect();

    /** 
     * Action to update Redis connection settings
     * 
     */
    if ($_POST) {
        rediscache::post_actions();
    }
}

/** 
 * admin interface - add Redis menu 
 *
 */
function admin_menu_rediscache()
{
    add_menu_page('Redis Light Speed Cache', 'Redis Cache', 4, 'rediscache', 'options_page_rediscache', 'dashicons-clock', 4);
}

/** 
 * admin interface - load template
 *
 */
function options_page_rediscache()
{
    rediscache::connect();

    require 'navigation.php';
}
