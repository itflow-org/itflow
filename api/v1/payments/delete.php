<?php
/*
 * API - Payments - Delete
 * POST /api/v1/payments/delete.php
 *
 * Delete a payment, as Delete does on it - its invoice is owing again by that
 * much. Not once it has cleared on a reconciled statement. A card payment
 * isn't refunded by this - refund it in Stripe.
 *
 * Parameters (POST, JSON body): api_key, payment_id, client_id (its invoice's client -
 * needed when the key's user is limited to certain clients)
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

if (!billingMoneyCan('delete')) {
    apiDeny(intval(lookupUserPermission('module_sales')) < 1 ? apiPermissionText('module_sales', 1) : apiPermissionText('module_financial', billingMoneyRules()['delete'][0]));
}

$payment_id = intval($_POST['payment_id'] ?? 0);

$delete_count = false;

$row = $payment_id ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT payment_amount, payment_currency_code, invoice_id, invoice_prefix, invoice_number, invoice_client_id
    FROM payments JOIN invoices ON invoice_id = payment_invoice_id
    WHERE payment_id = $payment_id " . clientScopeSql('invoice_client_id') . ($client_id ? " AND invoice_client_id = $client_id" : '') . " LIMIT 1")) : null;

if ($row) {

    $invoice_id = intval($row['invoice_id']);
    $client_id = intval($row['invoice_client_id']);
    $reference_text = $row['invoice_prefix'] . $row['invoice_number'];

    $error = '';
    if (!reconcileCheckDelete('payment', $payment_id, $error)) {
        http_response_code(400);
        $return_arr['success'] = "False";
        $return_arr['message'] = $error . '.';
        echo json_encode($return_arr);
        exit();
    }

    mysqli_query($mysqli, "DELETE FROM payments WHERE payment_id = $payment_id");
    $delete_count = mysqli_affected_rows($mysqli);

    if ($delete_count > 0) {
        // From what is left
        $status = updateInvoiceStatusFromPayments($invoice_id);
        $money = billingMoney($row['payment_amount'], $row['payment_currency_code']);
        billingLog('invoice', $invoice_id, $status, "Payment of $money deleted via the API (" . html_entity_decode($api_key_name, ENT_QUOTES, 'UTF-8') . ")");

        logAudit("Invoice", "Edit", "Deleted a payment of " . escapeSql($money) . " on invoice " . escapeSql($reference_text) . " via API ($api_key_name)", $client_id, $invoice_id);
        logAudit("API", "Success", "Deleted a payment on invoice " . escapeSql($reference_text) . " via API ($api_key_name)", $client_id);
    }
}

// Output
require_once '../delete_output.php';
