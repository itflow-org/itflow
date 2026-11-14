<?php

// A trip's fields from POST as tripSave() takes them - with what the trip has now
// ($trip_row) for those not sent, on an update. Sets $trip_in.

if (!function_exists('apiDeny')) {
    http_response_code(404);
    exit();
}

$trip_row = $trip_row ?? null;
$sent = function ($field, $current) {
    return array_key_exists($field, $_POST) ? (is_scalar($_POST[$field]) ? (string) $_POST[$field] : '') : (string) $current;
};

$trip_in = [
    'date' => $sent('trip_date', $trip_row['trip_date'] ?? date('Y-m-d')),
    // From the office, unless it says otherwise
    'source' => $sent('trip_source', $trip_row['trip_source'] ?? tripOfficeAddress()),
    'destination' => $sent('trip_destination', $trip_row['trip_destination'] ?? ''),
    'purpose' => $sent('trip_purpose', $trip_row['trip_purpose'] ?? ''),
    // The distance one way - a round trip is twice it
    'miles' => $sent('trip_miles', $trip_row ? tripOneWay($trip_row) : ''),
    'round_trip' => $sent('round_trip', $trip_row['round_trip'] ?? 0),
    'odometer_start' => $sent('trip_start_odometer', $trip_row['trip_start_odometer'] ?? ''),
    'odometer_end' => $sent('trip_end_odometer', $trip_row['trip_end_odometer'] ?? ''),
    'client_id' => $sent('client_id', $trip_row['trip_client_id'] ?? 0),
    'location_id' => $sent('trip_location_id', $trip_row['trip_location_id'] ?? 0),
    'ticket_id' => $sent('trip_ticket_id', $trip_row['trip_ticket_id'] ?? 0),
    // The key's own agent, unless it says who
    'driver' => $sent('trip_user_id', $trip_row['trip_user_id'] ?? $session_user_id),
    'own_vehicle' => $sent('trip_own_vehicle', $trip_row['trip_own_vehicle'] ?? 0),
    'billable' => $sent('trip_billable', $trip_row['trip_billable'] ?? 0),
];
// A trip with no client has no site, and a ticket brings its own client
if (!intval($trip_in['client_id'])) {
    $trip_in['location_id'] = 0;
}
