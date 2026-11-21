<?php
/*
 * API - Asset Interfaces - Delete
 * POST /api/v1/asset_interfaces/delete.php
 *
 * Delete an interface, as Delete does on it - unplugged from whatever it was
 * connected to.
 *
 * Parameters (POST, JSON body): api_key, interface_id, client_id (the asset's client -
 * needed when the key's user is limited to certain clients)
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

$delete_count = false;

$row = !empty($_POST['interface_id']) ? interfaceRow($_POST['interface_id']) : null;
if ($row && (!apiUserCanAccessClient($row['asset_client_id']) || ($client_id && intval($row['asset_client_id']) !== $client_id))) {
    $row = null;
}

if ($row) {

    $client_id = intval($row['asset_client_id']);

    // Its connection goes with it (ON DELETE CASCADE)
    mysqli_query($mysqli, "DELETE FROM asset_interfaces WHERE interface_id = " . intval($row['interface_id']));
    $delete_count = mysqli_affected_rows($mysqli);

    if ($delete_count > 0) {
        $label = escapeSql($row['interface_name'] . ' from asset ' . $row['asset_name']);
        logAudit("Asset Interface", "Delete", "Deleted interface $label via API ($api_key_name)", $client_id, intval($row['asset_id']));
        logAudit("API", "Success", "Deleted interface $label via API ($api_key_name)", $client_id);
    }
}

// Output
require_once '../delete_output.php';
