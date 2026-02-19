<?php
/**
 * Fixture-based contract checks for admin table schema + markup.
 */

require_once dirname(__DIR__, 2) . '/inc/modules/admin-table-columns/class-admin-table-config.php';
require_once dirname(__DIR__, 2) . '/inc/modules/admin-table-columns/class-admin-table-columns-module.php';
require_once dirname(__DIR__, 2) . '/inc/modules/pricing/class-quote-edit-screen.php';

/**
 * @param object $instance
 * @param string $method
 * @param array<int, mixed> $args
 * @return mixed
 */
function tah_contract_invoke_private_method($instance, $method, array $args = [])
{
    $reflection = new ReflectionClass($instance);
    $reflection_method = $reflection->getMethod($method);
    $reflection_method->setAccessible(true);
    return $reflection_method->invokeArgs($instance, $args);
}

function test_pricing_editor_contract_fixture_matches_config_and_sources()
{
    $fixture = require dirname(__DIR__) . '/fixtures/table-contract-pricing-editor.php';
    $quote_screen = new TAH_Quote_Edit_Screen();
    $config_helper = new TAH_Admin_Table_Config();

    $registered_tables = $quote_screen->register_table_config([], (string) $fixture['screen_id']);
    $normalized_tables = tah_contract_invoke_private_method($config_helper, 'normalize_table_registry', [$registered_tables]);
    $table_key = (string) $fixture['table_key'];

    Assert::true(isset($normalized_tables[$table_key]), 'Fixture table must be registered in normalized config');
    if (!isset($normalized_tables[$table_key])) {
        return;
    }

    $table_config = $normalized_tables[$table_key];
    Assert::same($fixture['variant_attr'], $table_config['variant_attr'], 'Variant attr must match fixture');
    Assert::same($fixture['row_selector'], $table_config['row_selector'], 'Row selector must match fixture');
    Assert::same($fixture['show_reset'], $table_config['show_reset'], 'Reset control toggle must match fixture');
    Assert::same($fixture['filler_column_key'], $table_config['filler_column_key'], 'Filler column key must match fixture');
    Assert::same($fixture['column_order'], array_keys($table_config['columns']), 'Column order must match fixture');

    $non_orderable_map = array_fill_keys($fixture['non_orderable'], true);
    $non_resizable_map = array_fill_keys($fixture['non_resizable'], true);

    foreach ($fixture['column_order'] as $column_key) {
        Assert::true(isset($table_config['columns'][$column_key]), 'Column must exist in normalized table config: ' . $column_key);
        if (!isset($table_config['columns'][$column_key])) {
            continue;
        }

        $column_config = $table_config['columns'][$column_key];
        Assert::true(array_key_exists('locked', $column_config), 'Column config must include "locked": ' . $column_key);
        Assert::true(array_key_exists('resizable', $column_config), 'Column config must include "resizable": ' . $column_key);
        Assert::true(array_key_exists('orderable', $column_config), 'Column config must include "orderable": ' . $column_key);
        Assert::true(array_key_exists('visible', $column_config), 'Column config must include "visible": ' . $column_key);

        $should_be_non_orderable = isset($non_orderable_map[$column_key]);
        $should_be_non_resizable = isset($non_resizable_map[$column_key]);
        Assert::same(!$should_be_non_orderable, $column_config['orderable'], 'Orderable contract must match fixture: ' . $column_key);
        Assert::same(!$should_be_non_resizable, $column_config['resizable'], 'Resizable contract must match fixture: ' . $column_key);
    }

    $source_files = [
        dirname(__DIR__, 2) . '/inc/modules/pricing/class-quote-pricing-metabox.php',
        dirname(__DIR__, 2) . '/assets/js/quote-pricing.js',
    ];

    foreach ($source_files as $source_path) {
        Assert::true(file_exists($source_path), 'Contract source must exist: ' . $source_path);
        if (!file_exists($source_path)) {
            continue;
        }

        $source_name = basename($source_path);
        $source_contents = (string) file_get_contents($source_path);
        $declares_table_key = strpos($source_contents, 'data-tah-table="' . $table_key . '"') !== false
            || strpos($source_contents, 'PRICING_EDITOR_TABLE_KEY') !== false
            || strpos($source_contents, 'pricingTableKey') !== false;
        Assert::true($declares_table_key, 'Source must declare data-tah-table for fixture key: ' . $source_name);

        if ($source_name === 'class-quote-pricing-metabox.php') {
            foreach ($fixture['column_order'] as $column_key) {
                Assert::true(
                    strpos($source_contents, 'data-tah-col="' . $column_key . '"') !== false,
                    'Source must declare data-tah-col for fixture key "' . $column_key . '": ' . $source_name
                );
            }
        }

        if ($source_name === 'quote-pricing.js') {
            Assert::same(
                false,
                strpos($source_contents, 'pricingRowRendererKeys') !== false,
                'quote-pricing.js should not keep a static pricingRowRendererKeys mirror'
            );
            Assert::same(
                true,
                strpos($source_contents, 'getCurrentPricingColumnOrder()') !== false,
                'quote-pricing.js must resolve row column order from DOM/template/config contracts'
            );
            Assert::same(
                true,
                strpos($source_contents, 'Object.keys(cellMap).forEach') !== false,
                'quote-pricing.js row reorder fallback should be derived from runtime row cells'
            );
        }
    }
}

// Run Tests
test_pricing_editor_contract_fixture_matches_config_and_sources();
