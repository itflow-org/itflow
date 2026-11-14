<?php

// Reopen a resolved ticket, as Reopen does in the app - back to Open, its
// resolution SLA running again. A closed ticket is final and stays closed.

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

// Parse Info
$ticket_id = intval($_POST['ticket_id'] ?? 0);

// Default
$update_count = false;

if (!empty($ticket_id)) {

    $ticket_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ticket_id, ticket_prefix, ticket_number, ticket_client_id, ticket_resolved_at, ticket_closed_at FROM tickets WHERE ticket_id = $ticket_id AND ticket_client_id = $client_id LIMIT 1"));

    if ($ticket_row && (!empty($ticket_row['ticket_closed_at']) || empty($ticket_row['ticket_resolved_at']))) {
        http_response_code(400);
        $return_arr['success'] = "False";
        $return_arr['message'] = "Ticket " . ticketReference($ticket_row) . (!empty($ticket_row['ticket_closed_at']) ? " is closed - closed tickets can't be reopened. Start a new one instead." : " is open already.");
        echo json_encode($return_arr);
        exit();
    }

    if ($ticket_row) {

        // Status, SLA clock, history, audit and custom actions (functions/ticket.php)
        ticketReopen($ticket_row, TICKET_STATUS_OPEN, " via the API ($api_key_name)");
        $update_count = 1;

        $reference = escapeSql(ticketReference($ticket_row));
        logAudit("API", "Success", "Reopened ticket $reference via API ($api_key_name)", $client_id);
    }
}

// Output
require_once '../update_output.php';
