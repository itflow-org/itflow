<?php
/*
 * API - Payments - Create
 * POST /api/v1/payments/create.php
 *
 * Record a payment on an invoice, as Add Payment does on its page - on one
 * that is sent and still owed on, no more than is owed, into one of your
 * accounts. The invoice becomes Partial or Paid, and the client's receipt goes
 * to its primary and billing contacts unless email_receipt is 0 (or ITFlow
 * sends no mail and it was left out). Paid in full,
 * a ticket waiting on it is told and a lead becomes a client (invoicePaidFollowUp(),
 * run by the invoice_pay custom action).
 *
 * Parameters (POST, JSON body):
 *   api_key         required - Your API key
 *   invoice_id      required - The invoice
 *   client_id       the invoice's client - needed when the key's user is limited to certain clients
 *   amount          optional - What was paid; all that is owed if left out
 *   date            optional - Y-m-d, today if left out
 *   account_id      optional - The account it went into; the default payment account if left out
 *   payment_method  optional - How it was paid, one of your payment methods; the default if left out
 *   reference       optional - A check number, transaction ID
 *   email_receipt   optional - 1 emails the receipt, 0 doesn't; left out, it goes when ITFlow sends mail
 *
 * Answer: insert_id, and invoice_status, balance (what is still owed) and receipt_to.
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once "../../../includes/load_global_settings.php";
require_once "../../../includes/load_company_settings.php";

// Payments are Financial's, with Sales at Read to see the invoices (billingMoneyCan())
if (!billingMoneyCan('take')) {
    apiDeny(intval(lookupUserPermission('module_sales')) < 1 ? apiPermissionText('module_sales', 1) : apiPermissionText('module_financial', billingMoneyRules()['take'][0]));
}

$invoice_id = intval($_POST['invoice_id'] ?? 0);

$insert_id = false;

$invoice = $invoice_id ? billingRow('invoice', $invoice_id) : null;
if ($invoice && $client_id != 0 && intval($invoice['invoice_client_id']) !== $client_id) {
    $invoice = null;
}

if ($invoice) {

    $client_id = intval($invoice['invoice_client_id']);
    $reference_text = billingNumber('invoice', $invoice);
    $currency = (string) $invoice['invoice_currency_code'];

    // Sent and still owed on, as the Add Payment button needs
    $state = invoiceState($invoice, invoicePaid($invoice_id));
    if (!invoiceCanPay($invoice, $state)) {
        http_response_code(400);
        $return_arr['success'] = "False";
        $return_arr['message'] = "$reference_text has nothing owed on it - it is " . strtolower($invoice['invoice_status']) . ".";
        echo json_encode($return_arr);
        exit();
    }

    require_once 'payment_model.php';

    // What it leaves out - all that is owed, today, and your defaults
    $amount = $payment_set['amount'] ?? round($state['balance'], 2);
    $date = $payment_set['date'] ?? date('Y-m-d');
    $account_id = $payment_set['account_id'] ?? intval($config_default_payment_account);
    $payment_method = $payment_set['payment_method'] ?? (string) $config_default_payment_method;
    $reference = $payment_set['reference'] ?? '';

    $refuse = '';
    if ((int) round($amount * 100) > (int) round($state['balance'] * 100)) {
        $refuse = 'amount is more than is owed on ' . $reference_text . ' - ' . billingMoney($state['balance'], $currency) . '.';
    } elseif (!isset(paymentAccounts()[$account_id])) {
        $refuse = 'account_id is needed - the account it went into. There is no default payment account (Admin > Settings > Invoices and billing).';
    } elseif ($payment_method === '' || !in_array($payment_method, paymentMethods('in'), true)) {
        $refuse = 'payment_method is needed - how it was paid, one of: ' . implode(', ', paymentMethods('in')) . '.';
    }
    if ($refuse !== '') {
        http_response_code(400);
        $return_arr['success'] = "False";
        $return_arr['message'] = $refuse;
        echo json_encode($return_arr);
        exit();
    }

    $date_sql = escapeSql($date);
    $currency_sql = escapeSql($currency);
    $method_sql = escapeSql($payment_method);
    $reference_sql = escapeSql($reference);

    mysqli_query($mysqli, "INSERT INTO payments SET payment_date = '$date_sql', payment_amount = $amount, payment_currency_code = '$currency_sql', payment_account_id = $account_id,
        payment_method = '$method_sql', payment_reference = '$reference_sql', payment_invoice_id = $invoice_id");
    $payment_id = intval(mysqli_insert_id($mysqli));

    if ($payment_id) {
        $insert_id = $payment_id;

        $status = updateInvoiceStatusFromPayments($invoice_id);
        $money = billingMoney($amount, $currency);
        billingLog('invoice', $invoice_id, $status, "Payment of $money added via the API (" . html_entity_decode($api_key_name, ENT_QUOTES, 'UTF-8') . ")");

        logAudit("Invoice", "Payment", "Payment amount of " . escapeSql($money) . " added to invoice " . escapeSql($reference_text) . " via API ($api_key_name)", $client_id, $invoice_id);
        logAudit("API", "Success", "Added a payment of " . escapeSql($money) . " to invoice " . escapeSql($reference_text) . " via API ($api_key_name)", $client_id);

        // The client's receipt (functions/billing_email.php)
        $receipt_to = [];
        // Left out, it goes when ITFlow sends mail - as the switch on Add Payment shows then
        if (array_key_exists('email_receipt', $_POST) ? !empty($_POST['email_receipt']) : !empty($config_smtp_provider)) {
            $receipt_to = billingPaymentSend($invoice_id, $amount, ['date' => $date, 'method' => $payment_method, 'reference' => $reference]);
            if ($receipt_to) {
                logAudit("Invoice", "Payment", "Payment receipt for invoice " . escapeSql($reference_text) . " queued to " . escapeSql(implode(', ', $receipt_to)), $client_id, $invoice_id);
            }
        }

        triggerCustomAction('invoice_pay', $invoice_id);

        $return_arr['invoice_status'] = (string) getFieldById('invoices', $invoice_id, 'invoice_status');
        $return_arr['balance'] = round(floatval($invoice['invoice_amount']) - invoicePaid($invoice_id), 2);
        $return_arr['receipt_to'] = array_values($receipt_to);
    }
}

// Output
require_once '../create_output.php';
