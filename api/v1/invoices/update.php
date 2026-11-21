<?php
/*
 * API - Invoices - Update
 * POST /api/v1/invoices/update.php
 *
 * Change an invoice's date, due date, scope, category, discount or note -
 * only what you send. Its total follows the discount, as on its page. Its
 * lines change through invoice_items/.
 *
 * Parameters (POST, JSON body):
 *   api_key, invoice_id, client_id (the invoice's client)
 *   date, due (Y-m-d), scope, category_id, discount, note
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

// Parse ID
$invoice_id = intval($_POST['invoice_id'] ?? 0);

// Default
$update_count = false;

if (!empty($invoice_id)) {

    $invoice = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT invoice_id, invoice_prefix, invoice_number, invoice_scope FROM invoices WHERE invoice_id = $invoice_id AND invoice_client_id = $client_id LIMIT 1"));

    if ($invoice) {

        require_once 'invoice_model.php';

        if (!$invoice_set) {
            http_response_code(400);
            $return_arr['success'] = "False";
            $return_arr['message'] = 'Send at least one of date, due, scope, category_id, discount or note.';
            echo json_encode($return_arr);
            exit();
        }

        $update_sql = mysqli_query($mysqli, "UPDATE invoices SET " . implode(', ', array_map(function ($column, $value) {
            return "$column = $value";
        }, array_keys($invoice_set), $invoice_set)) . " WHERE invoice_id = $invoice_id AND invoice_client_id = $client_id LIMIT 1");

        if ($update_sql) {
            // An invoice saved as it was still counts as updated
            $update_count = 1;

            // The total from the lines less the discount - and the status, if it has been paid against
            billingRecalc('invoice', $invoice_id);

            $reference = escapeSql(billingNumber('invoice', $invoice));
            logAudit("Invoice", "Edit", "Edited invoice $reference via API ($api_key_name)", $client_id, $invoice_id);
            logAudit("API", "Success", "Edited invoice $reference via API ($api_key_name)", $client_id);
        }
    }
}

// Output
require_once '../update_output.php';
