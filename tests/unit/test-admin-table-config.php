<?php
/**
 * Unit Tests for TAH_Admin_Table_Config
 */

require_once dirname(__DIR__, 2) . '/inc/modules/admin-table-columns/class-admin-table-config.php';

/**
 * @param object $instance
 * @param string $method
 * @param array<int, mixed> $args
 * @return mixed
 */
function tah_invoke_private_method($instance, $method, array $args = [])
{
    $reflection = new ReflectionClass($instance);
    $reflection_method = $reflection->getMethod($method);
    $reflection_method->setAccessible(true);
    return $reflection_method->invokeArgs($instance, $args);
}

function test_admin_table_config_normalizes_columns_schema()
{
    $config = new TAH_Admin_Table_Config();

    $normalized = tah_invoke_private_method($config, 'normalize_table_config', [[
        'variant_attr' => 'data-custom-variant',
        'row_selector' => 'tbody tr.custom-row',
        'allow_resize' => true,
        'allow_reorder' => true,
        'filler_column_key' => 'Item',
        'columns' => [
            'Item' => ['base_ch' => 14, 'min_ch' => 10, 'max_ch' => 20],
            'actions' => ['locked' => true, 'resizable' => true],
            'qty' => ['resizable' => false, 'min_px' => 80, 'max_px' => 120],
            '__bad key__' => ['min_ch' => 5],
        ],
    ]]);

    Assert::true(isset($normalized['columns']['item']), 'Normalized columns should include "item"');
    Assert::true(isset($normalized['columns']['qty']), 'Normalized columns should include "qty"');
    Assert::true(isset($normalized['columns']['actions']), 'Normalized columns should include "actions"');
    Assert::same(false, $normalized['columns']['actions']['resizable'], 'Locked columns must be forced non-resizable');
    Assert::same(false, $normalized['columns']['actions']['orderable'], 'Locked columns must be forced non-orderable');
    Assert::same(true, $normalized['columns']['actions']['visible'], 'Columns are visible by default');
    Assert::same(true, $normalized['columns']['qty']['orderable'], 'Non-locked columns are orderable by default');
    Assert::same(true, $normalized['columns']['qty']['visible'], 'Non-locked columns are visible by default');
    Assert::same(80, $normalized['columns']['item']['min_px_resolved'], 'Resolved min width should be derived from min_ch');
    Assert::same(160, $normalized['columns']['item']['max_px_resolved'], 'Resolved max width should be derived from max_ch');
    Assert::same(112, $normalized['columns']['item']['base_px_resolved'], 'Resolved base width should be derived from base_ch');
    Assert::same(80, $normalized['columns']['qty']['min_px_resolved'], 'Resolved min width should honor explicit min_px');
    Assert::same(120, $normalized['columns']['qty']['max_px_resolved'], 'Resolved max width should honor explicit max_px');
    Assert::same(true, $normalized['show_reset'], 'Reset controls should be enabled by default');
    Assert::same('item', $normalized['filler_column_key'], 'Filler key should be normalized and preserved when valid');
    Assert::same(false, isset($normalized['grow']), 'Normalized config should not keep legacy grow key');
    Assert::same(false, isset($normalized['locked']), 'Normalized config should not expose derived locked list');
    Assert::same(false, isset($normalized['allowed_columns']), 'Normalized config should not expose derived allowed list');

    $allowed_columns = tah_invoke_private_method($config, 'get_allowed_columns', [$normalized]);
    Assert::same(['item', 'actions', 'qty', '__badkey__'], $allowed_columns, 'Allowed columns should derive from column keys');
    Assert::same(false, isset($normalized['adapter']), 'Adapter mirror should not exist in normalized config');
}

function test_admin_table_config_rejects_invalid_widths_by_column_rules()
{
    $config = new TAH_Admin_Table_Config();

    $table_config = tah_invoke_private_method($config, 'normalize_table_config', [[
        'columns' => [
            'item' => ['min_ch' => 10, 'max_ch' => 20],
            'qty' => ['resizable' => false, 'min_px' => 60, 'max_px' => 120],
            'amount' => ['min_px' => 80, 'max_px' => 160],
        ],
    ]]);

    $allowed_columns = tah_invoke_private_method($config, 'get_allowed_columns', [$table_config]);

    $clean = tah_invoke_private_method($config, 'sanitize_widths', [[
        'item' => 70,      // below min (10ch * 8 = 80)
        'qty' => 90,       // non-resizable
        'amount' => 140,   // valid
        'unknown' => 100,  // unknown column
    ], $allowed_columns, $table_config]);

    Assert::same(['amount' => 140], $clean, 'Sanitize widths should keep only valid, resizable, in-range values');
}

function test_admin_table_config_rejects_unknown_filler_column_key()
{
    $config = new TAH_Admin_Table_Config();
    $normalized = tah_invoke_private_method($config, 'normalize_table_config', [[
        'filler_column_key' => 'missing_column',
        'columns' => [
            'item' => ['min_ch' => 10, 'max_ch' => 20],
            'amount' => ['min_px' => 80, 'max_px' => 160],
        ],
    ]]);

    Assert::same('', $normalized['filler_column_key'], 'Unknown filler key should fail closed');
}

function test_admin_table_config_clamps_base_width_to_bounds()
{
    $config = new TAH_Admin_Table_Config();
    $normalized = tah_invoke_private_method($config, 'normalize_table_config', [[
        'columns' => [
            'item' => ['min_px' => 100, 'base_px' => 70, 'max_px' => 180],
            'description' => ['min_px' => 120, 'base_px' => 260, 'max_px' => 180],
            'qty' => ['min_px' => 80, 'max_px' => 120],
        ],
    ]]);

    Assert::same(100, $normalized['columns']['item']['base_px_resolved'], 'Base width must clamp up to min');
    Assert::same(180, $normalized['columns']['description']['base_px_resolved'], 'Base width must clamp down to max');
    Assert::same(false, isset($normalized['columns']['qty']['base_px_resolved']), 'Base width should be optional when not configured');
}

function test_admin_table_config_sanitizes_order_with_fixed_columns()
{
    $config = new TAH_Admin_Table_Config();

    $table_config = tah_invoke_private_method($config, 'normalize_table_config', [[
        'columns' => [
            'handle' => ['locked' => true],
            'item' => [],
            'qty' => [],
            'amount' => ['orderable' => false],
            'actions' => ['locked' => true],
        ],
    ]]);

    $allowed_columns = tah_invoke_private_method($config, 'get_allowed_columns', [$table_config]);

    $clean_order = tah_invoke_private_method($config, 'sanitize_order', [[
        'qty',
        'item',
        'actions', // ignored (non-orderable)
        'amount',  // ignored (non-orderable)
    ], $allowed_columns, $table_config]);

    Assert::same(
        ['handle', 'qty', 'item', 'amount', 'actions'],
        $clean_order,
        'Sanitize order should only reorder movable columns and keep fixed columns anchored'
    );

    $default_order = tah_invoke_private_method($config, 'sanitize_order', [[
        'item',
        'qty',
    ], $allowed_columns, $table_config]);
    Assert::same([], $default_order, 'Default order should persist as an empty stored order');
}

function test_admin_table_config_reads_only_versioned_prefs()
{
    global $tah_test_user_meta_store;
    $tah_test_user_meta_store = [
        1 => [
            TAH_Admin_Table_Config::OPTION_KEY => [
                'screen_one' => [
                    'pricing_editor:standard' => [
                        'widths' => ['item' => 120],
                        'order' => ['item', 'amount'],
                        'updated' => 1700000000,
                    ],
                    'pricing_editor:insurance' => [
                        'v' => TAH_Admin_Table_Config::PREF_SCHEMA_VERSION,
                        'widths' => ['amount' => 140],
                        'order' => ['amount', 'item'],
                        'updated' => 1700000001,
                    ],
                ],
            ],
        ],
    ];

    $config = new TAH_Admin_Table_Config();
    $prefs = tah_invoke_private_method($config, 'get_user_prefs', ['screen_one']);

    Assert::same(true, isset($prefs['pricing_editor:insurance']), 'Versioned prefs should be kept');
    Assert::same(false, isset($prefs['pricing_editor:standard']), 'Unversioned prefs should be ignored');
}

function test_admin_table_config_width_sanitization_fails_closed_without_allowed_columns()
{
    $config = new TAH_Admin_Table_Config();
    $clean = tah_invoke_private_method($config, 'sanitize_widths', [[
        'item' => 120,
        'amount' => 140,
    ], [], []]);

    Assert::same([], $clean, 'Sanitize widths should fail closed when no allowed columns are defined');
}

function test_admin_table_config_exposes_client_runtime_constants()
{
    $constants = TAH_Admin_Table_Config::get_client_runtime_constants();

    Assert::same(true, isset($constants['widths']), 'Runtime constants should expose widths block');
    Assert::same(TAH_Admin_Table_Config::MIN_WIDTH_PX, $constants['widths']['minPx'], 'Client min width should mirror server anchor');
    Assert::same(TAH_Admin_Table_Config::MAX_WIDTH_PX, $constants['widths']['saveBounds']['fallbackMaxPx'], 'Client max fallback should mirror server anchor');
}

// Run Tests
test_admin_table_config_normalizes_columns_schema();
test_admin_table_config_rejects_invalid_widths_by_column_rules();
test_admin_table_config_rejects_unknown_filler_column_key();
test_admin_table_config_clamps_base_width_to_bounds();
test_admin_table_config_sanitizes_order_with_fixed_columns();
test_admin_table_config_reads_only_versioned_prefs();
test_admin_table_config_width_sanitization_fails_closed_without_allowed_columns();
test_admin_table_config_exposes_client_runtime_constants();
