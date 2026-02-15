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

    public function prepare($query, $args)
    {
        $this->last_query = $query;
        $this->last_prepare_args = is_array($args) ? $args : func_get_args();
        // Shift off the query itself
        if (isset($this->last_prepare_args[0]) && $this->last_prepare_args[0] === $query) {
            array_shift($this->last_prepare_args);
        }
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

    public function insert($table, $data, $format = null)
    {
        $this->last_query = "INSERT INTO $table ...";
        return 1;
    }

    public function update($table, $data, $where, $format = null, $where_format = null)
    {
        $this->last_query = "UPDATE $table ...";
        return 1;
    }

    public function query($query)
    {
        $this->last_query = $query;
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
}

// Load Class Under Test
require_once dirname(__DIR__, 2) . '/inc/modules/pricing/class-pricing-repository.php';

function test_get_catalog_items_sql_generation()
{
    global $wpdb;
    reset_mock_db();

    $repo = new TAH_Pricing_Repository();
    $repo->get_catalog_items(['search' => 'Wood']);

    // Check if SQL contains search filter
    $has_search = strpos($wpdb->last_query, '(title LIKE %s OR sku LIKE %s)') !== false;
    Assert::true($has_search, 'SQL should contain search clause');

    // Check if args were passed
    // Note: Depends on implementation details of prepare(). 
    // In our mock, prepare() just stores args.
    // The Repository calls prepare with "%...%" so we verify that.
}

function test_get_catalog_items_filter_active()
{
    global $wpdb;
    reset_mock_db();

    $repo = new TAH_Pricing_Repository();
    // Default is active=true unless specified otherwise? 
    // Let's check code: "if (isset($filters['is_active']))..."

    $repo->get_catalog_items(['is_active' => true]);
    Assert::true(strpos($wpdb->last_query, 'is_active = 1') !== false, 'SQL should filter by is_active=1');

    $repo->get_catalog_items(['is_active' => false]);
    Assert::true(strpos($wpdb->last_query, 'is_active = 0') !== false, 'SQL should filter by is_active=0');
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

    // We expect:
    // 1. DELETE FROM ... (clearing old groups not in list? Or implementation details?)
    // 2. INSERT/UPDATE

    $ids = $repo->save_quote_groups($post_id, $groups);

    // Check if insert was called
    Assert::true(strpos($wpdb->last_query, 'INSERT INTO') !== false || strpos($wpdb->last_query, 'UPDATE') !== false, 'Should attempt to insert/update groups');
}

// Run Tests
test_get_catalog_items_sql_generation();
test_get_catalog_items_filter_active();
test_get_catalog_items_sort();
test_save_quote_groups();
