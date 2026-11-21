<?php

// A rack's fields from POST, checked as the rack form checks them, each only when
// sent, into $rack_set (column => SQL value). On an update $rack is the rack.
// $client_id is its client.

if (!function_exists('apiDeny')) {
    http_response_code(404);
    exit();
}

$rack = $rack ?? null;
$rack_set = [];
$text = function ($field) {
    return trim((string) (is_scalar($_POST[$field] ?? '') ? ($_POST[$field] ?? '') : ''));
};

if (array_key_exists('rack_name', $_POST)) {
    if ($text('rack_name') === '') {
        $rack_refuse('A rack needs a name - rack_name.');
    }
    $rack_set['rack_name'] = "'" . escapeSql(mb_substr($text('rack_name'), 0, 200)) . "'";
}
if (array_key_exists('rack_type', $_POST)) {
    $type = categoryPickedName('rack_type', $text('rack_type'), (string) ($rack['rack_type'] ?? ''));
    if ($type === null || $type === '') {
        $rack_refuse('rack_type is one of: ' . implode(', ', array_keys(categoryPicker('rack_type'))) . '.');
    }
    $rack_set['rack_type'] = "'" . escapeSql(mb_substr($type, 0, 50)) . "'";
}
if (array_key_exists('rack_units', $_POST)) {
    $units = intval($_POST['rack_units']);
    if ($units < 1 || $units > 70) {
        $rack_refuse('rack_units is how tall it is - 1 to 70 units.');
    }
    $rack_set['rack_units'] = $units;
}
if (array_key_exists('rack_units_descending', $_POST)) {
    $rack_set['rack_units_descending'] = intval($_POST['rack_units_descending']) === 1 ? 1 : 0;
}
if (array_key_exists('rack_power_capacity', $_POST)) {
    $watts = intval($_POST['rack_power_capacity']);
    $rack_set['rack_power_capacity'] = $watts > 0 ? $watts : 'NULL';
}
if (array_key_exists('rack_location_id', $_POST)) {
    $location = intval($_POST['rack_location_id']);
    if ($location && !mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM locations WHERE location_id = $location AND location_client_id = " . intval($client_id)))) {
        $rack_refuse("rack_location_id isn't one of the client's locations.");
    }
    $rack_set['rack_location_id'] = $location;
}
foreach (['rack_model' => 200, 'rack_depth' => 50, 'rack_physical_location' => 200, 'rack_description' => 0, 'rack_notes' => 0] as $field => $length) {
    if (array_key_exists($field, $_POST)) {
        $rack_set[$field] = "'" . escapeSql($length ? mb_substr($text($field), 0, $length) : $text($field)) . "'";
    }
}
