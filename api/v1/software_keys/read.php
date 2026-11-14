<?php
/*
 * API - License Keys - Read
 * GET /api/v1/software_keys/read.php
 *
 * A license's keys, oldest first, each with the client's devices (assets) and
 * users (contacts) it is handed to.
 *
 *   software_id      required - The license
 *   software_key_id  optional - Just this key
 *   client_id        optional - Only if the license is this client's
 */

require_once '../validate_api_key.php';
require_once '../require_get_method.php';

$software_id = intval($_GET['software_id'] ?? 0);
$key_id = intval($_GET['software_key_id'] ?? 0);
if (!$software_id && $key_id) {
    $software_id = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT software_key_software_id FROM software_keys WHERE software_key_id = $key_id"))[0] ?? 0);
}

$sql = $software_id ? mysqli_query($mysqli, "SELECT software_id FROM software WHERE software_id = $software_id " . apiClientScopeSql('software_client_id')) : false;

if ($sql && mysqli_num_rows($sql)) {
    $keys = [];
    foreach (softwareKeys($software_id) as $key) {
        if ($key_id && $key['id'] !== $key_id) {
            continue;
        }
        $keys[] = [
            'software_key_id' => $key['id'],
            'software_key' => $key['key'],
            'software_id' => $software_id,
            'assets' => array_map(function ($row) {
                return ['asset_id' => intval($row['asset_id']), 'asset_name' => $row['asset_name']];
            }, $key['assets']),
            'contacts' => array_map(function ($row) {
                return ['contact_id' => intval($row['contact_id']), 'contact_name' => $row['contact_name']];
            }, $key['contacts']),
        ];
    }
    if ($keys) {
        echo json_encode(['success' => 'True', 'count' => count($keys), 'data' => $keys]);
        exit();
    }
    $sql = false;
}

if (!$software_id) {
    http_response_code(400);
    $return_arr['success'] = "False";
    $return_arr['message'] = "software_keys/read.php needs software_id or software_key_id.";
    echo json_encode($return_arr);
    exit();
}

// Output
require_once "../read_output.php";
