<?php

// Archive a reply, as Archive does on the ticket - it leaves the ticket's
// thread, and its time worked stops counting.

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

// Parse ID
$ticket_reply_id = intval($_POST['ticket_reply_id'] ?? 0);

// Default
$update_count = false;

if (!empty($ticket_reply_id)) {

    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "
        SELECT ticket_id, ticket_prefix, ticket_number
        FROM ticket_replies
        JOIN tickets ON ticket_reply_ticket_id = ticket_id
        WHERE ticket_reply_id = $ticket_reply_id AND ticket_client_id = $client_id AND ticket_reply_archived_at IS NULL
        LIMIT 1
    "));

    if ($row) {

        $update_sql = mysqli_query($mysqli, "UPDATE ticket_replies SET ticket_reply_archived_at = NOW() WHERE ticket_reply_id = $ticket_reply_id LIMIT 1");

        if ($update_sql) {
            $update_count = mysqli_affected_rows($mysqli);

            $reference = escapeSql(ticketReference($row));
            logAudit("Ticket Reply", "Archive", "Archived a reply on ticket $reference via API ($api_key_name)", $client_id, $ticket_reply_id);
            logAudit("API", "Success", "Archived a reply on ticket $reference via API ($api_key_name)", $client_id);
        }
    }
}

// Output
require_once '../update_output.php';
