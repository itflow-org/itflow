<?php
/*
 * API - Trips - Create
 * POST /api/v1/trips/create.php
 *
 * Log a trip, as New Trip does - by the key's own agent unless trip_user_id says
 * who drove; logging one for someone else needs Modify on Financial. The distance
 * is the odometer readings' difference when both are sent, else trip_miles one
 * way, doubled for a round trip.
 *
 * Parameters (POST, JSON body):
 *   api_key              required - Your API key
 *   trip_destination     required - Where it went
 *   trip_purpose         required - What it was for
 *   trip_miles           required without odometer readings - The distance one way
 *   trip_date            optional - Y-m-d, today if left out
 *   trip_source          optional - Where it started; your company's address if left out
 *   round_trip           optional - 1: there and back
 *   trip_start_odometer, trip_end_odometer   optional - Whole numbers
 *   client_id            optional - The client it was for
 *   trip_location_id     optional - One of the client's sites
 *   trip_ticket_id       optional - One of the client's tickets
 *   trip_user_id         optional - Who drove
 *   trip_own_vehicle     optional - 1: their own car, paid back for the distance
 *   trip_billable        optional - 1: to bill to the client
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

$insert_id = false;

require_once 'trip_model.php';

$error = '';
$trip_id = intval(tripSave($trip_in, 0, $error));
if (!$trip_id) {
    http_response_code(400);
    $return_arr['success'] = "False";
    $return_arr['message'] = $error . '.';
    echo json_encode($return_arr);
    exit();
}

$insert_id = $trip_id;
$row = tripRow($trip_id, false);
$label = escapeSql(tripLabel($row));
logAudit("Trip", "Create", "Logged the trip $label via API ($api_key_name)", intval($row['trip_client_id']), $trip_id);
logAudit("API", "Success", "Logged the trip $label via API ($api_key_name)", intval($row['trip_client_id']));

// Output
require_once '../create_output.php';
