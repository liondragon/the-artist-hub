<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin table columns module bootstrap.
 */
final class TAH_Admin_Table_Columns_Module
{
    /**
     * Prevent duplicate bootstrap.
     *
     * @var bool
     */
    private static $booted = false;

    /**
     * Module-owned config helper instance.
     *
     * @var TAH_Admin_Table_Config|null
     */
    private static $config_helper = null;

    /**
     * Boot module includes when enabled.
     */
    public static function boot(): void
    {
        if (self::$booted || !self::is_enabled()) {
            return;
        }

        self::$booted = true;

        if (!is_admin()) {
            return;
        }

        self::load_module_classes();
        self::register_hooks();
    }

    /**
     * Runtime module capability flag.
     *
     * @return bool
     */
    public static function is_enabled(): bool
    {
        return (bool) apply_filters('tah_module_admin_table_columns_enabled', true);
    }

    /**
     * Load module classes when files exist.
     */
    private static function load_module_classes(): void
    {
        $config_file = __DIR__ . '/class-admin-table-config.php';
        if (!file_exists($config_file)) {
            self::log_boot_issue('Missing required file: class-admin-table-config.php');
            return;
        }

        require_once $config_file;
    }

    /**
     * Register runtime hooks after classes are loaded.
     */
    private static function register_hooks(): void
    {
        if (!class_exists('TAH_Admin_Table_Config')) {
            self::log_boot_issue('Missing required class: TAH_Admin_Table_Config');
            return;
        }

        if (!(self::$config_helper instanceof TAH_Admin_Table_Config)) {
            self::$config_helper = new TAH_Admin_Table_Config();
        }

        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    /**
     * Enqueue table manager assets for supported admin contexts.
     *
     * @param string $hook_suffix
     */
    public static function enqueue_assets($hook_suffix): void
    {
        $screen_id = self::resolve_context_screen_id((string) $hook_suffix);
        if ($screen_id === '') {
            return;
        }

        $config_helper = self::get_config_helper();
        if (!$config_helper instanceof TAH_Admin_Table_Config) {
            return;
        }

        $screen_config = $config_helper->get_config_for_screen($screen_id);
        $tables = isset($screen_config['tables']) && is_array($screen_config['tables']) ? $screen_config['tables'] : [];
        if (empty($tables)) {
            return;
        }

        $base_dir = get_template_directory() . '/assets/js/';
        $base_uri = get_template_directory_uri() . '/assets/js/';

        $scripts = [
            'tah-admin-tables-constants' => [
                'file' => 'admin-tables-constants.js',
                'deps' => [],
                'required' => true,
            ],
            'tah-admin-tables-store' => [
                'file' => 'admin-tables-store.js',
                'deps' => ['jquery', 'tah-admin-tables-constants'],
                'required' => true,
            ],
            'tah-admin-tables-interaction' => [
                'file' => 'admin-tables-interaction.js',
                'deps' => ['jquery', 'jquery-ui-sortable', 'tah-admin-tables-constants'],
                'required' => true,
            ],
            'tah-admin-tables' => [
                'file' => 'admin-tables-core.js',
                'deps' => [
                    'jquery',
                    'tah-admin-tables-constants',
                    'tah-admin-tables-store',
                    'tah-admin-tables-interaction',
                ],
                'required' => true,
            ],
        ];
        $registered = [];
        $missing_required_files = [];

        foreach ($scripts as $handle => $script) {
            $path = $base_dir . $script['file'];
            if (!file_exists($path)) {
                if (!empty($script['required'])) {
                    $missing_required_files[] = $script['file'];
                }
                continue;
            }

            wp_register_script(
                $handle,
                $base_uri . $script['file'],
                $script['deps'],
                (string) filemtime($path),
                true
            );
            $registered[$handle] = true;
        }

        if (!empty($missing_required_files)) {
            self::log_boot_issue('Missing required admin table scripts: ' . implode(', ', $missing_required_files));
            return;
        }

        if (!empty($registered['tah-admin-tables-constants'])) {
            wp_add_inline_script(
                'tah-admin-tables-constants',
                'window.TAHAdminTablesRuntimeConstants = ' . wp_json_encode(TAH_Admin_Table_Config::get_client_runtime_constants()) . ';',
                'before'
            );
        }

        wp_enqueue_script('tah-admin-tables');

        wp_localize_script('tah-admin-tables', 'tahAdminTablesConfig', [
            'screenId' => $screen_id,
            'config' => $screen_config,
            'nonce' => wp_create_nonce(TAH_Admin_Table_Config::NONCE_ACTION),
        ]);
    }

    /**
     * Emit one-line debug diagnostics for bootstrap/runtime wiring issues.
     */
    private static function log_boot_issue(string $message): void
    {
        if (!defined('WP_DEBUG') || WP_DEBUG !== true) {
            return;
        }

        error_log('[TAH Admin Tables] ' . $message);
    }

    /**
     * @return TAH_Admin_Table_Config|null
     */
    private static function get_config_helper()
    {
        if (self::$config_helper instanceof TAH_Admin_Table_Config) {
            return self::$config_helper;
        }

        return null;
    }

    /**
     * Pure helper for table registration hooks.
     *
     * Input -> normalized table config map output, no side effects.
     *
     * @param array<string, mixed> $tables
     * @param string               $active_screen_id
     * @param string               $target_screen_id
     * @param string               $table_key
     * @param array<string, mixed> $columns
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function register_admin_table(
        array $tables,
        string $active_screen_id,
        string $target_screen_id,
        string $table_key,
        array $columns,
        array $options = []
    ): array {
        $active = sanitize_key($active_screen_id);
        $target = sanitize_key($target_screen_id);
        if ($active === '' || $target === '' || $active !== $target) {
            return $tables;
        }

        $key = sanitize_key($table_key);
        if ($key === '') {
            return $tables;
        }

        $variant_attr = isset($options['variant_attr']) && is_string($options['variant_attr']) && $options['variant_attr'] !== ''
            ? (string) $options['variant_attr']
            : 'data-tah-variant';
        $row_selector = isset($options['row_selector']) && is_string($options['row_selector']) && $options['row_selector'] !== ''
            ? (string) $options['row_selector']
            : 'tbody tr';
        $filler_column_key = isset($options['filler_column_key']) && is_string($options['filler_column_key'])
            ? sanitize_key($options['filler_column_key'])
            : '';

        $tables[$key] = [
            'variant_attr' => $variant_attr,
            'row_selector' => $row_selector,
            'allow_resize' => !isset($options['allow_resize']) || (bool) $options['allow_resize'],
            'allow_reorder' => isset($options['allow_reorder']) ? (bool) $options['allow_reorder'] : false,
            'show_reset' => !isset($options['show_reset']) || (bool) $options['show_reset'],
            'filler_column_key' => $filler_column_key,
            // Column behavior normalization is owned by TAH_Admin_Table_Config.
            'columns' => self::sanitize_columns_contract_input($columns),
        ];

        return $tables;
    }

    /**
     * @param array<string, mixed> $columns
     * @return array<string, array<string, mixed>>
     */
    private static function sanitize_columns_contract_input(array $columns): array
    {
        $sanitized = [];

        foreach ($columns as $column_key => $definition) {
            if (!is_string($column_key)) {
                continue;
            }

            $key = sanitize_key($column_key);
            if ($key === '') {
                continue;
            }

            $sanitized[$key] = is_array($definition) ? $definition : [];
        }

        return $sanitized;
    }

    /**
     * Resolve table config screen context for the current admin request.
     *
     * @param string $hook_suffix
     * @return string
     */
    private static function resolve_context_screen_id(string $hook_suffix): string
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen instanceof WP_Screen) {
            return '';
        }

        $context_screen_id = '';

        /**
         * Allow other modules to map admin requests to table-config contexts.
         *
         * @param string    $context_screen_id
         * @param string    $hook_suffix
         * @param WP_Screen $screen
         */
        $context_screen_id = (string) apply_filters(
            'tah_admin_table_context_screen_id',
            $context_screen_id,
            $hook_suffix,
            $screen
        );

        $context_screen_id = sanitize_key($context_screen_id);
        if ($context_screen_id !== '') {
            return $context_screen_id;
        }

        // Default fallback for screens that do not provide an explicit context mapping.
        return sanitize_key((string) $screen->id);
    }
}
