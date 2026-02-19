<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Quotes module bootstrap.
 *
 * Owns Quote CPT registration and quote-core admin behavior
 * that is not specific to pricing or info-sections modules.
 */
final class TAH_Quotes_Module
{
    /**
     * Prevent duplicate bootstrap.
     *
     * @var bool
     */
    private static $booted = false;

    /**
     * Boot module hooks.
     */
    public static function boot(): void
    {
        if (self::$booted || !self::is_enabled()) {
            return;
        }

        self::$booted = true;
        new TAH_Quotes_Post_Type();
    }

    /**
     * Runtime module capability flag.
     *
     * Default is ON; external code can disable via:
     * add_filter('tah_module_quotes_enabled', '__return_false');
     */
    public static function is_enabled(): bool
    {
        return (bool) apply_filters('tah_module_quotes_enabled', true);
    }
}

/**
 * Quote CPT + taxonomy registration and quote-core admin hooks.
 */
final class TAH_Quotes_Post_Type
{
    private const POST_TYPE = 'quotes';
    private const TAXONOMY = 'trade';
    private const NONCE_ACTION = 'the_artist_save_quote';
    private const NONCE_NAME = '_tfanonce';
    private const CUSTOMER_METABOX_ID = 'tfa_metabox';
    private const CUSTOMER_NAME_META_KEY = 'customer_name';
    private const CUSTOMER_ADDRESS_META_KEY = 'customer_address';
    private const ESTIMATE_TYPE_META_KEY = 'estimate_type';

    public function __construct()
    {
        add_action('init', [$this, 'register_quote_post_type']);
        add_action('init', [$this, 'register_trade_taxonomy']);

        if (!is_admin()) {
            return;
        }

        add_action('add_meta_boxes_' . self::POST_TYPE, [$this, 'register_quote_customer_metabox']);
        add_action('save_post_' . self::POST_TYPE, [$this, 'save_quote_meta'], 20, 3);
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [$this, 'filter_quote_columns']);
        add_filter('manage_edit-' . self::POST_TYPE . '_sortable_columns', [$this, 'filter_sortable_columns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [$this, 'render_quote_column'], 10, 2);
    }

    public function register_quote_post_type(): void
    {
        $labels = [
            'name' => _x('Quotes', 'quotes', 'the-artist'),
            'singular_name' => _x('Quote', 'quotes', 'the-artist'),
            'add_new' => _x('Add New', 'quotes', 'the-artist'),
            'add_new_item' => _x('Add New Quote', 'quotes', 'the-artist'),
            'edit_item' => _x('Edit Quote', 'quotes', 'the-artist'),
            'new_item' => _x('New Quote', 'quotes', 'the-artist'),
            'view_item' => _x('View Quote', 'quotes', 'the-artist'),
            'search_items' => _x('Search quotes', 'quotes', 'the-artist'),
            'not_found' => _x('No quotes found', 'quotes', 'the-artist'),
            'not_found_in_trash' => _x('No quotes found in Trash', 'quotes', 'the-artist'),
            'parent_item_colon' => _x('Parent Quote:', 'quotes', 'the-artist'),
            'menu_name' => _x('Quotes', 'quotes', 'the-artist'),
        ];

        $args = [
            'labels' => $labels,
            'hierarchical' => false,
            'description' => 'Customer Proposals.',
            'supports' => ['editor', 'custom-fields', 'revisions'],
            'taxonomies' => [self::TAXONOMY],
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'menu_position' => 5,
            'menu_icon' => 'dashicons-portfolio',
            'show_in_nav_menus' => false,
            'publicly_queryable' => true,
            'exclude_from_search' => false,
            'has_archive' => false,
            'query_var' => true,
            'can_export' => true,
            'rewrite' => true,
            'capability_type' => 'post',
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    public function register_trade_taxonomy(): void
    {
        $labels = [
            'name' => _x('Trades', 'taxonomy general name', 'the-artist'),
            'singular_name' => _x('Trade', 'taxonomy singular name', 'the-artist'),
            'search_items' => __('Search Trades', 'the-artist'),
            'popular_items' => __('Popular Trades', 'the-artist'),
            'all_items' => __('All Trades', 'the-artist'),
            'parent_item' => null,
            'parent_item_colon' => null,
            'edit_item' => __('Edit Trade', 'the-artist'),
            'update_item' => __('Update Trade', 'the-artist'),
            'add_new_item' => __('Add New Trade', 'the-artist'),
            'new_item_name' => __('New Trade Name', 'the-artist'),
            'separate_items_with_commas' => __('Separate trades with commas', 'the-artist'),
            'add_or_remove_items' => __('Add or remove trades', 'the-artist'),
            'choose_from_most_used' => __('Choose from the most used trades', 'the-artist'),
            'not_found' => __('No trades found.', 'the-artist'),
            'menu_name' => __('Trades', 'the-artist'),
        ];

        $args = [
            'labels' => $labels,
            'hierarchical' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => ['slug' => 'trade'],
        ];

        register_taxonomy(self::TAXONOMY, [self::POST_TYPE], $args);
    }

    public function register_quote_customer_metabox(): void
    {
        add_meta_box(
            self::CUSTOMER_METABOX_ID,
            __('Customer Information', 'the-artist'),
            [$this, 'render_quote_customer_metabox'],
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    /**
     * @param WP_Post $post
     */
    public function render_quote_customer_metabox($post): void
    {
        if (!$post instanceof WP_Post || $post->post_type !== self::POST_TYPE) {
            return;
        }

        $customer_name = (string) get_post_meta((int) $post->ID, self::CUSTOMER_NAME_META_KEY, true);
        $customer_address = (string) get_post_meta((int) $post->ID, self::CUSTOMER_ADDRESS_META_KEY, true);

        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);

        echo '<table class="form-table">';
        echo '<tbody>';
        echo '<tr>';
        echo '<th><label for="' . esc_attr(self::CUSTOMER_NAME_META_KEY) . '">' . esc_html__('Customer Name', 'the-artist') . '</label></th>';
        echo '<td><input type="text" id="' . esc_attr(self::CUSTOMER_NAME_META_KEY) . '" name="' . esc_attr(self::CUSTOMER_NAME_META_KEY) . '" value="' . esc_attr($customer_name) . '" class="regular-text"></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th><label for="' . esc_attr(self::CUSTOMER_ADDRESS_META_KEY) . '">' . esc_html__('Customer Address', 'the-artist') . '</label></th>';
        echo '<td><input type="text" id="' . esc_attr(self::CUSTOMER_ADDRESS_META_KEY) . '" name="' . esc_attr(self::CUSTOMER_ADDRESS_META_KEY) . '" value="' . esc_attr($customer_address) . '" class="regular-text"></td>';
        echo '</tr>';
        echo '</tbody>';
        echo '</table>';
    }

    /**
     * @param int     $post_id
     * @param WP_Post $post
     * @param bool    $update
     */
    public function save_quote_meta($post_id, $post, $update): void
    {
        if (!$post instanceof WP_Post || $post->post_type !== self::POST_TYPE) {
            return;
        }

        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        if (!current_user_can('edit_post', (int) $post_id)) {
            return;
        }

        $nonce = isset($_POST[self::NONCE_NAME]) ? sanitize_text_field(wp_unslash((string) $_POST[self::NONCE_NAME])) : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            return;
        }

        $this->persist_text_meta((int) $post_id, self::CUSTOMER_NAME_META_KEY);
        $this->persist_text_meta((int) $post_id, self::CUSTOMER_ADDRESS_META_KEY);
        $this->persist_text_meta((int) $post_id, self::ESTIMATE_TYPE_META_KEY);
    }

    /**
     * @param array<string, string> $columns
     * @return array<string, string>
     */
    public function filter_quote_columns($columns): array
    {
        $columns = is_array($columns) ? $columns : [];
        $date_label = $columns['date'] ?? null;
        unset($columns['date']);

        if (isset($columns['title'])) {
            $columns['title'] = __('Quote Title', 'the-artist');
        }

        $columns['customer_address'] = __('Address', 'the-artist');
        $columns['customer_name'] = __('Client Name', 'the-artist');

        if ($date_label !== null) {
            $columns['date'] = $date_label;
        }

        return $columns;
    }

    /**
     * @param array<string, string> $columns
     * @return array<string, string>
     */
    public function filter_sortable_columns($columns): array
    {
        $columns = is_array($columns) ? $columns : [];
        $columns['customer_address'] = 'customer_address';
        $columns['customer_name'] = 'customer_name';

        return $columns;
    }

    /**
     * @param string $column_key
     * @param int    $post_id
     */
    public function render_quote_column($column_key, $post_id): void
    {
        if ($column_key === 'customer_address') {
            $value = (string) get_post_meta((int) $post_id, self::CUSTOMER_ADDRESS_META_KEY, true);
            echo $value !== '' ? '<span>' . esc_html($value) . '</span>' : esc_html__('Not Provided', 'the-artist');
            return;
        }

        if ($column_key === 'customer_name') {
            $value = (string) get_post_meta((int) $post_id, self::CUSTOMER_NAME_META_KEY, true);
            echo $value !== '' ? '<span>' . esc_html($value) . '</span>' : esc_html__('Not Provided', 'the-artist');
        }
    }

    private function persist_text_meta(int $post_id, string $meta_key): void
    {
        if (!isset($_POST[$meta_key])) {
            delete_post_meta($post_id, $meta_key);
            return;
        }

        $value = sanitize_text_field(wp_unslash((string) $_POST[$meta_key]));
        update_post_meta($post_id, $meta_key, $value);
    }
}
