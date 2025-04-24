<?php

class vp
{
    const PAGE = 'vp';
    const OPTIONS = 'vp-options';
    const CAPABILITY = 'manage_options';

    /**
     * Process worpress template part
     *
     * @param string $part Part path, without part folder and without extension
     * @param array $args Arguments added to template part
     * @param boolean $cache Using cache or not, or a cache duration in seconds
     * @return string Template part after compilation
     */
    static function part(string $part, array $args = [], bool|int $cache = false): string
    {
        $cache = $cache === true ? 0 : $cache;
        $useCache = VP_CACHE && $cache !== false && !is_admin() ?? false;
        $content = false;
        $fromCache = false;
        if (!is_array($args)) {
            $args = [];
        }

        // check in cache
        $name = '';
        if (class_exists('VP_Cache') && $useCache) {
            // complete context of part
            $args['part'] = $part;
            $args['lang'] = defined('LANG') ? constant('LANG') : '';
            $post_id = get_the_ID();
            if ($post_id) {
                $args['post_id'] = $post_id;
            }
            ksort($args);
            $name = hash('sha1', json_encode($args));
            $content = VP_Cache::get($name, 'part');
            if ($content !== false) {
                $fromCache = true;
            }
        }

        if ($content === false) {
            // process part
            ob_start();
            $result = get_template_part('part/' . $part, null, $args);
            $content = ob_get_contents();
            ob_end_clean();

            // error: part not found
            if ($result === false) {
                $content = '<div class="error notice notice-error"><p>part "' . $part . '" not found.</p></div>';
            }

            // set cache
            if (class_exists('VP_Cache') && $useCache && $result !== false && $content !== '0' && !empty($content)) {
                VP_Cache::set($name, $content, 'part');
            }
        }

        // debug in dev
        if ($content !== '0' && !empty($content) && wp_get_environment_type() === 'local') {
            $content = strtr('<!-- {part}{cache} -->{content}<!-- /{part}{cache} -->', [
                '{part}' => $part,
                '{content}' => $content,
                '{cache}' => $fromCache ? ' (from cache)' : '',
            ]);
        }

        return $content;
    }

    static function menu(string $menu_location, array $ctx = []): string
    {
        $cache = VP_CACHE;
        $menu = false;
        $name = '';
        if ($cache) {
            $ctx = ['location' => $menu_location, ...$ctx];
            ksort($ctx);
            $name = hash('crc32c', json_encode($ctx));
            $name = $menu_location . '-' . $name;
            $menu = VP_Cache::get($name, 'menu');
        }
        if ($menu === false) {
            ob_start();
            if (has_nav_menu($menu_location)) {
                wp_nav_menu([
                    'theme_location' => $menu_location,
                    'container' => false,
                    'items_wrap' => '<ul class="%2$s" id="%1$s" tabindex="0">%3$s</ul>',
                ]);
            }
            $menu = ob_get_clean();
            if ($cache && !empty($menu)) {
                VP_Cache::set($name, $menu, 'menu');
            }
        }
        return $menu;
    }

    /**
     * Creates a breadcrumb item with the given title and url.
     *
     * @param string $title The title of the breadcrumb item.
     * @param string $url The url of the breadcrumb item, defaults to an empty string.
     * @return stdClass The created breadcrumb item.
     */
    static function breadcrumbs_item(string $title, string $url = ''): stdClass
    {
        $item = new stdClass();
        $item->title = $title;
        $item->url = $url;
        return $item;
    }

    /**
     * Returns a breadcrumb item for the given post object.
     *
     * @param WP_Post|integer $post The post object or its ID.
     * @return stdClass A breadcrumb item with title and url properties.
     */
    static function breadcrumbs_item_from_post($post): stdClass
    {
        $post = get_post($post);
        $title = get_the_title($post);
        $url = get_permalink($post);
        if (!$url) {
            $url = '';
        }
        return vp::breadcrumbs_item($title, $url);
    }

    /**
     * Converts an array of breadcrumb items into a json string of schema.org
     * BreadcrumbList.
     *
     * @param array $items Array of breadcrumb items, each item being an object with
     *                     properties `title` and `url`.
     * @return string The json string of schema.org BreadcrumbList.
     */
    static function breadcrumbs_json($items): string
    {
        if (is_array($items) && count($items) > 0) {
            $list = [];
            foreach ($items as $i => $item) {
                $list[] = [
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'name' => $item->title,
                    'item' => [
                        '@type' => 'WebPage',
                        '@id' => $item->url,
                    ],
                ];
            }
            $json = [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => $list,
            ];
            return json_encode($json, JSON_UNESCAPED_SLASHES) ?: '';
        }
        return '';
    }

    /**
     * Renders breadcrumbs, from homepage to current page.
     *
     * Hooked to `vp_breadcrumbs_ancestors` filter to allow adding more items to breadcrumbs, between homepage and current page.
     *
     * @return ?array HTML render array.
     */
    static function breadcrumbs(): ?array
    {
        if (is_front_page()) {
            // no breadcrumb on front page
            return null;
        }

        // current object in query
        $object = get_queried_object();
        if (!is_singular($object)) {
            return null;
        }

        // build items array
        $home = vp::breadcrumbs_item(__('Homepage', 'vupar'), get_home_url());
        $current = vp::breadcrumbs_item_from_post($object);
        $ancestors = apply_filters('vp_breadcrumbs_ancestors', []) ?? [];
        $items = [$home, ...$ancestors, $current];

        // prepare json
        $json = vp::breadcrumbs_json($items);

        // build breadcrumbs list from items array
        $crumbs = [];
        if (is_array($items) && count($items) > 0) {
            foreach ($items as $i => $item) {
                $title = $item->title ?? '';
                $url = $item->url ?? '';
                if (empty($url) || $url === '#') {
                    $crumb = ['tag' => 'span', 'content' => $title];
                } else {
                    $crumb = [
                        'tag' => 'a',
                        'class' => 'item',
                        'href' => $url,
                        'content' => $title,
                    ];
                }
                $crumbs[] = ['tag' => 'li', 'content' => $crumb];
            }
        }

        // build final html render array
        $html = [
            [
                'tag' => 'ol',
                'content' => $crumbs,
            ],
            [
                'tag' => 'script',
                'type' => 'application/ld+json',
                'title' => 'breadcrumbs',
                'content' => $json,
            ],
        ];

        return $html;
    }

    // Get option value from vp-options, or null
    static function option(string $name): ?string
    {
        $options = get_option(static::OPTIONS);
        return $options[$name] ?? null;
    }

    /**
     * Logs $data to the PHP error log if WP_DEBUG is true.
     *
     * @param mixed $data Data to log. If an array or object, will be pretty-printed with print_r.
     * @return void
     */
    static function log($data): void
    {
        if (true === constant('WP_DEBUG')) {
            if (is_array($data) || is_object($data)) {
                error_log(print_r($data, true));
            } else {
                error_log($data);
            }
        }
    }

    /**
     * Enqueues and registers CSS files as modules with versioning based on file hash.
     *
     * Retrieves the list of styles from the cache. If not available, it scans the given directory
     * for CSS files, calculates their hash, and stores the information in cache. The styles
     * are then registered and enqueued as modules for use in the site, and linked in the HTML
     * head with a preload tag.
     *
     * @param string $dir  The directory path containing CSS files.
     * @param string $url  The base URL to access the CSS files.
     * @param string $hash The hashing algorithm to use for versioning (default: 'crc32c').
     */
    static function styles(string $dir, string $url, string $hash = 'crc32c'): void
    {
        $vp_cache = defined('VP_CACHE') ? constant('VP_CACHE') : true;
        if (!$vp_cache) {
            VP_Cache::unset('styles.php');
        }
        $list = VP_Cache::get('styles.php');
        if (!is_array($list)) {
            $list = [];
            $map = self::filemap($dir);
            foreach ($map as $itemPath) {
                if (str_ends_with($itemPath, '.css')) {
                    $itemId = '@vp-css/' . preg_replace('#\.css$#', '', $itemPath);
                    $itemHash = hash_file($hash, $dir . '/' . $itemPath);
                    $list[] = [
                        'id' => $itemId,
                        'url' => $url . '/' . $itemPath,
                        'hash' => $itemHash,
                    ];
                }
            }
            VP_Cache::set('styles.php', "<?php\n\nreturn " . var_export($list, true) . ";");
        }

        $preload = [];
        foreach ($list as $item) {
            wp_enqueue_style($item['id'], $item['url'], [], $item['hash'], 'all');
            $preload[] = ['href' => $item['url'] . '?ver=' . $item['hash'], 'as' => 'style'];
        }
        add_filter('wp_preload_resources', function ($resources) use ($preload) {
            return [...$resources, ...$preload];
        });
    }

    /**
     * Enqueues and registers JavaScript files as modules with versioning based on file hash.
     *
     * Retrieves the list of scripts from the cache. If not available, it scans the given directory
     * for JavaScript files, calculates their hash, and stores the information in cache. The scripts
     * are then registered and enqueued as modules for use in the site.
     *
     * @param string $dir  The directory path containing JavaScript files.
     * @param string $url  The base URL to access the JavaScript files.
     * @param string $hash The hashing algorithm to use for versioning (default: 'crc32c').
     */
    static function scripts(string $dir, string $url, string $hash = 'crc32c'): void
    {
        $vp_cache = defined('VP_CACHE') ? constant('VP_CACHE') : true;
        if (!$vp_cache) {
            VP_Cache::unset('scripts.php');
        }
        $list = VP_Cache::get('scripts.php');
        if (!is_array($list)) {
            $list = [];
            $map = self::filemap($dir);
            foreach ($map as $itemPath) {
                if (str_ends_with($itemPath, '.js')) {
                    $itemId = '@vp/' . preg_replace('#\.js$#', '', $itemPath);
                    $itemHash = hash_file($hash, $dir . '/' . $itemPath);
                    $list[] = [
                        'id' => $itemId,
                        'url' => $url . '/' . $itemPath,
                        'hash' => $itemHash,
                    ];
                }
            }
            VP_Cache::set('scripts.php', "<?php\n\nreturn " . var_export($list, true) . ";");
        }

        foreach ($list as $item) {
            $deps = $item['id'] === '@vp/autoload' ? array_diff(array_column($list, 'id'), ['@vp/autoload']) : [];
            wp_register_script_module($item['id'], $item['url'], $deps, $item['hash']);
            wp_enqueue_script_module($item['id']);
        }
    }

    /**
     * Registers all block from the given directory.
     *
     * The directory is scanned for subdirectories, which are then registered as
     * block types with WordPress. The list of blocks is stored in cache.
     *
     * @param string $dir The path to the directory containing the blocks.
     */
    static function blocks(string $dir): void
    {
        $vp_cache = defined('VP_CACHE') ? constant('VP_CACHE') : true;
        if (!$vp_cache) {
            VP_Cache::unset('blocks.php');
        }
        $list = VP_Cache::get('blocks.php');
        if (!is_array($list)) {
            $list = [];
            $di = new DirectoryIterator($dir);
            foreach (new DirectoryIterator(get_template_directory() . '/block') as $block) {
                if ($block->isDir() && !$block->isDot()) {
                    $list[] = $block->getRealpath();
                }
            }
            VP_Cache::set('blocks.php', "<?php\n\nreturn " . var_export($list, true) . ";");
        }
        foreach ($list as $path) {
            register_block_type($path);
        }
    }

    /**
     * Recursive folder content mapping.
     *
     * @param string $folder The path to the folder.
     * @param int    $sort   The sorting flags (default: SORT_NATURAL | SORT_FLAG_CASE).
     *
     * @return array|false A list of relative paths to files and folders (or false if empty).
     */
    static function filemap($folder, $sort = SORT_NATURAL | SORT_FLAG_CASE)
    {
        if (is_dir($folder) && ($fp = @opendir($folder))) {
            $folders = [];
            $files = [];
            while (($entry = readdir($fp)) !== false) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                if (is_dir($folder . '/' . $entry)) {
                    $folders[] = $entry;
                } elseif (is_file($folder . '/' . $entry)) {
                    $files[] = $entry;
                }
            }
            closedir($fp);
            if (empty($folders) && empty($files)) {
                return false;
            }
            sort($folders, $sort);
            sort($files, $sort);
            foreach ($folders as $key => $value) {
                $map = self::filemap($folder . '/' . $value);
                unset($folders[$key]);
                if (is_array($map) && !empty($map)) {
                    foreach ($map as $p) {
                        $folders[] = $value . '/' . $p;
                    }
                }
            }
            $output = [...$folders, ...$files];
            return $output;
        }

        return false;
    }

    /**
     * Requires all PHP files found in given directory and its subdirectories.
     *
     * @param string $path The directory path to search for PHP files.
     *
     * @return void
     */
    static function require_dir($path): void
    {
        $dir = new RecursiveDirectoryIterator($path);
        $iterator = new RecursiveIteratorIterator($dir);
        foreach ($iterator as $file) {
            $fname = $file->getFilename();
            if (str_ends_with($fname, '.php')) {
                require_once $file->getPathname();
            }
        }
    }
}
