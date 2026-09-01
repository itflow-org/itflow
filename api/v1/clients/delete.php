<?php

require_once '../validate_api_key.php';

require_once '../require_post_method.php';

// Default
$delete_count = false;

if (!empty($client_id)) {
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT client_name FROM clients WHERE client_id = $client_id AND client_archived_at IS NOT NULL LIMIT 1"));
    $client_name = $row['client_name'];

    if (!empty($client_name)) {
        // Delete Associations
        mysqli_query($mysqli, "DELETE FROM certificates WHERE certificate_client_id = $client_id");
        mysqli_query($mysqli, "DELETE FROM documents WHERE document_client_id = $client_id");
        mysqli_query($mysqli, "DELETE FROM contacts WHERE contact_client_id = $client_id");
        mysqli_query($mysqli, "DELETE FROM assets WHERE asset_client_id = $client_id");
        mysqli_query($mysqli, "DELETE FROM domains WHERE domain_client_id = $client_id");
        mysqli_query($mysqli, "DELETE FROM calendar_events WHERE event_client_id = $client_id");
        mysqli_query($mysqli, "DELETE FROM files WHERE file_client_id = $client_id");
        mysqli_query($mysqli, "DELETE FROM folders WHERE folder_client_id = $client_id");

        $sql_invoices = mysqli_query($mysqli, "SELECT invoice_id FROM invoices WHERE invoice_client_id = $client_id");
        while ($invoice_row = mysqli_fetch_assoc($sql_invoices)) {
            $invoice_id = intval($invoice_row['invoice_id']);
            mysqli_query($mysqli, "DELETE FROM invoice_items WHERE item_invoice_id = $invoice_id");
            mysqli_query($mysqli, "DELETE FROM payments WHERE payment_invoice_id = $invoice_id");
            mysqli_query($mysqli, "DELETE FROM history WHERE history_invoice_id = $invoice_id");
        }
        mysqli_query($mysqli, "DELETE FROM invoices WHERE invoice_client_id = $client_id");

        mysqli_query($mysqli, "DELETE FROM locations WHERE location_client_id = $client_id");
        mysqli_query($mysqli, "DELETE FROM credentials WHERE credential_client_id = $client_id");
        mysqli_query($mysqli, "DELETE FROM logs WHERE log_client_id = $client_id");
        mysqli_query($mysqli, "DELETE FROM networks WHERE network_client_id = $client_id");
        mysqli_query($mysqli, "DELETE FROM notifications WHERE notification_client_id = $client_id");

        $sql_quotes = mysqli_query($mysqli, "SELECT quote_id FROM quotes WHERE quote_client_id = $client_id");
        while ($quote_row = mysqli_fetch_assoc($sql_quotes)) {
            $quote_id = intval($quote_row['quote_id']);
            mysqli_query($mysqli, "DELETE FROM quote_items WHERE item_quote_id = $quote_id");
        }
        mysqli_query($mysqli, "DELETE FROM quotes WHERE quote_client_id = $client_id");

        $sql_recurring_invoices = mysqli_query($mysqli, "SELECT recurring_invoice_id FROM recurring_invoices WHERE recurring_invoice_client_id = $client_id");
        while ($recurring_row = mysqli_fetch_assoc($sql_recurring_invoices)) {
            $recurring_invoice_id = intval($recurring_row['recurring_invoice_id']);
            mysqli_query($mysqli, "DELETE FROM recurring_invoice_items WHERE item_recurring_invoice_id = $recurring_invoice_id");
        }
        mysqli_query($mysqli, "DELETE FROM recurring_invoices WHERE recurring_invoice_client_id = $client_id");

        mysqli_query($mysqli, "DELETE FROM revenues WHERE revenue_client_id = $client_id");
        mysqli_query($mysqli, "DELETE FROM recurring_tickets WHERE recurring_ticket_client_id = $client_id");
        mysqli_query($mysqli, "DELETE FROM services WHERE service_client_id = $client_id");
        mysqli_query($mysqli, "DELETE FROM shared_items WHERE item_client_id = $client_id");
        mysqli_query($mysqli, "DELETE FROM software WHERE software_client_id = $client_id");

        $sql_tickets = mysqli_query($mysqli, "SELECT ticket_id FROM tickets WHERE ticket_client_id = $client_id");
        while ($ticket_row = mysqli_fetch_assoc($sql_tickets)) {
            $ticket_id = intval($ticket_row['ticket_id']);
            mysqli_query($mysqli, "DELETE FROM ticket_replies WHERE ticket_reply_ticket_id = $ticket_id");
            mysqli_query($mysqli, "DELETE FROM ticket_views WHERE view_ticket_id = $ticket_id");
        }
        mysqli_query($mysqli, "DELETE FROM tickets WHERE ticket_client_id = $client_id");
        mysqli_query($mysqli, "DELETE FROM trips WHERE trip_client_id = $client_id");
        mysqli_query($mysqli, "DELETE FROM vendors WHERE vendor_client_id = $client_id");

        removeDirectory("../../uploads/clients/$client_id");

        $delete_sql = mysqli_query($mysqli, "DELETE FROM clients WHERE client_id = $client_id");

        if ($delete_sql) {
            $delete_count = mysqli_affected_rows($mysqli);

            // Logging
            logAudit("Client", "Delete", "$client_name and all associated data via API ($api_key_name)", $client_id);
        }
    }
}

// Output
require_once '../delete_output.php';