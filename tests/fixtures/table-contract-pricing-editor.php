<?php

$column_contract = class_exists('TAH_Quote_Edit_Screen')
    ? TAH_Quote_Edit_Screen::get_pricing_editor_column_contract()
    : [];
$column_order = array_keys($column_contract);

$non_orderable = [];
$non_resizable = [];
foreach ($column_contract as $key => $column) {
    if (!is_string($key) || $key === '' || !is_array($column)) {
        continue;
    }

    $is_locked = !empty($column['locked']);
    if ($is_locked || (isset($column['orderable']) && $column['orderable'] === false)) {
        $non_orderable[] = $key;
    }
    if ($is_locked || (isset($column['resizable']) && $column['resizable'] === false)) {
        $non_resizable[] = $key;
    }
}

return [
    'table_key' => class_exists('TAH_Quote_Edit_Screen')
        ? TAH_Quote_Edit_Screen::PRICING_EDITOR_TABLE_KEY
        : 'pricing_editor',
    'screen_id' => class_exists('TAH_Quote_Edit_Screen')
        ? TAH_Quote_Edit_Screen::PRICING_EDITOR_CONTEXT_SCREEN_ID
        : 'tah-quote-editor',
    'variant_attr' => 'data-tah-variant',
    'row_selector' => 'tbody tr.tah-line-item-row',
    'show_reset' => false,
    'filler_column_key' => 'description',
    'column_order' => $column_order,
    'non_orderable' => $non_orderable,
    'non_resizable' => $non_resizable,
];
