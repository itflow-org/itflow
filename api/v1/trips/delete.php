<?php
/*
 * API - Trips - Delete
 * POST /api/v1/trips/delete.php
 *
 * Delete a trip, as Delete does - anyone their own, someone else's with Full on
 * Financial. Not one paid back for or billed.
 *
 * Parameters (POST, JSON body): api_key, trip_id, client_id (its client - needed when
 * the key's user is limited to certain clients)
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

$delete_count = false;

$trip_id = intval($_POST['trip_id'] ?? 0);
$trip_row = $trip_id ? tripRow($trip_id) : null;
if ($trip_row && $client_id && intval($trip_row['trip_client_id']) !== $client_id) {
    $trip_row = null;
}

if ($trip_row) {

    $error = '';
    $row = tripDelete($trip_id, $error);
    if (!$row) {
        http_response_code(400);
        $return_arr['success'] = "False";
        $return_arr['message'] = $error . '.';
        echo json_encode($return_arr);
        exit();
    }

    $delete_count = 1;
    $label = escapeSql(tripLabel($row));
    logAudit("Trip", "Delete", "Deleted the trip $label via API ($api_key_name)", intval($row['trip_client_id']));
    logAudit("API", "Success", "Deleted the trip $label via API ($api_key_name)", intval($row['trip_client_id']));
}

// Output
require_once '../delete_output.php';
