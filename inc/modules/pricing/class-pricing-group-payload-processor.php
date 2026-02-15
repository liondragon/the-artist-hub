<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Builds normalized quote-group rows from pricing payload input.
 */
final class TAH_Pricing_Group_Payload_Processor
{
    /**
     * @param array<int, mixed> $groups_raw
     * @return array<int, mixed>
     */
    public function normalize_groups_for_quote_format(string $quote_format, array $groups_raw): array
    {
        if ($quote_format === 'insurance' && empty($groups_raw)) {
            return [[
                'id' => 0,
                'client_key' => 'group-insurance',
                'name' => 'Insurance Items',
                'description' => '',
                'selection_mode' => 'all',
                'show_subtotal' => false,
                'is_collapsed' => false,
                'sort_order' => 0,
            ]];
        }

        if ($quote_format === 'insurance' && !empty($groups_raw)) {
            return [reset($groups_raw)];
        }

        return $groups_raw;
    }

    /**
     * @param array<int, mixed> $groups_raw
     * @return array{group_rows: array<int, array<string, mixed>>, group_keys: array<int, string>}
     */
    public function build_group_rows_with_keys(string $quote_format, array $groups_raw): array
    {
        $group_rows = [];
        $group_keys = [];

        foreach ($groups_raw as $index => $group_data) {
            if (!is_array($group_data)) {
                continue;
            }

            $client_key = isset($group_data['client_key']) ? sanitize_key((string) $group_data['client_key']) : '';
            if ($client_key === '') {
                $client_key = 'group-temp-' . $index;
            }

            $group_rows[] = [
                'id' => isset($group_data['id']) ? max(0, (int) $group_data['id']) : 0,
                'name' => $quote_format === 'insurance'
                    ? 'Insurance Items'
                    : (isset($group_data['name']) ? sanitize_text_field((string) $group_data['name']) : ''),
                'description' => isset($group_data['description']) ? sanitize_textarea_field((string) $group_data['description']) : '',
                'selection_mode' => $quote_format === 'insurance'
                    ? 'all'
                    : $this->normalize_selection_mode(isset($group_data['selection_mode']) ? (string) $group_data['selection_mode'] : 'all'),
                'show_subtotal' => $quote_format === 'insurance'
                    ? false
                    : !empty($group_data['show_subtotal']),
                'is_collapsed' => !empty($group_data['is_collapsed']),
                'sort_order' => max(0, (int) $index),
            ];
            $group_keys[] = $client_key;
        }

        return [
            'group_rows' => $group_rows,
            'group_keys' => $group_keys,
        ];
    }

    /**
     * @param array<int, string> $group_keys
     * @param array<int, int> $persisted_group_ids
     * @return array<string, int>
     */
    public function build_group_key_map(array $group_keys, array $persisted_group_ids): array
    {
        $group_key_map = [];
        foreach ($group_keys as $index => $client_key) {
            $group_id = isset($persisted_group_ids[$index]) ? (int) $persisted_group_ids[$index] : 0;
            if ($group_id > 0) {
                $group_key_map[$client_key] = $group_id;
            }
        }

        return $group_key_map;
    }

    private function normalize_selection_mode(string $selection_mode): string
    {
        $selection_mode = strtolower(trim($selection_mode));
        return in_array($selection_mode, ['all', 'multi', 'single'], true) ? $selection_mode : 'all';
    }
}
