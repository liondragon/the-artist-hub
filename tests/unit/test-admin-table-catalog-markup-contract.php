<?php
/**
 * Contract checks for pricing catalog table markup.
 */

require_once dirname(__DIR__, 2) . '/inc/modules/admin-table-columns/class-admin-table-columns-module.php';
if (!isset($GLOBALS['wpdb']) || !is_object($GLOBALS['wpdb'])) {
    $GLOBALS['wpdb'] = (object) ['prefix' => 'wp_'];
}
require_once dirname(__DIR__, 2) . '/inc/modules/pricing/class-pricing-repository.php';
require_once dirname(__DIR__, 2) . '/inc/modules/pricing/class-pricing-catalog-admin.php';

if (!function_exists('esc_attr')) {
    function esc_attr($value)
    {
        return (string) $value;
    }
}
if (!function_exists('esc_url')) {
    function esc_url($value)
    {
        return (string) $value;
    }
}
if (!function_exists('esc_html__')) {
    function esc_html__($value)
    {
        return (string) $value;
    }
}
if (!function_exists('add_query_arg')) {
    function add_query_arg($args, $url)
    {
        if (!is_array($args) || empty($args)) {
            return (string) $url;
        }

        $separator = strpos((string) $url, '?') === false ? '?' : '&';
        return (string) $url . $separator . http_build_query($args);
    }
}
if (!function_exists('wp_nonce_url')) {
    function wp_nonce_url($url, $action = -1, $name = '_wpnonce')
    {
        return add_query_arg([$name => (string) $action], (string) $url);
    }
}

/**
 * @param object $instance
 * @param string $method
 * @param array<int, mixed> $args
 * @return mixed
 */
function tah_catalog_contract_invoke_private_method($instance, $method, array $args = [])
{
    $reflection = new ReflectionClass($instance);
    $reflection_method = $reflection->getMethod($method);
    $reflection_method->setAccessible(true);
    return $reflection_method->invokeArgs($instance, $args);
}

function test_pricing_catalog_markup_contract_has_matching_header_and_row_column_keys()
{
    $catalog_admin = new TAH_Pricing_Catalog_Admin();
    $items = [[
        'id' => 17,
        'title' => 'Sample Item',
        'description' => 'Sample Description',
        'sku' => 'SKU-17',
        'trade_id' => 0,
        'unit_type' => 'flat',
        'unit_price' => 123.45,
        'is_active' => 1,
        'updated_at' => '2026-02-19 10:00:00',
        'price_history' => [],
    ]];

    ob_start();
    tah_catalog_contract_invoke_private_method(
        $catalog_admin,
        'render_items_table',
        [$items, 'standard', 'https://example.test/wp-admin/edit.php?post_type=quotes&page=tah-pricing-catalog']
    );
    $html = (string) ob_get_clean();

    preg_match_all('/<th[^>]+data-tah-col="([^"]+)"/', $html, $header_matches);
    preg_match_all('/<td[^>]+data-tah-col="([^"]+)"/', $html, $cell_matches);

    $header_keys = isset($header_matches[1]) ? $header_matches[1] : [];
    $cell_keys = isset($cell_matches[1]) ? $cell_matches[1] : [];
    $unique_cell_keys = array_values(array_unique($cell_keys));

    Assert::same(true, in_array('pricing_catalog', [$catalog_admin::TABLE_KEY], true), 'Catalog table key should be stable');
    Assert::same(true, strpos($html, 'data-tah-table="pricing_catalog"') !== false, 'Catalog markup must declare table key');
    Assert::same($header_keys, $unique_cell_keys, 'Catalog row cell keys must match header keys exactly');
    Assert::same(true, strpos($html, 'data-tah-col="actions" data-tah-locked="1"') !== false, 'Actions header must stay locked');
}

// Run tests
test_pricing_catalog_markup_contract_has_matching_header_and_row_column_keys();
