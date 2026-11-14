<?php
/*
 * API - Asset Interfaces - Update
 * POST /api/v1/asset_interfaces/update.php
 *
 * Change an interface, as Edit does - only what is sent, with the same checks
 * as adding one. interface_primary 1 makes it the asset's primary; connected_to
 * plugs it into another interface, or 0 unplugs it.
 *
 * Parameters (POST, JSON body):
 *   api_key        required - Your API key
 *   interface_id   required - The interface
 *   client_id      the asset's client - needed when the key's user is limited to certain clients
 *   the fields of asset_interfaces/create.php   optional
 *
 * Answer: warnings.
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

$update_count = false;

$interface_row = !empty($_POST['interface_id']) ? interfaceRow($_POST['interface_id']) : null;
if ($interface_row && (!apiUserCanAccessClient($interface_row['asset_client_id']) || ($client_id && intval($interface_row['asset_client_id']) !== $client_id))) {
    $interface_row = null;
}

if ($interface_row) {

    $interface_id = intval($interface_row['interface_id']);
    $asset_id = intval($interface_row['asset_id']);
    $client_id = intval($interface_row['asset_client_id']);
    $asset = ['asset_id' => $asset_id, 'asset_name' => $interface_row['asset_name'], 'asset_type' => $interface_row['asset_type'], 'asset_client_id' => $client_id];

    require_once 'interface_model.php';

    if (mysqli_query($mysqli, "UPDATE asset_interfaces SET $interface_set WHERE interface_id = $interface_id")) {
        $update_count = 1;

        if (!empty($_POST['interface_primary'])) {
            interfaceMakePrimary($interface_id, $asset_id);
        }
        // Moved, or let go when 0
        if ($interface_connect !== null && $interface_connect !== intval($interface_row['link']['interface_id'] ?? 0)) {
            if (!empty($interface_row['link'])) {
                interfaceConnect($interface_id, 0);
            }
            if ($interface_connect) {
                interfaceConnect($interface_id, $interface_connect);
            }
        }

        $return_arr['warnings'] = $interface_warnings;

        $label = escapeSql(trim($interface_post['name']) . ' on asset ' . $interface_row['asset_name']);
        logAudit("Asset Interface", "Edit", "Edited interface $label via API ($api_key_name)", $client_id, $asset_id);
        logAudit("API", "Success", "Edited interface $label via API ($api_key_name)", $client_id);
    }
}

// Output
require_once '../update_output.php';
