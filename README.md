![GitHub Tag](https://img.shields.io/github/v/tag/Amund/wp-vp)
![Static Badge](https://img.shields.io/badge/wordpress-plugin-blue)

# wp-vp

**wp-vp** is a WordPress plugin that provides the base of the Vupar themes: template parts and menus with object cache, breadcrumbs with JSON-LD, automatic registration of blocks, scripts and theme files, and a full cache flush (object cache, rewrite rules, OPcache) from the admin bar or WP-CLI.

## Features

- Template parts rendered as strings, with optional object cache.
- Menus with object cache, aware of the current page (active items stay correct).
- Breadcrumbs, with a schema.org `BreadcrumbList` in JSON-LD.
- Registration of all ACF/Gutenberg blocks of a folder.
- Registration of all JavaScript modules of a folder, versioned by modification time.
- Loading of all PHP files of a folder, in a deterministic order.
- A "Vider le cache" button in the admin bar, and a full flush on `wp cache flush`.

## Requirements

- PHP 8.1 or later
- WordPress 6.1 or later
- A persistent object cache is recommended (e.g. [SQLite Object Cache](https://wordpress.org/plugins/sqlite-object-cache/)): without one, the cache only lasts for the current request.

## Installation

### With Composer

1. Add the repository to your `composer.json` file:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/Amund/wp-vp.git"
        }
    ]
}
```

2. Install the plugin:

```sh
composer require amund/wp-vp
```

The package type is `wordpress-muplugin`: with [Bedrock](https://roots.io/bedrock/), it is installed in `web/app/mu-plugins/` and loaded automatically.

### Manually

Copy the plugin folder into `wp-content/plugins/` and activate it from the "Plugins" menu of the WordPress admin.

It can also be used as a must-use plugin, in `wp-content/mu-plugins/`. WordPress does not load plugins in subfolders of `mu-plugins`, so a loader file is needed, for example `wp-content/mu-plugins/wp-vp.php`:

```php
<?php
require_once __DIR__ . '/wp-vp/wp-vp.php';
```

## Typical theme setup

```php
// functions.php: current language, used in the part and menu cache keys
define('LANG', function_exists('pll_current_language') ? pll_current_language() : 'fr');

// load every PHP file of inc/ (hooks, post types, helpers...)
vp::require_dir(get_template_directory() . '/inc');

// inc/hook/init.php: register every block of block/
add_action('init', function () {
    vp::blocks(get_template_directory() . '/block');
});

// enqueue every JavaScript module of js/
add_action('wp_enqueue_scripts', function () {
    vp::scripts(get_template_directory() . '/js', get_template_directory_uri() . '/js');
});
```

## Usage

### `vp::part($part, $args = [], $cache = false)`

Renders `part/{$part}.php` of the theme with `get_template_part()`, and returns the output as a string. `$args` are available in the template as `$args`.

```php
echo vp::part('card/post', ['id' => $post_id]);

// cached until the next flush
echo vp::part('footer-links', [], true);

// cached for one hour
echo vp::part('instagram-feed', [], HOUR_IN_SECONDS);
```

- The cache key is made of the part name, the language (`LANG` constant, if defined), the current post id and `$args`. Values in `$args` may override the default ones (`part`, `lang`, `post_id`), on purpose: for example, `['post_id' => 0]` shares one cache entry between all pages.
- The cache is opt-in: with a persistent object cache, rendering a part is usually fast enough. Use it for parts that are costly to render (many queries, external data, translations...).
- A missing part renders an empty string. With `WP_DEBUG`, an error notice is displayed and logged instead. A missing part is never cached.
- In the `local` environment, the output is wrapped in `<!--part ...-->` HTML comments, with `(from cache)` when it comes from the cache.

### `vp::menu($menu_location, $context = [])`

Renders the menu assigned to a theme location with `wp_nav_menu()`, and returns it as a string (empty if no menu is assigned).

```php
echo vp::menu('primary', ['lang' => LANG, 'depth' => 2]);

// language switcher (Polylang): links depend on the current post
echo vp::menu('lang', ['lang' => LANG, 'id' => get_the_ID()]);
```

- `$context` is merged into the `wp_nav_menu()` arguments, with these defaults: no container, and a `<ul>` wrapper with `tabindex="0"`. `theme_location` can't be overridden.
- The menu is always cached. The key is made of `$context`, the language and the current page (post, term, author, post type archive, search, 404...), because the output contains the `current-menu-*` classes and `aria-current`. Keys unknown to `wp_nav_menu()` are ignored by it, but still vary the cache key.
- On a typical site, the cache saves 15 to 20 ms per page (walker, `current-*` classes, Polylang and theme filters).

### `vp::breadcrumbs($args = [])`

Returns the breadcrumbs of the current singular page as a render array, or `null` on the front page and on non-singular pages. The array contains an `<ol>` (links, the current page being a `<span aria-current="page">`) and a `<script type="application/ld+json">` with the schema.org `BreadcrumbList`. It is meant to be rendered with the `html::render()` helper of the theme.

```php
echo html::render([
    'tag' => 'nav',
    'class' => 'vp-breadcrumbs',
    'aria-label' => 'Breadcrumb',
    'content' => vp::breadcrumbs(['home_title' => 'Home']),
]);
```

- `$args`: `home_title` (default: `__('Homepage')`) and `home_url` (default: `get_home_url()`).
- Items between the homepage and the current page are added with the `vp_breadcrumbs_ancestors` filter, with `vp::breadcrumbs_item($title, $url = '')` and `vp::breadcrumbs_item_from_post($post)`:

```php
add_filter('vp_breadcrumbs_ancestors', function ($items) {
    if (is_singular('page')) {
        foreach (array_reverse(get_post_ancestors(get_post())) as $ancestor) {
            $items[] = vp::breadcrumbs_item_from_post($ancestor);
        }
    }
    return $items;
});
```

An item without url is rendered as a `<span>`. In JSON-LD, titles are converted to plain text.

### `vp::blocks($dir)`

Registers each subfolder of `$dir` as a block type with `register_block_type()` (each subfolder must contain a `block.json`). The list of folders is cached.

### `vp::scripts($dir, $url)`

Registers and enqueues every `.js` file of `$dir` (recursively) as a JavaScript module, with the id `@vp/{path without .js}` and the file modification time as version. The list is cached.

If the folder contains an `autoload.js` file, the `@vp/autoload` module depends on all the other ones.

### `vp::require_dir($path)`

Requires every `.php` file of `$path` and its subfolders, sorted by path (natural order, case-insensitive), so the load order is the same on every server.

### Other helpers

- `vp::option($name)`: value of `$name` in the `vp-options` option, or `null`.
- `vp::log($data)`: writes `$data` to the PHP error log (arrays and objects with `print_r()`) if `WP_DEBUG` is true.
- `vp::filemap($folder)`: recursive list of the files of a folder, relative paths, folders first, or `false` if empty or missing.

## Cache

All entries are stored in the `vupar` group of the object cache, and are never invalidated individually: the cache is meant to be flushed entirely, by the theme on content changes and on each deployment.

### Flushing

The `vp_cache_flush` action resets the OPcache of the web server, flushes the object cache (`wp_cache_flush()`) and deletes the rewrite rules, which WordPress rebuilds on the next request. It is triggered by:

- the **"Vider le cache"** button of the admin bar, available to users with the `edit_others_posts` capability (editors and above);
- **`wp cache flush`**, after the native command, e.g. at the end of a deployment.

Themes can hook `vp_cache_flush` to flush other caches (CDN...).

The plugin does not flush the cache on content changes, so that each theme can choose its strategy. A simple one:

```php
foreach (['save_post', 'delete_post', 'edit_term', 'delete_term', 'wp_create_nav_menu', 'wp_update_nav_menu', 'wp_delete_nav_menu', 'acf/options_page/save'] as $hook) {
    add_action($hook, fn() => wp_cache_flush());
}
```

### OPcache from WP-CLI

From WP-CLI, `opcache_reset()` only clears the OPcache of the CLI process. The web server is asked to clear its own with a loopback POST request to `admin-ajax.php` (action `vp_opcache_reset`), authenticated by a nonce valid 10 minutes. The site must therefore be able to reach its own url. WP-CLI must run without `--user`, since the nonce is created for the CLI user (anonymous). If the request fails, a warning is displayed and the flush goes on.

### Bypass

The cache is neither read nor written in editing contexts, which must always show fresh content and could render differently from the public site:

- admin screens (front-end ajax requests going through `admin-ajax.php` keep the cache),
- ACF block previews (ajax action `acf/ajax/fetch-block`),
- dynamic block previews (REST route `/wp/v2/block-renderer`),
- post previews and the customizer.

## Hooks

| Hook | Type | Description |
| --- | --- | --- |
| `vp_cache_flush` | action | Full flush. Hook it to flush other caches. |
| `vp_breadcrumbs_ancestors` | filter | Breadcrumb items between the homepage and the current page. |
| `vp_cache_bypass` | filter | Whether the cache is bypassed for the current request (`bool`). |
| `vp_cache_flush_capability` | filter | Capability required to flush the cache from the admin bar (default: `edit_others_posts`). |
| `vp_opcache_remote_reset` | filter | Return `false` to disable the loopback OPcache reset from WP-CLI. |

```php
// only administrators can flush the cache
add_filter('vp_cache_flush_capability', fn() => 'manage_options');

// no loopback request (e.g. the site can't reach itself)
add_filter('vp_opcache_remote_reset', '__return_false');
```
