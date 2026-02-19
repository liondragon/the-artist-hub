<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Pricing catalog admin page under Quotes menu.
 */
final class TAH_Pricing_Catalog_Admin
{
    const PAGE_SLUG = 'tah-pricing-catalog';
    const CONTEXT_SCREEN_ID = 'quotes_page_tah-pricing-catalog';
    const TABLE_KEY = 'pricing_catalog';
    const NONCE_SAVE_ACTION = 'tah_pricing_catalog_save';
    const NONCE_SAVE_NAME = '_tah_pricing_catalog_nonce';
    private const COLUMN_CONTRACT = [
        'title' => ['min_ch' => 18, 'base_ch' => 24],
        'sku' => ['min_ch' => 10, 'max_ch' => 16],
        'trade' => ['min_ch' => 10, 'max_ch' => 18],
        'unit' => ['min_ch' => 8, 'max_ch' => 12],
        'price' => ['min_ch' => 9, 'max_ch' => 14],
        'status' => ['min_ch' => 8, 'max_ch' => 12],
        'updated' => ['min_ch' => 10, 'max_ch' => 18],
        'history' => ['min_ch' => 14, 'max_ch' => 32],
        'actions' => ['locked' => true, 'resizable' => false, 'orderable' => false],
    ];

    /**
     * @var TAH_Pricing_Repository
     */
    private $repository;

    /**
     * @param TAH_Pricing_Repository|null $repository
     */
    public function __construct($repository = null)
    {
        $this->repository = $repository instanceof TAH_Pricing_Repository
            ? $repository
            : new TAH_Pricing_Repository();

        add_action('admin_menu', [$this, 'register_submenu_page']);
        add_filter('tah_admin_table_registry', [$this, 'register_table_config'], 10, 2);
    }

    /**
     * Register pricing catalog page under Quotes.
     */
    public function register_submenu_page()
    {
        $hook_suffix = add_submenu_page(
            'edit.php?post_type=quotes',
            __('Pricing Catalog', 'the-artist'),
            __('Pricing Catalog', 'the-artist'),
            'edit_posts',
            self::PAGE_SLUG,
            [$this, 'render_page']
        );

        if ($hook_suffix) {
            add_action('load-' . $hook_suffix, [$this, 'handle_page_actions']);
        }
    }

    /**
     * Register admin table contract for catalog page columns module integration.
     *
     * @param array<string, mixed> $tables
     * @param string               $screen_id
     * @return array<string, mixed>
     */
    public function register_table_config($tables, $screen_id)
    {
        if (!class_exists('TAH_Admin_Table_Columns_Module')) {
            return is_array($tables) ? $tables : [];
        }

        return TAH_Admin_Table_Columns_Module::register_admin_table(
            is_array($tables) ? $tables : [],
            (string) $screen_id,
            self::CONTEXT_SCREEN_ID,
            self::TABLE_KEY,
            self::COLUMN_CONTRACT,
            [
                'row_selector' => 'tbody tr',
                'variant_attr' => 'data-tah-variant',
                'allow_resize' => true,
                'allow_reorder' => false,
                'show_reset' => true,
                'filler_column_key' => 'title',
            ]
        );
    }

    /**
     * Handle page form submissions and status toggle actions.
     */
    public function handle_page_actions()
    {
        if (!current_user_can('edit_posts')) {
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = isset($_POST['tah_catalog_action']) ? sanitize_key((string) $_POST['tah_catalog_action']) : '';
            if ($action === 'save_item') {
                $this->handle_save_item();
            }
        }

        $action = isset($_GET['tah_action']) ? sanitize_key((string) $_GET['tah_action']) : '';
        if ($action === 'toggle_active') {
            $this->handle_toggle_active();
        }
    }

    /**
     * Render pricing catalog page.
     */
    public function render_page()
    {
        if (!current_user_can('edit_posts')) {
            wp_die(esc_html__('You do not have permission to manage the pricing catalog.', 'the-artist'));
        }

        $catalog_type = $this->sanitize_catalog_type(
            isset($_GET['catalog_type']) ? wp_unslash((string) $_GET['catalog_type']) : 'standard'
        );
        $status_filter = $this->sanitize_status_filter(
            isset($_GET['status']) ? wp_unslash((string) $_GET['status']) : 'active'
        );
        $trade_filter_id = isset($_GET['trade_id']) ? (int) $_GET['trade_id'] : 0;
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash((string) $_GET['s'])) : '';

        $filters = [
            'catalog_type' => $catalog_type,
        ];

        if ($trade_filter_id > 0) {
            $filters['trade_id'] = $trade_filter_id;
        }

        if ($status_filter === 'active') {
            $filters['is_active'] = true;
        } elseif ($status_filter === 'inactive') {
            $filters['is_active'] = false;
        }

        if ($search !== '') {
            $filters['search'] = $search;
        }

        $edit_item_id = isset($_GET['edit_item']) ? (int) $_GET['edit_item'] : 0;
        $edit_item = $edit_item_id > 0 ? $this->repository->get_item_by_id($edit_item_id) : null;
        if ($edit_item && isset($edit_item['catalog_type'])) {
            $catalog_type = $this->sanitize_catalog_type((string) $edit_item['catalog_type']);
            $filters['catalog_type'] = $catalog_type;
        }

        $trade_terms = $this->get_trade_terms_for_catalog($catalog_type);
        $items = $this->repository->get_catalog_items($filters);
        $impact_preview_count = $edit_item ? $this->repository->count_active_quotes_by_pricing_item((int) $edit_item['id']) : null;
        $base_url = $this->build_page_url([
            'catalog_type' => $catalog_type,
            'status' => $status_filter,
            'trade_id' => $trade_filter_id > 0 ? $trade_filter_id : null,
            's' => $search !== '' ? $search : null,
        ]);

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Pricing Catalog', 'the-artist') . '</h1>';

        $this->render_notice_from_query();
        $this->render_catalog_tabs($catalog_type);
        $this->render_filters_form($catalog_type, $status_filter, $trade_filter_id, $search, $trade_terms);
        $this->render_item_form($catalog_type, $edit_item, $trade_terms, $base_url, $impact_preview_count);
        $this->render_items_table($items, $catalog_type, $base_url);

        echo '</div>';
    }

    /**
     * Save or update a catalog item.
     */
    private function handle_save_item()
    {
        check_admin_referer(self::NONCE_SAVE_ACTION, self::NONCE_SAVE_NAME);

        $item_id = isset($_POST['item_id']) ? (int) $_POST['item_id'] : 0;
        $catalog_type = $this->sanitize_catalog_type(
            isset($_POST['catalog_type']) ? wp_unslash((string) $_POST['catalog_type']) : 'standard'
        );
        $return_url = $this->build_page_url([
            'catalog_type' => $catalog_type,
            'status' => isset($_POST['return_status']) ? sanitize_key((string) $_POST['return_status']) : 'active',
            'trade_id' => isset($_POST['return_trade_id']) ? (int) $_POST['return_trade_id'] : null,
            's' => isset($_POST['return_search']) ? sanitize_text_field(wp_unslash((string) $_POST['return_search'])) : null,
        ]);

        $data = $this->sanitize_item_form_data($_POST, $catalog_type);
        if ($data === null) {
            wp_safe_redirect($this->add_notice_to_url($return_url, 'validation_error'));
            exit;
        }

        if ($item_id > 0) {
            $existing = $this->repository->get_item_by_id($item_id);
            if (!$existing) {
                wp_safe_redirect($this->add_notice_to_url($return_url, 'not_found'));
                exit;
            }

            $data['price_history'] = $this->build_updated_price_history($existing, (float) $data['unit_price']);
            $updated = $this->repository->update_item($item_id, $data);

            wp_safe_redirect($this->add_notice_to_url($return_url, $updated ? 'updated' : 'save_error'));
            exit;
        }

        $data['price_history'] = $this->new_price_history((float) $data['unit_price']);
        $inserted_id = $this->repository->insert_item($data);

        wp_safe_redirect($this->add_notice_to_url($return_url, $inserted_id > 0 ? 'created' : 'save_error'));
        exit;
    }

    /**
     * Toggle active/inactive status for one item.
     */
    private function handle_toggle_active()
    {
        $item_id = isset($_GET['item_id']) ? (int) $_GET['item_id'] : 0;
        if ($item_id <= 0) {
            return;
        }

        $catalog_type = $this->sanitize_catalog_type(
            isset($_GET['catalog_type']) ? wp_unslash((string) $_GET['catalog_type']) : 'standard'
        );

        check_admin_referer('tah_toggle_catalog_item_' . $item_id);

        $item = $this->repository->get_item_by_id($item_id);
        if (!$item) {
            wp_safe_redirect($this->add_notice_to_url($this->build_page_url(['catalog_type' => $catalog_type]), 'not_found'));
            exit;
        }

        $next_status = !empty($item['is_active']) ? 0 : 1;
        $updated = $this->repository->update_item($item_id, ['is_active' => $next_status]);

        $redirect_url = $this->build_page_url([
            'catalog_type' => $catalog_type,
            'status' => isset($_GET['status']) ? sanitize_key((string) $_GET['status']) : null,
            'trade_id' => isset($_GET['trade_id']) ? (int) $_GET['trade_id'] : null,
            's' => isset($_GET['s']) ? sanitize_text_field(wp_unslash((string) $_GET['s'])) : null,
        ]);

        wp_safe_redirect($this->add_notice_to_url($redirect_url, $updated ? 'status_changed' : 'save_error'));
        exit;
    }

    /**
     * @param array<string, mixed> $source
     * @return array<string, mixed>|null
     */
    private function sanitize_item_form_data(array $source, string $catalog_type)
    {
        $sku = isset($source['sku']) ? sanitize_text_field(wp_unslash((string) $source['sku'])) : '';
        $title = isset($source['title']) ? sanitize_text_field(wp_unslash((string) $source['title'])) : '';
        $description = isset($source['description']) ? wp_kses_post(wp_unslash((string) $source['description'])) : '';
        $unit_type = isset($source['unit_type']) ? sanitize_text_field(wp_unslash((string) $source['unit_type'])) : '';
        $category = isset($source['category']) ? sanitize_text_field(wp_unslash((string) $source['category'])) : '';
        $sort_order = isset($source['sort_order']) ? (int) $source['sort_order'] : 0;
        $unit_price_raw = isset($source['unit_price']) ? wp_unslash((string) $source['unit_price']) : '0';
        $trade_id = isset($source['trade_id']) ? (int) $source['trade_id'] : 0;
        $is_active = isset($source['is_active']) ? (int) $source['is_active'] === 1 : true;

        if ($sku === '' || $title === '' || $unit_type === '' || !is_numeric($unit_price_raw)) {
            return null;
        }

        return [
            'sku' => $sku,
            'title' => $title,
            'description' => $description,
            'unit_type' => $unit_type,
            'unit_price' => round((float) $unit_price_raw, 2),
            'trade_id' => $trade_id > 0 ? $trade_id : null,
            'category' => $category === '' ? null : $category,
            'sort_order' => max(0, $sort_order),
            'is_active' => $is_active,
            'catalog_type' => $catalog_type,
        ];
    }

    /**
     * @param array<string, mixed> $existing
     * @return array<int, array<string, mixed>>
     */
    private function build_updated_price_history(array $existing, float $new_price)
    {
        $history = isset($existing['price_history']) && is_array($existing['price_history'])
            ? array_values($existing['price_history'])
            : [];
        $current_price = isset($existing['unit_price']) ? round((float) $existing['unit_price'], 2) : 0.0;

        if (round($new_price, 2) !== $current_price) {
            array_unshift($history, [
                'price' => round($new_price, 2),
                'date' => current_time('Y-m-d'),
            ]);
        }

        return $history;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function new_price_history(float $price)
    {
        return [[
            'price' => round($price, 2),
            'date' => current_time('Y-m-d'),
        ]];
    }

    /**
     * @return array<int, WP_Term>
     */
    private function get_trade_terms_for_catalog(string $catalog_type)
    {
        $terms = get_terms([
            'taxonomy' => 'trade',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);

        if (is_wp_error($terms) || !is_array($terms)) {
            return [];
        }

        $allowed = [];
        foreach ($terms as $term) {
            if (!$term instanceof WP_Term) {
                continue;
            }

            $context = (string) get_term_meta((int) $term->term_id, '_tah_trade_context', true);
            $context = $context !== '' ? strtolower($context) : '';

            if ($context === '' || $context === 'all' || $context === 'both' || $context === $catalog_type) {
                $allowed[] = $term;
            }
        }

        return $allowed;
    }

    /**
     * Render notices from redirect query args.
     */
    private function render_notice_from_query()
    {
        $notice = isset($_GET['tah_notice']) ? sanitize_key((string) $_GET['tah_notice']) : '';
        if ($notice === '') {
            return;
        }

        $messages = [
            'created' => __('Catalog item created.', 'the-artist'),
            'updated' => __('Catalog item updated.', 'the-artist'),
            'status_changed' => __('Catalog item status updated.', 'the-artist'),
            'validation_error' => __('Could not save item. Check required fields and numeric values.', 'the-artist'),
            'not_found' => __('Catalog item not found.', 'the-artist'),
            'save_error' => __('Could not save item. SKU may already exist.', 'the-artist'),
        ];

        if (!isset($messages[$notice])) {
            return;
        }

        $is_success = in_array($notice, ['created', 'updated', 'status_changed'], true);
        $notice_class = $is_success ? 'notice notice-success' : 'notice notice-error';
        echo '<div class="' . esc_attr($notice_class) . ' is-dismissible"><p>';
        echo esc_html($messages[$notice]);
        echo '</p></div>';
    }

    /**
     * Render standard/insurance tabs.
     */
    private function render_catalog_tabs(string $catalog_type)
    {
        $tabs = [
            'standard' => __('Standard Catalog', 'the-artist'),
            'insurance' => __('Insurance Catalog', 'the-artist'),
        ];

        echo '<h2 class="nav-tab-wrapper">';
        foreach ($tabs as $key => $label) {
            $tab_url = $this->build_page_url(['catalog_type' => $key, 'status' => 'active']);
            $class = $catalog_type === $key ? 'nav-tab nav-tab-active' : 'nav-tab';
            echo '<a href="' . esc_url($tab_url) . '" class="' . esc_attr($class) . '">' . esc_html($label) . '</a>';
        }
        echo '</h2>';
    }

    /**
     * @param array<int, WP_Term> $trade_terms
     */
    private function render_filters_form(string $catalog_type, string $status_filter, int $trade_filter_id, string $search, array $trade_terms)
    {
        echo '<form method="get" action="">';
        echo '<input type="hidden" name="post_type" value="quotes">';
        echo '<input type="hidden" name="page" value="' . esc_attr(self::PAGE_SLUG) . '">';
        echo '<input type="hidden" name="catalog_type" value="' . esc_attr($catalog_type) . '">';

        echo '<p class="search-box">';
        echo '<label class="screen-reader-text" for="tah-catalog-search">' . esc_html__('Search catalog items', 'the-artist') . '</label>';
        echo '<input type="search" id="tah-catalog-search" name="s" value="' . esc_attr($search) . '" placeholder="' . esc_attr__('Search by title...', 'the-artist') . '">';
        submit_button(__('Search', 'the-artist'), '', '', false, ['id' => 'search-submit']);
        echo '</p>';

        echo '<p>';
        echo '<label for="tah-status-filter"><strong>' . esc_html__('Status:', 'the-artist') . '</strong></label> ';
        echo '<select id="tah-status-filter" name="status">';
        echo '<option value="active" ' . selected($status_filter, 'active', false) . '>' . esc_html__('Active', 'the-artist') . '</option>';
        echo '<option value="inactive" ' . selected($status_filter, 'inactive', false) . '>' . esc_html__('Inactive', 'the-artist') . '</option>';
        echo '<option value="all" ' . selected($status_filter, 'all', false) . '>' . esc_html__('All', 'the-artist') . '</option>';
        echo '</select> ';

        echo '<label for="tah-trade-filter"><strong>' . esc_html__('Trade:', 'the-artist') . '</strong></label> ';
        echo '<select id="tah-trade-filter" name="trade_id">';
        echo '<option value="0">' . esc_html__('All Trades', 'the-artist') . '</option>';
        foreach ($trade_terms as $trade_term) {
            echo '<option value="' . esc_attr((string) $trade_term->term_id) . '" ' . selected($trade_filter_id, (int) $trade_term->term_id, false) . '>';
            echo esc_html($trade_term->name);
            echo '</option>';
        }
        echo '</select> ';

        submit_button(__('Apply Filters', 'the-artist'), 'secondary', 'filter_action', false);
        echo '</p>';
        echo '</form>';
    }

    /**
     * @param array<string, mixed>|null $edit_item
     * @param array<int, WP_Term> $trade_terms
     */
    private function render_item_form(string $catalog_type, $edit_item, array $trade_terms, string $base_url, $impact_preview_count = null)
    {
        $is_edit_mode = is_array($edit_item);
        $item_id = $is_edit_mode ? (int) $edit_item['id'] : 0;
        $heading = $is_edit_mode ? __('Edit Catalog Item', 'the-artist') : __('Add Catalog Item', 'the-artist');

        $defaults = [
            'sku' => '',
            'title' => '',
            'description' => '',
            'unit_type' => 'flat',
            'unit_price' => '0.00',
            'trade_id' => 0,
            'category' => '',
            'sort_order' => 0,
            'is_active' => 1,
        ];
        $item = $is_edit_mode ? array_merge($defaults, $edit_item) : $defaults;

        echo '<h2>' . esc_html($heading) . '</h2>';
        echo '<form method="post" action="">';
        wp_nonce_field(self::NONCE_SAVE_ACTION, self::NONCE_SAVE_NAME);
        echo '<input type="hidden" name="tah_catalog_action" value="save_item">';
        echo '<input type="hidden" name="item_id" value="' . esc_attr((string) $item_id) . '">';
        echo '<input type="hidden" name="catalog_type" value="' . esc_attr($catalog_type) . '">';
        echo '<input type="hidden" name="return_status" value="' . esc_attr(isset($_GET['status']) ? sanitize_key((string) $_GET['status']) : 'active') . '">';
        echo '<input type="hidden" name="return_trade_id" value="' . esc_attr(isset($_GET['trade_id']) ? (string) ((int) $_GET['trade_id']) : '0') . '">';
        echo '<input type="hidden" name="return_search" value="' . esc_attr(isset($_GET['s']) ? sanitize_text_field((string) $_GET['s']) : '') . '">';

        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr><th><label for="tah-sku">' . esc_html__('SKU', 'the-artist') . '</label></th><td><input required type="text" id="tah-sku" name="sku" value="' . esc_attr((string) $item['sku']) . '" class="regular-text"></td></tr>';
        echo '<tr><th><label for="tah-title">' . esc_html__('Title', 'the-artist') . '</label></th><td><input required type="text" id="tah-title" name="title" value="' . esc_attr((string) $item['title']) . '" class="regular-text"></td></tr>';
        echo '<tr><th><label for="tah-description">' . esc_html__('Description', 'the-artist') . '</label></th><td><textarea id="tah-description" name="description" rows="3" class="large-text">' . esc_textarea((string) $item['description']) . '</textarea></td></tr>';
        echo '<tr><th><label for="tah-unit-type">' . esc_html__('Unit Type', 'the-artist') . '</label></th><td><input required type="text" id="tah-unit-type" name="unit_type" value="' . esc_attr((string) $item['unit_type']) . '" class="regular-text" placeholder="sqft"></td></tr>';
        echo '<tr><th><label for="tah-unit-price">' . esc_html__('Unit Price', 'the-artist') . '</label></th><td><input required step="0.01" type="number" id="tah-unit-price" name="unit_price" value="' . esc_attr((string) $item['unit_price']) . '" class="small-text">';
        if ($is_edit_mode && $impact_preview_count !== null) {
            $message = sprintf(
                _n(
                    'Changing this price will affect %d active quote.',
                    'Changing this price will affect %d active quotes.',
                    (int) $impact_preview_count,
                    'the-artist'
                ),
                (int) $impact_preview_count
            );
            echo '<p class="description">' . esc_html($message) . '</p>';
        }
        echo '</td></tr>';

        echo '<tr><th><label for="tah-trade-id">' . esc_html__('Trade', 'the-artist') . '</label></th><td>';
        echo '<select id="tah-trade-id" name="trade_id">';
        echo '<option value="0">' . esc_html__('Universal (All Trades)', 'the-artist') . '</option>';
        foreach ($trade_terms as $trade_term) {
            echo '<option value="' . esc_attr((string) $trade_term->term_id) . '" ' . selected((int) $item['trade_id'], (int) $trade_term->term_id, false) . '>';
            echo esc_html($trade_term->name);
            echo '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__('Trade list is filtered by _tah_trade_context when set; terms with no context remain visible.', 'the-artist') . '</p>';
        echo '</td></tr>';

        echo '<tr><th><label for="tah-category">' . esc_html__('Category', 'the-artist') . '</label></th><td><input type="text" id="tah-category" name="category" value="' . esc_attr((string) $item['category']) . '" class="regular-text"></td></tr>';
        echo '<tr><th><label for="tah-sort-order">' . esc_html__('Sort Order', 'the-artist') . '</label></th><td><input type="number" id="tah-sort-order" name="sort_order" value="' . esc_attr((string) $item['sort_order']) . '" class="small-text"></td></tr>';
        echo '<tr><th><label for="tah-is-active">' . esc_html__('Active', 'the-artist') . '</label></th><td><select id="tah-is-active" name="is_active"><option value="1" ' . selected((int) $item['is_active'], 1, false) . '>' . esc_html__('Yes', 'the-artist') . '</option><option value="0" ' . selected((int) $item['is_active'], 0, false) . '>' . esc_html__('No', 'the-artist') . '</option></select></td></tr>';
        echo '</tbody></table>';

        submit_button($is_edit_mode ? __('Update Item', 'the-artist') : __('Add Item', 'the-artist'));
        if ($is_edit_mode) {
            echo '<a class="button button-secondary" href="' . esc_url($base_url) . '">' . esc_html__('Cancel Edit', 'the-artist') . '</a>';
        }
        echo '</form>';
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function render_items_table(array $items, string $catalog_type, string $base_url)
    {
        echo '<h2>' . esc_html__('Catalog Items', 'the-artist') . '</h2>';

        if (empty($items)) {
            echo '<p>' . esc_html__('No catalog items found for current filters.', 'the-artist') . '</p>';
            return;
        }

        $column_labels = $this->get_catalog_table_column_labels();
        $column_keys = array_keys(self::COLUMN_CONTRACT);

        echo '<table class="widefat striped tah-pricing-catalog-table tah-resizable-table" data-tah-table="' . esc_attr(self::TABLE_KEY) . '" data-tah-variant="' . esc_attr($catalog_type) . '"><thead><tr>';
        foreach ($column_keys as $column_key) {
            if (!isset($column_labels[$column_key])) {
                continue;
            }

            $locked_attr = ($column_key === 'actions') ? ' data-tah-locked="1"' : '';
            echo '<th data-tah-col="' . esc_attr($column_key) . '"' . $locked_attr . '>' . esc_html($column_labels[$column_key]) . '</th>';
        }
        echo '</tr></thead><tbody>';

        foreach ($items as $item) {
            $item_id = isset($item['id']) ? (int) $item['id'] : 0;
            $trade_name = __('Universal', 'the-artist');
            if (!empty($item['trade_id'])) {
                $trade_term = get_term((int) $item['trade_id'], 'trade');
                if ($trade_term instanceof WP_Term) {
                    $trade_name = $trade_term->name;
                }
            }

            $is_active = !empty($item['is_active']);
            $edit_url = add_query_arg(
                ['edit_item' => $item_id],
                $base_url
            );
            $toggle_url = wp_nonce_url(
                add_query_arg(
                    [
                        'tah_action' => 'toggle_active',
                        'item_id' => $item_id,
                        'catalog_type' => $catalog_type,
                    ],
                    $base_url
                ),
                'tah_toggle_catalog_item_' . $item_id
            );

            echo '<tr>';
            $row_cells = $this->build_catalog_row_cells($item, $trade_name, $is_active, $edit_url, $toggle_url);
            foreach ($column_keys as $column_key) {
                if (!isset($row_cells[$column_key])) {
                    continue;
                }

                echo $row_cells[$column_key];
            }
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    /**
     * @return array<string, string>
     */
    private function get_catalog_table_column_labels(): array
    {
        return [
            'title' => __('Title', 'the-artist'),
            'sku' => __('SKU', 'the-artist'),
            'trade' => __('Trade', 'the-artist'),
            'unit' => __('Unit', 'the-artist'),
            'price' => __('Price', 'the-artist'),
            'status' => __('Status', 'the-artist'),
            'updated' => __('Updated', 'the-artist'),
            'history' => __('Price History', 'the-artist'),
            'actions' => __('Actions', 'the-artist'),
        ];
    }

    /**
     * @param array<string, mixed> $item
     */
    private function build_catalog_row_cells(
        array $item,
        string $trade_name,
        bool $is_active,
        string $edit_url,
        string $toggle_url
    ): array {
        return [
            'title' => '<td data-tah-col="title"><strong>' . esc_html((string) $item['title']) . '</strong><br><span class="description">' . esc_html((string) $item['description']) . '</span></td>',
            'sku' => '<td data-tah-col="sku">' . esc_html((string) $item['sku']) . '</td>',
            'trade' => '<td data-tah-col="trade">' . esc_html($trade_name) . '</td>',
            'unit' => '<td data-tah-col="unit">' . esc_html((string) $item['unit_type']) . '</td>',
            'price' => '<td data-tah-col="price">$' . esc_html(number_format((float) $item['unit_price'], 2)) . '</td>',
            'status' => '<td data-tah-col="status">' . ($is_active ? esc_html__('Active', 'the-artist') : esc_html__('Inactive', 'the-artist')) . '</td>',
            'updated' => '<td data-tah-col="updated">' . esc_html((string) $item['updated_at']) . '</td>',
            'history' => '<td data-tah-col="history">' . $this->render_price_history_html(isset($item['price_history']) && is_array($item['price_history']) ? $item['price_history'] : []) . '</td>',
            'actions' => '<td data-tah-col="actions"><a href="' . esc_url($edit_url) . '">' . esc_html__('Edit', 'the-artist') . '</a> | <a href="' . esc_url($toggle_url) . '">' . ($is_active ? esc_html__('Deactivate', 'the-artist') : esc_html__('Activate', 'the-artist')) . '</a></td>',
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $history
     */
    private function render_price_history_html(array $history)
    {
        if (empty($history)) {
            return esc_html__('No history', 'the-artist');
        }

        $rows = [];
        foreach ($history as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $price = isset($entry['price']) ? (float) $entry['price'] : 0.0;
            $date = isset($entry['date']) ? (string) $entry['date'] : '';
            $rows[] = '$' . number_format($price, 2) . ' (' . $date . ')';
        }

        if (empty($rows)) {
            return esc_html__('No history', 'the-artist');
        }

        return esc_html(implode(' | ', $rows));
    }

    /**
     * @param array<string, mixed> $args
     */
    private function build_page_url(array $args = [])
    {
        $query_args = [
            'post_type' => 'quotes',
            'page' => self::PAGE_SLUG,
        ];

        foreach ($args as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $query_args[$key] = $value;
        }

        return add_query_arg($query_args, admin_url('edit.php'));
    }

    private function add_notice_to_url(string $url, string $notice)
    {
        return add_query_arg(['tah_notice' => $notice], $url);
    }

    private function sanitize_catalog_type(string $catalog_type)
    {
        return in_array($catalog_type, ['standard', 'insurance'], true) ? $catalog_type : 'standard';
    }

    private function sanitize_status_filter(string $status)
    {
        return in_array($status, ['active', 'inactive', 'all'], true) ? $status : 'active';
    }
}

$GLOBALS['tah_pricing_catalog_admin'] = new TAH_Pricing_Catalog_Admin();
