<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create/update pricing module custom tables.
 */
function tah_pricing_create_tables()
{
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();
    $pricing_items_table = $wpdb->prefix . 'tah_pricing_items';
    $quote_groups_table = $wpdb->prefix . 'tah_quote_groups';
    $quote_line_items_table = $wpdb->prefix . 'tah_quote_line_items';

    $pricing_items_sql = "CREATE TABLE {$pricing_items_table} (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
sku varchar(100) NOT NULL,
title varchar(255) NOT NULL,
description text NOT NULL,
unit_type varchar(50) NOT NULL,
unit_price decimal(10,2) NOT NULL DEFAULT 0.00,
trade_id bigint(20) unsigned DEFAULT NULL,
category varchar(100) DEFAULT NULL,
sort_order int(10) unsigned NOT NULL DEFAULT 0,
is_active tinyint(1) NOT NULL DEFAULT 1,
catalog_type varchar(20) NOT NULL DEFAULT 'standard',
price_history json NOT NULL,
created_at datetime NOT NULL,
updated_at datetime NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY sku (sku),
KEY catalog_trade_active (catalog_type,trade_id,is_active)
) {$charset_collate};";

    $quote_groups_sql = "CREATE TABLE {$quote_groups_table} (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
quote_id bigint(20) unsigned NOT NULL,
name varchar(255) NOT NULL,
description text DEFAULT NULL,
selection_mode varchar(20) NOT NULL,
show_subtotal tinyint(1) NOT NULL DEFAULT 1,
is_collapsed tinyint(1) NOT NULL DEFAULT 0,
sort_order int(10) unsigned NOT NULL DEFAULT 0,
PRIMARY KEY  (id),
KEY quote_id (quote_id)
) {$charset_collate};";

    $quote_line_items_sql = "CREATE TABLE {$quote_line_items_table} (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
quote_id bigint(20) unsigned NOT NULL,
group_id bigint(20) unsigned NOT NULL,
pricing_item_id bigint(20) unsigned DEFAULT NULL,
item_type varchar(20) NOT NULL DEFAULT 'standard',
title varchar(255) NOT NULL,
description text DEFAULT NULL,
quantity decimal(10,2) NOT NULL,
unit_type varchar(50) NOT NULL,
price_mode varchar(20) NOT NULL,
price_modifier decimal(10,2) NOT NULL,
resolved_price decimal(10,2) NOT NULL,
previous_resolved_price decimal(10,2) DEFAULT NULL,
is_selected tinyint(1) NOT NULL DEFAULT 1,
sort_order int(10) unsigned NOT NULL DEFAULT 0,
material_cost decimal(10,2) DEFAULT NULL,
labor_cost decimal(10,2) DEFAULT NULL,
line_sku varchar(100) DEFAULT NULL,
tax_rate decimal(5,4) DEFAULT NULL,
note text DEFAULT NULL,
PRIMARY KEY  (id),
KEY quote_id (quote_id),
KEY group_id (group_id),
KEY pricing_item_id (pricing_item_id)
) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    dbDelta($pricing_items_sql);
    dbDelta($quote_groups_sql);
    dbDelta($quote_line_items_sql);
}
