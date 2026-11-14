<?php

// The license's fields from POST as softwareFormSet() takes them - on an update,
// what isn't sent stays as the license has it ($software_row)

$software_row = $software_row ?? null;

$software_fields = [
    'software_name' => 'name',
    'software_version' => 'version',
    'software_description' => 'description',
    'software_type' => 'type',
    'software_license_type' => 'license_type',
    'software_key' => 'key',
    'software_seats' => 'seats',
    'software_purchase_reference' => 'purchase_reference',
    'software_purchase' => 'purchase',
    'software_expire' => 'expire',
    'software_notes' => 'notes',
    'software_favorite' => 'favorite',
    'software_vendor_id' => 'vendor',
];

$software_post = [];
foreach ($software_fields as $api_field => $key) {
    if (array_key_exists($api_field, $_POST)) {
        $software_post[$key] = is_scalar($_POST[$api_field]) ? (string) $_POST[$api_field] : '';
    } elseif ($software_row) {
        $software_post[$key] = (string) ($software_row[$api_field] ?? '');
    }
}
