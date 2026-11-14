<?php

// An item in a rack from POST, checked as the rack item form checks it - with what
// the item has now ($unit) for those not sent, on an update. $rack is its rack and
// $client_id the rack's client. Sets $unit_set (the SET clause) and $unit_label, or
// turns the request down with 400 and why.

if (!function_exists('apiDeny')) {
    http_response_code(404);
    exit();
}

$unit = $unit ?? null;
$unit_id = intval($unit['unit_id'] ?? 0);
$sent = function ($field, $current) {
    return array_key_exists($field, $_POST) ? $_POST[$field] : $current;
};

$asset_id = intval($sent('asset_id', $unit['unit_asset_id'] ?? 0));
$label = trim((string) $sent('unit_device', $unit['unit_device'] ?? ''));
$unit_type = (string) $sent('unit_type', $unit['unit_type'] ?? 'Device');
$start = intval($sent('unit_start_number', $unit['unit_start_number'] ?? 0));
$height = max(1, intval($sent('unit_height', $unit ? intval($unit['unit_end_number']) - intval($unit['unit_start_number']) + 1 : 1)));
$end = $start + $height - 1;
$face = (string) $sent('unit_face', $unit['unit_face'] ?? 'front');
$full_depth = intval($sent('unit_full_depth', $unit['unit_full_depth'] ?? 0)) === 1 ? 1 : 0;
$power_watts = intval($sent('unit_power_watts', $unit['unit_power_watts'] ?? 0));
$notes = mb_substr(trim((string) $sent('unit_notes', $unit['unit_notes'] ?? '')), 0, 255);
$rack_units = intval($rack['rack_units']);

if ($unit_type === '' || !array_key_exists($unit_type, rackItemTypes())) {
    if (array_key_exists('unit_type', $_POST)) {
        $rack_refuse('unit_type is one of: ' . implode(', ', array_keys(rackItemTypes())) . '.');
    }
    $unit_type = 'Device';
}
if (!in_array($face, ['front', 'rear'], true)) {
    $rack_refuse('unit_face is front or rear.');
}
if ($start < 1 || $end > $rack_units) {
    $rack_refuse("That doesn't fit - this rack runs from U1 to U$rack_units.");
}

// The asset has to be the client's, and in one place at a time
$asset_name = '';
if ($asset_id) {
    $asset_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT asset_name FROM assets WHERE asset_id = $asset_id AND asset_client_id = " . intval($client_id)));
    if (!$asset_row) {
        $rack_refuse("asset_id isn't one of the client's assets.");
    }
    $asset_name = $asset_row['asset_name'];
    $racked = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT rack_name, unit_start_number, unit_end_number FROM rack_units LEFT JOIN racks ON rack_id = unit_rack_id
        WHERE unit_asset_id = $asset_id AND unit_id != $unit_id LIMIT 1"));
    if ($racked) {
        $rack_refuse("$asset_name is already racked in {$racked['rack_name']} at " . html_entity_decode(rackPositionLabel($racked['unit_start_number'], $racked['unit_end_number'])) . '.');
    }
} elseif ($label === '') {
    $rack_refuse('Send asset_id, or a name for the item in unit_device.');
}

$conflict = rackUnitConflict($rack['rack_id'], $start, $end, $face, $full_depth, $unit_id);
if ($conflict) {
    $rack_refuse(html_entity_decode(rackPositionLabel($start, $end)) . " on the $face is taken by {$conflict['name']} (" . html_entity_decode(rackPositionLabel($conflict['start'], $conflict['end'])) . ").");
}

// A linked asset is drawn by its own asset type, so only unlinked items keep one
$unit_set = "unit_device = '" . escapeSql(mb_substr($label, 0, 200)) . "', unit_asset_id = " . ($asset_id ?: 'NULL') . ", unit_type = " . ($asset_id ? 'NULL' : "'" . escapeSql($unit_type) . "'") . ",
    unit_start_number = $start, unit_end_number = $end, unit_face = '$face', unit_full_depth = $full_depth,
    unit_power_watts = " . ($power_watts > 0 ? $power_watts : 'NULL') . ", unit_notes = '" . escapeSql($notes) . "'";
$unit_label = rackUnitName($asset_name, $label, $unit_type) . ' at ' . html_entity_decode(rackPositionLabel($start, $end)) . " ($face)";
