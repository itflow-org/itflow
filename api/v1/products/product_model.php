<?php

// Map product API field names to the input keys used by productSave().
$field_names = [
    'product_name' => 'name',
    'product_type' => 'type',
    'product_category_id' => 'category',
    'product_price' => 'price',
    'product_cost' => 'cost',
    'product_tax_id' => 'tax',
    'product_frequency' => 'frequency',
    'product_seat_source' => 'seat_source',
    'product_code' => 'code',
    'product_vendor_id' => 'vendor',
    'product_vendor_part' => 'vendor_part',
    'product_reorder_level' => 'reorder_level',
    'product_reorder_qty' => 'reorder_qty',
    'product_location' => 'location',
    'product_asset_type' => 'asset_type',
    'product_make' => 'make',
    'product_model' => 'model',
    'product_warranty_months' => 'warranty_months',
    'product_description' => 'description',
    'product_notes' => 'notes',
    'product_track_stock' => 'track_stock',
];

$product_input = [];
$product_model_error = '';

foreach ($field_names as $api_field => $key) {
    if (!array_key_exists($api_field, $_POST)) {
        continue;
    }
    if (!is_scalar($_POST[$api_field]) && $_POST[$api_field] !== null) {
        $product_model_error = "The $api_field field must be a scalar value.";
        break;
    }

    $value = (string) ($_POST[$api_field] ?? '');
    if ($key === 'track_stock') {
        $value = in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on', 'tracked'], true);
    }
    $product_input[$key] = $value;
}

// The product form posts the stock switch with the type, including when it is off.
if (array_key_exists('type', $product_input) && !array_key_exists('track_stock', $product_input)) {
    $product_input['track_stock'] = false;
}
