<?php

class vp
{
    const PAGE = 'vp';
    const OPTIONS = 'vp-options';
    const CAPABILITY = 'manage_options';
    const DEBUG_WRAPPER = [
        'tag' => 'pre',
        'style' =>
        'font:12px/13px Consolas,\'Lucida Console\',monospace;text-align:left;color:#ddd;background-color:#222;padding:10px;max-height:500px;overflow:auto;',
    ];

    /**
     * Return an HTML tag string, with its attributes and contents
     * @param string $tag HTML tag name
     * @param array $attributes HTML tag attributes
     * @param string $content HTML tag content
     * @param bool $allow_empty_content Allow empty string as content
     * @return string HTML tag string
     */
    static function html_tag(
        string $tag,
        array $attributes = [],
        string $content = '',
        bool $allow_empty_content = false,
    ): string {
        // empty tag, return empty
        $tag = trim($tag);
        if (empty($tag)) {
            return '';
        }
        // https://www.thoughtco.com/html-singleton-tags-3468620
        $voidElements = [
            'area' => 1,
            'base' => 1,
            'br' => 1,
            'col' => 1,
            'command' => 1,
            'embed' => 1,
            'hr' => 1,
            'img' => 1,
            'input' => 1,
            'keygen' => 1,
            'link' => 1,
            'meta' => 1,
            'param' => 1,
            'source' => 1,
            'track' => 1,
            'wbr' => 1,
        ];
        $has_closing_tag = !isset($voidElements[strtolower($tag)]);

        // prepare attributes output
        if (count($attributes) > 0) {
            foreach ($attributes as $key => $value) {
                if ($value !== '0' && empty($value)) {
                    $attributes[$key] = ' ' . $key;
                } else {
                    $attributes[$key] = ' ' . $key . '="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
                }
            }
            $attributes = implode('', $attributes);
        } else {
            $attributes = '';
        }
        if ($has_closing_tag) {
            if ($content !== '0' && empty($content) && !$allow_empty_content) {
                $output = '';
            } else {
                $output = '<' . $tag . $attributes . '>' . $content . '</' . $tag . '>';
            }
        } else {
            // no closing tag, no content needeed
            $output = '<' . $tag . $attributes . '>';
        }

        return $output;
    }

    /**
     * Render an array or string to html
     *
     * If $data is a string, it is returned as is.
     * If $data is an array, it can be one of two things:
     * - an indexed array of strings or arrays, in which case each item is rendered using this function, and the results are concatenated.
     * - an associative array, in which case the 'tag' key must be present, and optionally the 'content' key.
     *   The 'tag' key is used to determine the html tag to use, and the 'content' key is used as the content of this tag.
     *   if the 'content' key is an array, it is rendered using this function, and the result is used as the content of the tag.
     *   If the 'allow_empty_content' key is present, it is used to determine if an empty content is allowed for the tag.
     *
     * @param array|string $data the data to render
     * @param bool $allow_empty_content whether to allow empty content for the tag
     * @return string the rendered html
     */
    static function render(mixed $data, bool $allow_empty_content = false): string
    {
        if (!is_string($data) && !is_array($data)) {
            $data = (string) $data;
        }

        if (is_string($data)) {
            return $data;
        }

        if (is_array($data)) {
            if (!array_is_list($data)) {
                $tag = strtolower($data['tag'] ?? '');
                unset($data['tag']);
                $content = $data['content'] ?? '';
                unset($data['content']);
                if (isset($data['allow_empty_content'])) {
                    $allow_empty_content = (bool) $data['allow_empty_content'];
                    unset($data['allow_empty_content']);
                }

                if (is_array($content)) {
                    if (!array_is_list($content)) {
                        $content = self::render($content);
                    } else {
                        $content = implode('', array_map(self::class . '::render', $content));
                    }
                }
                $output = self::html_tag($tag, $data, $content, $allow_empty_content);
            } else {
                $output = implode('', array_map(self::class . '::render', $data));
            }
        }

        return $output;
    }

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
            $args['lang'] = defined('LANG') ? LANG : '';
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
                $content = vp::render([
                    'tag' => 'div',
                    'class' => 'error notice notice-error',
                    'content' => strtr('<p>part "{part}" not found.</p>', [
                        '{part}' => $part,
                    ]),
                ]);
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

    static function menu($menu_location)
    {
        $cache = VP_CACHE;
        $menu = false;
        if ($cache) {
            $name = $menu_location . '-' . LANG . '-' . get_the_ID();
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

    /**
     * Block rendering function, using a template part with a name equivalent to the block name
     * ex: for a block 'foo', the template will be themename/part/block/foo.php
     *
     * @return void
     */
    static function acf_block_render_callback(): void
    {
        $func_arg = func_get_args();
        $part = '';
        $cache = true;
        $data = [];
        if (is_array($func_arg[0]) && !empty($func_arg[0])) {
            $block = $func_arg[0];
            $part = !empty($block['name']) ? preg_replace('#^acf/#', 'block/', $block['name']) : $part;
            $cache = isset($block['vp_cache']) ? (bool) $block['vp_cache'] : $cache;
            $data = [...$block['data'] ?? [], ...get_fields() ?: []];
            // $data = get_fields() ?: [];
            if (($data['example'] ?? false) === 'true') {
                echo '<img src="' .
                    get_template_directory_uri() .
                    '/img/' .
                    $part .
                    '.jpg" style="display:block;width:100%; height:auto;"/>';
                return;
            }
        }
        echo vp::part($part, $data, $cache);
    }

    // Debug: print_r html formatted
    static function print($args): void
    {
        echo vp::render(array_merge(self::DEBUG_WRAPPER, ['content' => print_r($args, 1)]));
    }

    // Debug: var_dump html formatted
    static function dump(...$args): void
    {
        ob_start();
        var_dump(...$args);
        $dump = ob_get_contents();
        ob_end_clean();
        echo vp::render(array_merge(self::DEBUG_WRAPPER, ['content' => $dump]));
    }

    // Debug: var_export html formatted
    static function export($args): void
    {
        echo vp::render(
            array_merge(self::DEBUG_WRAPPER, [
                'content' => var_export($args, 1),
            ]),
        );
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
