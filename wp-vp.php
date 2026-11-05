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


add_action('vp_cache_flush', function () {
    // opcache first: caches and rewrite rules must be rebuilt by fresh code
    vp_opcache_reset();
    wp_cache_flush();
    delete_option('rewrite_rules');
});

/**
 * Resets the web server OPcache.
 *
 * In CLI (wp cache flush, at the end of a deployment), opcache_reset() only clears the CLI's own
 * OPcache: the web server is asked to clear its own with a loopback request, authenticated by a
 * nonce valid 10 minutes. The nonce is created for the CLI user, so wp-cli must run without --user.
 * The loopback request can be disabled with the `vp_opcache_remote_reset` filter.
 */
function vp_opcache_reset(): void
{
    if (!defined('WP_CLI') || !WP_CLI) {
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
        return;
    }

    if (!apply_filters('vp_opcache_remote_reset', true)) return;

    $response = wp_remote_post(admin_url('admin-ajax.php'), [
        'body' => [
            'action' => 'vp_opcache_reset',
            'nonce' => wp_create_nonce('vp_opcache_reset'),
        ],
        'sslverify' => wp_get_environment_type() !== 'local',
    ]);

    if (wp_remote_retrieve_response_code($response) !== 200) {
        $error = is_wp_error($response)
            ? $response->get_error_message()
            : 'HTTP ' . wp_remote_retrieve_response_code($response);
        WP_CLI::warning('OPcache du serveur web non vidé : ' . $error);
    }
}

add_action('wp_ajax_nopriv_vp_opcache_reset', function () {
    $nonce = $_POST['nonce'] ?? '';
    if (!is_string($nonce) || !wp_verify_nonce($nonce, 'vp_opcache_reset')) {
        wp_send_json_error('FORBIDDEN', 403);
    }
    if (function_exists('opcache_reset')) {
        opcache_reset();
    }
    wp_send_json_success();
});

add_filter('nonce_life', function ($life, $action = -1) {
    return $action === 'vp_opcache_reset' ? 10 * MINUTE_IN_SECONDS : $life;
}, 10, 2);

// capability required to flush the cache (editors and above by default)
function vp_cache_flush_capability(): string
{
    return (string) apply_filters('vp_cache_flush_capability', 'edit_others_posts');
}

// add a clear cache button to the admin bar
add_action('admin_bar_menu', function ($wp_admin_bar) {
    if (!current_user_can(vp_cache_flush_capability())) return;
    $wp_admin_bar->add_node([
        'id'    => 'vp-cache-clear',
        'title' => 'Vider le cache',
        'href'  => '#',
    ]);
}, 9999);

add_action('wp_ajax_vp_cache_flush', function () {
    check_ajax_referer('vp_cache_flush', 'nonce');
    if (!current_user_can(vp_cache_flush_capability())) {
        wp_send_json_error(['message' => 'Forbidden'], 403);
    }
    do_action('vp_cache_flush');
    wp_send_json_success(['message' => 'Ok']);
});

function vp_cache_ajax_script()
{
    if (!is_admin_bar_showing()) return;
    if (!current_user_can(vp_cache_flush_capability())) return;
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

// wp cache flush: run the full flush after the native command (skipped if it fails)
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_hook('after_invoke:cache flush', function () {
        do_action('vp_cache_flush');
        WP_CLI::success('Cache Vupar vidé.');
    });
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
