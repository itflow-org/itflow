<?php

// Resolve endpoint for tickets
// Just send a POST here with a ticket & client id, and we do the rest

require_once '../validate_api_key.php';

require_once '../require_post_method.php';

// Parse Info
$ticket_id = intval($_POST['ticket_id']);

// Default
$update_count = false;

if (!empty($ticket_id)) {

    $ticket_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_id = '$ticket_id' AND ticket_resolved_at IS NULL AND ticket_client_id = $client_id LIMIT 1"));

    // Grab what we need, not using the model
    $ticket_id = intval($ticket_row['ticket_id']); // Override so things fail if this is bad
    $ticket_prefix = sanitizeInput($ticket_row['ticket_prefix']);
    $ticket_number = intval($ticket_row['ticket_number']);
    $ticket_first_response_at = sanitizeInput($ticket_row['ticket_first_response_at']);

    // Mark FR (if not)
    if (empty($ticket_first_response_at)) {
        mysqli_query($mysqli, "UPDATE tickets SET ticket_first_response_at = NOW() WHERE ticket_id = $ticket_id AND ticket_client_id = $client_id LIMIT 1");
    }

    // Resolve
    $update_sql = mysqli_query($mysqli, "UPDATE tickets SET ticket_status = 4, ticket_resolved_at = NOW() WHERE ticket_id = $ticket_id AND ticket_client_id = $client_id LIMIT 1");

    // Check insert & get insert ID
    if ($update_sql) {
        $update_count = mysqli_affected_rows($mysqli);

        // Logging
        logAction("Ticket", "Resolved", "$ticket_prefix$ticket_number ticket via API ($api_key_name)", $client_id, $ticket_id);
        logAction("API", "Success", "Resolved ticket $ticket_prefix$ticket_number via API ($api_key_name)", $client_id);
    }

    customAction('ticket_resolve', $ticket_id);

}

// Output
require_once '../update_output.php';