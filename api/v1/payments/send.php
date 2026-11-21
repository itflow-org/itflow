<?php
/*
 * API - Payments - Send
 * POST /api/v1/payments/send.php
 *
 * Email a payment's receipt again, as Send Receipt does on it - to its client's
 * primary and billing contacts.
 *
 * Parameters (POST, JSON body): api_key, payment_id, client_id (its invoice's client -
 * needed when the key's user is limited to certain clients)
 *
 * Answer: receipt_to - who it was queued to.
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once "../../../includes/load_global_settings.php";
require_once "../../../includes/load_company_settings.php";

if (!billingMoneyCan('receipt')) {
    apiDeny(intval(lookupUserPermission('module_sales')) < 1 ? apiPermissionText('module_sales', 1) : apiPermissionText('module_financial', billingMoneyRules()['receipt'][0]));
}

$payment_id = intval($_POST['payment_id'] ?? 0);

$update_count = false;

// The payment, of a client the key's user can see (incomePaymentRow())
$row = $payment_id ? incomePaymentRow($payment_id) : null;
if ($row && ($row['invoice_number'] === null || ($client_id && intval($row['invoice_client_id']) !== $client_id))) {
    $row = null;
}

if ($row) {

    $client_id = intval($row['invoice_client_id']);
    $reference_text = $row['invoice_prefix'] . $row['invoice_number'];

    $receipt_to = billingPaymentSend($row['invoice_id'], floatval($row['payment_amount']), [
        'date' => $row['payment_date'],
        'method' => (string) $row['payment_method'],
        'reference' => (string) $row['payment_reference'],
    ]);
    if (!$receipt_to) {
        http_response_code(400);
        $return_arr['success'] = "False";
        $return_arr['message'] = $row['client_name'] . ' has no primary or billing contact with an email address to send the receipt to.';
        echo json_encode($return_arr);
        exit();
    }

    $update_count = 1;
    $return_arr['receipt_to'] = array_values($receipt_to);

    logAudit("Invoice", "Payment", "Payment receipt for invoice " . escapeSql($reference_text) . " queued to " . escapeSql(implode(', ', $receipt_to)) . " via API ($api_key_name)", $client_id, intval($row['invoice_id']));
    logAudit("API", "Success", "Sent a payment receipt for invoice " . escapeSql($reference_text) . " via API ($api_key_name)", $client_id);
}

// Output
require_once '../update_output.php';
