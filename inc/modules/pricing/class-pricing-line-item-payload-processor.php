<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Builds normalized quote line-item rows from pricing payload input.
 */
final class TAH_Pricing_Line_Item_Payload_Processor
{
    /**
     * @var TAH_Pricing_Repository
     */
    private $repository;

    public function __construct(TAH_Pricing_Repository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @param array<int, mixed> $items_raw
     * @param array<string, int> $group_key_map
     * @return array<int, array<string, mixed>>
     */
    public function build_line_item_rows(
        array $items_raw,
        array $group_key_map,
        string $quote_format,
        float $rounding,
        string $rounding_direction
    ): array {
        $line_item_rows = [];
        $catalog_price_cache = [];
        $first_group_id = !empty($group_key_map) ? (int) reset($group_key_map) : 0;

        foreach ($items_raw as $index => $item_data) {
            if (!is_array($item_data)) {
                continue;
            }

            $group_key = isset($item_data['group_key']) ? sanitize_key((string) $item_data['group_key']) : '';
            $group_id = isset($group_key_map[$group_key]) ? (int) $group_key_map[$group_key] : 0;
            if ($quote_format === 'insurance' && $group_id <= 0 && $first_group_id > 0) {
                $group_id = $first_group_id;
            }
            if ($group_id <= 0) {
                continue;
            }

            $title = isset($item_data['title']) ? sanitize_text_field((string) $item_data['title']) : '';
            if ($title === '') {
                continue;
            }

            $description = isset($item_data['description']) ? sanitize_textarea_field((string) $item_data['description']) : '';

            $quantity = isset($item_data['quantity']) && is_numeric($item_data['quantity'])
                ? round((float) $item_data['quantity'], 2)
                : 0.0;
            if ($quantity <= 0) {
                $quantity = 1.0;
            }

            $pricing_item_id = isset($item_data['pricing_item_id']) && (int) $item_data['pricing_item_id'] > 0
                ? (int) $item_data['pricing_item_id']
                : null;

            $catalog_price = 0.0;
            if ($pricing_item_id !== null) {
                if (!array_key_exists($pricing_item_id, $catalog_price_cache)) {
                    $catalog_item = $this->repository->get_item_by_id($pricing_item_id);
                    $catalog_price_cache[$pricing_item_id] = is_array($catalog_item) && isset($catalog_item['unit_price'])
                        ? (float) $catalog_item['unit_price']
                        : 0.0;
                }
                $catalog_price = (float) $catalog_price_cache[$pricing_item_id];
            }

            $material_cost = isset($item_data['material_cost']) && is_numeric($item_data['material_cost'])
                ? round((float) $item_data['material_cost'], 2)
                : null;
            $labor_cost = isset($item_data['labor_cost']) && is_numeric($item_data['labor_cost'])
                ? round((float) $item_data['labor_cost'], 2)
                : null;

            if ($quote_format === 'insurance') {
                $material = $material_cost !== null ? $material_cost : 0.0;
                $labor = $labor_cost !== null ? $labor_cost : 0.0;
                $resolved_price = round($material + $labor, 2);
                $formula = [
                    'mode' => TAH_Price_Formula::MODE_OVERRIDE,
                    'modifier' => $resolved_price,
                ];
            } else {
                $rate_formula = isset($item_data['rate_formula']) ? trim((string) $item_data['rate_formula']) : '';
                $formula = TAH_Price_Formula::parse($rate_formula);
                $resolved_price = TAH_Price_Formula::resolve(
                    (string) $formula['mode'],
                    (float) $formula['modifier'],
                    $catalog_price,
                    $rounding,
                    $rounding_direction
                );
            }

            $line_sku = isset($item_data['line_sku']) ? sanitize_text_field((string) $item_data['line_sku']) : null;
            $line_tax_rate = isset($item_data['tax_rate']) && is_numeric($item_data['tax_rate'])
                ? round((float) $item_data['tax_rate'], 4)
                : null;
            $line_note = $quote_format === 'insurance'
                ? $description
                : (isset($item_data['note']) ? sanitize_textarea_field((string) $item_data['note']) : null);

            $line_item_rows[] = [
                'id' => isset($item_data['id']) ? max(0, (int) $item_data['id']) : 0,
                'group_id' => $group_id,
                'pricing_item_id' => $pricing_item_id,
                'item_type' => $this->normalize_item_type(
                    isset($item_data['item_type']) ? (string) $item_data['item_type'] : 'standard',
                    $resolved_price
                ),
                'title' => $title,
                'description' => $description,
                'quantity' => $quantity,
                'unit_type' => isset($item_data['unit_type']) ? sanitize_text_field((string) $item_data['unit_type']) : 'flat',
                'price_mode' => $formula['mode'],
                'price_modifier' => round((float) $formula['modifier'], 2),
                'resolved_price' => round((float) $resolved_price, 2),
                'previous_resolved_price' => isset($item_data['previous_resolved_price']) && is_numeric($item_data['previous_resolved_price'])
                    ? round((float) $item_data['previous_resolved_price'], 2)
                    : null,
                'is_selected' => !empty($item_data['is_selected']),
                'sort_order' => isset($item_data['sort_order'])
                    ? max(0, (int) $item_data['sort_order'])
                    : max(0, (int) $index),
                'material_cost' => $material_cost,
                'labor_cost' => $labor_cost,
                'line_sku' => $line_sku,
                'tax_rate' => $line_tax_rate,
                'note' => $line_note,
            ];
        }

        return $line_item_rows;
    }

    private function normalize_item_type(string $item_type, float $resolved_price): string
    {
        $item_type = strtolower(trim($item_type));
        if (!in_array($item_type, ['standard', 'discount'], true)) {
            $item_type = 'standard';
        }

        if ($item_type === 'standard' && $resolved_price < 0) {
            return 'discount';
        }

        return $item_type;
    }
}
