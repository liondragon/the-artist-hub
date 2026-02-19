<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles configuration and persistence for resizable/sortable admin tables.
 */
class TAH_Admin_Table_Config
{
    const OPTION_KEY = '_tah_table_prefs';
    const PREF_SCHEMA_VERSION = 1;
    const NONCE_ACTION = 'tah_save_table_prefs';
    const MIN_WIDTH_PX = 40;
    const MAX_WIDTH_PX = 3000;
    const CHAR_WIDTH_PX = 8;
    const NORMALIZE_EPSILON_PX = 2;
    const SAVE_BOUNDS_MAX_FLOOR_PX = 480;
    const SAVE_BOUNDS_MAX_FACTOR = 3;
    const SAVED_SANITY_MIN_FACTOR = 0.7;
    const SAVED_SANITY_MAX_FACTOR = 1.02;
    const DEFAULT_VARIANT_ATTR = 'data-tah-variant';
    const DEFAULT_ROW_SELECTOR = 'tbody tr';

    public function __construct()
    {
        add_action('wp_ajax_tah_save_table_prefs', [$this, 'ajax_save_prefs']);
    }

    /**
     * Client runtime constants consumed by admin table JS modules.
     *
     * @return array<string, mixed>
     */
    public static function get_client_runtime_constants(): array
    {
        return [
            'widths' => [
                'minPx' => self::MIN_WIDTH_PX,
                'normalizeEpsilonPx' => self::NORMALIZE_EPSILON_PX,
                'saveBounds' => [
                    'maxFloorPx' => self::SAVE_BOUNDS_MAX_FLOOR_PX,
                    'maxFactor' => self::SAVE_BOUNDS_MAX_FACTOR,
                    'fallbackMaxPx' => self::MAX_WIDTH_PX,
                ],
                'savedSanity' => [
                    'minFactor' => self::SAVED_SANITY_MIN_FACTOR,
                    'maxFactor' => self::SAVED_SANITY_MAX_FACTOR,
                ],
            ],
        ];
    }

    /**
     * Get configuration for a specific screen/table.
     * 
     * @param string $screen_id The current screen ID.
     * @return array
     */
    public function get_config_for_screen($screen_id)
    {
        // Default configuration structure
        $config = [
            'tables' => [],
            'prefs' => $this->get_user_prefs($screen_id),
            'i18n' => [
                'resetTableLayout' => __('Reset table layout', 'the-artist'),
            ]
        ];

        /**
         * Filter to register tables for this screen.
         *
         * Expected format:
         * [
         *   'table_key' => [
         *     'row_selector' => 'tbody tr', // rows managed by reorder logic
         *     'variant_attr' => 'data-tah-variant',
         *     'allow_resize' => true/false,
         *     'allow_reorder' => true/false,
         *     'show_reset' => true/false,
         *     'columns' => [
         *       'price' => [
         *         'locked' => false,
         *         'resizable' => true,
         *         'orderable' => true,
         *         'visible' => true,
         *         'base_ch' => 14,
         *         'base_px' => 112, // optional deterministic initial/reset width
         *         'min_ch' => 11,
         *         'min_px' => 96, // optional
         *         'max_ch' => 16,
         *         'max_px' => 240, // optional
         *       ],
         *     ],
         *   ]
         * ]
         */
        $tables = apply_filters('tah_admin_table_registry', [], $screen_id);
        $config['tables'] = $this->normalize_table_registry(is_array($tables) ? $tables : []);

        return $config;
    }

    /**
     * Get user preferences for a screen.
     */
    private function get_user_prefs($screen_id)
    {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return [];
        }

        $all_prefs = get_user_meta($user_id, self::OPTION_KEY, true);
        if (!is_array($all_prefs)) {
            return [];
        }

        $screen_prefs = isset($all_prefs[$screen_id]) && is_array($all_prefs[$screen_id]) ? $all_prefs[$screen_id] : [];
        return $this->normalize_screen_prefs($screen_prefs);
    }

    /**
     * @param array<string, mixed> $screen_prefs
     * @return array<string, array<string, mixed>>
     */
    private function normalize_screen_prefs(array $screen_prefs): array
    {
        $normalized = [];

        foreach ($screen_prefs as $storage_key => $entry) {
            if (!is_string($storage_key) || $storage_key === '' || !is_array($entry)) {
                continue;
            }

            $version = isset($entry['v']) && is_numeric($entry['v']) ? (int) $entry['v'] : 0;
            if ($version !== self::PREF_SCHEMA_VERSION) {
                continue;
            }

            $normalized[$storage_key] = [
                'v' => self::PREF_SCHEMA_VERSION,
                'widths' => isset($entry['widths']) && is_array($entry['widths']) ? $entry['widths'] : [],
                'order' => isset($entry['order']) && is_array($entry['order']) ? $entry['order'] : [],
                'updated' => isset($entry['updated']) && is_numeric($entry['updated']) ? (int) $entry['updated'] : 0,
            ];
        }

        return $normalized;
    }

    /**
     * AJAX handler to save table preferences.
     */
    public function ajax_save_prefs()
    {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error('insufficient_permissions');
        }

        $screen_id = isset($_POST['screen_id']) ? sanitize_key((string) $_POST['screen_id']) : '';
        $table_key = isset($_POST['table_key']) ? sanitize_key((string) $_POST['table_key']) : '';
        $variant = isset($_POST['variant']) ? sanitize_key((string) $_POST['variant']) : '';

        if (!$screen_id || !$table_key) {
            wp_send_json_error('missing_params');
        }

        $tables = $this->get_tables_for_screen($screen_id);
        if (!isset($tables[$table_key]) || !is_array($tables[$table_key])) {
            wp_send_json_error('invalid_table');
        }

        $table_config = $tables[$table_key];
        $allowed_columns = $this->get_allowed_columns($table_config);

        // Construct the storage key (e.g., "pricing_editor:standard" vs "pricing_editor")
        $storage_key = $variant ? $table_key . ':' . $variant : $table_key;

        $widths = isset($_POST['widths']) ? (array) $_POST['widths'] : [];
        $order = isset($_POST['order']) ? (array) $_POST['order'] : [];

        $clean_widths = $this->sanitize_widths($widths, $allowed_columns, $table_config);
        $clean_order = $this->sanitize_order($order, $allowed_columns, $table_config);

        $this->update_user_pref($screen_id, $storage_key, [
            'v' => self::PREF_SCHEMA_VERSION,
            'widths' => $clean_widths,
            'order' => $clean_order,
            'updated' => time()
        ]);

        wp_send_json_success();
    }

    /**
     * @param string $screen_id
     * @return array<string, mixed>
     */
    private function get_tables_for_screen($screen_id)
    {
        $tables = apply_filters('tah_admin_table_registry', [], $screen_id);
        return $this->normalize_table_registry(is_array($tables) ? $tables : []);
    }

    /**
     * @param array<string, mixed> $tables
     * @return array<string, array<string, mixed>>
     */
    private function normalize_table_registry(array $tables): array
    {
        $normalized = [];

        foreach ($tables as $table_key => $table_config) {
            if (!is_string($table_key) || !is_array($table_config)) {
                continue;
            }
            $key = sanitize_key($table_key);
            if ($key === '') {
                continue;
            }

            $normalized[$key] = $this->normalize_table_config($table_config);
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $table_config
     * @return array<string, mixed>
     */
    private function normalize_table_config(array $table_config): array
    {
        $variant_attr = self::DEFAULT_VARIANT_ATTR;
        if (isset($table_config['variant_attr']) && is_string($table_config['variant_attr']) && $table_config['variant_attr'] !== '') {
            $variant_attr = $table_config['variant_attr'];
        }

        $row_selector = self::DEFAULT_ROW_SELECTOR;
        if (isset($table_config['row_selector']) && is_string($table_config['row_selector']) && $table_config['row_selector'] !== '') {
            $row_selector = $table_config['row_selector'];
        }

        $allow_resize = true;
        if (array_key_exists('allow_resize', $table_config)) {
            $allow_resize = (bool) $table_config['allow_resize'];
        }

        $allow_reorder = false;
        if (array_key_exists('allow_reorder', $table_config)) {
            $allow_reorder = (bool) $table_config['allow_reorder'];
        }

        $show_reset = true;
        if (array_key_exists('show_reset', $table_config)) {
            $show_reset = (bool) $table_config['show_reset'];
        }

        $columns = [];
        if (isset($table_config['columns']) && is_array($table_config['columns'])) {
            $columns = $this->normalize_columns_config($table_config['columns']);
        }
        $filler_column_key = '';
        if (isset($table_config['filler_column_key']) && is_string($table_config['filler_column_key'])) {
            $filler_column_key = sanitize_key($table_config['filler_column_key']);
            if ($filler_column_key !== '' && !isset($columns[$filler_column_key])) {
                $filler_column_key = '';
            }
        }

        $normalized = [
            'variant_attr' => $variant_attr,
            'row_selector' => $row_selector,
            'allow_resize' => $allow_resize,
            'allow_reorder' => $allow_reorder,
            'show_reset' => $show_reset,
            'filler_column_key' => $filler_column_key,
            'columns' => $columns,
        ];

        return $normalized;
    }

    /**
     * @param array<string, mixed> $table_config
     * @return array<int, string>
     */
    private function get_allowed_columns(array $table_config): array
    {
        if (isset($table_config['columns']) && is_array($table_config['columns'])) {
            return $this->sanitize_column_key_list(array_keys($table_config['columns']));
        }

        return [];
    }

    /**
     * @param array<int, mixed> $column_keys
     * @return array<int, string>
     */
    private function sanitize_column_key_list(array $column_keys): array
    {
        $clean = [];

        foreach ($column_keys as $column_key) {
            if (!is_string($column_key)) {
                continue;
            }

            $key = sanitize_key($column_key);
            if ($key === '' || isset($clean[$key])) {
                continue;
            }

            $clean[$key] = true;
        }

        return array_keys($clean);
    }

    /**
     * @param array<string, mixed> $columns
     * @return array<string, array<string, mixed>>
     */
    private function normalize_columns_config(array $columns): array
    {
        $normalized = [];

        foreach ($columns as $column_key => $raw_column_config) {
            if (!is_string($column_key)) {
                continue;
            }

            $key = sanitize_key($column_key);
            if ($key === '') {
                continue;
            }

            $column_config = is_array($raw_column_config) ? $raw_column_config : [];
            $locked = isset($column_config['locked']) ? (bool) $column_config['locked'] : false;
            $resizable = isset($column_config['resizable']) ? (bool) $column_config['resizable'] : true;
            $orderable = isset($column_config['orderable']) ? (bool) $column_config['orderable'] : true;
            $visible = isset($column_config['visible']) ? (bool) $column_config['visible'] : true;
            if ($locked) {
                $resizable = false;
                $orderable = false;
            }
            if (!$visible) {
                $resizable = false;
                $orderable = false;
            }

            $normalized_column = [
                'locked' => $locked,
                'resizable' => $resizable,
                'orderable' => $orderable,
                'visible' => $visible,
            ];

            if (isset($column_config['min_ch']) && is_numeric($column_config['min_ch'])) {
                $normalized_column['min_ch'] = (float) $column_config['min_ch'];
            }
            if (isset($column_config['min_px']) && is_numeric($column_config['min_px'])) {
                $normalized_column['min_px'] = (int) round((float) $column_config['min_px']);
            }
            if (isset($column_config['base_ch']) && is_numeric($column_config['base_ch'])) {
                $normalized_column['base_ch'] = (float) $column_config['base_ch'];
            }
            if (isset($column_config['base_px']) && is_numeric($column_config['base_px'])) {
                $normalized_column['base_px'] = (int) round((float) $column_config['base_px']);
            }
            if (isset($column_config['max_ch']) && is_numeric($column_config['max_ch'])) {
                $normalized_column['max_ch'] = (float) $column_config['max_ch'];
            }
            if (isset($column_config['max_px']) && is_numeric($column_config['max_px'])) {
                $normalized_column['max_px'] = (int) round((float) $column_config['max_px']);
            }
            $resolved_min_px = $this->coerce_column_min_px($normalized_column);
            $resolved_max_px = $this->coerce_column_max_px($normalized_column, $resolved_min_px);
            $normalized_column['min_px_resolved'] = $resolved_min_px;
            $normalized_column['max_px_resolved'] = $resolved_max_px;
            $resolved_base_px = $this->coerce_column_base_px($normalized_column, $resolved_min_px, $resolved_max_px);
            if ($resolved_base_px !== null) {
                $normalized_column['base_px_resolved'] = $resolved_base_px;
            }

            $normalized[$key] = $normalized_column;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $table_config
     * @return array<string, mixed>
     */
    private function get_column_config(array $table_config, string $column_key): array
    {
        if (!isset($table_config['columns']) || !is_array($table_config['columns'])) {
            return [];
        }

        $columns = $table_config['columns'];
        if (!isset($columns[$column_key]) || !is_array($columns[$column_key])) {
            return [];
        }

        return $columns[$column_key];
    }

    /**
     * @param array<int|string, mixed> $order
     * @param array<int, string> $allowed_columns
     * @param array<string, mixed> $table_config
     * @return array<int, string>
     */
    private function sanitize_order(array $order, array $allowed_columns, array $table_config): array
    {
        if (empty($allowed_columns)) {
            return [];
        }

        $default_order = array_values($allowed_columns);
        $allowed_map = array_fill_keys($default_order, true);
        $orderable_map = [];
        foreach ($default_order as $column_key) {
            if ($this->is_column_orderable($table_config, $column_key)) {
                $orderable_map[$column_key] = true;
            }
        }

        if (empty($orderable_map)) {
            return [];
        }

        $incoming_orderable = [];
        $seen_orderable = [];

        foreach ($order as $column_key) {
            if (!is_scalar($column_key)) {
                continue;
            }

            $key = sanitize_key((string) $column_key);
            if ($key === '' || !isset($allowed_map[$key]) || !isset($orderable_map[$key]) || isset($seen_orderable[$key])) {
                continue;
            }

            $seen_orderable[$key] = true;
            $incoming_orderable[] = $key;
        }

        foreach (array_keys($orderable_map) as $column_key) {
            if (!isset($seen_orderable[$column_key])) {
                $incoming_orderable[] = $column_key;
            }
        }

        $rebuilt = [];
        $order_index = 0;
        foreach ($default_order as $column_key) {
            if (isset($orderable_map[$column_key])) {
                $rebuilt[] = $incoming_orderable[$order_index] ?? $column_key;
                $order_index++;
                continue;
            }

            $rebuilt[] = $column_key;
        }

        if ($rebuilt === $default_order) {
            return [];
        }

        return $rebuilt;
    }

    /**
     * @param array<int|string, mixed> $widths
     * @param array<int, string> $allowed_columns
     * @param array<string, mixed> $table_config
     * @return array<string, int>
     */
    private function sanitize_widths(array $widths, array $allowed_columns, array $table_config): array
    {
        if (empty($allowed_columns)) {
            return [];
        }

        $clean = [];
        $allowed_map = array_fill_keys($allowed_columns, true);

        foreach ($widths as $column_key => $width) {
            $key = sanitize_key((string) $column_key);
            if ($key === '') {
                continue;
            }

            if (!isset($allowed_map[$key])) {
                continue;
            }
            if ($this->is_column_non_resizable($table_config, $key)) {
                continue;
            }
            if (!$this->is_column_visible($table_config, $key)) {
                continue;
            }

            if (!is_scalar($width) || !is_numeric($width)) {
                continue;
            }

            $value = (int) round((float) $width);
            if ($value < self::MIN_WIDTH_PX || $value > self::MAX_WIDTH_PX) {
                continue;
            }

            $min_allowed = $this->get_column_min_width_px($table_config, $key);
            $max_allowed = $this->get_column_max_width_px($table_config, $key, $min_allowed);
            if ($value < $min_allowed || $value > $max_allowed) {
                continue;
            }

            $clean[$key] = $value;
        }

        return $clean;
    }

    /**
     * @param array<string, mixed> $table_config
     */
    private function get_column_min_width_px(array $table_config, string $column_key): int
    {
        $column_config = $this->get_column_config($table_config, $column_key);
        return $this->coerce_column_min_px($column_config);
    }

    /**
     * @param array<string, mixed> $table_config
     */
    private function get_column_max_width_px(array $table_config, string $column_key, int $min_allowed): int
    {
        $column_config = $this->get_column_config($table_config, $column_key);
        return $this->coerce_column_max_px($column_config, $min_allowed);
    }

    /**
     * @param array<string, mixed> $column_config
     */
    private function coerce_column_min_px(array $column_config): int
    {
        $min = self::MIN_WIDTH_PX;
        if (isset($column_config['min_ch']) && is_numeric($column_config['min_ch'])) {
            $min = max($min, (int) round((float) $column_config['min_ch'] * self::CHAR_WIDTH_PX));
        }
        if (isset($column_config['min_px']) && is_numeric($column_config['min_px'])) {
            $min = max($min, (int) round((float) $column_config['min_px']));
        }
        return $min;
    }

    /**
     * @param array<string, mixed> $column_config
     */
    private function coerce_column_max_px(array $column_config, int $min_allowed): int
    {
        $max = self::MAX_WIDTH_PX;
        if (isset($column_config['max_ch']) && is_numeric($column_config['max_ch'])) {
            $max = min($max, (int) round((float) $column_config['max_ch'] * self::CHAR_WIDTH_PX));
        }
        if (isset($column_config['max_px']) && is_numeric($column_config['max_px'])) {
            $max = min($max, (int) round((float) $column_config['max_px']));
        }
        return max($max, $min_allowed);
    }

    /**
     * @param array<string, mixed> $column_config
     */
    private function coerce_column_base_px(array $column_config, int $min_allowed, int $max_allowed): ?int
    {
        $has_base = false;
        $base = $min_allowed;

        if (isset($column_config['base_ch']) && is_numeric($column_config['base_ch'])) {
            $base = (int) round((float) $column_config['base_ch'] * self::CHAR_WIDTH_PX);
            $has_base = true;
        }

        if (isset($column_config['base_px']) && is_numeric($column_config['base_px'])) {
            $base = (int) round((float) $column_config['base_px']);
            $has_base = true;
        }

        if (!$has_base) {
            return null;
        }

        if ($base < $min_allowed) {
            $base = $min_allowed;
        }
        if ($base > $max_allowed) {
            $base = $max_allowed;
        }

        return $base;
    }

    /**
     * @param array<string, mixed> $table_config
     */
    private function is_column_non_resizable(array $table_config, string $column_key): bool
    {
        $column_config = $this->get_column_config($table_config, $column_key);
        if (empty($column_config)) {
            return false;
        }

        return isset($column_config['resizable']) && $column_config['resizable'] === false;
    }

    /**
     * @param array<string, mixed> $table_config
     */
    private function is_column_orderable(array $table_config, string $column_key): bool
    {
        $column_config = $this->get_column_config($table_config, $column_key);
        if (empty($column_config)) {
            return true;
        }

        return !isset($column_config['orderable']) || $column_config['orderable'] !== false;
    }

    /**
     * @param array<string, mixed> $table_config
     */
    private function is_column_visible(array $table_config, string $column_key): bool
    {
        $column_config = $this->get_column_config($table_config, $column_key);
        if (empty($column_config)) {
            return true;
        }

        return !isset($column_config['visible']) || $column_config['visible'] !== false;
    }

    /**
     * Update a specific preference.
     */
    private function update_user_pref($screen_id, $table_key, $data)
    {
        $user_id = get_current_user_id();
        $all_prefs = get_user_meta($user_id, self::OPTION_KEY, true);

        if (!is_array($all_prefs)) {
            $all_prefs = [];
        }

        if (!isset($all_prefs[$screen_id])) {
            $all_prefs[$screen_id] = [];
        }

        $all_prefs[$screen_id][$table_key] = $data;

        update_user_meta($user_id, self::OPTION_KEY, $all_prefs);
    }
}
