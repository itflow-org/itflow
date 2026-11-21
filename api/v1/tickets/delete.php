<?php

// Delete a ticket and everything on it - replies, tasks, attachments, history -
// as Delete does in the app. A closed ticket is kept, not deleted.

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

// Parse Info
$ticket_id = intval($_POST['ticket_id'] ?? 0);

// Default
$delete_count = false;

if (!empty($ticket_id)) {

    $ticket_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_closed_at FROM tickets WHERE ticket_id = $ticket_id AND ticket_client_id = $client_id LIMIT 1"));

    if ($ticket_row && !empty($ticket_row['ticket_closed_at'])) {
        http_response_code(400);
        $return_arr['success'] = "False";
        $return_arr['message'] = "Ticket " . ticketReference($ticket_row) . " is closed - closed tickets are kept, not deleted.";
        echo json_encode($return_arr);
        exit();
    }

    if ($ticket_row) {

        ticketDeleteForGood($ticket_id);

        if (!mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM tickets WHERE ticket_id = $ticket_id"))) {
            $delete_count = 1;

            $reference = escapeSql(ticketReference($ticket_row));
            $subject = escapeSql($ticket_row['ticket_subject']);
            logAudit("Ticket", "Delete", "$reference - $subject along with all replies via API ($api_key_name)", $client_id);
            logAudit("API", "Success", "Deleted ticket $reference via API ($api_key_name)", $client_id);

            triggerCustomAction('ticket_delete', $ticket_id);
        }
    }
}

// Output
require_once '../delete_output.php';
