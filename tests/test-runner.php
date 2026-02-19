<?php
/**
 * Standalone Test Runner
 * 
 * Usage: php tests/test-runner.php
 */

// Define basic WP constants/functions mocks if needed
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}
if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}
if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

// Mock WP functions used in classes
if (!function_exists('add_action')) {
    function add_action()
    {
    }
}
if (!function_exists('add_filter')) {
    function add_filter()
    {
    }
}
if (!function_exists('apply_filters')) {
    function apply_filters($tag, $value)
    {
        return $value;
    }
}
if (!function_exists('esc_html')) {
    function esc_html($s)
    {
        return $s;
    }
}
if (!function_exists('__')) {
    function __($s)
    {
        return $s;
    }
}
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($s)
    {
        return trim($s);
    }
}
if (!function_exists('sanitize_key')) {
    function sanitize_key($key)
    {
        $key = is_scalar($key) ? (string) $key : '';
        $key = strtolower($key);
        return preg_replace('/[^a-z0-9_-]/', '', $key);
    }
}
if (!function_exists('absint')) {
    function absint($n)
    {
        return abs((int) $n);
    }
}
if (!function_exists('get_current_user_id')) {
    function get_current_user_id()
    {
        return 1;
    }
}
if (!function_exists('get_user_meta')) {
    function get_user_meta($user_id, $meta_key, $single = false)
    {
        global $tah_test_user_meta_store;
        if (!is_array($tah_test_user_meta_store)) {
            $tah_test_user_meta_store = [];
        }
        if (!isset($tah_test_user_meta_store[$user_id]) || !array_key_exists($meta_key, $tah_test_user_meta_store[$user_id])) {
            return $single ? '' : [];
        }
        return $tah_test_user_meta_store[$user_id][$meta_key];
    }
}
if (!function_exists('update_user_meta')) {
    function update_user_meta($user_id, $meta_key, $meta_value)
    {
        global $tah_test_user_meta_store;
        if (!is_array($tah_test_user_meta_store)) {
            $tah_test_user_meta_store = [];
        }
        if (!isset($tah_test_user_meta_store[$user_id]) || !is_array($tah_test_user_meta_store[$user_id])) {
            $tah_test_user_meta_store[$user_id] = [];
        }
        $tah_test_user_meta_store[$user_id][$meta_key] = $meta_value;
        return true;
    }
}

// Simple Assertion Library
class Assert
{
    public static $passed = 0;
    public static $failed = 0;

    public static function same($expected, $actual, $message = '')
    {
        if ($expected === $actual) {
            self::$passed++;
            // echo "."; 
        } else {
            self::$failed++;
            echo "\n[FAIL] $message\n";
            echo "   Expected: " . var_export($expected, true) . "\n";
            echo "   Actual:   " . var_export($actual, true) . "\n";
        }
    }

    public static function true($condition, $message = '')
    {
        self::same(true, $condition, $message);
    }
}

// Test Loader
$test_files = glob(__DIR__ . '/unit/test-*.php');

echo "Running " . count($test_files) . " test files...\n";

foreach ($test_files as $file) {
    echo "\nRunning " . basename($file) . "...\n";
    require_once $file;
}

echo "\n------------------------------------------------\n";
echo "Tests Completed.\n";
echo "Passed: " . Assert::$passed . "\n";
echo "Failed: " . Assert::$failed . "\n";

if (Assert::$failed > 0) {
    exit(1);
} else {
    exit(0);
}
