<?php
/*
 * API - Licenses - Seats
 * POST /api/v1/software/seats.php
 *
 * Who holds a seat on a license, as Seats does on it - the client's devices
 * (asset_ids) and users (contact_ids), each list in place of the one it had.
 * One left out stays as it is. A device or user that loses its seat gives back
 * the keys it was handed.
 *
 * Parameters (POST, JSON body):
 *   api_key      required - Your API key
 *   software_id  required - The license
 *   client_id    its client - needed when the key's user is limited to certain clients
 *   asset_ids    optional - The client's assets on it
 *   contact_ids  optional - The client's contacts on it
 *
 * Answer: added and removed (names), and warning when it is over its seats.
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

$update_count = false;

$software_lookup_id = intval($_POST['software_id'] ?? 0);
require_once 'software_find.php';

if ($software && (array_key_exists('asset_ids', $_POST) || array_key_exists('contact_ids', $_POST))) {

    $software_id = intval($software['software_id']);

    // What is not sent stays as it is
    $holders = softwareSeatHolders($software_id);
    $wanted = [];
    foreach (['asset' => ['asset_ids', 'assets'], 'contact' => ['contact_ids', 'contacts']] as $kind => [$field, $list]) {
        if (array_key_exists($field, $_POST)) {
            $ids = array_values(array_unique(array_filter(array_map('intval', (array) $_POST[$field]))));
            $owned = softwareClientIds($kind, $ids, $client_id);
            if (count($owned) !== count($ids)) {
                http_response_code(400);
                $return_arr['success'] = "False";
                $return_arr['message'] = "$field are the client's own {$kind}s, not archived - these aren't: " . implode(', ', array_diff($ids, $owned)) . '.';
                echo json_encode($return_arr);
                exit();
            }
            $wanted[$kind] = $owned;
        } else {
            $wanted[$kind] = array_map(function ($row) use ($kind) {
                return intval($row[$kind . '_id']);
            }, $holders[$list]);
        }
    }

    $changes = softwareSyncSeats($software_id, $client_id, $wanted['asset'], $wanted['contact']);

    $update_count = 1;
    $return_arr['added'] = array_values($changes['added']);
    $return_arr['removed'] = array_values($changes['removed']);
    $return_arr['warning'] = $software_seat_warning($software_id);

    if ($changes['added'] || $changes['removed']) {
        $label = escapeSql($software['software_name'] . softwareSeatChangeText($changes));
        logAudit("Software", "Seats", "Changed seats on license $label via API ($api_key_name)", $client_id, $software_id);
        logAudit("API", "Success", "Changed seats on license " . escapeSql($software['software_name']) . " via API ($api_key_name)", $client_id);
    }
}

// Output
require_once '../update_output.php';
