<?php

/**
 * Manage Vupar caches.
 */

class VP_Cache_CLI
{
    /**
     * Clear currently typed cached entries
     *
     * ## OPTIONS
     *
     * [<type>]
     * : Clear a specific cache type.
     */
    function clear($args, $assoc_args)
    {
        [$type] = $args;
        $stat = VP_Cache::stat();

        $type = empty($type) ? null : $type;
        if ($type === null) {
            if ($stat['typed'] == 0) {
                WP_CLI::success('No typed entries found, vp-cache is already empty.');
            } else {
                VP_Cache::clear();
                WP_CLI::success($stat['typed'] . ' typed cache entries cleared.');
            }
        } else {
            if (empty($stat['type'][$type])) {
                WP_CLI::success('No ' . $type . ' entries found, vp-cache is already empty.');
            } else {
                VP_Cache::clear($type);
                WP_CLI::success($stat['type'][$type] . ' ' . $type . ' cache entries cleared.');
            }
        }
    }

    /**
     * Clear all currently cached entries (typed and root)
     */
    function flush()
    {
        $stat = VP_Cache::stat();

        if ($stat['total'] == 0) {
            WP_CLI::success('No entries found, vp-cache is already empty.');
        } else {
            VP_Cache::flush();
            WP_CLI::success($stat['total'] . ' cache entries cleared.');
        }
    }

    /**
     * Show statistics about cached entries
     *
     * ## OPTIONS
     *
     * [<format>]
     * : Format to use: ‘table’, ‘json’, ‘csv’, ‘yaml’, ‘ids’, ‘count’. Defaults to table.
     */
    function stat($args, $assoc_args)
    {
        [$format] = $args;
        if (!in_array($format, ['table', 'json', 'csv', 'yaml', 'ids', 'count'])) {
            $format = 'table';
        }

        $stat = VP_Cache::stat();

        $summary = [];

        if (!empty($stat['type'])) {
            foreach ($stat['type'] as $type => $count) {
                $summary[] = [
                    'name' => $type,
                    'count' => $count,
                    'description' => 'Number of ' . $type . ' typed cache entries',
                ];
            }
        }

        $summary[] = ['name' => 'typed', 'count' => $stat['typed'], 'description' => 'Sum of all typed cache entries (part, menu,...)'];
        $summary[] = ['name' => 'root', 'count' => $stat['root'], 'description' => 'Number of root cache entries, without type'];
        $summary[] = ['name' => 'total', 'count' => $stat['total'], 'description' => 'Sum of all typed and root cache entries'];
        WP_CLI\Utils\format_items($format, $summary, ['name', 'count', 'description']);
    }
}
