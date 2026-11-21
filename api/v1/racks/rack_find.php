<?php

// The rack a request names (rack_id) - of a client the key's user can reach, and
// client_id's when it is sent - as $rack, or null. $client_id becomes its client.

if (!function_exists('apiDeny')) {
    http_response_code(404);
    exit();
}

$rack_id = intval($rack_lookup_id ?? ($_POST['rack_id'] ?? 0));
$rack = $rack_id ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM racks WHERE rack_id = $rack_id "
    . clientScopeSql('rack_client_id') . ($client_id ? " AND rack_client_id = $client_id" : '') . " LIMIT 1")) : null;
if ($rack && !apiUserCanAccessClient($rack['rack_client_id'])) {
    $rack = null;
}
if ($rack) {
    $client_id = intval($rack['rack_client_id']);
}

$rack_refuse = function ($message) use (&$return_arr) {
    http_response_code(400);
    $return_arr['success'] = "False";
    $return_arr['message'] = $message;
    echo json_encode($return_arr);
    exit();
};
