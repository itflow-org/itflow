<?php
/*
 * API - Quotes - Delete
 * POST /api/v1/quotes/delete.php
 *
 * Delete a quote, as Delete does on its page - its lines and history go with
 * it, and invoices and recurring invoices made from it no longer point at it.
 *
 * Parameters (POST, JSON body): api_key, quote_id, client_id (the quote's client)
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

// Parse ID
$quote_id = intval($_POST['quote_id'] ?? 0);

// Default
$delete_count = false;

if (!empty($quote_id)) {

    $quote = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT quote_id, quote_prefix, quote_number FROM quotes WHERE quote_id = $quote_id AND quote_client_id = $client_id LIMIT 1"));

    if ($quote) {

        mysqli_query($mysqli, "DELETE FROM quotes WHERE quote_id = $quote_id AND quote_client_id = $client_id LIMIT 1");
        $delete_count = mysqli_affected_rows($mysqli);

        if ($delete_count > 0) {
            mysqli_query($mysqli, "DELETE FROM quote_items WHERE item_quote_id = $quote_id");

            // Its history - a recurring invoice's entry for the lines it added stays theirs
            mysqli_query($mysqli, "DELETE FROM history WHERE history_quote_id = $quote_id AND history_recurring_invoice_id = 0");
            mysqli_query($mysqli, "UPDATE history SET history_quote_id = 0 WHERE history_quote_id = $quote_id");

            // What was made from it no longer points at it
            mysqli_query($mysqli, "UPDATE invoices SET invoice_quote_id = 0 WHERE invoice_quote_id = $quote_id");
            mysqli_query($mysqli, "UPDATE recurring_invoices SET recurring_invoice_quote_id = 0 WHERE recurring_invoice_quote_id = $quote_id");

            $reference = escapeSql(billingNumber('quote', $quote));
            logAudit("Quote", "Delete", "Deleted quote $reference via API ($api_key_name)", $client_id);
            logAudit("API", "Success", "Deleted quote $reference via API ($api_key_name)", $client_id);
        }
    }
}

// Output
require_once '../delete_output.php';
