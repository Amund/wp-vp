<?php

class vp
{
    const OPTIONS = 'vp-options';

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
        $name = null;
        if ($cache !== false && !static::cache_bypass()) {
            // generate cache name
            $context = [
                'part' => $part,
                'lang' => defined('LANG') ? constant('LANG') : '',
                'post_id' => get_the_ID() ?? '',
                ...$args
            ];
            ksort($context);
            $name = static::cache_name('part', $context);

            // get cache (no cache name: context can't be encoded, part is generated without cache)
            $content = $name === null ? false : wp_cache_get($name, 'vupar');
            if ($content !== false) {
                if (wp_get_environment_type() === 'local' && !empty($content)) {
                    $content = strtr('<!--part {part} (from cache)-->{content}<!--part /{part} (from cache)-->', [
                        '{part}' => $part,
                        '{content}' => $content,
                    ]);
                }
                return $content;
            }
        }

        // generate content (finally: an exception in the template must not leave the buffer open)
        ob_start();
        try {
            $result = get_template_part('part/' . $part, null, $args);
            $content = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        // error: part not found, logged and displayed in debug mode only
        if ($result === false) {
            $content = '';
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $message = 'part "' . $part . '" not found.';
                error_log('vp::part: ' . $message);
                $content = '<div class="error notice notice-error"><p>' . esc_html($message) . '</p></div>';
            }
        }

        // set cache (not for a missing part: it must show up as soon as the file exists)
        if ($name !== null && $result !== false) {
            wp_cache_set($name, $content, 'vupar', is_int($cache) ? $cache : 0);
        }

        // debug in dev
        if (wp_get_environment_type() === 'local' && !empty($content)) {
            $content = strtr('<!--part {part}-->{content}<!--part /{part}-->', [
                '{part}' => $part,
                '{content}' => $content,
            ]);
        }

        return $content;
    }

    static function menu(string $menu_location, array $context = []): string
    {
        // generate cache name
        $context = [
            'container' => false,
            'items_wrap' => '<ul class="%2$s" id="%1$s" tabindex="0">%3$s</ul>',
            ...$context,
            'theme_location' => $menu_location
        ];
        ksort($context);

        // the rendered menu depends on the current page (current-menu-* classes, aria-current)
        // and on the language (polylang assigns a different menu to the same location)
        $cache_context = [
            'args' => $context,
            'lang' => defined('LANG') ? constant('LANG') : '',
            'page' => static::page_context(),
        ];
        $name = static::cache_bypass() ? null : static::cache_name('menu', $cache_context);

        // get cache
        $menu = $name === null ? false : wp_cache_get($name, 'vupar');
        if ($menu !== false) {
            return $menu;
        }

        // generate menu
        ob_start();
        if (has_nav_menu($menu_location)) {
            wp_nav_menu($context);
        }
        $menu = ob_get_clean();

        // set cache
        if ($name !== null) {
            wp_cache_set($name, $menu, 'vupar');
        }

        return $menu;
    }

    /**
     * Whether the vupar cache must be bypassed (neither read nor written) for this request.
     *
     * Editing contexts must always render fresh content, and must never write into the cache
     * a rendering that could differ from the public one (block preview placeholders...):
     * - admin screens (but not front-end ajax requests going through admin-ajax.php),
     * - ACF block previews in the editor (ajax action acf/ajax/fetch-block),
     * - dynamic block previews in the editor (REST route /wp/v2/block-renderer),
     * - post previews and customizer previews.
     * Other ajax and REST requests (e.g. list loading) keep the cache.
     *
     * Filterable with `vp_cache_bypass`.
     */
    static function cache_bypass(): bool
    {
        if (wp_doing_ajax()) {
            $bypass = ($_REQUEST['action'] ?? '') === 'acf/ajax/fetch-block';
        } elseif (defined('REST_REQUEST') && REST_REQUEST) {
            $route = $GLOBALS['wp']->query_vars['rest_route'] ?? '';
            $bypass = is_string($route) && str_starts_with($route, '/wp/v2/block-renderer');
        } else {
            $bypass = is_admin() || is_preview() || is_customize_preview();
        }
        return (bool) apply_filters('vp_cache_bypass', $bypass);
    }

    /**
     * Builds a cache name from a context array.
     *
     * Returns null if the context can't be json encoded (recursion, NAN/INF...): without this
     * check, every failing context would share the same cache entry and serve the same content.
     * Invalid UTF-8 is substituted instead of failing.
     *
     * @param string $prefix Cache name prefix, e.g. "part" or "menu".
     * @param array $context Values identifying the cached content.
     * @return ?string Cache name, or null if the content must not be cached.
     */
    static function cache_name(string $prefix, array $context): ?string
    {
        $json = json_encode($context, JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            return null;
        }
        return $prefix . '-' . hash('xxh128', $json);
    }

    /**
     * Returns a string identifying the current page, based on the main query.
     *
     * Built from the queried object rather than the url, so the number of possible
     * values is bounded by the site content (no cache pollution with query strings
     * or random 404 urls).
     *
     * @return string Page identifier, e.g. "post:42", "term:7", "archive:product", "search".
     */
    static function page_context(): string
    {
        global $wp_query;
        if (!isset($wp_query)) {
            return 'none';
        }

        $object = get_queried_object();
        if ($object instanceof WP_Post) return 'post:' . $object->ID;
        if ($object instanceof WP_Term) return 'term:' . $object->term_id;
        if ($object instanceof WP_User) return 'user:' . $object->ID;
        if ($object instanceof WP_Post_Type) return 'archive:' . $object->name;

        if (is_front_page()) return 'front';
        if (is_home()) return 'home';
        if (is_search()) return 'search';
        if (is_404()) return '404';
        if (is_date()) return 'date';

        return 'other';
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
    static function breadcrumbs_item_from_post($post, $current = false): stdClass
    {
        $post = get_post($post);
        $title = get_the_title($post);
        $url = get_permalink($post);
        if (!$url || $current) {
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
                    'name' => strip_tags($item->title),
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
     * Make a render array of breadcrumbs, from homepage to current page.
     *
     * Hooked to `vp_breadcrumbs_ancestors` filter to allow adding more items to breadcrumbs, between homepage and current page.
     *
     * @param array $args Array of optional arguments, `home_title` and `home_url`.
     * @return ?array HTML render array.
     */
    static function breadcrumbs(array $args = []): ?array
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
        $home_title = (string) ($args['home_title'] ?? __('Homepage'));
        $home_url = (string) ($args['home_url'] ?? get_home_url());
        $home = vp::breadcrumbs_item($home_title, $home_url);
        // keep the current url for json-ld, the link is removed at html rendering
        $current = vp::breadcrumbs_item_from_post($object);
        $ancestors = apply_filters('vp_breadcrumbs_ancestors', []) ?? [];
        // reindex: string keys from the filter would break current item detection and json positions
        $items = array_values([$home, ...$ancestors, $current]);

        // prepare json
        $json = vp::breadcrumbs_json($items);

        // build breadcrumbs list from items array
        $crumbs = [];
        if (is_array($items)) {
            $nb = count($items);
            foreach ($items as $i => $item) {
                $title = $item->title ?? '';
                $url = $item->url ?? '';
                $is_current = $i === $nb - 1;
                if ($is_current || empty($url) || $url === '#') {
                    $crumb = ['tag' => 'span', 'content' => $title];
                } else {
                    $crumb = [
                        'tag' => 'a',
                        'class' => 'item',
                        'href' => $url,
                        'content' => $title,
                    ];
                }
                if ($is_current) $crumb['aria-current'] = 'page';
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
     * Enqueues and registers JavaScript files as modules with versioning based on file hash.
     *
     * Retrieves the list of scripts from the cache. If not available, it scans the given directory
     * for JavaScript files, calculates their hash, and stores the information in cache. The scripts
     * are then registered and enqueued as modules for use in the site.
     *
     * @param string $dir  The directory path containing JavaScript files.
     * @param string $url  The base URL to access the JavaScript files.
     */
    static function scripts(string $dir, string $url): void
    {
        $list = wp_cache_get('scripts', 'vupar');
        if ($list === false) {
            $list = [];
            // filemap() returns false for an empty or missing directory
            $map = self::filemap($dir) ?: [];
            foreach ($map as $itemPath) {
                if (str_ends_with($itemPath, '.js')) {
                    $itemId = '@vp/' . preg_replace('#\.js$#', '', $itemPath);
                    $itemHash = filemtime($dir . '/' . $itemPath);
                    $list[] = [
                        'id' => $itemId,
                        'url' => $url . '/' . $itemPath,
                        'hash' => $itemHash,
                    ];
                }
            }
            wp_cache_set('scripts', $list, 'vupar');
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
        $name = 'blocks-' . hash('crc32c', $dir);
        $list = wp_cache_get($name, 'vupar');
        if ($list === false) {
            $list = [];
            foreach (new DirectoryIterator($dir) as $block) {
                if ($block->isDir() && !$block->isDot()) {
                    $list[] = $block->getRealpath();
                }
            }
            wp_cache_set($name, $list, 'vupar');
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
