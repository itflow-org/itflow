<?php
/*
 * API - Invoices - Cancel
 * POST /api/v1/invoices/cancel.php
 *
 * Cancel an invoice, as Cancel does on its page - it stays, marked
 * Cancelled, and what it took off the shelf goes back.
 *
 * Parameters (POST, JSON body): api_key, invoice_id, client_id (the invoice's client)
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

// Parse ID
$invoice_id = intval($_POST['invoice_id'] ?? 0);

// Default
$update_count = false;

if (!empty($invoice_id)) {

    $invoice = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT invoice_id, invoice_prefix, invoice_number, invoice_status FROM invoices WHERE invoice_id = $invoice_id AND invoice_client_id = $client_id LIMIT 1"));

    if ($invoice && $invoice['invoice_status'] !== 'Cancelled') {

        $reference = billingNumber('invoice', $invoice);

        $update_sql = mysqli_query($mysqli, "UPDATE invoices SET invoice_status = 'Cancelled' WHERE invoice_id = $invoice_id AND invoice_client_id = $client_id LIMIT 1");

        if ($update_sql) {
            $update_count = 1;

            logHistory('Cancelled', "Invoice cancelled via API ($api_key_name)", $invoice_id);

            // What it took off the shelf goes back
            productStockReturnInvoice($invoice_id, "$reference cancelled");

            $reference = escapeSql($reference);
            logAudit("Invoice", "Edit", "Cancelled invoice $reference via API ($api_key_name)", $client_id, $invoice_id);
            logAudit("API", "Success", "Cancelled invoice $reference via API ($api_key_name)", $client_id);
        }
    }
}

// Output
require_once '../update_output.php';
