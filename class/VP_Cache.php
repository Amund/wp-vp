<?php

class VP_Cache
{
    public static $table = 'vp_cache';

    /**
     * Clears the cache when content is updated.
     *
     * @see clear()
     */
    static function hook_admin_init()
    {
        add_action('delete_post', [static::class, 'clear']);
        add_action('save_post', [static::class, 'clear']);
        add_action('delete_term', [static::class, 'clear']);
        add_action('edit_term', [static::class, 'clear']);
        add_action('wp_create_nav_menu', [static::class, 'clear']);
        add_action('wp_update_nav_menu', [static::class, 'clear']);
        add_action('wp_delete_nav_menu', [static::class, 'clear']);
    }

    /**
     * Save a value to cache.
     *
     * @param string $name Name of the cache entry
     * @param mixed $value Value to save
     * @param string $type Optional type of the cache entry
     *
     * @return bool True if the value was saved, false otherwise
     */
    static function set(string $name, $value, string $type = ''): bool
    {
        if (empty($name)) {
            throw new \Exception('Cache name cannot be empty');
        }
        $type = empty($type) ? '' : '/' . trim($type);
        $path = VP_CACHE_PATH . $type;
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        $path .= '/' . trim($name);
        $tmp = $path . '.tmp';
        $set = @file_put_contents($tmp, $value, LOCK_EX) === false ? false : true;
        if ($set) {
            rename($tmp, $path);
        }
        return $set;
    }

    /**
     * Retrieve a value from the cache.
     *
     * @param string $name Name of the cache entry
     * @param string $type Optional type of the cache entry
     *
     * @return mixed The value of the cache entry, or false if it does not exist
     */
    static function get(string $name, string $type = null): mixed
    {
        if (empty($name)) {
            throw new \Exception('Cache name cannot be empty');
        }
        $type = empty($type) ? '' : '/' . trim($type);
        $path = VP_CACHE_PATH . $type;
        $path .= '/' . trim($name);
        if (str_ends_with($name, '.php')) {
            return file_exists($path) ? @include($path) : false;
        } else {
            return file_exists($path) ? @file_get_contents($path) : false;
        }
    }

    /**
     * Delete a value from the cache.
     *
     * @param string $name Name of the cache entry
     * @param string $type Optional type of the cache entry
     *
     * @return bool True if the cache entry was deleted, false otherwise
     */
    static function unset(string $name, string $type = null): bool
    {
        $type = empty($type) ? '' : '/' . $type;
        $path = VP_CACHE_PATH . $type;
        $path .= '/' . $name;
        $value = file_exists($path) ? @unlink($path) : true;
        return $value;
    }

    /**
     * Delete all typed cache entries, or all cache entries of a given type.
     * I does not delete not typed cache entries (root entries).
     *
     * @param string $type Optional type of the cache entries to delete
     *
     * @return bool True
     */
    static function clear(string $type = null): bool
    {
        if ($type === null) {
            $di = new DirectoryIterator(static::path());
            foreach ($di as $fileinfo) {
                if ($fileinfo->isDir() && !$fileinfo->isDot()) {
                    static::clear($fileinfo->getFilename());
                }
            }
        } else {
            $path = self::path($type);
            if (is_dir($path)) {
                exec(sprintf('rm -r %s', $path));
            }
        }
        clearstatcache();
        return true;
    }

    /**
     * Flush all cache entries, including not typed cache entries (root entries).
     *
     * @return bool True
     */
    static function flush(): bool
    {
        $path = self::path();
        if (is_dir($path)) {
            exec(sprintf('rm -r %s', $path));
        }
        clearstatcache();
        return true;
    }

    /**
     * Return statistics about the cache.
     *
     * The returned array will have the following keys:
     * - 'type': an associative array with the type as key and the count as value
     * - 'root': the number of root entries (without type)
     * - 'total': the total number of cache entries
     *
     * @return array
     */
    static function stat(): array
    {
        clearstatcache();
        $stat = [
            'type' => [],
            'typed' => 0,
            'root' => 0,
            'total' => 0,
        ];
        $path = static::path();

        if (!is_dir($path)) {
            return $stat;
        }

        $di = new DirectoryIterator($path);
        foreach ($di as $fileinfo) {
            if ($fileinfo->isDir()) {
                if (!$fileinfo->isDot()) {
                    $name = $fileinfo->getFilename();
                    $files = new FilesystemIterator($fileinfo->getPathname(), FilesystemIterator::SKIP_DOTS);
                    $count = iterator_count($files);
                    $stat['type'][$name] = $count;
                    $stat['typed'] += $count;
                    $stat['total'] += $count;
                }
            } else if ($fileinfo->isFile()) {
                $stat['root']++;
                $stat['total']++;
            }
        }
        ksort($stat['type']);
        return $stat;
    }

    /**
     * Get the path of the cache, or a subpath of it.
     *
     * @param string $subpath Subpath to append to the cache path
     * @return string Cache path, or cache path with subpath appended
     */
    static function path(string $subpath = ''): string
    {
        $subpath = empty($subpath) ? '' : '/' . str_replace('/', '-', trim($subpath, '/'));
        return VP_CACHE_PATH . $subpath;
    }
}
