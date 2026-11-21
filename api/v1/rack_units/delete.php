<?php
/*
 * API - Rack Units - Delete
 * POST /api/v1/rack_units/delete.php
 *
 * Take an item out of its rack, as Remove does - the asset stays.
 *
 * Parameters (POST, JSON body): api_key, unit_id, client_id (the rack's client -
 * needed when the key's user is limited to certain clients)
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

$delete_count = false;

require_once 'unit_find.php';

if ($unit) {

    mysqli_query($mysqli, "DELETE FROM rack_units WHERE unit_id = $unit_id");
    $delete_count = mysqli_affected_rows($mysqli);

    if ($delete_count > 0) {
        $what = escapeSql(rackUnitName($unit['asset_name'], $unit['unit_device'], $unit['unit_type']) . ' from rack ' . $rack['rack_name']);
        logAudit("Rack", "Edit", "Removed $what via API ($api_key_name)", $client_id, $rack_id);
        logAudit("API", "Success", "Removed $what via API ($api_key_name)", $client_id);
    }
}

// Output
require_once '../delete_output.php';
