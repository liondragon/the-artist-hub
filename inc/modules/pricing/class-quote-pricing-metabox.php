<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Pricing table metabox for quote edit screen.
 */
final class TAH_Quote_Pricing_Metabox
{
    private const POST_TYPE = 'quotes';
    private const METABOX_ID = 'tah_quote_pricing';
    private const NONCE_ACTION = 'tah_quote_pricing_save';
    private const NONCE_NAME = '_tah_quote_pricing_nonce';
    private const FIELD_GROUPS_JSON = 'tah_quote_pricing_groups_json';
    private const FIELD_ITEMS_JSON = 'tah_quote_pricing_items_json';
    private const FIELD_QUOTE_FORMAT = 'tah_quote_format';
    private const FIELD_QUOTE_TAX_RATE = 'tah_quote_tax_rate';
    private const META_QUOTE_FORMAT = '_tah_quote_format';
    private const META_QUOTE_TAX_RATE = '_tah_quote_tax_rate';

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

        add_action('add_meta_boxes_' . self::POST_TYPE, [$this, 'register_metabox'], 20);
        add_action('save_post_' . self::POST_TYPE, [$this, 'save_quote_pricing'], 30, 3);
        add_action('wp_ajax_tah_save_pricing', [$this, 'ajax_save_pricing']);
        add_action('wp_ajax_tah_search_pricing_items', [$this, 'ajax_search_pricing_items']);
        add_action('wp_ajax_tah_apply_trade_pricing_preset', [$this, 'ajax_apply_trade_pricing_preset']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /**
     * Register pricing metabox.
     */
    public function register_metabox(): void
    {
        add_meta_box(
            self::METABOX_ID,
            __('Pricing Table', 'the-artist'),
            [$this, 'render_metabox'],
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    /**
     * Render pricing table editor UI.
     *
     * @param WP_Post $post
     */
    public function render_metabox($post): void
    {
        if (!$post instanceof WP_Post || $post->post_type !== self::POST_TYPE) {
            return;
        }

        $quote_id = (int) $post->ID;
        $quote_format = $this->normalize_quote_format((string) get_post_meta($quote_id, self::META_QUOTE_FORMAT, true));
        $tax_rate_raw = get_post_meta($quote_id, self::META_QUOTE_TAX_RATE, true);
        $tax_rate = is_numeric($tax_rate_raw) ? $this->format_plain_number((float) $tax_rate_raw) : '';
        $groups = $this->repository->get_quote_groups($quote_id);
        $line_items = $this->repository->get_quote_line_items($quote_id);

        if (empty($groups)) {
            $default_group_name = $quote_format === 'insurance'
                ? __('Insurance Items', 'the-artist')
                : __('Base Quote', 'the-artist');
            $groups = [[
                'id' => 0,
                'name' => $default_group_name,
                'description' => '',
                'selection_mode' => 'all',
                'show_subtotal' => 1,
                'is_collapsed' => 0,
                'sort_order' => 0,
            ]];
        }

        $line_items_by_group = $this->map_line_items_by_group($line_items);
        $catalog_prices = $this->load_catalog_price_map($line_items);

        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);

        echo '<div id="tah-quote-pricing" class="tah-pricing-editor" data-quote-id="' . esc_attr((string) $quote_id) . '" data-quote-format="' . esc_attr($quote_format) . '">';
        echo '<div class="tah-pricing-format-controls">';
        echo '<label class="tah-pricing-format-field">';
        echo '<span class="tah-pricing-format-label">' . esc_html__('Quote Format', 'the-artist') . '</span>';
        echo '<select id="tah-quote-format" name="' . esc_attr(self::FIELD_QUOTE_FORMAT) . '" class="tah-form-control">';
        echo '<option value="standard" ' . selected($quote_format, 'standard', false) . '>' . esc_html__('Standard', 'the-artist') . '</option>';
        echo '<option value="insurance" ' . selected($quote_format, 'insurance', false) . '>' . esc_html__('Insurance', 'the-artist') . '</option>';
        echo '</select>';
        echo '</label>';
        echo '<label class="tah-pricing-format-field tah-insurance-tax-rate-field">';
        echo '<span class="tah-pricing-format-label">' . esc_html__('Sales Tax Rate', 'the-artist') . '</span>';
        echo '<input type="number" id="tah-quote-tax-rate" name="' . esc_attr(self::FIELD_QUOTE_TAX_RATE) . '" class="tah-form-control" step="0.0001" min="0" value="' . esc_attr($tax_rate) . '" placeholder="0.0000">';
        echo '</label>';
        echo '</div>';
        echo '<p class="description tah-pricing-insurance-hint">' . esc_html__('Insurance format uses a flat line-item list (single implicit group).', 'the-artist') . '</p>';
        echo '<input type="hidden" id="tah-pricing-groups-json" name="' . esc_attr(self::FIELD_GROUPS_JSON) . '" value="">';
        echo '<input type="hidden" id="tah-pricing-items-json" name="' . esc_attr(self::FIELD_ITEMS_JSON) . '" value="">';

        echo '<div id="tah-pricing-groups" class="tah-pricing-groups">';

        foreach ($groups as $index => $group) {
            $group_id = isset($group['id']) ? (int) $group['id'] : 0;
            $client_key = $group_id > 0 ? ('group-' . $group_id) : ('group-temp-' . $index);
            $this->render_group_card(
                $group,
                $line_items_by_group[$group_id] ?? [],
                $catalog_prices,
                $client_key
            );
        }

        echo '</div>';

        echo '<div class="tah-pricing-editor-footer">';
        echo '<button type="button" id="tah-add-group" class="button button-secondary">' . esc_html__('+ Add Group', 'the-artist') . '</button>';
        echo '<span id="tah-pricing-save-status" class="tah-pricing-save-status" aria-live="polite"></span>';
        echo '<div id="tah-pricing-subtotal-row" class="tah-pricing-summary-row">';
        echo '<span class="tah-pricing-summary-label">' . esc_html__('Subtotal', 'the-artist') . '</span>';
        echo '<strong id="tah-pricing-subtotal-value" class="tah-pricing-summary-value">$0.00</strong>';
        echo '</div>';
        echo '<div id="tah-pricing-tax-total-row" class="tah-pricing-summary-row">';
        echo '<span class="tah-pricing-summary-label">' . esc_html__('Tax Total', 'the-artist') . '</span>';
        echo '<strong id="tah-pricing-tax-total-value" class="tah-pricing-summary-value">$0.00</strong>';
        echo '</div>';
        echo '<div class="tah-pricing-grand-total-row">';
        echo '<span class="tah-pricing-grand-total-label">' . esc_html__('Grand Total', 'the-artist') . '</span>';
        echo '<strong id="tah-pricing-grand-total-value" class="tah-pricing-grand-total-value">$0.00</strong>';
        echo '</div>';
        echo '</div>';

        echo '<p class="description">' . esc_html__('Tip: Tab navigates cells. Press Enter in a row to insert a new line item below.', 'the-artist') . '</p>';
        echo '</div>';
    }

    /**
     * Save pricing groups and line items from hidden JSON fields.
     *
     * @param int     $post_id
     * @param WP_Post $post
     * @param bool    $update
     */
    public function save_quote_pricing($post_id, $post, $update): void
    {
        if (!$post instanceof WP_Post || $post->post_type !== self::POST_TYPE) {
            return;
        }

        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $nonce = isset($_POST[self::NONCE_NAME]) ? sanitize_text_field(wp_unslash((string) $_POST[self::NONCE_NAME])) : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            return;
        }

        $this->persist_quote_format_meta_from_request((int) $post_id, $_POST);

        if (!isset($_POST[self::FIELD_GROUPS_JSON], $_POST[self::FIELD_ITEMS_JSON])) {
            return;
        }

        $groups_raw = $this->decode_json_array(wp_unslash((string) $_POST[self::FIELD_GROUPS_JSON]));
        $items_raw = $this->decode_json_array(wp_unslash((string) $_POST[self::FIELD_ITEMS_JSON]));

        if ($groups_raw === null || $items_raw === null) {
            return;
        }

        $this->persist_pricing_payload((int) $post_id, $groups_raw, $items_raw);
    }

    /**
     * Save quote pricing payload from admin AJAX request.
     */
    public function ajax_save_pricing(): void
    {
        check_ajax_referer('tah_pricing_nonce', 'nonce');

        $quote_id = isset($_POST['quote_id']) ? (int) wp_unslash((string) $_POST['quote_id']) : 0;
        if ($quote_id <= 0) {
            wp_send_json_error(['message' => __('Missing quote ID.', 'the-artist')], 400);
        }

        $post = get_post($quote_id);
        if (!$post instanceof WP_Post || $post->post_type !== self::POST_TYPE) {
            wp_send_json_error(['message' => __('Invalid quote.', 'the-artist')], 400);
        }

        if (!current_user_can('edit_post', $quote_id)) {
            wp_send_json_error(['message' => __('Unauthorized.', 'the-artist')], 403);
        }

        $this->persist_quote_format_meta_from_request($quote_id, $_POST);

        $groups_raw = $this->decode_json_array(isset($_POST['groups_json']) ? wp_unslash((string) $_POST['groups_json']) : '');
        $items_raw = $this->decode_json_array(isset($_POST['items_json']) ? wp_unslash((string) $_POST['items_json']) : '');
        if ($groups_raw === null || $items_raw === null) {
            wp_send_json_error(['message' => __('Invalid pricing payload.', 'the-artist')], 400);
        }

        $result = $this->persist_pricing_payload($quote_id, $groups_raw, $items_raw);
        if (!$result['success']) {
            wp_send_json_error(['message' => __('Failed to save pricing draft.', 'the-artist')], 500);
        }

        wp_send_json_success([
            'saved_at' => current_time('mysql'),
            'resolved_prices' => $result['resolved_prices'],
        ]);
    }

    /**
     * Search active pricing catalog items for quote line-item auto-suggest.
     */
    public function ajax_search_pricing_items(): void
    {
        check_ajax_referer('tah_pricing_nonce', 'nonce');

        $quote_id = isset($_POST['quote_id']) ? (int) wp_unslash((string) $_POST['quote_id']) : 0;
        if ($quote_id <= 0) {
            wp_send_json_error(['message' => __('Missing quote ID.', 'the-artist')], 400);
        }

        $post = get_post($quote_id);
        if (!$post instanceof WP_Post || $post->post_type !== self::POST_TYPE) {
            wp_send_json_error(['message' => __('Invalid quote.', 'the-artist')], 400);
        }

        if (!current_user_can('edit_post', $quote_id)) {
            wp_send_json_error(['message' => __('Unauthorized.', 'the-artist')], 403);
        }

        $term = '';
        if (isset($_POST['term'])) {
            $term = sanitize_text_field(wp_unslash((string) $_POST['term']));
        } elseif (isset($_POST['search'])) {
            $term = sanitize_text_field(wp_unslash((string) $_POST['search']));
        }
        if ($term === '') {
            wp_send_json_success(['items' => []]);
        }

        $catalog_type = isset($_POST[self::FIELD_QUOTE_FORMAT])
            ? sanitize_key(wp_unslash((string) $_POST[self::FIELD_QUOTE_FORMAT]))
            : (string) get_post_meta($quote_id, self::META_QUOTE_FORMAT, true);
        $catalog_type = $this->normalize_quote_format($catalog_type);
        if ($catalog_type === '') {
            $catalog_type = 'standard';
        }

        $trade_id = $this->get_quote_trade_id($quote_id);
        $matches = $this->repository->search_catalog_items_for_quote($term, $catalog_type, $trade_id, 20);

        $items = [];
        foreach ($matches as $match) {
            if (!is_array($match)) {
                continue;
            }

            $title = isset($match['title']) ? trim((string) $match['title']) : '';
            $sku = isset($match['sku']) ? trim((string) $match['sku']) : '';
            $label = $title !== '' ? $title : $sku;
            if ($label === '') {
                $label = __('Untitled item', 'the-artist');
            }

            $unit_price = isset($match['unit_price']) && is_numeric($match['unit_price'])
                ? round((float) $match['unit_price'], 2)
                : 0.0;

            $items[] = [
                'id' => isset($match['id']) ? (int) $match['id'] : 0,
                'sku' => $sku,
                'title' => $title,
                'label' => $label,
                'value' => $label,
                'description' => isset($match['description']) ? (string) $match['description'] : '',
                'unit_type' => isset($match['unit_type']) ? (string) $match['unit_type'] : 'flat',
                'unit_price' => $unit_price,
                'trade_id' => isset($match['trade_id']) && $match['trade_id'] !== null ? (int) $match['trade_id'] : null,
            ];
        }

        wp_send_json_success(['items' => $items]);
    }

    /**
     * Apply pricing preset for selected trade to a quote with no persisted pricing rows.
     */
    public function ajax_apply_trade_pricing_preset(): void
    {
        check_ajax_referer('tah_pricing_nonce', 'nonce');

        $quote_id = isset($_POST['quote_id']) ? (int) wp_unslash((string) $_POST['quote_id']) : 0;
        $trade_id = isset($_POST['trade_id']) ? (int) wp_unslash((string) $_POST['trade_id']) : 0;

        if ($quote_id <= 0 || $trade_id <= 0) {
            wp_send_json_error(['message' => __('Missing quote or trade.', 'the-artist')], 400);
        }

        $post = get_post($quote_id);
        if (!$post instanceof WP_Post || $post->post_type !== self::POST_TYPE) {
            wp_send_json_error(['message' => __('Invalid quote.', 'the-artist')], 400);
        }

        if (!current_user_can('edit_post', $quote_id)) {
            wp_send_json_error(['message' => __('Unauthorized.', 'the-artist')], 403);
        }

        $quote_format = isset($_POST[self::FIELD_QUOTE_FORMAT])
            ? sanitize_key(wp_unslash((string) $_POST[self::FIELD_QUOTE_FORMAT]))
            : (string) get_post_meta($quote_id, self::META_QUOTE_FORMAT, true);
        $quote_format = $this->normalize_quote_format($quote_format);
        if ($quote_format !== 'standard') {
            wp_send_json_success([
                'applied' => false,
                'reason' => 'unsupported_format',
                'message' => __('Pricing presets apply only to standard quotes.', 'the-artist'),
            ]);
        }

        if ($this->has_persisted_pricing_data($quote_id)) {
            wp_send_json_success([
                'applied' => false,
                'reason' => 'already_populated',
                'message' => __('Pricing is already populated for this quote. Preset was not applied.', 'the-artist'),
            ]);
        }

        $preset = $this->get_trade_pricing_preset($trade_id);
        if (empty($preset['groups'])) {
            wp_send_json_success([
                'applied' => false,
                'reason' => 'no_preset',
                'message' => __('No pricing preset is configured for this trade.', 'the-artist'),
            ]);
        }

        $rounding = (float) get_option('tah_price_rounding', 1);
        $rounding_direction = (string) get_option('tah_price_rounding_direction', TAH_Price_Formula::ROUNDING_NEAREST);
        $response_groups = [];
        $missing_skus = [];

        foreach ($preset['groups'] as $group) {
            if (!is_array($group)) {
                continue;
            }

            $response_items = [];
            $group_items = isset($group['items']) && is_array($group['items']) ? $group['items'] : [];

            foreach ($group_items as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $sku = isset($item['pricing_item_sku']) ? sanitize_text_field((string) $item['pricing_item_sku']) : '';
                if ($sku === '') {
                    continue;
                }

                $catalog_item = $this->repository->get_active_item_by_sku_and_catalog($sku, 'standard');
                if (!is_array($catalog_item)) {
                    $missing_skus[] = $sku;
                    continue;
                }

                $catalog_price = isset($catalog_item['unit_price']) ? (float) $catalog_item['unit_price'] : 0.0;
                $resolved_price = TAH_Price_Formula::resolve(
                    TAH_Price_Formula::MODE_DEFAULT,
                    0.0,
                    $catalog_price,
                    $rounding,
                    $rounding_direction
                );

                $quantity = isset($item['quantity']) && is_numeric($item['quantity'])
                    ? round((float) $item['quantity'], 2)
                    : 1.0;
                if ($quantity <= 0) {
                    $quantity = 1.0;
                }

                $response_items[] = [
                    'pricing_item_id' => isset($catalog_item['id']) ? (int) $catalog_item['id'] : 0,
                    'title' => isset($catalog_item['title']) ? (string) $catalog_item['title'] : '',
                    'description' => isset($catalog_item['description']) ? (string) $catalog_item['description'] : '',
                    'unit_type' => isset($catalog_item['unit_type']) ? (string) $catalog_item['unit_type'] : 'flat',
                    'quantity' => $quantity,
                    'rate_formula' => '$',
                    'catalog_price' => round($catalog_price, 2),
                    'resolved_price' => round($resolved_price, 2),
                    'pricing_item_sku' => $sku,
                ];
            }

            $response_groups[] = [
                'name' => isset($group['name']) ? sanitize_text_field((string) $group['name']) : '',
                'description' => isset($group['description']) ? sanitize_textarea_field((string) $group['description']) : '',
                'selection_mode' => $this->normalize_selection_mode(isset($group['selection_mode']) ? (string) $group['selection_mode'] : 'all'),
                'show_subtotal' => !empty($group['show_subtotal']),
                'is_collapsed' => !empty($group['is_collapsed']),
                'items' => $response_items,
            ];
        }

        $missing_skus = array_values(array_unique($missing_skus));
        $missing_count = count($missing_skus);

        wp_send_json_success([
            'applied' => true,
            'reason' => 'applied',
            'groups' => $response_groups,
            'missing_count' => $missing_count,
            'missing_skus' => $missing_skus,
            'message' => $missing_count > 0
                ? sprintf(
                    /* translators: %d is number of preset items skipped. */
                    __('%d preset items were skipped because they no longer exist in the catalog.', 'the-artist'),
                    $missing_count
                )
                : __('Pricing preset applied.', 'the-artist'),
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $groups_raw
     * @param array<int, array<string, mixed>> $items_raw
     * @return array{success:bool,resolved_prices:array<int,float>}
     */
    private function persist_pricing_payload(int $post_id, array $groups_raw, array $items_raw): array
    {
        $quote_format = $this->normalize_quote_format((string) get_post_meta($post_id, self::META_QUOTE_FORMAT, true));
        $group_processor = new TAH_Pricing_Group_Payload_Processor();
        $groups_raw = $group_processor->normalize_groups_for_quote_format($quote_format, $groups_raw);
        $group_payload = $group_processor->build_group_rows_with_keys($quote_format, $groups_raw);
        $group_rows = $group_payload['group_rows'];
        $group_keys = $group_payload['group_keys'];

        $persisted_group_ids = $this->repository->save_quote_groups((int) $post_id, $group_rows);
        if (!empty($group_rows) && empty($persisted_group_ids)) {
            return [
                'success' => false,
                'resolved_prices' => [],
            ];
        }

        $group_key_map = $group_processor->build_group_key_map($group_keys, $persisted_group_ids);

        $rounding = (float) get_option('tah_price_rounding', 1);
        $rounding_direction = (string) get_option('tah_price_rounding_direction', TAH_Price_Formula::ROUNDING_NEAREST);
        $line_item_processor = new TAH_Pricing_Line_Item_Payload_Processor($this->repository);
        $line_item_rows = $line_item_processor->build_line_item_rows(
            $items_raw,
            $group_key_map,
            $quote_format,
            $rounding,
            $rounding_direction
        );

        $persisted_line_item_ids = $this->repository->save_quote_line_items((int) $post_id, $line_item_rows);
        if (!empty($line_item_rows) && empty($persisted_line_item_ids)) {
            return [
                'success' => false,
                'resolved_prices' => [],
            ];
        }

        update_post_meta($post_id, '_tah_prices_resolved_at', current_time('mysql'));

        $resolved_prices = [];
        foreach ($line_item_rows as $line_item_row) {
            $resolved_prices[] = isset($line_item_row['resolved_price'])
                ? (float) $line_item_row['resolved_price']
                : 0.0;
        }

        return [
            'success' => true,
            'resolved_prices' => $resolved_prices,
        ];
    }

    /**
     * Enqueue pricing editor JS on quote edit screen only.
     */
    public function enqueue_assets($hook_suffix): void
    {
        if (!$this->is_quote_edit_screen((string) $hook_suffix)) {
            return;
        }

        $file_path = get_template_directory() . '/assets/js/quote-pricing.js';
        $version = file_exists($file_path) ? (string) filemtime($file_path) : '1.0.0';

        wp_enqueue_script(
            'tah-quote-pricing',
            get_template_directory_uri() . '/assets/js/quote-pricing.js',
            ['jquery', 'jquery-ui-sortable', 'jquery-ui-autocomplete'],
            $version,
            true
        );

        wp_localize_script('tah-quote-pricing', 'tahQuotePricingConfig', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'ajaxAction' => 'tah_save_pricing',
            'ajaxSearchAction' => 'tah_search_pricing_items',
            'ajaxApplyPresetAction' => 'tah_apply_trade_pricing_preset',
            'ajaxNonce' => wp_create_nonce('tah_pricing_nonce'),
            'tradeContexts' => $this->get_trade_context_map(),
            'rounding' => (float) get_option('tah_price_rounding', 1),
            'roundingDirection' => (string) get_option('tah_price_rounding_direction', TAH_Price_Formula::ROUNDING_NEAREST),
            'labels' => [
                'defaultBadge' => __('DEFAULT', 'the-artist'),
                'modifiedBadge' => __('MODIFIED', 'the-artist'),
                'customBadge' => __('CUSTOM', 'the-artist'),
                'groupDefaultName' => __('New Group', 'the-artist'),
                'itemDefaultName' => __('New Item', 'the-artist'),
                'subtotal' => __('Subtotal', 'the-artist'),
                'grandTotal' => __('Grand Total', 'the-artist'),
                'expand' => __('Expand group', 'the-artist'),
                'collapse' => __('Collapse group', 'the-artist'),
                'deleteGroup' => __('Delete group', 'the-artist'),
                'deleteItem' => __('Delete line item', 'the-artist'),
                'saveSaving' => __('Saving...', 'the-artist'),
                'saveSaved' => __('Saved', 'the-artist'),
                'saveError' => __('Save failed', 'the-artist'),
                'suggestionNoPrice' => __('No price', 'the-artist'),
                'presetApplied' => __('Pricing preset applied.', 'the-artist'),
                'presetNoPreset' => __('No pricing preset is configured for this trade.', 'the-artist'),
                'presetAlreadyPopulated' => __('Pricing is already populated for this quote. Preset was not applied.', 'the-artist'),
                'presetUnsupportedFormat' => __('Pricing presets apply only to standard quotes.', 'the-artist'),
                'presetSkipped' => __('Some preset items were skipped because they no longer exist in the catalog.', 'the-artist'),
                'insuranceGroupName' => __('Insurance Items', 'the-artist'),
            ],
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $line_items
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function map_line_items_by_group(array $line_items): array
    {
        $mapped = [];
        foreach ($line_items as $line_item) {
            $group_id = isset($line_item['group_id']) ? (int) $line_item['group_id'] : 0;
            if ($group_id <= 0) {
                continue;
            }

            if (!isset($mapped[$group_id])) {
                $mapped[$group_id] = [];
            }
            $mapped[$group_id][] = $line_item;
        }

        return $mapped;
    }

    /**
     * @param array<int, array<string, mixed>> $line_items
     * @return array<int, float>
     */
    private function load_catalog_price_map(array $line_items): array
    {
        $ids = [];
        foreach ($line_items as $line_item) {
            $catalog_id = isset($line_item['pricing_item_id']) ? (int) $line_item['pricing_item_id'] : 0;
            if ($catalog_id > 0) {
                $ids[$catalog_id] = true;
            }
        }

        $prices = [];
        foreach (array_keys($ids) as $catalog_id) {
            $catalog_item = $this->repository->get_item_by_id((int) $catalog_id);
            if (!is_array($catalog_item)) {
                continue;
            }
            $prices[(int) $catalog_id] = isset($catalog_item['unit_price']) ? (float) $catalog_item['unit_price'] : 0.0;
        }

        return $prices;
    }

    /**
     * @param array<string, mixed> $group
     * @param array<int, array<string, mixed>> $line_items
     * @param array<int, float> $catalog_prices
     */
    private function render_group_card(array $group, array $line_items, array $catalog_prices, string $client_key): void
    {
        $group_id = isset($group['id']) ? (int) $group['id'] : 0;
        $name = isset($group['name']) && trim((string) $group['name']) !== ''
            ? (string) $group['name']
            : __('Group', 'the-artist');
        $description = isset($group['description']) ? (string) $group['description'] : '';
        $selection_mode = $this->normalize_selection_mode(isset($group['selection_mode']) ? (string) $group['selection_mode'] : 'all');
        $show_subtotal = !empty($group['show_subtotal']);
        $is_collapsed = !empty($group['is_collapsed']);
        $toggle_label = $is_collapsed
            ? __('Expand group', 'the-artist')
            : __('Collapse group', 'the-artist');
        $toggle_icon = $is_collapsed ? 'dashicons-arrow-down-alt2' : 'dashicons-arrow-up-alt2';

        $wrap_classes = 'tah-group-card';
        if ($is_collapsed) {
            $wrap_classes .= ' is-collapsed';
        }

        echo '<section class="' . esc_attr($wrap_classes) . '" data-group-id="' . esc_attr((string) $group_id) . '" data-group-key="' . esc_attr($client_key) . '">';
        echo '<header class="tah-group-header">';
        echo '<div class="tah-group-title-row">';
        echo '<span class="tah-drag-handle tah-group-handle" aria-hidden="true">' . $this->drag_handle_svg() . '</span>';
        echo '<input type="text" class="tah-form-control tah-group-name" value="' . esc_attr($name) . '" placeholder="' . esc_attr__('Group name', 'the-artist') . '">';
        echo '<div class="tah-group-actions">';
        echo '<button type="button" class="tah-icon-button tah-toggle-group" aria-label="' . esc_attr($toggle_label) . '" title="' . esc_attr($toggle_label) . '"><span class="dashicons ' . esc_attr($toggle_icon) . '" aria-hidden="true"></span></button>';
        echo '<button type="button" class="tah-icon-button tah-icon-button--danger tah-delete-group" aria-label="' . esc_attr__('Delete group', 'the-artist') . '" title="' . esc_attr__('Delete group', 'the-artist') . '"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button>';
        echo '</div>';
        echo '</div>';

        echo '<div class="tah-group-settings">';
        echo '<input type="text" class="tah-form-control tah-group-description" value="' . esc_attr($description) . '" placeholder="' . esc_attr__('Group description (optional)', 'the-artist') . '">';
        echo '<label class="tah-group-setting">' . esc_html__('Selection', 'the-artist') . ' ';
        echo '<select class="tah-form-control tah-group-selection-mode">';
        echo '<option value="all" ' . selected($selection_mode, 'all', false) . '>' . esc_html__('All', 'the-artist') . '</option>';
        echo '<option value="multi" ' . selected($selection_mode, 'multi', false) . '>' . esc_html__('Multi', 'the-artist') . '</option>';
        echo '<option value="single" ' . selected($selection_mode, 'single', false) . '>' . esc_html__('Single', 'the-artist') . '</option>';
        echo '</select>';
        echo '</label>';

        echo '<label class="tah-inline-checkbox"><input type="checkbox" class="tah-group-show-subtotal" ' . checked($show_subtotal, true, false) . '> ' . esc_html__('Show subtotal', 'the-artist') . '</label>';
        echo '<label class="tah-inline-checkbox"><input type="checkbox" class="tah-group-collapsed" ' . checked($is_collapsed, true, false) . '> ' . esc_html__('Start collapsed', 'the-artist') . '</label>';
        echo '</div>';
        echo '</header>';

        echo '<div class="tah-group-table-wrap">';
        echo '<table class="tah-pricing-table-editor">';
        echo '<thead><tr>';
        echo '<th class="tah-col-handle"></th>';
        echo '<th class="tah-col-index">#</th>';
        echo '<th class="tah-col-item">' . esc_html__('Item', 'the-artist') . '</th>';
        echo '<th class="tah-col-sku tah-col-insurance">' . esc_html__('SKU', 'the-artist') . '</th>';
        echo '<th class="tah-col-description" data-standard-label="' . esc_attr__('Description', 'the-artist') . '" data-insurance-label="' . esc_attr__('F9 Note', 'the-artist') . '">' . esc_html__('Description', 'the-artist') . '</th>';
        echo '<th class="tah-col-material tah-col-insurance">' . esc_html__('Material', 'the-artist') . '</th>';
        echo '<th class="tah-col-labor tah-col-insurance">' . esc_html__('Labor', 'the-artist') . '</th>';
        echo '<th class="tah-col-qty">' . esc_html__('Qty', 'the-artist') . '</th>';
        echo '<th class="tah-col-rate" data-standard-label="' . esc_attr__('Rate', 'the-artist') . '" data-insurance-label="' . esc_attr__('Unit Price', 'the-artist') . '">' . esc_html__('Rate', 'the-artist') . '</th>';
        echo '<th class="tah-col-tax tah-col-insurance">' . esc_html__('Tax', 'the-artist') . '</th>';
        echo '<th class="tah-col-amount">' . esc_html__('Amount', 'the-artist') . '</th>';
        echo '<th class="tah-col-margin">' . esc_html__('Margin', 'the-artist') . '</th>';
        echo '<th class="tah-col-actions"></th>';
        echo '</tr></thead>';
        echo '<tbody class="tah-line-items-body">';

        if (empty($line_items)) {
            $this->render_line_item_row([], 1, 0.0);
        } else {
            foreach ($line_items as $index => $line_item) {
                $catalog_id = isset($line_item['pricing_item_id']) ? (int) $line_item['pricing_item_id'] : 0;
                $catalog_price = isset($catalog_prices[$catalog_id]) ? (float) $catalog_prices[$catalog_id] : 0.0;
                $this->render_line_item_row($line_item, $index + 1, $catalog_price);
            }
        }

        echo '</tbody>';
        echo '</table>';

        echo '<div class="tah-group-footer">';
        echo '<button type="button" class="button button-secondary tah-add-line-item">' . esc_html__('+ Add Item', 'the-artist') . '</button>';
        echo '<div class="tah-group-subtotal-row">';
        echo '<span class="tah-group-subtotal-label">' . esc_html__('Subtotal', 'the-artist') . '</span>';
        echo '<strong class="tah-group-subtotal-value">$0.00</strong>';
        echo '</div>';
        echo '</div>';

        echo '</div>';
        echo '</section>';
    }

    /**
     * @param array<string, mixed> $line_item
     */
    private function render_line_item_row(array $line_item, int $index, float $catalog_price): void
    {
        $line_id = isset($line_item['id']) ? (int) $line_item['id'] : 0;
        $pricing_item_id = isset($line_item['pricing_item_id']) ? (int) $line_item['pricing_item_id'] : 0;
        $item_type = isset($line_item['item_type']) ? (string) $line_item['item_type'] : 'standard';
        $title = isset($line_item['title']) ? (string) $line_item['title'] : '';
        $description = isset($line_item['description']) ? (string) $line_item['description'] : '';
        $quantity = isset($line_item['quantity']) ? (float) $line_item['quantity'] : 0.0;
        $unit_type = isset($line_item['unit_type']) ? (string) $line_item['unit_type'] : 'flat';
        $price_mode = isset($line_item['price_mode']) ? (string) $line_item['price_mode'] : TAH_Price_Formula::MODE_OVERRIDE;
        $price_modifier = isset($line_item['price_modifier']) ? (float) $line_item['price_modifier'] : 0.0;
        $resolved_price = isset($line_item['resolved_price']) ? (float) $line_item['resolved_price'] : 0.0;
        $previous_resolved_price = isset($line_item['previous_resolved_price']) && is_numeric($line_item['previous_resolved_price'])
            ? (float) $line_item['previous_resolved_price']
            : null;
        $is_selected = !array_key_exists('is_selected', $line_item) || !empty($line_item['is_selected']);
        $material_cost = isset($line_item['material_cost']) && is_numeric($line_item['material_cost'])
            ? (float) $line_item['material_cost']
            : null;
        $labor_cost = isset($line_item['labor_cost']) && is_numeric($line_item['labor_cost'])
            ? (float) $line_item['labor_cost']
            : null;
        $line_sku = isset($line_item['line_sku']) ? (string) $line_item['line_sku'] : '';
        $tax_rate = isset($line_item['tax_rate']) && is_numeric($line_item['tax_rate'])
            ? (float) $line_item['tax_rate']
            : null;
        $note = isset($line_item['note']) ? (string) $line_item['note'] : '';
        $description_or_note = $description !== '' ? $description : $note;

        $amount = round($quantity * $resolved_price, 2);
        $qty_formula = $this->format_plain_number($quantity);
        $rate_formula = $this->format_rate_formula($price_mode, $price_modifier);
        $badge = $this->resolve_rate_badge($price_mode, $pricing_item_id);
        $margin = $this->format_margin($resolved_price, $material_cost, $labor_cost);

        echo '<tr class="tah-line-item-row" data-item-id="' . esc_attr((string) $line_id) . '">';
        echo '<td class="tah-cell-handle"><span class="tah-drag-handle tah-line-handle" aria-hidden="true">' . $this->drag_handle_svg() . '</span></td>';
        echo '<td class="tah-cell-index"><span class="tah-line-index">' . esc_html((string) $index) . '</span></td>';

        echo '<td class="tah-cell-item">';
        echo '<input type="text" class="tah-form-control tah-line-title" value="' . esc_attr($title) . '" placeholder="' . esc_attr__('Line item', 'the-artist') . '">';
        echo '<input type="hidden" class="tah-line-id" value="' . esc_attr((string) $line_id) . '">';
        echo '<input type="hidden" class="tah-line-pricing-item-id" value="' . esc_attr((string) $pricing_item_id) . '">';
        echo '<input type="hidden" class="tah-line-item-type" value="' . esc_attr($item_type) . '">';
        echo '<input type="hidden" class="tah-line-unit-type" value="' . esc_attr($unit_type) . '">';
        echo '<input type="hidden" class="tah-line-is-selected" value="' . esc_attr($is_selected ? '1' : '0') . '">';
        echo '<input type="hidden" class="tah-line-note" value="' . esc_attr($note) . '">';
        echo '<input type="hidden" class="tah-line-previous-resolved-price" value="' . esc_attr($previous_resolved_price !== null ? (string) $previous_resolved_price : '') . '">';
        echo '</td>';

        echo '<td class="tah-cell-sku tah-cell-insurance"><input type="text" class="tah-form-control tah-line-line-sku" value="' . esc_attr($line_sku) . '" placeholder="' . esc_attr__('SKU', 'the-artist') . '"></td>';

        echo '<td class="tah-cell-description">';
        echo '<button type="button" class="button-link tah-line-note-toggle tah-cell-insurance" aria-label="' . esc_attr__('Toggle F9 note', 'the-artist') . '" title="' . esc_attr__('Toggle F9 note', 'the-artist') . '">F9</button>';
        echo '<input type="text" class="tah-form-control tah-line-description" value="' . esc_attr($description_or_note) . '" placeholder="' . esc_attr__('Description', 'the-artist') . '">';
        echo '</td>';

        echo '<td class="tah-cell-material tah-cell-insurance"><input type="number" step="0.01" class="tah-form-control tah-line-material-cost" value="' . esc_attr($material_cost !== null ? (string) $material_cost : '') . '" placeholder="0.00"></td>';
        echo '<td class="tah-cell-labor tah-cell-insurance"><input type="number" step="0.01" class="tah-form-control tah-line-labor-cost" value="' . esc_attr($labor_cost !== null ? (string) $labor_cost : '') . '" placeholder="0.00"></td>';

        echo '<td class="tah-cell-qty">';
        echo '<input type="text" class="tah-form-control tah-line-qty" value="' . esc_attr($this->format_plain_number($quantity)) . '" data-formula="' . esc_attr($qty_formula) . '" data-resolved="' . esc_attr((string) $quantity) . '">';
        echo '</td>';

        echo '<td class="tah-cell-rate">';
        echo '<div class="tah-rate-field">';
        echo '<input type="text" class="tah-form-control tah-line-rate" value="' . esc_attr($this->format_currency($resolved_price)) . '" data-formula="' . esc_attr($rate_formula) . '" data-resolved="' . esc_attr((string) $resolved_price) . '">';
        echo '<span class="tah-badge ' . esc_attr($badge['class']) . ' tah-line-rate-badge">' . esc_html($badge['label']) . '</span>';
        echo '<input type="hidden" class="tah-line-catalog-price" value="' . esc_attr((string) $catalog_price) . '">';
        echo '</div>';
        echo '</td>';

        echo '<td class="tah-cell-tax tah-cell-insurance">';
        echo '<input type="number" step="0.0001" min="0" class="tah-form-control tah-line-tax-rate" value="' . esc_attr($tax_rate !== null ? (string) $tax_rate : '') . '" placeholder="' . esc_attr__('Quote default', 'the-artist') . '">';
        echo '<span class="tah-line-tax-amount">$0.00</span>';
        echo '</td>';

        echo '<td class="tah-cell-amount"><span class="tah-line-amount" data-amount="' . esc_attr((string) $amount) . '">' . esc_html($this->format_currency($amount)) . '</span></td>';
        echo '<td class="tah-cell-margin"><span class="tah-line-margin">' . esc_html($margin) . '</span></td>';
        echo '<td class="tah-cell-actions"><button type="button" class="tah-icon-button tah-icon-button--danger tah-delete-line" aria-label="' . esc_attr__('Delete line item', 'the-artist') . '" title="' . esc_attr__('Delete line item', 'the-artist') . '"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button></td>';
        echo '</tr>';
    }

    /**
     * @return array{class:string,label:string}
     */
    private function resolve_rate_badge(string $price_mode, int $pricing_item_id): array
    {
        if ($pricing_item_id <= 0 || $price_mode === TAH_Price_Formula::MODE_OVERRIDE) {
            return ['class' => 'tah-badge--custom', 'label' => __('CUSTOM', 'the-artist')];
        }

        if ($price_mode === TAH_Price_Formula::MODE_DEFAULT) {
            return ['class' => 'tah-badge--neutral', 'label' => __('DEFAULT', 'the-artist')];
        }

        return ['class' => 'tah-badge--accent', 'label' => __('MODIFIED', 'the-artist')];
    }

    private function normalize_selection_mode(string $selection_mode): string
    {
        $selection_mode = strtolower(trim($selection_mode));
        return in_array($selection_mode, ['all', 'multi', 'single'], true) ? $selection_mode : 'all';
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    private function decode_json_array(string $raw)
    {
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    private function is_quote_edit_screen(string $hook_suffix = ''): bool
    {
        if ($hook_suffix !== '' && !in_array($hook_suffix, ['post.php', 'post-new.php'], true)) {
            return false;
        }

        $screen = get_current_screen();

        return $screen instanceof WP_Screen
            && $screen->base === 'post'
            && $screen->post_type === self::POST_TYPE;
    }

    private function get_quote_trade_id(int $quote_id): int
    {
        $terms = wp_get_post_terms($quote_id, 'trade');
        if (is_wp_error($terms) || empty($terms)) {
            return 0;
        }

        $first = reset($terms);
        if (!$first instanceof WP_Term) {
            return 0;
        }

        return (int) $first->term_id;
    }

    /**
     * @return array{groups: array<int, array<string, mixed>>}
     */
    private function get_trade_pricing_preset(int $trade_id): array
    {
        if ($trade_id <= 0) {
            return ['groups' => []];
        }

        $raw = get_term_meta($trade_id, '_tah_trade_pricing_preset', true);
        if (!is_string($raw) || trim($raw) === '') {
            return ['groups' => []];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['groups']) || !is_array($decoded['groups'])) {
            return ['groups' => []];
        }

        return ['groups' => $decoded['groups']];
    }

    private function has_persisted_pricing_data(int $quote_id): bool
    {
        return !empty($this->repository->get_quote_groups($quote_id))
            || !empty($this->repository->get_quote_line_items($quote_id));
    }

    /**
     * @param array<string, mixed> $request
     */
    private function persist_quote_format_meta_from_request(int $post_id, array $request): void
    {
        if (isset($request[self::FIELD_QUOTE_FORMAT])) {
            $quote_format = $this->normalize_quote_format(
                sanitize_key(wp_unslash((string) $request[self::FIELD_QUOTE_FORMAT]))
            );
            update_post_meta($post_id, self::META_QUOTE_FORMAT, $quote_format);
        }

        if (!array_key_exists(self::FIELD_QUOTE_TAX_RATE, $request)) {
            return;
        }

        $raw_tax_rate = trim((string) wp_unslash((string) $request[self::FIELD_QUOTE_TAX_RATE]));
        if ($raw_tax_rate === '') {
            delete_post_meta($post_id, self::META_QUOTE_TAX_RATE);
            return;
        }

        if (!is_numeric($raw_tax_rate)) {
            return;
        }

        $tax_rate = round((float) $raw_tax_rate, 4);
        if ($tax_rate < 0) {
            $tax_rate = 0.0;
        }

        update_post_meta($post_id, self::META_QUOTE_TAX_RATE, $tax_rate);
    }

    private function normalize_quote_format(string $quote_format): string
    {
        $quote_format = strtolower(trim($quote_format));
        return in_array($quote_format, ['standard', 'insurance'], true) ? $quote_format : 'standard';
    }

    /**
     * @return array<string, string>
     */
    private function get_trade_context_map(): array
    {
        $terms = get_terms([
            'taxonomy' => 'trade',
            'hide_empty' => false,
            'fields' => 'ids',
        ]);

        if (is_wp_error($terms) || !is_array($terms)) {
            return [];
        }

        $contexts = [];
        foreach ($terms as $term_id) {
            $id = (int) $term_id;
            if ($id <= 0) {
                continue;
            }

            $context = (string) get_term_meta($id, '_tah_trade_context', true);
            $contexts[(string) $id] = $this->normalize_trade_context($context);
        }

        return $contexts;
    }

    private function normalize_trade_context(string $context): string
    {
        $context = strtolower(trim($context));
        if ($context === 'all') {
            $context = 'both';
        }

        return in_array($context, ['standard', 'insurance', 'both'], true) ? $context : 'standard';
    }

    private function format_rate_formula(string $price_mode, float $modifier): string
    {
        if ($price_mode === TAH_Price_Formula::MODE_DEFAULT) {
            return '$';
        }

        if ($price_mode === TAH_Price_Formula::MODE_ADDITION) {
            if ($modifier >= 0) {
                return '$+' . $this->format_plain_number($modifier);
            }
            return '$-' . $this->format_plain_number(abs($modifier));
        }

        if ($price_mode === TAH_Price_Formula::MODE_PERCENTAGE) {
            return '$*' . $this->format_plain_number($modifier);
        }

        return $this->format_plain_number($modifier);
    }

    private function format_margin(float $resolved_price, ?float $material_cost, ?float $labor_cost): string
    {
        $cost = 0.0;
        if ($material_cost !== null) {
            $cost += $material_cost;
        }
        if ($labor_cost !== null) {
            $cost += $labor_cost;
        }

        if ($cost <= 0.0 || $resolved_price == 0.0) {
            return '--';
        }

        $margin = (($resolved_price - $cost) / $resolved_price) * 100;
        return number_format_i18n($margin, 1) . '%';
    }

    private function format_currency(float $amount): string
    {
        $sign = $amount < 0 ? '-' : '';
        return $sign . '$' . number_format_i18n(abs($amount), 2);
    }

    private function format_plain_number(float $value): string
    {
        $formatted = number_format($value, 4, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');
        return $formatted === '-0' ? '0' : $formatted;
    }

    private function drag_handle_svg(): string
    {
        return '<svg viewBox="0 0 32 32" class="svg-icon"><path d="M 14 5.5 a 3 3 0 1 1 -3 -3 A 3 3 0 0 1 14 5.5 Z m 7 3 a 3 3 0 1 0 -3 -3 A 3 3 0 0 0 21 8.5 Z m -10 4 a 3 3 0 1 0 3 3 A 3 3 0 0 0 11 12.5 Z m 10 0 a 3 3 0 1 0 3 3 A 3 3 0 0 0 21 12.5 Z m -10 10 a 3 3 0 1 0 3 3 A 3 3 0 0 0 11 22.5 Z m 10 0 a 3 3 0 1 0 3 3 A 3 3 0 0 0 21 22.5 Z"></path></svg>';
    }
}

$GLOBALS['tah_quote_pricing_metabox'] = new TAH_Quote_Pricing_Metabox();
