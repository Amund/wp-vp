<?php

/**
 * Plugin Name: Vupar
 * Plugin URI:        https://github.com/Amund/wp-vp
 * Description:       Base plugin to enhance WordPress templating system and cache.
 * Version:           1.0.0
 * Requires at least: 6.1
 * Requires PHP:      8.1
 * Author:            Dimitri Avenel
 * License:           MIT
 */

if (!defined('ABSPATH')) {
    exit();
}

$cache_path = [sys_get_temp_dir(), 'vp-cache', hash('crc32c', get_bloginfo('url'))];
define('VP_CACHE_PATH', implode(DIRECTORY_SEPARATOR, $cache_path));
defined('VP_CACHE') ?: define('VP_CACHE', true);

require_once 'class/VP.php';
require_once 'class/VP_Cache.php';

add_action('plugins_loaded', function () {
    if (current_user_can(vp::CAPABILITY) && !wp_doing_ajax()) {
        require_once 'class/VP_Cache_Admin.php';
        VP_Cache_Admin::init();
    }
});

// wp-cli
if (defined('WP_CLI') && WP_CLI) {

    require_once 'class/VP_Cache_CLI.php';

    add_action('cli_init', function () {
        WP_CLI::add_command('vp-cache', 'VP_Cache_CLI');
    });
}
