<?php
/*
 * API - Invoices - Mark sent
 * POST /api/v1/invoices/mark_sent.php
 *
 * Mark an invoice sent without emailing it, as Mark Sent does on its page -
 * it went another way.
 *
 * Parameters (POST, JSON body):
 *   api_key, invoice_id, client_id (the invoice's client)
 *   sent_method  required - Sent by Snail Mail, Sent by Email Client, Hand Delivered,
 *                           Sent by Fax, Sent by Courier, or another of the app's list
 *   note         optional - Kept in its history
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

// Parse ID
$invoice_id = intval($_POST['invoice_id'] ?? 0);

// Default
$update_count = false;

if (!empty($invoice_id)) {

    $invoice = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT invoice_id, invoice_prefix, invoice_number FROM invoices WHERE invoice_id = $invoice_id AND invoice_client_id = $client_id LIMIT 1"));

    if ($invoice) {

        // The app's fixed list
        $sent_method = (string) ($_POST['sent_method'] ?? '');
        if (!in_array($sent_method, getSentMethods(), true)) {
            http_response_code(400);
            $return_arr['success'] = "False";
            $return_arr['message'] = 'sent_method is one of: ' . implode(', ', getSentMethods()) . '.';
            echo json_encode($return_arr);
            exit();
        }

        $note = escapeSql(mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 500));

        $update_sql = mysqli_query($mysqli, "UPDATE invoices SET invoice_status = 'Sent' WHERE invoice_id = $invoice_id AND invoice_client_id = $client_id LIMIT 1");

        if ($update_sql) {
            $update_count = 1;

            $sent_method = escapeSql($sent_method);
            logHistory('Sent', "Invoice marked sent via API ($api_key_name) - $sent_method" . ($note !== '' ? "\nNote: $note" : ''), $invoice_id);

            $reference = escapeSql(billingNumber('invoice', $invoice));
            logAudit("Invoice", "Edit", "Marked invoice $reference sent - $sent_method via API ($api_key_name)", $client_id, $invoice_id);
            logAudit("API", "Success", "Marked invoice $reference sent via API ($api_key_name)", $client_id);
        }
    }
}

// Output
require_once '../update_output.php';
