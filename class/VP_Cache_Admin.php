<?php

class VP_Cache_Admin
{
    const SECTION = 'vp-cache';

    /**
     * Registers admin hooks.
     *
     * Adds a menu item to the admin bar and hooks to clear/flush cache.
     */
    static function init()
    {
        add_action('admin_bar_menu', [static::class, 'admin_bar_menu'], 9999);
        add_action('admin_action_vp_cache_clear', [static::class, 'clear']);
        add_action('admin_action_vp_cache_flush', [static::class, 'flush']);
    }

    /**
     * Adds a "Cache" menu item to the WordPress admin bar.
     *
     * The menu item displays a colored circle (green if caching is enabled, red if disabled)
     * and shows the current number of typed cache entries. It provides a link to clear the cache.
     *
     * @param WP_Admin_Bar $wp_admin_bar The WP_Admin_Bar instance, passed by reference.
     */
    static function admin_bar_menu($wp_admin_bar)
    {
        $stat = VP_Cache::stat();
        $color = VP_CACHE ? '#00FF00' : '#FF0000';
        $args = [
            'id' => 'vp-cache-clear',
            'title' => '<svg width="8" height="8" viewBox="0 0 8 8" xmlns="http://www.w3.org/2000/svg"><circle fill="' . $color . '" cx="4" cy="4" r="4" /></svg> Clear cache (' . $stat['typed'] . ')',
            'href' => admin_url('admin.php?&action=vp_cache_clear'),
        ];
        $wp_admin_bar->add_node($args);
    }

    /**
     * Clears all typed cache entries.
     *
     * @see VP_Cache::clear()
     */
    static function clear()
    {
        VP_Cache::clear();
        wp_redirect($_SERVER['HTTP_REFERER']);
        exit();
    }

    /**
     * Clears all typed and untyped cache entries. Only usable via WP CLI.
     *
     * @see VP_Cache::flush()
     */
    static function flush()
    {
        VP_Cache::flush();
        wp_redirect($_SERVER['HTTP_REFERER']);
        exit();
    }
}
