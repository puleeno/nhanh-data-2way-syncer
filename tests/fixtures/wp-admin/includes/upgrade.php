<?php

/**
 * Test fixture stub for WordPress's wp-admin/includes/upgrade.php.
 *
 * Schema::install() requires this file for dbDelta(). In unit tests we only
 * need the function to exist so the require succeeds.
 */

if (!function_exists('dbDelta')) {
    /**
     * @param array<string>|string $queries
     */
    function dbDelta($queries)
    {
        $GLOBALS['__dbdelta_queries'] = $GLOBALS['__dbdelta_queries'] ?? [];
        foreach ((array) $queries as $query) {
            $GLOBALS['__dbdelta_queries'][] = $query;
        }
        return [];
    }
}