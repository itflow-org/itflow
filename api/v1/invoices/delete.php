<?php
/*
 * API - Invoices - Delete
 * POST /api/v1/invoices/delete.php
 *
 * Delete an invoice, as Delete does on its page - its lines, history and
 * payments go with it, what it took off the shelf goes back, and the tickets,
 * expenses and trips billed on it are to bill again. Not once a payment on it
 * has cleared on a reconciled statement - cancel it instead.
 *
 * Parameters (POST, JSON body): api_key, invoice_id, client_id (the invoice's client)
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

// Parse ID
$invoice_id = intval($_POST['invoice_id'] ?? 0);

// Default
$delete_count = false;

if (!empty($invoice_id)) {

    $invoice = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT invoice_id, invoice_prefix, invoice_number FROM invoices WHERE invoice_id = $invoice_id AND invoice_client_id = $client_id LIMIT 1"));

    if ($invoice) {

        $reference = billingNumber('invoice', $invoice);

        // Its payments go with it - not once one has cleared on a reconciled statement
        $lock = reconcileInvoiceLock($invoice_id);
        if ($lock) {
            http_response_code(400);
            $return_arr['success'] = "False";
            $return_arr['message'] = "A payment on $reference cleared on the {$lock['account_name']} statement to " . date('M j, Y', strtotime($lock['reconciliation_statement_date'])) . ' - cancel the invoice instead, or undo that reconciliation to delete it.';
            echo json_encode($return_arr);
            exit();
        }

        // What it took off the shelf goes back before its lines go
        productStockReturnInvoice($invoice_id, "$reference deleted");

        mysqli_query($mysqli, "DELETE FROM invoices WHERE invoice_id = $invoice_id AND invoice_client_id = $client_id LIMIT 1");
        $delete_count = mysqli_affected_rows($mysqli);

        if ($delete_count > 0) {
            mysqli_query($mysqli, "DELETE FROM invoice_items WHERE item_invoice_id = $invoice_id");
            mysqli_query($mysqli, "DELETE FROM history WHERE history_invoice_id = $invoice_id");
            mysqli_query($mysqli, "DELETE FROM payments WHERE payment_invoice_id = $invoice_id");

            // Its tickets, and the assets made from its hardware, stay - unlinked
            mysqli_query($mysqli, "UPDATE tickets SET ticket_invoice_id = 0 WHERE ticket_invoice_id = $invoice_id");
            mysqli_query($mysqli, "UPDATE assets SET asset_invoice_id = 0, asset_invoice_item_id = 0 WHERE asset_invoice_id = $invoice_id");

            // Expenses and trips billed on it are to bill again
            mysqli_query($mysqli, "UPDATE expenses SET expense_invoice_id = NULL, expense_invoice_item_id = NULL WHERE expense_invoice_id = $invoice_id");
            mysqli_query($mysqli, "UPDATE trips SET trip_invoice_id = 0, trip_invoice_item_id = 0 WHERE trip_invoice_id = $invoice_id");

            $reference = escapeSql($reference);
            logAudit("Invoice", "Delete", "Deleted invoice $reference via API ($api_key_name)", $client_id);
            logAudit("API", "Success", "Deleted invoice $reference via API ($api_key_name)", $client_id);
        }
    }
}

// Output
require_once '../delete_output.php';
