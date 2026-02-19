<?php
/**
 * Contract checks for pricing catalog table registration.
 */

require_once dirname(__DIR__, 2) . '/inc/modules/admin-table-columns/class-admin-table-columns-module.php';
if (!isset($GLOBALS['wpdb']) || !is_object($GLOBALS['wpdb'])) {
    $GLOBALS['wpdb'] = (object) ['prefix' => 'wp_'];
}
require_once dirname(__DIR__, 2) . '/inc/modules/pricing/class-pricing-repository.php';
require_once dirname(__DIR__, 2) . '/inc/modules/pricing/class-pricing-catalog-admin.php';

function test_pricing_catalog_contract_is_registered_for_catalog_screen()
{
    $catalog_admin = new TAH_Pricing_Catalog_Admin();
    $tables = $catalog_admin->register_table_config([], TAH_Pricing_Catalog_Admin::CONTEXT_SCREEN_ID);

    Assert::true(isset($tables[TAH_Pricing_Catalog_Admin::TABLE_KEY]), 'Catalog table contract should be registered for catalog screen');
    if (!isset($tables[TAH_Pricing_Catalog_Admin::TABLE_KEY]) || !is_array($tables[TAH_Pricing_Catalog_Admin::TABLE_KEY])) {
        return;
    }

    $table = $tables[TAH_Pricing_Catalog_Admin::TABLE_KEY];
    Assert::same(false, $table['allow_reorder'], 'Catalog table should keep header reorder disabled');
    Assert::same(true, $table['allow_resize'], 'Catalog table should allow resize');
    Assert::same(true, $table['show_reset'], 'Catalog table should expose reset controls');
    Assert::same(true, isset($table['columns']['actions']), 'Catalog table should define actions column in contract');
}

function test_pricing_catalog_contract_skips_other_screens()
{
    $catalog_admin = new TAH_Pricing_Catalog_Admin();
    $tables = $catalog_admin->register_table_config([], 'edit-quotes');

    Assert::same(false, isset($tables[TAH_Pricing_Catalog_Admin::TABLE_KEY]), 'Catalog table contract should not register on unrelated screens');
}

// Run tests
test_pricing_catalog_contract_is_registered_for_catalog_screen();
test_pricing_catalog_contract_skips_other_screens();
