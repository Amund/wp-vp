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
    $active = is_plugin_active(
        'sqlite-object-cache/sqlite-object-cache.php'
    );
    $color = $active ? '#00FF00' : '#FF0000';
    $wp_admin_bar->add_node([
        'id'    => 'vp-cache-clear',
        'title' => '<svg width="8" height="8" viewBox="0 0 8 8" '
            . 'xmlns="http://www.w3.org/2000/svg">'
            . '<circle fill="' . esc_attr($color) . '" '
            . 'cx="4" cy="4" r="4" /></svg> '
            . 'Vider le cache',
        'href'  => '#',
        'meta'  => [
            'title' => 'Vider le cache',
        ],
    ]);
}, 9999);

add_action('wp_ajax_vp_cache_flush', function () {
    check_ajax_referer('vp_cache_flush', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permission refusée.'], 403);
    }
    global $wp_object_cache;
    if (is_plugin_active('sqlite-object-cache/sqlite-object-cache.php')) {
        $result = $wp_object_cache->flush(true);
        if (false === $result) {
            wp_send_json_error(['message' => 'Erreur lors du vidage du cache.'], 500);
        }
    }
    do_action('vp_cache_flush');
    delete_option('rewrite_rules');
    wp_send_json_success(['message' => 'Cache vidé avec succès.']);
});

function vp_cache_ajax_script()
{
    if (!is_admin_bar_showing()) return;
    if (!current_user_can('manage_options')) return;
    $ajax_url = admin_url('admin-ajax.php');
    $nonce = wp_create_nonce('vp_cache_flush');
?>
    <script>
        document.addEventListener('DOMContentLoaded', () => {

            const button = document.querySelector('#wp-admin-bar-vp-cache-clear > a')
            if (!button) return
            const originalHTML = button.innerHTML
            let loading = false
            let resetTimer

            button.addEventListener('click', async (event) => {
                event.preventDefault()
                if (loading) return
                loading = true
                clearTimeout(resetTimer)
                button.textContent = 'vidage...'
                try {
                    const data = new URLSearchParams({
                        action: 'vp_cache_flush',
                        nonce: <?php echo wp_json_encode($nonce) ?>
                    })
                    const response = await fetch(<?= wp_json_encode($ajax_url) ?>, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: data.toString()
                    })

                    const result = await response.json()
                    if (!response.ok || !result.success) {
                        throw new Error(result.data?.message || 'Erreur AJAX')
                    }
                    button.textContent = 'Cache vidé'
                } catch (error) {
                    console.error('Cache flush:', error)
                    button.textContent = '✕ Cache error'
                    button.title = error.message
                } finally {
                    loading = false
                    resetTimer = setTimeout(() => {
                        button.innerHTML = originalHTML
                        button.title = 'Vider le cache'
                    }, 2000)
                }
            });
        });
    </script>
<?php
}

add_action('admin_footer', 'vp_cache_ajax_script');
add_action('wp_footer', 'vp_cache_ajax_script');

// add_action('delete_post', [static::class, 'clear']);
// add_action('save_post', [static::class, 'clear']);
// add_action('delete_term', [static::class, 'clear']);
// add_action('edit_term', [static::class, 'clear']);
// add_action('wp_create_nav_menu', [static::class, 'clear']);
// add_action('wp_update_nav_menu', [static::class, 'clear']);
// add_action('wp_delete_nav_menu', [static::class, 'clear']);
