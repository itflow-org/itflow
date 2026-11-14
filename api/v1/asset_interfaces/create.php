<?php
/*
 * API - Asset Interfaces - Create
 * POST /api/v1/asset_interfaces/create.php
 *
 * Add a port or network card to an asset, as Add Interface does - a name the
 * asset doesn't have yet, an address inside its network, a MAC that is one, a
 * network of the asset's client. The asset's first interface is its primary.
 *
 * Parameters (POST, JSON body):
 *   api_key                required - Your API key
 *   asset_id               required - The asset
 *   client_id              the asset's client - needed when the key's user is limited to certain clients
 *   interface_name         required - Its name or port number
 *   interface_type         optional - An interface type (Admin > Categories)
 *   interface_ip           optional - An IP address, DHCP, or empty for none
 *   interface_network_id   optional - One of the client's networks
 *   interface_mac, interface_nat_ip, interface_ipv6, interface_vlans, interface_description, interface_notes   optional
 *   interface_primary      optional - 1: the asset's primary interface
 *   connected_to           optional - The interface it is plugged into, on another of the client's assets
 *
 * Answer: insert_id, and warnings - such as the address being on another device too.
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

$insert_id = false;

$asset_id = intval($_POST['asset_id'] ?? 0);
$asset = $asset_id ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT asset_id, asset_name, asset_type, asset_client_id FROM assets WHERE asset_id = $asset_id "
    . clientScopeSql('asset_client_id') . ($client_id ? " AND asset_client_id = $client_id" : '') . " LIMIT 1")) : null;

if ($asset && apiUserCanAccessClient($asset['asset_client_id'])) {

    $client_id = intval($asset['asset_client_id']);

    require_once 'interface_model.php';

    mysqli_query($mysqli, "INSERT INTO asset_interfaces SET $interface_set, interface_asset_id = $asset_id");
    $interface_id = intval(mysqli_insert_id($mysqli));

    if ($interface_id) {
        $insert_id = $interface_id;

        // The first one is the primary whether it is asked for or not
        $has_primary = mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM asset_interfaces WHERE interface_asset_id = $asset_id AND interface_primary = 1 AND interface_archived_at IS NULL"));
        if (!empty($_POST['interface_primary']) || !$has_primary) {
            interfaceMakePrimary($interface_id, $asset_id);
        }
        if ($interface_connect) {
            interfaceConnect($interface_id, $interface_connect);
        }

        $return_arr['warnings'] = $interface_warnings;

        $label = escapeSql(trim($interface_post['name']) . ' on asset ' . $asset['asset_name']);
        logAudit("Asset Interface", "Create", "Created interface $label via API ($api_key_name)", $client_id, $asset_id);
        logAudit("API", "Success", "Created interface $label via API ($api_key_name)", $client_id);
    }
}

// Output
require_once '../create_output.php';
