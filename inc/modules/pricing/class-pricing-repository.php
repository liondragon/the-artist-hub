<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Data access layer for pricing module tables.
 */
final class TAH_Pricing_Repository
{
    /**
     * WordPress DB adapter.
     *
     * @var wpdb
     */
    private $wpdb;

    /**
     * Fully qualified table names.
     *
     * @var array<string, string>
     */
    private $tables;

    /**
     * @param wpdb|null $wpdb_instance Optional wpdb injection for testing.
     */
    public function __construct($wpdb_instance = null)
    {
        global $wpdb;

        $this->wpdb = $wpdb_instance ?: $wpdb;
        $this->tables = [
            'pricing_items' => $this->wpdb->prefix . 'tah_pricing_items',
            'quote_groups' => $this->wpdb->prefix . 'tah_quote_groups',
            'quote_line_items' => $this->wpdb->prefix . 'tah_quote_line_items',
        ];
    }

    /**
     * Fetch catalog items with optional filters.
     *
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function get_catalog_items(array $filters = [])
    {
        $sql = "SELECT * FROM {$this->tables['pricing_items']} WHERE 1=1";
        $args = [];

        if (isset($filters['catalog_type']) && $filters['catalog_type'] !== '') {
            $sql .= ' AND catalog_type = %s';
            $args[] = (string) $filters['catalog_type'];
        }

        if (array_key_exists('trade_id', $filters)) {
            if ($filters['trade_id'] === null) {
                $sql .= ' AND trade_id IS NULL';
            } else {
                $sql .= ' AND trade_id = %d';
                $args[] = (int) $filters['trade_id'];
            }
        }

        if (array_key_exists('is_active', $filters)) {
            $sql .= ' AND is_active = %d';
            $args[] = (int) ((bool) $filters['is_active']);
        }

        if (!empty($filters['search'])) {
            $sql .= ' AND title LIKE %s';
            $args[] = '%' . $this->wpdb->esc_like((string) $filters['search']) . '%';
        }

        $sql .= ' ORDER BY sort_order ASC, title ASC, id ASC';

        if (!empty($filters['limit'])) {
            $sql .= ' LIMIT %d';
            $args[] = max(1, (int) $filters['limit']);
        }

        if (!empty($filters['offset'])) {
            $sql .= ' OFFSET %d';
            $args[] = max(0, (int) $filters['offset']);
        }

        if (!empty($args)) {
            $sql = $this->wpdb->prepare($sql, $args);
        }

        $rows = $this->wpdb->get_results($sql, ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        foreach ($rows as &$row) {
            if (isset($row['price_history']) && is_string($row['price_history']) && $row['price_history'] !== '') {
                $decoded = json_decode($row['price_history'], true);
                $row['price_history'] = is_array($decoded) ? $decoded : [];
            } else {
                $row['price_history'] = [];
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * Fetch a catalog item by SKU.
     *
     * @return array<string, mixed>|null
     */
    public function get_item_by_sku(string $sku)
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tables['pricing_items']} WHERE sku = %s LIMIT 1",
            $sku
        );

        $row = $this->wpdb->get_row($sql, ARRAY_A);
        if (!is_array($row)) {
            return null;
        }

        if (isset($row['price_history']) && is_string($row['price_history']) && $row['price_history'] !== '') {
            $decoded = json_decode($row['price_history'], true);
            $row['price_history'] = is_array($decoded) ? $decoded : [];
        } else {
            $row['price_history'] = [];
        }

        return $row;
    }

    /**
     * Fetch a catalog item by ID.
     *
     * @return array<string, mixed>|null
     */
    public function get_item_by_id(int $item_id)
    {
        if ($item_id <= 0) {
            return null;
        }

        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tables['pricing_items']} WHERE id = %d LIMIT 1",
            $item_id
        );

        $row = $this->wpdb->get_row($sql, ARRAY_A);
        if (!is_array($row)) {
            return null;
        }

        if (isset($row['price_history']) && is_string($row['price_history']) && $row['price_history'] !== '') {
            $decoded = json_decode($row['price_history'], true);
            $row['price_history'] = is_array($decoded) ? $decoded : [];
        } else {
            $row['price_history'] = [];
        }

        return $row;
    }

    /**
     * Insert a catalog item.
     */
    public function insert_item(array $data)
    {
        $now = current_time('mysql');
        $row = [
            'sku' => isset($data['sku']) ? (string) $data['sku'] : '',
            'title' => isset($data['title']) ? (string) $data['title'] : '',
            'description' => isset($data['description']) ? (string) $data['description'] : '',
            'unit_type' => isset($data['unit_type']) ? (string) $data['unit_type'] : '',
            'unit_price' => isset($data['unit_price']) ? (float) $data['unit_price'] : 0.0,
            'trade_id' => array_key_exists('trade_id', $data) && $data['trade_id'] !== null ? (int) $data['trade_id'] : null,
            'category' => array_key_exists('category', $data) ? $data['category'] : null,
            'sort_order' => isset($data['sort_order']) ? (int) $data['sort_order'] : 0,
            'is_active' => isset($data['is_active']) ? (int) ((bool) $data['is_active']) : 1,
            'catalog_type' => isset($data['catalog_type']) ? (string) $data['catalog_type'] : 'standard',
            'price_history' => wp_json_encode(isset($data['price_history']) ? $data['price_history'] : []),
            'created_at' => isset($data['created_at']) ? (string) $data['created_at'] : $now,
            'updated_at' => isset($data['updated_at']) ? (string) $data['updated_at'] : $now,
        ];

        $formats = [
            '%s', '%s', '%s', '%s', '%f', '%d', '%s', '%d', '%d', '%s', '%s', '%s', '%s',
        ];

        $inserted = $this->wpdb->insert($this->tables['pricing_items'], $row, $formats);
        if ($inserted === false) {
            return 0;
        }

        return (int) $this->wpdb->insert_id;
    }

    /**
     * Update a catalog item.
     */
    public function update_item(int $item_id, array $data)
    {
        if ($item_id <= 0) {
            return false;
        }

        $update = [];
        $formats = [];

        $allowed_fields = [
            'sku' => '%s',
            'title' => '%s',
            'description' => '%s',
            'unit_type' => '%s',
            'unit_price' => '%f',
            'trade_id' => '%d',
            'category' => '%s',
            'sort_order' => '%d',
            'is_active' => '%d',
            'catalog_type' => '%s',
            'price_history' => '%s',
            'created_at' => '%s',
            'updated_at' => '%s',
        ];

        foreach ($allowed_fields as $field => $format) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            if ($field === 'price_history') {
                $update[$field] = wp_json_encode($data[$field]);
            } else {
                $update[$field] = $data[$field];
            }

            $formats[] = $format;
        }

        $update['updated_at'] = current_time('mysql');
        $formats[] = '%s';

        $updated = $this->wpdb->update(
            $this->tables['pricing_items'],
            $update,
            ['id' => $item_id],
            $formats,
            ['%d']
        );

        return $updated !== false;
    }

    /**
     * Soft-delete a catalog item.
     */
    public function deactivate_item(int $item_id)
    {
        return $this->update_item($item_id, ['is_active' => 0]);
    }

    /**
     * Count active quotes affected by a catalog pricing item.
     *
     * @param int $pricing_item_id
     * @return int
     */
    public function count_active_quotes_by_pricing_item($pricing_item_id)
    {
        $pricing_item_id = (int) $pricing_item_id;
        if ($pricing_item_id <= 0) {
            return 0;
        }

        $sql = $this->wpdb->prepare(
            "SELECT COUNT(DISTINCT li.quote_id)
            FROM {$this->tables['quote_line_items']} li
            INNER JOIN {$this->wpdb->posts} p ON p.ID = li.quote_id
            WHERE li.pricing_item_id = %d
            AND p.post_type = %s
            AND p.post_status NOT IN ('trash', 'auto-draft', 'inherit')",
            $pricing_item_id,
            'quotes'
        );

        $count = $this->wpdb->get_var($sql);
        return is_numeric($count) ? (int) $count : 0;
    }

    /**
     * Fetch all quote groups for a quote.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get_quote_groups(int $quote_id)
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tables['quote_groups']} WHERE quote_id = %d ORDER BY sort_order ASC, id ASC",
            $quote_id
        );

        $rows = $this->wpdb->get_results($sql, ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    /**
     * Fetch line items for a quote, optionally filtered by group.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get_quote_line_items(int $quote_id, $group_id = null)
    {
        $sql = "SELECT * FROM {$this->tables['quote_line_items']} WHERE quote_id = %d";
        $args = [$quote_id];

        if ($group_id !== null) {
            $sql .= ' AND group_id = %d';
            $args[] = (int) $group_id;
        }

        $sql .= ' ORDER BY sort_order ASC, id ASC';
        $prepared = $this->wpdb->prepare($sql, $args);

        $rows = $this->wpdb->get_results($prepared, ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    /**
     * Upsert quote groups and delete missing rows.
     *
     * @param array<int, array<string, mixed>> $groups
     * @return array<int, int> Persisted group IDs in input order.
     */
    public function save_quote_groups(int $quote_id, array $groups)
    {
        if ($quote_id <= 0) {
            return [];
        }

        $this->wpdb->query('START TRANSACTION');

        $persisted_ids = [];
        foreach ($groups as $index => $group) {
            $group_id = isset($group['id']) ? (int) $group['id'] : 0;
            $row = [
                'quote_id' => $quote_id,
                'name' => isset($group['name']) ? (string) $group['name'] : '',
                'description' => array_key_exists('description', $group) ? $group['description'] : null,
                'selection_mode' => isset($group['selection_mode']) ? (string) $group['selection_mode'] : 'all',
                'show_subtotal' => isset($group['show_subtotal']) ? (int) ((bool) $group['show_subtotal']) : 1,
                'is_collapsed' => isset($group['is_collapsed']) ? (int) ((bool) $group['is_collapsed']) : 0,
                'sort_order' => isset($group['sort_order']) ? (int) $group['sort_order'] : (int) $index,
            ];

            $formats = ['%d', '%s', '%s', '%s', '%d', '%d', '%d'];

            if ($group_id > 0) {
                $updated = $this->wpdb->update(
                    $this->tables['quote_groups'],
                    $row,
                    ['id' => $group_id, 'quote_id' => $quote_id],
                    $formats,
                    ['%d', '%d']
                );

                if ($updated === false) {
                    $this->wpdb->query('ROLLBACK');
                    return [];
                }

                $persisted_ids[] = $group_id;
                continue;
            }

            $inserted = $this->wpdb->insert($this->tables['quote_groups'], $row, $formats);
            if ($inserted === false) {
                $this->wpdb->query('ROLLBACK');
                return [];
            }

            $persisted_ids[] = (int) $this->wpdb->insert_id;
        }

        $existing_ids = $this->wpdb->get_col(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->tables['quote_groups']} WHERE quote_id = %d",
                $quote_id
            )
        );
        $existing_ids = array_map('intval', is_array($existing_ids) ? $existing_ids : []);

        $stale_ids = array_values(array_diff($existing_ids, $persisted_ids));
        if (!empty($stale_ids)) {
            $this->delete_line_items_by_group_ids($stale_ids);
            $deleted = $this->delete_groups_by_ids($stale_ids, $quote_id);
            if ($deleted === false) {
                $this->wpdb->query('ROLLBACK');
                return [];
            }
        }

        $this->wpdb->query('COMMIT');
        return $persisted_ids;
    }

    /**
     * Upsert quote line items and delete missing rows.
     *
     * @param array<int, array<string, mixed>> $line_items
     * @return array<int, int> Persisted line item IDs in input order.
     */
    public function save_quote_line_items(int $quote_id, array $line_items)
    {
        if ($quote_id <= 0) {
            return [];
        }

        $this->wpdb->query('START TRANSACTION');

        $persisted_ids = [];
        foreach ($line_items as $index => $item) {
            $item_id = isset($item['id']) ? (int) $item['id'] : 0;
            $row = [
                'quote_id' => $quote_id,
                'group_id' => isset($item['group_id']) ? (int) $item['group_id'] : 0,
                'pricing_item_id' => array_key_exists('pricing_item_id', $item) && $item['pricing_item_id'] !== null ? (int) $item['pricing_item_id'] : null,
                'item_type' => isset($item['item_type']) ? (string) $item['item_type'] : 'standard',
                'title' => isset($item['title']) ? (string) $item['title'] : '',
                'description' => array_key_exists('description', $item) ? $item['description'] : null,
                'quantity' => isset($item['quantity']) ? (float) $item['quantity'] : 0.0,
                'unit_type' => isset($item['unit_type']) ? (string) $item['unit_type'] : '',
                'price_mode' => isset($item['price_mode']) ? (string) $item['price_mode'] : 'default',
                'price_modifier' => isset($item['price_modifier']) ? (float) $item['price_modifier'] : 0.0,
                'resolved_price' => isset($item['resolved_price']) ? (float) $item['resolved_price'] : 0.0,
                'previous_resolved_price' => array_key_exists('previous_resolved_price', $item) && $item['previous_resolved_price'] !== null ? (float) $item['previous_resolved_price'] : null,
                'is_selected' => isset($item['is_selected']) ? (int) ((bool) $item['is_selected']) : 1,
                'sort_order' => isset($item['sort_order']) ? (int) $item['sort_order'] : (int) $index,
                'material_cost' => array_key_exists('material_cost', $item) && $item['material_cost'] !== null ? (float) $item['material_cost'] : null,
                'labor_cost' => array_key_exists('labor_cost', $item) && $item['labor_cost'] !== null ? (float) $item['labor_cost'] : null,
                'line_sku' => array_key_exists('line_sku', $item) ? $item['line_sku'] : null,
                'tax_rate' => array_key_exists('tax_rate', $item) && $item['tax_rate'] !== null ? (float) $item['tax_rate'] : null,
                'note' => array_key_exists('note', $item) ? $item['note'] : null,
            ];

            $formats = [
                '%d', '%d', '%d', '%s', '%s', '%s', '%f', '%s', '%s', '%f',
                '%f', '%f', '%d', '%d', '%f', '%f', '%s', '%f', '%s',
            ];

            if ($item_id > 0) {
                $updated = $this->wpdb->update(
                    $this->tables['quote_line_items'],
                    $row,
                    ['id' => $item_id, 'quote_id' => $quote_id],
                    $formats,
                    ['%d', '%d']
                );

                if ($updated === false) {
                    $this->wpdb->query('ROLLBACK');
                    return [];
                }

                $persisted_ids[] = $item_id;
                continue;
            }

            $inserted = $this->wpdb->insert($this->tables['quote_line_items'], $row, $formats);
            if ($inserted === false) {
                $this->wpdb->query('ROLLBACK');
                return [];
            }

            $persisted_ids[] = (int) $this->wpdb->insert_id;
        }

        $existing_ids = $this->wpdb->get_col(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->tables['quote_line_items']} WHERE quote_id = %d",
                $quote_id
            )
        );
        $existing_ids = array_map('intval', is_array($existing_ids) ? $existing_ids : []);
        $stale_ids = array_values(array_diff($existing_ids, $persisted_ids));

        if (!empty($stale_ids)) {
            $deleted = $this->delete_line_items_by_ids($stale_ids, $quote_id);
            if ($deleted === false) {
                $this->wpdb->query('ROLLBACK');
                return [];
            }
        }

        $this->wpdb->query('COMMIT');
        return $persisted_ids;
    }

    /**
     * Delete all pricing groups and line items for a quote.
     */
    public function delete_quote_pricing_data(int $quote_id)
    {
        if ($quote_id <= 0) {
            return false;
        }

        $this->wpdb->query('START TRANSACTION');

        $deleted_items = $this->wpdb->delete(
            $this->tables['quote_line_items'],
            ['quote_id' => $quote_id],
            ['%d']
        );
        if ($deleted_items === false) {
            $this->wpdb->query('ROLLBACK');
            return false;
        }

        $deleted_groups = $this->wpdb->delete(
            $this->tables['quote_groups'],
            ['quote_id' => $quote_id],
            ['%d']
        );
        if ($deleted_groups === false) {
            $this->wpdb->query('ROLLBACK');
            return false;
        }

        $this->wpdb->query('COMMIT');
        return true;
    }

    /**
     * @param array<int, int> $group_ids
     */
    private function delete_line_items_by_group_ids(array $group_ids)
    {
        if (empty($group_ids)) {
            return true;
        }

        $placeholders = implode(', ', array_fill(0, count($group_ids), '%d'));
        $sql = "DELETE FROM {$this->tables['quote_line_items']} WHERE group_id IN ({$placeholders})";
        $prepared = $this->wpdb->prepare($sql, $group_ids);
        return $this->wpdb->query($prepared) !== false;
    }

    /**
     * @param array<int, int> $group_ids
     */
    private function delete_groups_by_ids(array $group_ids, int $quote_id)
    {
        if (empty($group_ids)) {
            return true;
        }

        $placeholders = implode(', ', array_fill(0, count($group_ids), '%d'));
        $sql = "DELETE FROM {$this->tables['quote_groups']} WHERE quote_id = %d AND id IN ({$placeholders})";
        $args = array_merge([$quote_id], $group_ids);
        $prepared = $this->wpdb->prepare($sql, $args);
        return $this->wpdb->query($prepared) !== false;
    }

    /**
     * @param array<int, int> $line_item_ids
     */
    private function delete_line_items_by_ids(array $line_item_ids, int $quote_id)
    {
        if (empty($line_item_ids)) {
            return true;
        }

        $placeholders = implode(', ', array_fill(0, count($line_item_ids), '%d'));
        $sql = "DELETE FROM {$this->tables['quote_line_items']} WHERE quote_id = %d AND id IN ({$placeholders})";
        $args = array_merge([$quote_id], $line_item_ids);
        $prepared = $this->wpdb->prepare($sql, $args);
        return $this->wpdb->query($prepared) !== false;
    }
}
