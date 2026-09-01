<?php

// Close endpoint for tickets
// Just send a POST here with a ticket id and client id, and we do the rest

require_once '../validate_api_key.php';

require_once '../require_post_method.php';

// Parse Info
$ticket_id = intval($_POST['ticket_id']);

// Default
$update_count = false;

if (!empty($ticket_id)) {

    $ticket_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ticket_id, ticket_number, ticket_prefix FROM tickets WHERE ticket_id = '$ticket_id' AND ticket_client_id = $client_id AND ticket_closed_at IS NULL LIMIT 1"));

    if ($ticket_row) {
        // Grab what we need, not using the model
        $ticket_id = intval($ticket_row['ticket_id']); // Override so things fail if this is bad
        $ticket_prefix = escapeSql($ticket_row['ticket_prefix']);
        $ticket_number = intval($ticket_row['ticket_number']);

        // Resolve first if the ticket has not already been resolved
        $resolve_sql = mysqli_query($mysqli, "UPDATE tickets SET ticket_status = 4, ticket_resolved_at = NOW() WHERE ticket_id = $ticket_id AND ticket_client_id = $client_id AND ticket_resolved_at IS NULL LIMIT 1");
        syncTicketSlaClock($ticket_id);
        setTicketResolutionSlaMet($ticket_id);

        // Close
        $update_sql = mysqli_query($mysqli, "UPDATE tickets SET ticket_status = 5, ticket_closed_at = NOW(), ticket_closed_by = $api_key_user_id WHERE ticket_id = $ticket_id AND ticket_client_id = $client_id LIMIT 1");

        // Check insert & get insert ID
        if ($update_sql) {
            $update_count = mysqli_affected_rows($mysqli);

            // Logging
            logTicketHistory($ticket_id, "Closed via the API ($api_key_name)");

            logAudit("Ticket", "Closed", "$ticket_prefix$ticket_number ticket via API ($api_key_name)", $client_id, $ticket_id);
            logAudit("API", "Success", "Closed ticket $ticket_prefix$ticket_number via API ($api_key_name)", $client_id);
        }

        triggerCustomAction('ticket_close', $ticket_id);
    }

}

// Output
require_once '../update_output.php';
