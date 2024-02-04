<?php
if ($_SERVER['REQUEST_METHOD'] !== "POST") {
    header("HTTP/1.1 405 Method Not Allowed");
    $return_arr['success'] = "False";
    $return_arr['message'] = "Can only send POST requests to this endpoint.";
    echo json_encode($return_arr);
    exit();
}

// Client ID must be specific for INSERT/UPDATE/DELETE queries
// If this API key allows any client, set $client_id to the one specified, else leave it
if ($client_id == 0 && isset($_POST['client_id'])) {
    $client_id = intval($_POST['client_id']);
}
