<?php

require_once '../validate_api_key.php';

require_once '../require_post_method.php';

// Parse ID
$ticket_id = intval($_POST['ticket_id']);

// Default
$update_count = false;

if (!empty($ticket_id)) {

    $ticket_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_id = '$ticket_id' AND ticket_client_id = $client_id LIMIT 1"));

    if ($ticket_row) {
        // Assign model values from POST, falling back to the current ticket values.
        require_once 'ticket_model.php';

        $ticket_id = intval($ticket_row['ticket_id']);
        $ticket_prefix = escapeSql($ticket_row['ticket_prefix']);
        $ticket_number = intval($ticket_row['ticket_number']);

        $update_sql = mysqli_query($mysqli, "UPDATE tickets SET ticket_subject = '$subject', ticket_details = '$details', ticket_priority = '$priority', ticket_billable = $billable, ticket_vendor_ticket_number = '$vendor_ticket_number', ticket_vendor_id = $vendor_id, ticket_assigned_to = $assigned_to, ticket_contact_id = $contact, ticket_asset_id = $asset WHERE ticket_id = $ticket_id AND ticket_client_id = $client_id LIMIT 1");

        if ($update_sql) {
            $update_count = mysqli_affected_rows($mysqli);

            logTicketHistory($ticket_id, "Edited via the API ($api_key_name)");
            logAudit("Ticket", "Edit", "$ticket_prefix$ticket_number ticket via API ($api_key_name)", $client_id, $ticket_id);
            logAudit("API", "Success", "Edited ticket $ticket_prefix$ticket_number via API ($api_key_name)", $client_id, $ticket_id);
        }
    }
}

// Output
require_once '../update_output.php';
