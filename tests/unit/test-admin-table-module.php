<?php
/**
 * Unit Tests for TAH_Admin_Table_Columns_Module helper behavior.
 */

require_once dirname(__DIR__, 2) . '/inc/modules/admin-table-columns/class-admin-table-columns-module.php';

function test_admin_table_module_register_helper_preserves_column_visibility_flags()
{
    $tables = TAH_Admin_Table_Columns_Module::register_admin_table(
        [],
        'tah-quote-editor',
        'tah-quote-editor',
        'pricing_editor',
        [
            'item' => ['visible' => false, 'min_ch' => 12],
            'amount' => ['visible' => true],
        ],
        []
    );

    Assert::true(isset($tables['pricing_editor']), 'Helper should register matching table key');
    Assert::same(false, $tables['pricing_editor']['columns']['item']['visible'], 'Helper must preserve explicit visible=false');
    Assert::same(true, $tables['pricing_editor']['columns']['amount']['visible'], 'Helper must preserve explicit visible=true');
}

function test_admin_table_module_register_helper_only_sanitizes_column_keys()
{
    $tables = TAH_Admin_Table_Columns_Module::register_admin_table(
        [],
        'tah-quote-editor',
        'tah-quote-editor',
        'pricing_editor',
        [
            'Weird Key!!' => ['locked' => true, 'resizable' => true],
            'plain' => 'not-an-array',
        ],
        []
    );

    Assert::true(isset($tables['pricing_editor']['columns']['weirdkey']), 'Helper should sanitize column keys');
    Assert::same(true, $tables['pricing_editor']['columns']['weirdkey']['resizable'], 'Helper should not normalize column behavior flags');
    Assert::same([], $tables['pricing_editor']['columns']['plain'], 'Non-array definitions should fail closed to empty array');
}

function test_admin_table_module_register_helper_sanitizes_filler_column_key()
{
    $tables = TAH_Admin_Table_Columns_Module::register_admin_table(
        [],
        'tah-quote-editor',
        'tah-quote-editor',
        'pricing_editor',
        [
            'description' => [],
            'amount' => [],
        ],
        [
            'filler_column_key' => 'Description!!',
        ]
    );

    Assert::same('description', $tables['pricing_editor']['filler_column_key'], 'Helper should sanitize filler key');
}

// Run Tests
test_admin_table_module_register_helper_preserves_column_visibility_flags();
test_admin_table_module_register_helper_only_sanitizes_column_keys();
test_admin_table_module_register_helper_sanitizes_filler_column_key();
