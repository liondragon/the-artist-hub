<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Trade taxonomy pricing preset editor.
 */
final class TAH_Pricing_Trade_Presets
{
    private const META_KEY = '_tah_trade_pricing_preset';
    private const NONCE_ACTION = 'tah_pricing_trade_presets_save';
    private const NONCE_NAME = '_tah_pricing_trade_presets_nonce';
    private const FIELD_NAME = 'tah_trade_pricing_preset_json';

    public function __construct()
    {
        add_action('trade_add_form_fields', [$this, 'render_field_add']);
        add_action('trade_edit_form_fields', [$this, 'render_field_edit']);
        add_action('created_trade', [$this, 'save_meta']);
        add_action('edited_trade', [$this, 'save_meta']);
    }

    /**
     * @param string $taxonomy
     */
    public function render_field_add($taxonomy): void
    {
        if ((string) $taxonomy !== 'trade') {
            return;
        }

        $preset_json = $this->format_preset_for_editor(['groups' => []]);

        echo '<div class="form-field term-pricing-preset-wrap">';
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);
        echo '<label for="' . esc_attr(self::FIELD_NAME) . '">' . esc_html__('Pricing Preset (Standard Quotes)', 'the-artist') . '</label>';
        echo '<p class="description">' . esc_html__('Configure default pricing groups/items as JSON. Applied to new standard-format quotes when this trade is selected.', 'the-artist') . '</p>';
        echo '<textarea id="' . esc_attr(self::FIELD_NAME) . '" name="' . esc_attr(self::FIELD_NAME) . '" class="large-text code" rows="14" spellcheck="false">' . esc_textarea($preset_json) . '</textarea>';
        echo '<p class="description">' . esc_html__('Shape: {"groups":[{"name":"...","selection_mode":"all|multi|single","show_subtotal":true,"is_collapsed":false,"items":[{"pricing_item_sku":"sku","quantity":1}]}]}', 'the-artist') . '</p>';
        echo '</div>';
    }

    /**
     * @param WP_Term $term
     */
    public function render_field_edit($term): void
    {
        if (!$term instanceof WP_Term || $term->taxonomy !== 'trade') {
            return;
        }

        $saved_raw = get_term_meta((int) $term->term_id, self::META_KEY, true);
        $saved_array = is_string($saved_raw) ? $this->decode_preset_json($saved_raw) : null;
        $preset_json = $saved_array !== null
            ? $this->format_preset_for_editor($saved_array)
            : $this->format_preset_for_editor(['groups' => []]);

        echo '<tr class="form-field term-pricing-preset-wrap">';
        echo '<th scope="row"><label for="' . esc_attr(self::FIELD_NAME) . '">' . esc_html__('Pricing Preset (Standard Quotes)', 'the-artist') . '</label></th>';
        echo '<td>';
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);
        echo '<p class="description" style="margin-bottom:10px;">' . esc_html__('Configure default pricing groups/items as JSON. Applied to new standard-format quotes when this trade is selected.', 'the-artist') . '</p>';
        echo '<textarea id="' . esc_attr(self::FIELD_NAME) . '" name="' . esc_attr(self::FIELD_NAME) . '" class="large-text code" rows="14" spellcheck="false">' . esc_textarea($preset_json) . '</textarea>';
        echo '<p class="description">' . esc_html__('Shape: {"groups":[{"name":"...","selection_mode":"all|multi|single","show_subtotal":true,"is_collapsed":false,"items":[{"pricing_item_sku":"sku","quantity":1}]}]}', 'the-artist') . '</p>';
        echo '</td>';
        echo '</tr>';
    }

    /**
     * @param int $term_id
     */
    public function save_meta($term_id): void
    {
        if (!current_user_can('manage_categories')) {
            return;
        }

        if (
            !isset($_POST[self::NONCE_NAME])
            || !wp_verify_nonce(
                sanitize_text_field(wp_unslash((string) $_POST[self::NONCE_NAME])),
                self::NONCE_ACTION
            )
        ) {
            return;
        }

        if (!isset($_POST[self::FIELD_NAME])) {
            return;
        }

        $raw_json = trim(wp_unslash((string) $_POST[self::FIELD_NAME]));
        if ($raw_json === '') {
            update_term_meta((int) $term_id, self::META_KEY, wp_json_encode(['groups' => []]));
            return;
        }

        $decoded = $this->decode_preset_json($raw_json);
        if ($decoded === null) {
            return;
        }

        $normalized = $this->normalize_preset($decoded);
        update_term_meta((int) $term_id, self::META_KEY, wp_json_encode($normalized));
    }

    /**
     * @param array<string, mixed> $preset
     */
    private function format_preset_for_editor(array $preset): string
    {
        $json = wp_json_encode($this->normalize_preset($preset), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || $json === '') {
            return "{\n  \"groups\": []\n}";
        }

        return $json;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decode_preset_json(string $raw_json): ?array
    {
        $decoded = json_decode($raw_json, true);
        if (!is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $preset
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function normalize_preset(array $preset): array
    {
        $groups = [];
        if (!isset($preset['groups']) || !is_array($preset['groups'])) {
            return ['groups' => []];
        }

        foreach ($preset['groups'] as $group) {
            if (!is_array($group)) {
                continue;
            }

            $name = isset($group['name']) ? sanitize_text_field((string) $group['name']) : '';
            if ($name === '') {
                continue;
            }

            $selection_mode = strtolower(trim(isset($group['selection_mode']) ? (string) $group['selection_mode'] : 'all'));
            if (!in_array($selection_mode, ['all', 'multi', 'single'], true)) {
                $selection_mode = 'all';
            }

            $items = [];
            if (isset($group['items']) && is_array($group['items'])) {
                foreach ($group['items'] as $item) {
                    if (!is_array($item)) {
                        continue;
                    }

                    $pricing_item_sku = isset($item['pricing_item_sku'])
                        ? sanitize_text_field((string) $item['pricing_item_sku'])
                        : '';
                    if ($pricing_item_sku === '') {
                        continue;
                    }

                    $quantity = 1.0;
                    if (isset($item['quantity']) && is_numeric($item['quantity'])) {
                        $quantity = round((float) $item['quantity'], 2);
                    }
                    if ($quantity <= 0) {
                        $quantity = 1.0;
                    }

                    $items[] = [
                        'pricing_item_sku' => $pricing_item_sku,
                        'quantity' => $quantity,
                    ];
                }
            }

            $groups[] = [
                'name' => $name,
                'selection_mode' => $selection_mode,
                'show_subtotal' => !empty($group['show_subtotal']),
                'is_collapsed' => !empty($group['is_collapsed']),
                'items' => $items,
            ];
        }

        return ['groups' => $groups];
    }
}

$GLOBALS['tah_pricing_trade_presets'] = new TAH_Pricing_Trade_Presets();
