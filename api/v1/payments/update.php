<?php
/*
 * API - Payments - Update
 * POST /api/v1/payments/update.php
 *
 * Change a payment, as Edit Payment does - its date, amount, account, how it was
 * paid and its reference, only what is sent. Not more than its invoice's total
 * with the other payments on it. Cleared on a reconciled statement, its date,
 * amount and account stay. Its invoice is paid, part paid or owing again to match.
 *
 * Parameters (POST, JSON body):
 *   api_key         required - Your API key
 *   payment_id      required - The payment
 *   client_id       its invoice's client - needed when the key's user is limited to certain clients
 *   date, amount, account_id, payment_method, reference   optional - as for payments/create.php
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once "../../../includes/load_global_settings.php";

if (!billingMoneyCan('edit')) {
    apiDeny(intval(lookupUserPermission('module_sales')) < 1 ? apiPermissionText('module_sales', 1) : apiPermissionText('module_financial', billingMoneyRules()['edit'][0]));
}

$payment_id = intval($_POST['payment_id'] ?? 0);

$update_count = false;

$payment_row = $payment_id ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT payments.*, invoice_id, invoice_prefix, invoice_number, invoice_amount, invoice_currency_code, invoice_client_id
    FROM payments JOIN invoices ON invoice_id = payment_invoice_id
    WHERE payment_id = $payment_id " . clientScopeSql('invoice_client_id') . ($client_id ? " AND invoice_client_id = $client_id" : '') . " LIMIT 1")) : null;

if ($payment_row) {

    $invoice_id = intval($payment_row['invoice_id']);
    $reference_text = $payment_row['invoice_prefix'] . $payment_row['invoice_number'];

    require_once 'payment_model.php';

    if (!$payment_set) {
        http_response_code(400);
        $return_arr['success'] = "False";
        $return_arr['message'] = 'Send at least one of date, amount, account_id, payment_method or reference.';
        echo json_encode($return_arr);
        exit();
    }

    $date = $payment_set['date'] ?? (string) $payment_row['payment_date'];
    $amount = $payment_set['amount'] ?? round(floatval($payment_row['payment_amount']), 2);
    $account_id = $payment_set['account_id'] ?? intval($payment_row['payment_account_id']);
    $payment_method = $payment_set['payment_method'] ?? (string) $payment_row['payment_method'];
    $reference = $payment_set['reference'] ?? (string) $payment_row['payment_reference'];

    // No more than the invoice's total with its other payments
    $others = invoicePaid($invoice_id) - floatval($payment_row['payment_amount']);
    $room = round(floatval($payment_row['invoice_amount']) - $others, 2);
    if ((int) round($amount * 100) > (int) round($room * 100)) {
        http_response_code(400);
        $return_arr['success'] = "False";
        $return_arr['message'] = "amount is more than $reference_text has left to pay with its other payments - " . billingMoney(max(0, $room), $payment_row['invoice_currency_code']) . '.';
        echo json_encode($return_arr);
        exit();
    }

    // Cleared on a reconciled statement - its date, amount and account stay
    $error = '';
    if (!reconcileCheckChange('payment', $payment_id, $date, $amount, $account_id, $error)) {
        http_response_code(400);
        $return_arr['success'] = "False";
        $return_arr['message'] = $error . '.';
        echo json_encode($return_arr);
        exit();
    }
    // Moved to another account - out of the deposit it was in
    transferDepositRelease('payment', $payment_id, $account_id);

    $date_sql = escapeSql($date);
    $method_sql = escapeSql($payment_method);
    $reference_sql = escapeSql($reference);

    $update_sql = mysqli_query($mysqli, "UPDATE payments SET payment_date = '$date_sql', payment_amount = $amount, payment_account_id = $account_id,
        payment_method = '$method_sql', payment_reference = '$reference_sql' WHERE payment_id = $payment_id");

    if ($update_sql) {
        $update_count = 1;

        // A changed amount can leave the invoice paid, part paid or owing again
        updateInvoiceStatusFromPayments($invoice_id);

        $money = escapeSql(billingMoney($amount, $payment_row['invoice_currency_code']));
        logAudit("Payment", "Edit", "Payment on invoice " . escapeSql($reference_text) . " edited to $money via API ($api_key_name)", intval($payment_row['invoice_client_id']), $invoice_id);
        logAudit("API", "Success", "Edited a payment on invoice " . escapeSql($reference_text) . " via API ($api_key_name)", intval($payment_row['invoice_client_id']));
    }
}

// Output
require_once '../update_output.php';
