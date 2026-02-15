<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Frontend rendering for quote pricing groups and line items.
 */
final class TAH_Quote_Pricing_Frontend
{
    private const META_QUOTE_FORMAT = '_tah_quote_format';
    private const DEFAULT_QUOTE_FORMAT = 'standard';

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
    }

    /**
     * Render standard-format quote pricing below quote content.
     */
    public function render(int $post_id)
    {
        if ($post_id <= 0 || get_post_type($post_id) !== 'quotes') {
            return;
        }

        $quote_format = $this->get_quote_format($post_id);
        if ($quote_format === 'insurance') {
            $this->render_insurance_quote($post_id);
            return;
        }

        $this->render_standard_quote($post_id);
    }

    private function render_standard_quote(int $post_id): void
    {
        $groups = $this->repository->get_quote_groups($post_id);
        if (empty($groups)) {
            return;
        }

        $line_items = $this->repository->get_quote_line_items($post_id);
        if (empty($line_items)) {
            return;
        }

        $line_items = $this->hydrate_missing_descriptions($line_items);
        $line_items_by_group = $this->map_line_items_by_group($line_items);

        ob_start();

        echo '<section class="tah-quote-pricing" data-quote-id="' . esc_attr((string) $post_id) . '">';

        $grand_total = 0.0;
        $rendered_groups = 0;

        foreach ($groups as $index => $group) {
            $group_id = isset($group['id']) ? (int) $group['id'] : 0;
            if ($group_id <= 0) {
                continue;
            }

            $selection_mode = $this->normalize_selection_mode(
                isset($group['selection_mode']) ? (string) $group['selection_mode'] : 'all'
            );
            $rows_data = $this->build_group_rows(
                $line_items_by_group[$group_id] ?? [],
                $selection_mode
            );

            $group_subtotal = (float) $rows_data['subtotal'];
            $show_subtotal = !empty($group['show_subtotal']);
            $is_collapsed = !empty($group['is_collapsed']);
            $group_name = isset($group['name']) ? trim((string) $group['name']) : '';
            $group_description = isset($group['description']) ? trim((string) $group['description']) : '';

            if ($group_name === '') {
                $group_name = sprintf(
                    /* translators: %d: group index in quote */
                    __('Group %d', 'the-artist'),
                    (int) $index + 1
                );
            }

            $group_classes = [
                'tah-pricing-group',
                'tah-pricing-group-mode-' . $selection_mode,
            ];
            if ($is_collapsed) {
                $group_classes[] = 'tah-pricing-group-collapsed';
            }

            echo '<section class="' . esc_attr(implode(' ', $group_classes)) . '" data-group-id="' . esc_attr((string) $group_id) . '">';
            echo '<header class="tah-pricing-group-header">';
            echo '<h3 class="tah-pricing-group-title">' . esc_html($group_name) . '</h3>';

            if ($group_description !== '') {
                echo '<p class="tah-pricing-group-description">' . esc_html($group_description) . '</p>';
            }

            echo '</header>';

            if ($is_collapsed) {
                echo '<div class="tah-pricing-group-collapsed-total">';
                echo '<span class="tah-pricing-group-collapsed-label">' . esc_html__('Subtotal', 'the-artist') . '</span>';
                echo '<strong class="tah-pricing-group-collapsed-value">' . esc_html($this->format_currency($group_subtotal)) . '</strong>';
                echo '</div>';
                echo '<details class="tah-pricing-group-details">';
                echo '<summary class="tah-pricing-group-toggle">' . esc_html__('Show details', 'the-artist') . '</summary>';
                $this->render_group_table((array) $rows_data['rows'], false, $group_subtotal);
                echo '</details>';
            } else {
                $this->render_group_table((array) $rows_data['rows'], $show_subtotal, $group_subtotal);
            }

            echo '</section>';

            $grand_total = round($grand_total + $group_subtotal, 2);
            $rendered_groups++;
        }

        if ($rendered_groups <= 0) {
            ob_end_clean();
            return;
        }

        echo '<div class="tah-pricing-grand-total">';
        echo '<span class="tah-pricing-grand-total-label">' . esc_html__('Grand Total', 'the-artist') . '</span>';
        echo '<strong class="tah-pricing-grand-total-value">' . esc_html($this->format_currency($grand_total)) . '</strong>';
        echo '</div>';
        echo '</section>';

        $markup = trim((string) ob_get_clean());
        if ($markup !== '') {
            echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
    }

    private function render_insurance_quote(int $post_id): void
    {
        $line_items = $this->repository->get_quote_line_items($post_id);
        if (empty($line_items)) {
            return;
        }

        $quote_tax_rate = $this->to_float(get_post_meta($post_id, '_tah_quote_tax_rate', true));
        $catalog_meta = $this->get_catalog_meta_by_item_id($line_items);
        $line_index = 0;
        $subtotal = 0.0;
        $tax_total = 0.0;
        $current_category = '';

        ob_start();

        echo '<section class="tah-quote-pricing tah-quote-pricing-insurance" data-quote-id="' . esc_attr((string) $post_id) . '">';
        echo '<table class="tah-pricing-table tah-pricing-table-insurance">';
        echo '<thead>';
        echo '<tr>';
        echo '<th scope="col">' . esc_html__('#', 'the-artist') . '</th>';
        echo '<th scope="col">' . esc_html__('Description', 'the-artist') . '</th>';
        echo '<th scope="col">' . esc_html__('SKU', 'the-artist') . '</th>';
        echo '<th scope="col">' . esc_html__('Qty', 'the-artist') . '</th>';
        echo '<th scope="col">' . esc_html__('Unit Price', 'the-artist') . '</th>';
        echo '<th scope="col">' . esc_html__('Tax', 'the-artist') . '</th>';
        echo '<th scope="col">' . esc_html__('Total', 'the-artist') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($line_items as $line_item) {
            $line_index++;
            $quantity = $this->to_float($line_item['quantity'] ?? 0);
            $resolved_price = $this->to_float($line_item['resolved_price'] ?? 0);
            $material_cost = array_key_exists('material_cost', $line_item) && $line_item['material_cost'] !== null
                ? $this->to_float($line_item['material_cost'])
                : 0.0;
            $labor_cost = array_key_exists('labor_cost', $line_item) && $line_item['labor_cost'] !== null
                ? $this->to_float($line_item['labor_cost'])
                : 0.0;
            $line_sku = isset($line_item['line_sku']) ? trim((string) $line_item['line_sku']) : '';
            $note = isset($line_item['note']) ? trim((string) $line_item['note']) : '';

            $description = isset($line_item['title']) ? trim((string) $line_item['title']) : '';
            if ($description === '') {
                $description = __('Untitled item', 'the-artist');
            }

            $line_tax_rate = isset($line_item['tax_rate']) && $line_item['tax_rate'] !== null
                ? $this->to_float($line_item['tax_rate'])
                : $quote_tax_rate;
            if ($line_tax_rate < 0) {
                $line_tax_rate = 0.0;
            }

            $line_subtotal = round($quantity * $resolved_price, 2);
            $line_tax_amount = round($quantity * $material_cost * $line_tax_rate, 2);
            $line_total = round($line_subtotal + $line_tax_amount, 2);

            $subtotal = round($subtotal + $line_subtotal, 2);
            $tax_total = round($tax_total + $line_tax_amount, 2);

            $pricing_item_id = isset($line_item['pricing_item_id']) ? (int) $line_item['pricing_item_id'] : 0;
            $category = '';
            if ($pricing_item_id > 0 && isset($catalog_meta[$pricing_item_id]['category'])) {
                $category = (string) $catalog_meta[$pricing_item_id]['category'];
            }
            if ($category !== '' && $category !== $current_category) {
                $current_category = $category;
                echo '<tr class="tah-pricing-insurance-category-row">';
                echo '<th colspan="7" scope="colgroup">' . esc_html($current_category) . '</th>';
                echo '</tr>';
            }

            echo '<tr class="tah-pricing-line-item tah-pricing-line-item-insurance">';
            echo '<td class="tah-pricing-col-index">' . esc_html((string) $line_index) . '</td>';
            echo '<td class="tah-pricing-col-item">';
            echo '<span class="tah-pricing-item-title">' . esc_html($description) . '</span>';
            if ($material_cost > 0 || $labor_cost > 0) {
                echo '<span class="tah-pricing-insurance-breakdown">';
                echo esc_html(
                    sprintf(
                        /* translators: 1: material amount, 2: labor amount */
                        __('Material %1$s + Labor %2$s', 'the-artist'),
                        $this->format_currency($material_cost),
                        $this->format_currency($labor_cost)
                    )
                );
                echo '</span>';
            }
            echo '</td>';
            echo '<td class="tah-pricing-col-sku">' . ($line_sku !== '' ? esc_html($line_sku) : '-') . '</td>';
            echo '<td class="tah-pricing-col-qty">' . esc_html($this->format_quantity($quantity)) . '</td>';
            echo '<td class="tah-pricing-col-rate">' . esc_html($this->format_currency($resolved_price)) . '</td>';
            echo '<td class="tah-pricing-col-tax">' . esc_html($this->format_currency($line_tax_amount)) . '</td>';
            echo '<td class="tah-pricing-col-total">' . esc_html($this->format_currency($line_total)) . '</td>';
            echo '</tr>';

            if ($note !== '') {
                echo '<tr class="tah-pricing-insurance-note-row">';
                echo '<td></td>';
                echo '<td colspan="6" class="tah-pricing-insurance-note-cell">';
                echo '<strong class="tah-pricing-insurance-note-label">' . esc_html__('F9 Note:', 'the-artist') . '</strong> ';
                echo '<span class="tah-pricing-insurance-note-text">' . esc_html($note) . '</span>';
                echo '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody>';
        echo '<tfoot>';
        echo '<tr class="tah-pricing-subtotal-row tah-pricing-insurance-subtotal-row">';
        echo '<th colspan="6" scope="row">' . esc_html__('Subtotal', 'the-artist') . '</th>';
        echo '<td class="tah-pricing-subtotal-value">' . esc_html($this->format_currency($subtotal)) . '</td>';
        echo '</tr>';
        echo '<tr class="tah-pricing-subtotal-row tah-pricing-insurance-tax-total-row">';
        echo '<th colspan="6" scope="row">' . esc_html__('Tax Total', 'the-artist') . '</th>';
        echo '<td class="tah-pricing-subtotal-value">' . esc_html($this->format_currency($tax_total)) . '</td>';
        echo '</tr>';
        echo '<tr class="tah-pricing-grand-total-row tah-pricing-insurance-grand-total-row">';
        echo '<th colspan="6" scope="row">' . esc_html__('Grand Total', 'the-artist') . '</th>';
        echo '<td class="tah-pricing-grand-total-value">' . esc_html($this->format_currency(round($subtotal + $tax_total, 2))) . '</td>';
        echo '</tr>';
        echo '</tfoot>';
        echo '</table>';
        echo '</section>';

        $markup = trim((string) ob_get_clean());
        if ($markup !== '') {
            echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function render_group_table(array $rows, bool $show_subtotal, float $subtotal)
    {
        echo '<table class="tah-pricing-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th scope="col">' . esc_html__('#', 'the-artist') . '</th>';
        echo '<th scope="col">' . esc_html__('Item', 'the-artist') . '</th>';
        echo '<th scope="col">' . esc_html__('Qty', 'the-artist') . '</th>';
        echo '<th scope="col">' . esc_html__('Unit Price', 'the-artist') . '</th>';
        echo '<th scope="col">' . esc_html__('Total', 'the-artist') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        if (empty($rows)) {
            echo '<tr class="tah-pricing-line-item tah-pricing-line-item-empty">';
            echo '<td colspan="5">' . esc_html__('No line items configured.', 'the-artist') . '</td>';
            echo '</tr>';
        }

        foreach ($rows as $row) {
            $item = isset($row['item']) && is_array($row['item']) ? $row['item'] : [];
            $is_discount = $this->is_discount_item($item, (float) $row['resolved_price'], (float) $row['line_total']);
            $included = !empty($row['included']);
            $item_title = isset($item['title']) ? trim((string) $item['title']) : '';
            $item_description = isset($item['description']) ? trim((string) $item['description']) : '';
            $unit_type = isset($item['unit_type']) ? trim((string) $item['unit_type']) : '';

            if ($item_title === '') {
                $item_title = __('Untitled item', 'the-artist');
            }

            $row_classes = ['tah-pricing-line-item'];
            if (!$included) {
                $row_classes[] = 'tah-pricing-line-item-excluded';
            }
            if ($is_discount) {
                $row_classes[] = 'tah-pricing-line-item-discount';
            }

            echo '<tr class="' . esc_attr(implode(' ', $row_classes)) . '">';
            echo '<td class="tah-pricing-col-index">' . esc_html((string) $row['index']) . '</td>';
            echo '<td class="tah-pricing-col-item">';
            echo '<div class="tah-pricing-item-main">';
            echo '<span class="tah-pricing-item-title">' . esc_html($item_title) . '</span>';

            if ($is_discount) {
                echo '<span class="tah-pricing-item-badge">' . esc_html__('Discount', 'the-artist') . '</span>';
            }
            if (!$included) {
                echo '<span class="tah-pricing-item-badge tah-pricing-item-badge-muted">' . esc_html__('Not selected', 'the-artist') . '</span>';
            }

            echo '</div>';

            if ($item_description !== '') {
                echo '<details class="tah-pricing-item-description">';
                echo '<summary>' . esc_html__('Show details', 'the-artist') . '</summary>';
                echo '<div class="tah-pricing-item-description-body">';
                echo wp_kses_post(wpautop($item_description));
                echo '</div>';
                echo '</details>';
            }

            echo '</td>';
            echo '<td class="tah-pricing-col-qty">';
            echo '<span class="tah-pricing-qty-value">' . esc_html($this->format_quantity((float) $row['quantity'])) . '</span>';
            if ($unit_type !== '') {
                echo '<span class="tah-pricing-qty-unit"> ' . esc_html($unit_type) . '</span>';
            }
            echo '</td>';
            echo '<td class="tah-pricing-col-rate">' . esc_html($this->format_currency((float) $row['resolved_price'])) . '</td>';
            echo '<td class="tah-pricing-col-total">' . esc_html($this->format_currency((float) $row['line_total'])) . '</td>';
            echo '</tr>';
        }

        echo '</tbody>';

        if ($show_subtotal) {
            echo '<tfoot>';
            echo '<tr class="tah-pricing-subtotal-row">';
            echo '<th colspan="4" scope="row">' . esc_html__('Subtotal', 'the-artist') . '</th>';
            echo '<td class="tah-pricing-subtotal-value">' . esc_html($this->format_currency($subtotal)) . '</td>';
            echo '</tr>';
            echo '</tfoot>';
        }

        echo '</table>';
    }

    /**
     * @param array<int, array<string, mixed>> $line_items
     * @return array<int, array<string, mixed>>
     */
    private function map_line_items_by_group(array $line_items): array
    {
        $grouped = [];
        foreach ($line_items as $line_item) {
            $group_id = isset($line_item['group_id']) ? (int) $line_item['group_id'] : 0;
            if ($group_id <= 0) {
                continue;
            }

            if (!isset($grouped[$group_id])) {
                $grouped[$group_id] = [];
            }
            $grouped[$group_id][] = $line_item;
        }

        return $grouped;
    }

    /**
     * @param array<int, array<string, mixed>> $line_items
     * @return array<int, array<string, mixed>>
     */
    private function hydrate_missing_descriptions(array $line_items): array
    {
        $catalog_ids = [];
        foreach ($line_items as $line_item) {
            $description = isset($line_item['description']) ? trim((string) $line_item['description']) : '';
            $catalog_id = isset($line_item['pricing_item_id']) ? (int) $line_item['pricing_item_id'] : 0;
            if ($description === '' && $catalog_id > 0) {
                $catalog_ids[$catalog_id] = true;
            }
        }

        if (empty($catalog_ids)) {
            return $line_items;
        }

        $catalog_descriptions = [];
        foreach (array_keys($catalog_ids) as $catalog_id) {
            $catalog_item = $this->repository->get_item_by_id((int) $catalog_id);
            if (!is_array($catalog_item)) {
                continue;
            }

            $description = isset($catalog_item['description']) ? trim((string) $catalog_item['description']) : '';
            if ($description !== '') {
                $catalog_descriptions[(int) $catalog_id] = $description;
            }
        }

        if (empty($catalog_descriptions)) {
            return $line_items;
        }

        foreach ($line_items as &$line_item) {
            $description = isset($line_item['description']) ? trim((string) $line_item['description']) : '';
            $catalog_id = isset($line_item['pricing_item_id']) ? (int) $line_item['pricing_item_id'] : 0;

            if ($description === '' && $catalog_id > 0 && isset($catalog_descriptions[$catalog_id])) {
                $line_item['description'] = $catalog_descriptions[$catalog_id];
            }
        }
        unset($line_item);

        return $line_items;
    }

    /**
     * @param array<int, array<string, mixed>> $group_line_items
     * @return array{rows: array<int, array<string, mixed>>, subtotal: float}
     */
    private function build_group_rows(array $group_line_items, string $selection_mode): array
    {
        $rows = [];
        $subtotal = 0.0;
        $single_selection_consumed = false;

        foreach ($group_line_items as $index => $line_item) {
            $quantity = $this->to_float($line_item['quantity'] ?? 0);
            $resolved_price = $this->to_float($line_item['resolved_price'] ?? 0);
            $line_total = round($quantity * $resolved_price, 2);
            $is_selected = isset($line_item['is_selected']) ? (int) $line_item['is_selected'] === 1 : true;
            $included = false;

            if ($selection_mode === 'all') {
                $included = true;
            } elseif ($selection_mode === 'multi') {
                $included = $is_selected;
            } elseif ($selection_mode === 'single') {
                $included = $is_selected && !$single_selection_consumed;
                if ($included) {
                    $single_selection_consumed = true;
                }
            }

            if ($included) {
                $subtotal = round($subtotal + $line_total, 2);
            }

            $rows[] = [
                'index' => (int) $index + 1,
                'item' => $line_item,
                'quantity' => $quantity,
                'resolved_price' => $resolved_price,
                'line_total' => $line_total,
                'included' => $included,
                'is_selected' => $is_selected,
            ];
        }

        return [
            'rows' => $rows,
            'subtotal' => $subtotal,
        ];
    }

    /**
     * Normalize unknown modes to "all".
     */
    private function normalize_selection_mode(string $selection_mode): string
    {
        $selection_mode = strtolower(trim($selection_mode));
        return in_array($selection_mode, ['all', 'multi', 'single'], true) ? $selection_mode : 'all';
    }

    private function get_quote_format(int $post_id): string
    {
        $format = (string) get_post_meta($post_id, self::META_QUOTE_FORMAT, true);
        if ($format === '') {
            $format = self::DEFAULT_QUOTE_FORMAT;
        }

        $format = strtolower(trim($format));
        return in_array($format, ['standard', 'insurance'], true) ? $format : self::DEFAULT_QUOTE_FORMAT;
    }

    /**
     * @param array<int, array<string, mixed>> $line_items
     * @return array<int, array{category: string}>
     */
    private function get_catalog_meta_by_item_id(array $line_items): array
    {
        $item_ids = [];
        foreach ($line_items as $line_item) {
            $item_id = isset($line_item['pricing_item_id']) ? (int) $line_item['pricing_item_id'] : 0;
            if ($item_id > 0) {
                $item_ids[$item_id] = true;
            }
        }

        if (empty($item_ids)) {
            return [];
        }

        $meta_map = [];
        foreach (array_keys($item_ids) as $item_id) {
            $catalog_item = $this->repository->get_item_by_id((int) $item_id);
            if (!is_array($catalog_item)) {
                continue;
            }

            $meta_map[(int) $item_id] = [
                'category' => isset($catalog_item['category']) ? trim((string) $catalog_item['category']) : '',
            ];
        }

        return $meta_map;
    }

    private function is_discount_item(array $line_item, float $resolved_price, float $line_total): bool
    {
        $item_type = isset($line_item['item_type']) ? strtolower(trim((string) $line_item['item_type'])) : '';
        return $item_type === 'discount' || $resolved_price < 0 || $line_total < 0;
    }

    private function to_float($value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function format_currency(float $amount): string
    {
        $sign = $amount < 0 ? '-' : '';
        $value = number_format_i18n(abs($amount), 2);
        return $sign . '$' . $value;
    }

    private function format_quantity(float $quantity): string
    {
        return number_format_i18n($quantity, 2);
    }
}

$GLOBALS['tah_quote_pricing_frontend'] = new TAH_Quote_Pricing_Frontend();

function tah_render_quote_pricing($post_id)
{
    $renderer = $GLOBALS['tah_quote_pricing_frontend'] ?? null;
    if (!$renderer instanceof TAH_Quote_Pricing_Frontend) {
        return;
    }

    $renderer->render((int) $post_id);
}
