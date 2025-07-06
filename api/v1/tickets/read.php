<?php

require_once '../validate_api_key.php';

require_once '../require_get_method.php';


// Specific ticket via ID (single)
if (isset($_GET['ticket_id'])) {
    $id = intval($_GET['ticket_id']);
    $sql = mysqli_query(
        $mysqli,
        "SELECT * FROM tickets
        LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
        WHERE ticket_id = '$id' AND ticket_client_id LIKE '$client_id'"
    );

} else {
    // All tickets (by client ID if given, or all in general if key permits)
    $sql = mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_client_id LIKE '$client_id' ORDER BY ticket_id LIMIT $limit OFFSET $offset");
}

// Output
require_once "../read_output.php";

