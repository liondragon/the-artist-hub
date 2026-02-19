<?php
/**
 * Mock Tests for TAH_Pricing_Repository
 */

// Mock $wpdb class
class MockWPDB
{
    public $prefix = 'wp_';
    public $last_query = '';
    public $last_prepare_args = [];
    public $insert_id = 1;
    public $prepared_queries = [];
    public $query_log = [];
    public $insert_calls = 0;
    public $update_calls = 0;

    public function prepare($query, ...$args)
    {
        $this->last_query = $query;
        if (count($args) === 1 && is_array($args[0])) {
            $this->last_prepare_args = $args[0];
        } else {
            $this->last_prepare_args = $args;
        }
        $this->prepared_queries[] = $query;
        return $query; // In mock, we just return the query structure to check it
    }

    public function get_results($query, $output = OBJECT)
    {
        $this->last_query = $query;
        return []; // Return empty array by default
    }

    public function get_row($query, $output = OBJECT, $y = 0)
    {
        $this->last_query = $query;
        return null;
    }

    public function get_col($query, $x = 0)
    {
        $this->last_query = $query;
        return [];
    }

    public function insert($table, $data, $format = null)
    {
        $this->insert_calls++;
        $this->last_query = "INSERT INTO $table ...";
        return 1;
    }

    public function update($table, $data, $where, $format = null, $where_format = null)
    {
        $this->update_calls++;
        $this->last_query = "UPDATE $table ...";
        return 1;
    }

    public function query($query)
    {
        $this->last_query = $query;
        $this->query_log[] = $query;
        return 1;
    }

    public function esc_like($text)
    {
        return addcslashes($text, '_%\\');
    }
}

// Global Mock
global $wpdb;
$wpdb = new MockWPDB();

// Mock dependencies
class TAH_Pricing_Module
{
    public static function get_table_name($name)
    {
        global $wpdb;
        return $wpdb->prefix . 'tah_' . $name;
    }
}

// Helper to reset mock
function reset_mock_db()
{
    global $wpdb;
    $wpdb->last_query = '';
    $wpdb->last_prepare_args = [];
    $wpdb->prepared_queries = [];
    $wpdb->query_log = [];
    $wpdb->insert_calls = 0;
    $wpdb->update_calls = 0;
}

// Load Class Under Test
require_once dirname(__DIR__, 2) . '/inc/modules/pricing/class-pricing-repository.php';

function test_get_catalog_items_sql_generation()
{
    global $wpdb;
    reset_mock_db();

    $repo = new TAH_Pricing_Repository();
    $repo->get_catalog_items(['search' => 'Wood']);

    $has_search = strpos($wpdb->last_query, 'title LIKE %s') !== false;
    Assert::true($has_search, 'SQL should contain title search clause');
    Assert::same(['%Wood%'], $wpdb->last_prepare_args, 'Search value should be bound via prepare args');
}

function test_get_catalog_items_filter_active()
{
    global $wpdb;
    reset_mock_db();

    $repo = new TAH_Pricing_Repository();
    // Default is active=true unless specified otherwise? 
    // Let's check code: "if (isset($filters['is_active']))..."

    $repo->get_catalog_items(['is_active' => true]);
    Assert::true(strpos($wpdb->last_query, 'is_active = %d') !== false, 'SQL should include is_active placeholder');
    Assert::same([1], $wpdb->last_prepare_args, 'Active filter should bind 1');

    $repo->get_catalog_items(['is_active' => false]);
    Assert::true(strpos($wpdb->last_query, 'is_active = %d') !== false, 'SQL should include is_active placeholder');
    Assert::same([0], $wpdb->last_prepare_args, 'Inactive filter should bind 0');
}

function test_get_catalog_items_sort()
{
    global $wpdb;
    reset_mock_db();

    $repo = new TAH_Pricing_Repository();
    $repo->get_catalog_items([]);

    // Check default sort
    Assert::true(strpos($wpdb->last_query, 'ORDER BY sort_order ASC') !== false, 'SQL should order by sort_order');
}

function test_save_quote_groups()
{
    global $wpdb;
    reset_mock_db();

    $repo = new TAH_Pricing_Repository();
    $post_id = 123;
    $groups = [
        [
            'name' => 'Group 1',
            'selection_mode' => 'all',
            'sort_order' => 0
        ]
    ];

    $ids = $repo->save_quote_groups($post_id, $groups);
    Assert::same([1], $ids, 'Insert path should return persisted group id');
    Assert::same(1, $wpdb->insert_calls, 'Should insert one group');
    Assert::same(0, $wpdb->update_calls, 'Should not update when input id is empty');
    Assert::true(in_array('START TRANSACTION', $wpdb->query_log, true), 'Should open transaction');
    Assert::true(in_array('COMMIT', $wpdb->query_log, true), 'Should commit transaction');
}

// Run Tests
test_get_catalog_items_sql_generation();
test_get_catalog_items_filter_active();
test_get_catalog_items_sort();
test_save_quote_groups();
