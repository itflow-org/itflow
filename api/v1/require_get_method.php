<?php
if ($_SERVER['REQUEST_METHOD'] !== "GET") {
    header("HTTP/1.1 405 Method Not Allowed");
    $return_arr['success'] = "False";
    $return_arr['message'] = "Can only send GET requests to this endpoint.";
    echo json_encode($return_arr);
    exit();
}

// Wildcard client ID for most SELECT queries
if ($client_id == 0) {
    $client_id = "%";
}