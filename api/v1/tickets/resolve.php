<?php

// Resolve endpoint for tickets
// Just send a POST here with a ticket & client id, and we do the rest

require_once '../validate_api_key.php';

require_once '../require_post_method.php';

// Parse Info
$ticket_id = isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : 0;

// Default
$update_count = false;

if ($ticket_id) {

    $client_filter = '';
    if ((int) $client_id > 0) {
        $client_filter = " AND ticket_client_id = " . intval($client_id);
    }

    $ticket_sql = mysqli_query($mysqli, "SELECT ticket_id, ticket_client_id, ticket_prefix, ticket_number, ticket_first_response_at FROM tickets WHERE ticket_id = '$ticket_id' AND ticket_resolved_at IS NULL $client_filter LIMIT 1");

    if ($ticket_sql && mysqli_num_rows($ticket_sql) === 1) {
        $ticket_row = mysqli_fetch_assoc($ticket_sql);

        $ticket_id = intval($ticket_row['ticket_id']);
        $ticket_client_id = intval($ticket_row['ticket_client_id']);
        $ticket_prefix = sanitizeInput($ticket_row['ticket_prefix']);
        $ticket_number = intval($ticket_row['ticket_number']);
        $ticket_first_response_at = sanitizeInput($ticket_row['ticket_first_response_at']);

        if (empty($ticket_first_response_at)) {
            mysqli_query($mysqli, "UPDATE tickets SET ticket_first_response_at = NOW() WHERE ticket_id = $ticket_id $client_filter LIMIT 1");
        }

        $update_sql = mysqli_query($mysqli, "UPDATE tickets SET ticket_status = 4, ticket_resolved_at = NOW() WHERE ticket_id = $ticket_id $client_filter LIMIT 1");

        if ($update_sql) {
            $update_count = mysqli_affected_rows($mysqli);
            logAction("Ticket", "Resolved", "$ticket_prefix$ticket_number ticket via API ($api_key_name)", $ticket_client_id, $ticket_id);
            logAction("API", "Success", "Resolved ticket $ticket_prefix$ticket_number via API ($api_key_name)", $ticket_client_id);
        }

        customAction('ticket_resolve', $ticket_id);
    }
}

// Output
require_once '../update_output.php';