<?php

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

// Default
$update_count = false;

if (!empty($client_id)) {

    // Fetch client info
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "
        SELECT client_name
        FROM clients
        WHERE client_id = $client_id AND client_archived_at IS NULL
        LIMIT 1
    "));

    if ($row) {

        $client_name = sanitizeInput($row['client_name']);

        // Stop recurring invoices
        $sql_recurring_invoices = mysqli_query($mysqli, "SELECT * FROM recurring_invoices WHERE recurring_invoice_client_id = $client_id AND recurring_invoice_status = 1");
        while ($row = mysqli_fetch_array($sql_recurring_invoices)) {
            $recurring_invoice_id = intval($row['recurring_invoice_id']);
            mysqli_query($mysqli,"UPDATE recurring_invoices SET recurring_invoice_status = 0 WHERE recurring_invoice_id = $recurring_invoice_id AND recurring_invoice_client_id = $client_id");
            mysqli_query($mysqli,"INSERT INTO history SET history_status = 0, history_description = 'Recurring Invoice inactive as client archived', history_recurring_invoice_id = $recurring_invoice_id");
        }

        // Archive client
        $update_sql = mysqli_query($mysqli, "UPDATE clients SET client_archived_at = NOW() WHERE client_id = $client_id");

        if ($update_sql) {
            $update_count = mysqli_affected_rows($mysqli);

            // Logging
            logAction("Contact", "Archive", "$client_name archived via API ($api_key_name)", $client_id);
        }
    }
}

// Output
require_once '../update_output.php';