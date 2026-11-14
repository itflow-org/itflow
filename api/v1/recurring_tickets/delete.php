<?php
/*
 * API - Recurring Tickets - Delete
 * POST /api/v1/recurring_tickets/delete.php
 *
 * Delete a recurring ticket, as Delete does - its tasks and other assets go with
 * it; the tickets it raised stay.
 *
 * Parameters (POST, JSON body): api_key, recurring_ticket_id, client_id (its client -
 * needed when the key's user is limited to certain clients)
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

$delete_count = false;

require_once 'rt_find.php';

if ($rt_row) {

    mysqli_query($mysqli, "DELETE FROM recurring_tickets WHERE recurring_ticket_id = $recurring_ticket_id");
    $delete_count = mysqli_affected_rows($mysqli);

    if ($delete_count > 0) {
        $subject = escapeSql($rt_row['recurring_ticket_subject']);
        logAudit("Recurring Ticket", "Delete", "Deleted recurring ticket $subject via API ($api_key_name)", $client_id, $recurring_ticket_id);
        logAudit("API", "Success", "Deleted recurring ticket $subject via API ($api_key_name)", $client_id);
    }
}

// Output
require_once '../delete_output.php';
