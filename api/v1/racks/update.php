<?php
/*
 * API - Racks - Update
 * POST /api/v1/racks/update.php
 *
 * Change a rack, as Edit does - only what is sent. It can't be made shorter than
 * the highest item mounted in it.
 *
 * Parameters (POST, JSON body):
 *   api_key    required - Your API key
 *   rack_id    required - The rack
 *   client_id  its client - needed when the key's user is limited to certain clients
 *   the fields of racks/create.php   optional
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

$update_count = false;

require_once 'rack_find.php';

if ($rack) {

    require_once 'rack_model.php';

    if (!$rack_set) {
        $rack_refuse('Send at least one of the fields of racks/create.php.');
    }

    // Shrinking the rack under an item would leave it hanging off the top
    if (isset($rack_set['rack_units'])) {
        $units = intval($rack_set['rack_units']);
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT unit_device, unit_type, unit_end_number, asset_name FROM rack_units
            LEFT JOIN assets ON asset_id = unit_asset_id
            WHERE unit_rack_id = $rack_id AND unit_end_number > $units ORDER BY unit_end_number DESC LIMIT 1"));
        if ($row) {
            $rack_refuse("Can't shrink the rack to {$units}U - " . rackUnitName($row['asset_name'], $row['unit_device'], $row['unit_type']) . " is mounted up to U" . intval($row['unit_end_number']) . ". Move or remove it first.");
        }
    }

    if (mysqli_query($mysqli, "UPDATE racks SET " . implode(', ', array_map(function ($column, $value) {
        return "$column = $value";
    }, array_keys($rack_set), $rack_set)) . " WHERE rack_id = $rack_id")) {
        $update_count = 1;
        $name = escapeSql((string) getFieldById('racks', $rack_id, 'rack_name'));
        logAudit("Rack", "Edit", "Edited rack $name via API ($api_key_name)", $client_id, $rack_id);
        logAudit("API", "Success", "Edited rack $name via API ($api_key_name)", $client_id);
    }
}

// Output
require_once '../update_output.php';
