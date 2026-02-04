<?php
declare(strict_types=1);

/**
 * Search Configuration
 * 
 * Extends default WordPress search to include custom fields.
 * https://adambalee.com
 */

/**
 * Extend WordPress search to include custom fields.
 *
 * Joins posts and postmeta tables.
 *
 * @param string $join The JOIN clause of the query.
 * @return string Modified JOIN clause.
 */
function cf_search_join($join)
{
    global $wpdb;

    if (is_search()) {
        $join .= ' LEFT JOIN ' . $wpdb->postmeta . ' ON ' . $wpdb->posts . '.ID = ' . $wpdb->postmeta . '.post_id ';
    }

    return $join;
}
add_filter('posts_join', 'cf_search_join');

/**
 * Modify the search query with posts_where.
 *
 * Search allows custom fields to be searched.
 *
 * @param string $where The WHERE clause of the query.
 * @return string Modified WHERE clause.
 */
function cf_search_where($where)
{
    global $pagenow, $wpdb;

    if (is_search()) {
        $where = preg_replace(
            "/\(\s*" . $wpdb->posts . ".post_title\s+LIKE\s*(\'[^\']+\')\s*\)/",
            "(" . $wpdb->posts . ".post_title LIKE $1) OR (" . $wpdb->postmeta . ".meta_value LIKE $1)",
            $where
        );
    }

    return $where;
}
add_filter('posts_where', 'cf_search_where');

/**
 * Prevent duplicates in search results.
 *
 * @param string $where The WHERE clause of the query.
 * @return string DISTINCT clause or original WHERE.
 */
function cf_search_distinct($where)
{
    global $wpdb;

    if (is_search()) {
        return "DISTINCT";
    }

    return $where;
}
add_filter('posts_distinct', 'cf_search_distinct');
