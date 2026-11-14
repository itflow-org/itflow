<?php
/*
 * API - Asset Interfaces - Read
 * GET /api/v1/asset_interfaces/read.php
 *
 * An asset's ports and network cards, or one - each with its network's name and
 * what it is plugged into (connected_to: the interface id at the other end, with
 * its asset), or null.
 *
 *   asset_id       This asset's interfaces - this or interface_id is needed
 *   interface_id   Just this interface
 *   client_id      optional - Only if the asset is this client's
 */

require_once '../validate_api_key.php';
require_once '../require_get_method.php';

$asset_id = intval($_GET['asset_id'] ?? 0);
$interface_id = intval($_GET['interface_id'] ?? 0);

if (!$asset_id && !$interface_id) {
    http_response_code(400);
    $return_arr['success'] = "False";
    $return_arr['message'] = "asset_interfaces/read.php needs asset_id or interface_id.";
    echo json_encode($return_arr);
    exit();
}

$sql = mysqli_query($mysqli, "SELECT asset_interfaces.*, asset_name, asset_client_id, network_name, network
    FROM asset_interfaces
    JOIN assets ON asset_id = interface_asset_id
    LEFT JOIN networks ON network_id = interface_network_id AND interface_network_id > 0
    WHERE " . ($interface_id ? "interface_id = $interface_id" : "interface_asset_id = $asset_id") . " " . apiClientScopeSql('asset_client_id') . "
    ORDER BY interface_primary DESC, interface_name ASC");

$rows = [];
while ($sql && ($row = mysqli_fetch_assoc($sql))) {
    $rows[intval($row['interface_id'])] = $row;
}

if ($rows) {
    $links = interfaceLinks(array_keys($rows));
    foreach ($rows as $id => $row) {
        $link = $links[$id] ?? null;
        $rows[$id]['connected_to'] = $link ? ['interface_id' => intval($link['interface_id']), 'interface_name' => $link['interface_name'], 'asset_id' => intval($link['asset_id']), 'asset_name' => $link['asset_name']] : null;
    }
    echo json_encode(['success' => 'True', 'count' => count($rows), 'data' => array_values($rows)]);
    exit();
}

$sql = false;

// Output
require_once "../read_output.php";
