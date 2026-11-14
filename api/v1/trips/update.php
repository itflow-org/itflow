<?php
/*
 * API - Trips - Update
 * POST /api/v1/trips/update.php
 *
 * Change a trip, as Edit does - only what is sent. Anyone changes their own;
 * someone else's needs Modify on Financial. One paid back for keeps its driver,
 * vehicle and distance, one billed its client and distance.
 *
 * Parameters (POST, JSON body):
 *   api_key    required - Your API key
 *   trip_id    required - The trip
 *   client_id  the client it is for - needed when the key's user is limited to certain clients
 *   the fields of trips/create.php   optional
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

$update_count = false;

$trip_id = intval($_POST['trip_id'] ?? 0);
$trip_row = $trip_id ? tripRow($trip_id) : null;

if ($trip_row) {

    require_once 'trip_model.php';

    $error = '';
    if (!tripSave($trip_in, $trip_id, $error)) {
        http_response_code(400);
        $return_arr['success'] = "False";
        $return_arr['message'] = $error . '.';
        echo json_encode($return_arr);
        exit();
    }

    $update_count = 1;
    $row = tripRow($trip_id, false);
    $label = escapeSql(tripLabel($row));
    logAudit("Trip", "Edit", "Edited the trip $label via API ($api_key_name)", intval($row['trip_client_id']), $trip_id);
    logAudit("API", "Success", "Edited the trip $label via API ($api_key_name)", intval($row['trip_client_id']));
}

// Output
require_once '../update_output.php';
