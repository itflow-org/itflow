<?php
if ($_SERVER['REQUEST_METHOD'] !== "GET") {
    header("HTTP/1.1 405 Method Not Allowed");
    $return_arr['success'] = "False";
    $return_arr['message'] = "Can only send GET requests to this endpoint.";
    echo json_encode($return_arr);
    exit();
}

// Wildcard client ID for most SELECT queries, unless otherwise specified (and allowed)
if ($client_id == 0) {
    if (isset($_GET['client_id'])) {
        $client_id = intval($_GET['client_id']);
    } else {
        $client_id = "%";
    }
}
