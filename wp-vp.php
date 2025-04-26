<?php

/**
 * Plugin Name: Vupar
 * Plugin URI:        https://github.com/Amund/wp-vp
 * Description:       Base plugin to enhance WordPress templating system and cache.
 * Requires at least: 6.1
 * Requires PHP:      8.1
 * Author:            Dimitri Avenel
 * License:           MIT
 */

if (!defined('ABSPATH')) {
    exit();
}

require_once 'class/VP.php';

// add a clear cache button to the admin bar for sqlite-object-cache plugin
add_action('admin_bar_menu', function ($wp_admin_bar) {
    global $wp_object_cache;
    $color = '#FF0000';
    if (is_plugin_active('sqlite-object-cache/sqlite-object-cache.php')) {
        $color = '#00FF00';
    }

    $args = [
        'id' => 'vp-cache-clear',
        'title' => '<svg width="8" height="8" viewBox="0 0 8 8" xmlns="http://www.w3.org/2000/svg"><circle fill="' . $color . '" cx="4" cy="4" r="4" /></svg> Clear cache',
        'href' => admin_url('admin.php?&action=vp_cache_flush'),
    ];
    $wp_admin_bar->add_node($args);
}, 9999);

add_action('admin_action_vp_cache_flush', function () {
    global $wp_object_cache;
    if (is_plugin_active('sqlite-object-cache/sqlite-object-cache.php')) {
        $wp_object_cache->flush(true);
    }
    wp_redirect($_SERVER['HTTP_REFERER']);
    exit();
});

// add_action('delete_post', [static::class, 'clear']);
// add_action('save_post', [static::class, 'clear']);
// add_action('delete_term', [static::class, 'clear']);
// add_action('edit_term', [static::class, 'clear']);
// add_action('wp_create_nav_menu', [static::class, 'clear']);
// add_action('wp_update_nav_menu', [static::class, 'clear']);
// add_action('wp_delete_nav_menu', [static::class, 'clear']);
