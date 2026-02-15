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
if (!function_exists('absint')) {
    function absint($n)
    {
        return abs((int) $n);
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
