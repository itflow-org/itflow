<?php

/*
 * ITFlow - GET/POST request handler for credits & payments
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_payment'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);
    enforceUserPermission('module_financial', 2);

    $invoice_id = intval($_POST['invoice_id']);
    $balance = floatval($_POST['balance']);
    $date = escapeSql($_POST['date']);
    $amount = floatval($_POST['amount']);
    $account = intval($_POST['account']);
    $currency_code = escapeSql($_POST['currency_code']);
    $payment_method = escapeSql($_POST['payment_method']);
    $reference = escapeSql($_POST['reference']);
    $email_receipt = intval($_POST['email_receipt']);

    $client_id = intval(getFieldById('invoices', $invoice_id, 'invoice_client_id'));

    enforceClientAccess();

    //Check to see if amount entered is greater than the balance of the invoice
    if ($amount > $balance) {
        flashAlert("Payment can not be more than the balance", 'error');
        redirect();
    } else {
        mysqli_query($mysqli,"INSERT INTO payments SET payment_date = '$date', payment_amount = $amount, payment_currency_code = '$currency_code', payment_account_id = $account, payment_method = '$payment_method', payment_reference = '$reference', payment_invoice_id = $invoice_id");

        // Get Payment ID for reference
        $payment_id = mysqli_insert_id($mysqli);

        //Add up all the payments for the invoice and get the total amount paid to the invoice
        $sql_total_payments_amount = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS payments_amount FROM payments WHERE payment_invoice_id = $invoice_id");
        $row = mysqli_fetch_assoc($sql_total_payments_amount);
        $total_payments_amount = floatval($row['payments_amount']);

        //Get the invoice total
        $sql = mysqli_query($mysqli,"SELECT invoice_amount, invoice_currency_code, invoice_number, invoice_prefix FROM invoices WHERE invoice_id = $invoice_id");
        $row = mysqli_fetch_assoc($sql);
        $invoice_amount = floatval($row['invoice_amount']);
        $invoice_prefix = escapeSql($row['invoice_prefix']);
        $invoice_number = intval($row['invoice_number']);
        $invoice_currency_code = escapeSql($row['invoice_currency_code']);

        //Calculate the Invoice balance
        $invoice_balance = $invoice_amount - $total_payments_amount;

        //Determine if invoice has been paid then set the status accordingly
        $invoice_status = $invoice_balance == 0 ? "Paid" : "Partial";

        //Update Invoice Status
        mysqli_query($mysqli,"UPDATE invoices SET invoice_status = '$invoice_status' WHERE invoice_id = $invoice_id");

        //Add Payment to History
        mysqli_query($mysqli,"INSERT INTO history SET history_status = '$invoice_status', history_description = 'Payment added', history_invoice_id = $invoice_id");

        logAudit("Invoice", "Payment", "Payment amount of " . numfmt_format_currency($currency_format, $amount, $invoice_currency_code) . " added to invoice $invoice_prefix$invoice_number", $client_id, $invoice_id);

        // The client's receipt - functions/billing_email.php
        if ($email_receipt == 1) {
            $receipt_to = billingPaymentSend($invoice_id, $amount, ['date' => $date, 'method' => stripslashes($payment_method), 'reference' => stripslashes($reference)]);
            if ($receipt_to) {
                logAudit("Invoice", "Payment", "Payment receipt for invoice $invoice_prefix$invoice_number queued to " . escapeSql(implode(', ', $receipt_to)), $client_id, $invoice_id);
            }
        }

        triggerCustomAction('invoice_pay', $invoice_id);

        flashAlert("Payment amount <strong>" . numfmt_format_currency($currency_format, $amount, $invoice_currency_code) . "</strong> added");

        redirect();

    }

}

if (isset($_POST['edit_payment'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 3);
    enforceUserPermission('module_financial', 3);

    $payment_id = intval($_POST['payment_id']);
    $date = escapeSql($_POST['date']);
    $amount = floatval($_POST['amount']);
    $account = intval($_POST['account']);
    $payment_method = escapeSql($_POST['payment_method']);
    $reference = escapeSql($_POST['reference']);

    // payments has no client column - the client comes from the invoice the payment sits on
    $client_id = intval(getFieldById('invoices', intval(getFieldById('payments', $payment_id, 'payment_invoice_id')), 'invoice_client_id'));

    enforceClientAccess();

    // Cleared on a reconciled statement - its date, amount and account stay
    if (!reconcileCheckChange('payment', $payment_id, $date, $amount, $account, $error)) {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }
    // Moved to another account - out of the deposit it was in
    transferDepositRelease('payment', $payment_id, $account);

    mysqli_query($mysqli,"UPDATE payments SET payment_date = '$date', payment_amount = $amount, payment_account_id = $account, payment_method = '$payment_method', payment_reference = '$reference' WHERE payment_id = $payment_id");

    // A changed amount can leave the invoice paid, part paid or owing again
    updateInvoiceStatusFromPayments(intval(getFieldById('payments', $payment_id, 'payment_invoice_id')));

    logAudit("Payment", "Edit", "Payment edited amount of " . numfmt_format_currency($currency_format, $amount, $session_company_currency));

    flashAlert("Payment edited to amount <strong>" . numfmt_format_currency($currency_format, $amount, $session_company_currency) . "</strong> added");

    redirect();

}

/*
Apply Credit Not ready for use 2025-08-27 - JQ

if (isset($_POST['apply_credit'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);
    enforceUserPermission('module_financial', 2);

    $invoice_id = intval($_POST['invoice_id']);
    $credit_amount_applied = floatval($_POST['credit_amount_applied']);

    $sql = mysqli_query($mysqli, "SELECT invoice_client_id, invoice_credit_amount, invoice_number, invoice_prefix, invoice_status FROM invoices LEFT JOIN clients ON invoice_client_id = client_id WHERE invoice_id = $invoice_id");
    $row = mysqli_fetch_assoc($sql);

    $invoice_prefix = escapeSql($row['invoice_prefix']);
    $invoice_number = intval($row['invoice_number']);
    $invoice_status = escapeSql($row['invoice_status']);
    $invoice_credit_amount = floatval($row['invoice_credit_amount']);
    $invoice_amount = floatval('invoice_amount');
    $client_id = intval($row['invoice_client_id']);

    enforceClientAccess();

    // Get Credit Balance
    $sql_credit_balance = mysqli_query($mysqli, "SELECT SUM(credit_amount) AS credit_balance FROM credits WHERE credit_client_id = $client_id");
    $row = mysqli_fetch_assoc($sql_credit_balance);

    $credit_balance = floatval($row['credit_balance']);

    // Get Invoice Balance
    $sql_amount_paid = mysqli_query($mysqli, "SELECT SUM(payment_amount) AS amount_paid FROM payments WHERE payment_invoice_id = $invoice_id");
    $row = mysqli_fetch_assoc($sql_amount_paid);
    $amount_paid = floatval($row['amount_paid']);

    $invoice_balance = $invoice_amount - $amount_paid;

    // Get Credit Tally applied to invoice
    $sql_credit_tally = mysqli_query($mysqli, "SELECT SUM(credit_tally) AS credit_balance FROM credits WHERE credit_invoice_id = $invoice_id");
    $row = mysqli_fetch_assoc($sql_credit_tally);

    $credit_tally = floatval($row['credit_tally']);

    // Check to see if amount entered is greater than the balance of the invoice
    if ($credit_amount_applied > $invoice_balance) {
        flashAlert("Credit can not be more than the balance", 'alert');
        redirect();
    }

    // Check to see if amount entered is greater than the credit balance
    if ($credit_amount_applied > $credit_balance) {
        flashAlert("Credit can not be more than the available credit", 'alert');
        redirect();
    }

    // Insert a new credit usage record linked to the invoice
    mysqli_query($mysqli, "
        INSERT INTO credits SET
            credit_amount = -$amount,
            credit_type = 'usage',
            credit_created_by = $session_user_id,
            credit_client_id = $client_id,
            credit_invoice_id = $invoice_id
    ");

    $new_invoice_amount = $invoice_amount - $credit_amount_applied;

    // Calculate updated invoice credit sum
    $result = mysqli_query($mysqli, "
        SELECT SUM(credit_amount) AS credit_total
        FROM credits
        WHERE credit_invoice_id = $invoice_id
    ");
    $total_credit_applied = floatval(mysqli_fetch_assoc($result)['credit_total']);

    // Get invoice amount
    $invoice_amount = floatval(getFieldByID('invoices', $invoice_id, 'invoice_amount'));

    // Determine new status
    $invoice_due = $invoice_amount + $total_credit_applied;
    $invoice_status = ($invoice_due <= 0) ? 'Paid' : 'Partial';

    // Update the invoice credit amount
    mysqli_query($mysqli, "
        UPDATE invoices
        SET invoice_credit_amount = $total_credit_applied
        WHERE invoice_id = $invoice_id
    ");

    // Update invoice status only (not invoice_credit_amount)
    mysqli_query($mysqli, "UPDATE invoices SET invoice_status = '$invoice_status' WHERE invoice_id = $invoice_id");

    // Log credit application in history
    mysqli_query($mysqli, "
        INSERT INTO history SET
            history_status = '$invoice_status',
            history_description = 'Credit applied',
            history_invoice_id = $invoice_id
    ");

    logAudit("Invoice", "Payment", "Credit " . numfmt_format_currency($currency_format, $amount, $session_company_currency) . " applied to invoice $invoice_prefix$invoice_number", $client_id, $invoice_id);

    triggerCustomAction('invoice_pay', $invoice_id);

    flashAlert("Credit amount <strong>" . numfmt_format_currency($currency_format, $amount, $session_company_currency) . "</strong> applied");

    redirect();

}

*/

if (isset($_POST['add_payment_stripe'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);
    enforceUserPermission('module_financial', 2);

    $invoice_id = intval($_POST['invoice_id']);
    $saved_payment_id = intval($_POST['saved_payment_id']);

    // Get invoice details
    $sql = mysqli_query($mysqli,"SELECT client_id, client_name, contact_email, contact_extension, contact_mobile,
        contact_mobile_country_code, contact_name, contact_phone, contact_phone_country_code,
        invoice_amount, invoice_currency_code, invoice_number, invoice_prefix, invoice_status,
        invoice_url_key FROM invoices
            LEFT JOIN clients ON invoice_client_id = client_id
            LEFT JOIN contacts ON client_id = contact_client_id AND contact_primary = 1
            WHERE invoice_id = $invoice_id"
    );
    $row = mysqli_fetch_assoc($sql);
    $invoice_number = intval($row['invoice_number']);
    $invoice_status = escapeSql($row['invoice_status']);
    $invoice_amount = floatval($row['invoice_amount']);
    $invoice_prefix = escapeSql($row['invoice_prefix']);
    $invoice_number = intval($row['invoice_number']);
    $invoice_url_key = escapeSql($row['invoice_url_key']);
    $invoice_currency_code = escapeSql($row['invoice_currency_code']);
    $client_id = intval($row['client_id']);
    $client_name = escapeSql($row['client_name']);
    $contact_name = escapeSql($row['contact_name']);
    $contact_email = escapeSql($row['contact_email']);
    $contact_phone = escapeSql(formatPhoneNumber($row['contact_phone'], $row['contact_phone_country_code']));
    $contact_extension = preg_replace("/[^0-9]/", '',$row['contact_extension']);
    $contact_mobile = escapeSql(formatPhoneNumber($row['contact_mobile'], $row['contact_mobile_country_code']));

    enforceClientAccess();

    // Get ITFlow company details
    $sql = mysqli_query($mysqli,"SELECT company_address, company_city, company_country, company_email, company_name, company_phone,
        company_phone_country_code, company_state, company_website, company_zip FROM companies WHERE company_id = 1");
    $row = mysqli_fetch_assoc($sql);
    $company_name = escapeSql($row['company_name']);
    $company_country = escapeSql($row['company_country']);
    $company_address = escapeSql($row['company_address']);
    $company_city = escapeSql($row['company_city']);
    $company_state = escapeSql($row['company_state']);
    $company_zip = escapeSql($row['company_zip']);
    $company_phone = escapeSql(formatPhoneNumber($row['company_phone'], $row['company_phone_country_code']));
    $company_email = escapeSql($row['company_email']);
    $company_website = escapeSql($row['company_website']);

    // Sanitize Config vars from get_settings.php
    $config_invoice_from_name = escapeSql($config_invoice_from_name);
    $config_invoice_from_email = escapeSql($config_invoice_from_email);

    // Get Client Payment Details
    $sql = mysqli_query($mysqli, "SELECT payment_provider_account, payment_provider_client, payment_provider_private_key,
        payment_provider_public_key, saved_payment_description, saved_payment_provider_method FROM client_saved_payment_methods LEFT JOIN payment_providers ON saved_payment_provider_id = payment_provider_id LEFT JOIN client_payment_provider ON saved_payment_client_id = client_id WHERE saved_payment_id = $saved_payment_id LIMIT 1");
    $row = mysqli_fetch_assoc($sql);

    $public_key = escapeSql($row['payment_provider_public_key']);
    $private_key = escapeSql($row['payment_provider_private_key']);
    $account_id = intval($row['payment_provider_account']);
    $payment_provider_client = escapeSql($row['payment_provider_client']);
    $saved_payment_method = escapeSql($row['saved_payment_provider_method']);
    $saved_payment_description = escapeSql($row['saved_payment_description']);

    // Sanity checks
    if (!$payment_provider_client || !$saved_payment_method) {
        flashAlert("Stripe not enabled or no client card saved", 'error');
        redirect();
    } elseif ($invoice_status !== 'Sent' && $invoice_status !== 'Viewed') {
        flashAlert("Invalid invoice state (draft/partial/paid/not billable)", 'error');
        redirect();
    } elseif ($invoice_amount == 0) {
        flashAlert("Invalid invoice amount", 'error');
        redirect();
    }

    // Initialize Stripe
    require_once __DIR__ . '/../../includes/stripe_init.php';
    $stripe = new \Stripe\StripeClient($private_key);

    $balance_to_pay = round($invoice_amount, 2);
    $pi_description = "ITFlow: $client_name payment of $invoice_currency_code $balance_to_pay for $invoice_prefix$invoice_number";

    // Create a payment intent
    try {
        $payment_intent = $stripe->paymentIntents->create([
            'amount' => intval(round($balance_to_pay * 100)), // Times by 100 as Stripe expects values in cents
            'currency' => $invoice_currency_code,
            'customer' => $payment_provider_client,
            'payment_method' => $saved_payment_method,
            'off_session' => true,
            'confirm' => true,
            'description' => $pi_description,
            'metadata' => [
                'itflow_client_id' => $client_id,
                'itflow_client_name' => $client_name,
                'itflow_invoice_number' => $invoice_prefix . $invoice_number,
                'itflow_invoice_id' => $invoice_id,
            ]
        ]);

        // Get details from PI
        $pi_id = escapeSql($payment_intent->id);
        $pi_date = date('Y-m-d', $payment_intent->created);
        $pi_amount_paid = floatval(($payment_intent->amount_received / 100));
        $pi_currency = strtoupper(escapeSql($payment_intent->currency));
        $pi_livemode = $payment_intent->livemode;

    } catch (Exception $e) {
        $error = $e->getMessage();
        error_log("Stripe payment error - encountered exception during payment intent for invoice ID $invoice_id / $invoice_prefix$invoice_number: $error");
        logApp("Stripe", "error", "Exception during PI for invoice ID $invoice_id: $error");
    }

    if ($payment_intent->status == "succeeded" && intval($balance_to_pay) == intval($pi_amount_paid)) {

        // Update Invoice Status
        mysqli_query($mysqli, "UPDATE invoices SET invoice_status = 'Paid' WHERE invoice_id = $invoice_id");

        // Add Payment to History
        mysqli_query($mysqli, "INSERT INTO payments SET payment_date = '$pi_date', payment_amount = $pi_amount_paid, payment_currency_code = '$pi_currency', payment_account_id = $account_id, payment_method = 'Stripe', payment_reference = 'Stripe - $pi_id', payment_invoice_id = $invoice_id");
        mysqli_query($mysqli, "INSERT INTO history SET history_status = 'Paid', history_description = 'Online Payment added (agent)', history_invoice_id = $invoice_id");

        // The client's receipt, and the paid notification address - functions/billing_email.php
        if (!empty($config_smtp_provider)) {
            $receipt_to = billingPaymentSend($invoice_id, $pi_amount_paid, ['date' => $pi_date, 'method' => 'Card', 'reference' => "Stripe - $pi_id", 'how' => 'Card'], true);
            if ($receipt_to) {
                logAudit("Invoice", "Payment", "Payment receipt for invoice $invoice_prefix$invoice_number queued to " . escapeSql(implode(', ', $receipt_to)), $client_id, $invoice_id);
            }
        }

        // Log info
        $extended_log_desc = '';
        if (!$pi_livemode) {
            $extended_log_desc = '(DEV MODE)';
        }

        // Notify/log
        appNotify("Invoice Paid", "Invoice $invoice_prefix$invoice_number automatically paid", "/agent/invoice.php?invoice_id=$invoice_id", $client_id);
        logAudit("Invoice", "Payment", "$session_name initiated Stripe payment amount of " . numfmt_format_currency($currency_format, $invoice_amount, $invoice_currency_code) . " added to invoice $invoice_prefix$invoice_number - $pi_id $extended_log_desc", $client_id, $invoice_id);
        triggerCustomAction('invoice_pay', $invoice_id);

        flashAlert("Payment amount <strong>" . numfmt_format_currency($currency_format, $invoice_amount, $invoice_currency_code) . "</strong> added");

        redirect();

    } else {
        mysqli_query($mysqli, "INSERT INTO history SET history_status = 'Payment failed', history_description = 'Stripe pay failed due to payment error', history_invoice_id = $invoice_id");

        logAudit("Invoice", "Payment", "Failed online payment amount of invoice $invoice_prefix$invoice_number due to Stripe payment error", $client_id, $invoice_id);
        flashAlert("Payment failed", 'error');

        redirect();
    }

}

if (isset($_POST['add_bulk_payment'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);
    enforceUserPermission('module_financial', 2);

    $client_id = intval($_POST['client_id']);
    $date = escapeSql($_POST['date']);
    $bulk_payment_amount = floatval($_POST['amount']);
    $bulk_payment_amount_static = floatval($_POST['amount']);
    $total_account_balance = floatval($_POST['balance']);
    $account = intval($_POST['account']);
    $currency_code = escapeSql($_POST['currency_code']);
    $payment_method = escapeSql($_POST['payment_method']);
    $reference = escapeSql($_POST['reference']);
    $email_receipt = intval($_POST['email_receipt']);

    enforceClientAccess();

    // Check if bulk_payment_amount exceeds total_account_balance
    if ($bulk_payment_amount > $total_account_balance) {
        flashAlert("Payment exceeds Client Balance.", 'error');
        redirect();
    }

    // Get Invoices
    // Only invoices still waiting on money - not drafts, not paid, not cancelled or non-billable
    $sql_invoices = "SELECT * FROM invoices
        WHERE invoice_status IN ('Sent', 'Viewed', 'Partial')
        AND invoice_client_id = $client_id
        ORDER BY invoice_number ASC";
    $result_invoices = mysqli_query($mysqli, $sql_invoices);

    // Loop Through Each Invoice
    $applied_payments = [];
    while ($row = mysqli_fetch_assoc($result_invoices)) {
        $invoice_id = intval($row['invoice_id']);
        $invoice_prefix = escapeSql($row['invoice_prefix']);
        $invoice_number = intval($row['invoice_number']);
        $invoice_amount = floatval($row['invoice_amount']);
        $invoice_url_key = escapeSql($row['invoice_url_key']);
        $invoice_balance_query = "SELECT SUM(payment_amount) AS amount_paid FROM payments WHERE payment_invoice_id = $invoice_id";
        $result_amount_paid = mysqli_query($mysqli, $invoice_balance_query);
        $row_amount_paid = mysqli_fetch_assoc($result_amount_paid);
        $amount_paid = floatval($row_amount_paid['amount_paid']);
        $invoice_balance = $invoice_amount - $amount_paid;

        if ($bulk_payment_amount <= 0) {
            break; // Exit the loop if no payment amount is left
        }

        // Nothing owed on this one - a zero-value invoice, or one paid without its status catching up
        if ($invoice_balance <= 0) {
            continue;
        }

        if ($bulk_payment_amount >= $invoice_balance) {
            $payment_amount = $invoice_balance;
            $invoice_status = "Paid";
        } else {
            $payment_amount = $bulk_payment_amount;
            $invoice_status = "Partial";
        }

        // Subtract the payment amount from the bulk payment amount
        $bulk_payment_amount -= $payment_amount;

        // Get Invoice Remain Balance
        $remaining_invoice_balance = $invoice_balance - $payment_amount;

        // Add Payment
        $payment_query = "INSERT INTO payments (payment_date, payment_amount, payment_currency_code, payment_account_id, payment_method, payment_reference, payment_invoice_id) VALUES ('{$date}', {$payment_amount}, '{$currency_code}', {$account}, '{$payment_method}', '{$reference}', {$invoice_id})";
        mysqli_query($mysqli, $payment_query);
        $payment_id = mysqli_insert_id($mysqli);

        // Update Invoice Status
        $update_invoice_query = "UPDATE invoices SET invoice_status = '{$invoice_status}' WHERE invoice_id = {$invoice_id}";
        mysqli_query($mysqli, $update_invoice_query);

        // Add Payment to History
        $history_description = "Payment added";
        $add_history_query = "INSERT INTO history (history_status, history_description, history_invoice_id) VALUES ('{$invoice_status}', '{$history_description}', {$invoice_id})";
        mysqli_query($mysqli, $add_history_query);

        // For the receipt - what went on each invoice
        $applied_payments[] = ['invoice_id' => $invoice_id, 'amount' => $payment_amount];

        triggerCustomAction('invoice_pay', $invoice_id);

    } // End Invoice Loop

    // The client's receipt, listing each invoice paid - functions/billing_email.php
    $alert_message = '';
    if ($email_receipt == 1 && !empty($applied_payments)) {
        $applied = [];
        foreach ($applied_payments as $entry) {
            $applied[] = ['invoice' => billingRow('invoice', $entry['invoice_id'], false), 'amount' => $entry['amount']];
        }
        [$subject, $email] = billingPaymentEmail($applied, ['date' => $date, 'method' => stripslashes($payment_method), 'reference' => stripslashes($reference), 'currency' => $currency_code]);
        $receipt_to = billingEmailQueue(billingEmailRecipients($client_id), $subject, $email);
        if ($receipt_to) {
            foreach ($applied_payments as $entry) {
                billingLog('invoice', $entry['invoice_id'], 'Sent', 'Payment receipt emailed to ' . implode(', ', $receipt_to));
            }
            logAudit("Payment", "Email", "Bulk Payment receipt for multiple Invoices queued to " . escapeSql(implode(', ', $receipt_to)), $client_id);
            $alert_message .= "Email receipt queued and ";
        }
    }

    logAudit("Invoice", "Payment", "Bulk Payment amount of " . numfmt_format_currency($currency_format, $bulk_payment_amount_static, $currency_code) . " applied to multiple invoices", $client_id);

    flashAlert("$alert_message Bulk Payment added");

    redirect();

}

if (isset($_GET['delete_payment'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 3);
    enforceUserPermission('module_financial', 3);

    $payment_id = intval($_GET['delete_payment']);

    // payments has no client column - the client comes from the invoice the payment sits on
    $sql = mysqli_query($mysqli,"SELECT invoice_client_id, invoice_number, invoice_prefix, payment_invoice_id FROM payments
        LEFT JOIN invoices ON payment_invoice_id = invoice_id
        WHERE payment_id = $payment_id
        LIMIT 1"
    );
    $row = mysqli_fetch_assoc($sql);
    $invoice_id = intval($row['payment_invoice_id']);
    $invoice_prefix = escapeSql($row['invoice_prefix']);
    $invoice_number = intval($row['invoice_number']);
    $client_id = intval($row['invoice_client_id']);

    enforceClientAccess();

    if (!reconcileCheckDelete('payment', $payment_id, $error)) {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }

    mysqli_query($mysqli,"DELETE FROM payments WHERE payment_id = $payment_id");

    // Recalculate from what is left rather than from the pre-delete total
    $invoice_status = updateInvoiceStatusFromPayments($invoice_id);

    mysqli_query($mysqli,"INSERT INTO history SET history_status = '$invoice_status', history_description = 'Payment deleted', history_invoice_id = $invoice_id");

    logAudit("Invoice", "Edit", "$session_name deleted Payment on Invoice $invoice_prefix$invoice_number", $client_id, $invoice_id);

    flashAlert("Payment deleted", 'error');

    redirect();

}

if (isset($_GET['refund_payment_stripe'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 3);
    enforceUserPermission('module_financial', 3);

    $payment_id = intval($_GET['refund_payment_stripe']);

    // payments has no client column - the client comes from the invoice the payment sits on
    $sql = mysqli_query($mysqli,"SELECT invoice_client_id, invoice_number, invoice_prefix, payment_invoice_id FROM payments
        LEFT JOIN invoices ON payment_invoice_id = invoice_id
        WHERE payment_id = $payment_id
        AND payment_method = 'Stripe'
        LIMIT 1"
    );
    $row = mysqli_fetch_assoc($sql);
    $invoice_id = intval($row['payment_invoice_id']);
    $invoice_prefix = escapeSql($row['invoice_prefix']);
    $invoice_number = intval($row['invoice_number']);
    $client_id = intval($row['invoice_client_id']);

    enforceClientAccess();

    // Refunding deletes the payment - not one cleared on a reconciled statement
    if (!reconcileCheckDelete('payment', $payment_id, $error)) {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }

    // Get Stripe Payment Intent ID
    $sql = mysqli_query($mysqli,"SELECT payment_amount, payment_reference FROM payments WHERE payment_id = $payment_id LIMIT 1");
    $row = mysqli_fetch_assoc($sql);
    if (preg_match('/(?<payment_intent>pi_[A-Za-z0-9]+)/', $row['payment_reference'], $matches)) {
        $stripe_payment_intent_id = $matches['payment_intent'];
    }
    // Only this payment's part - one card payment from the client portal can pay several invoices
    $refund_cents = (int) round(floatval($row['payment_amount']) * 100);
    
    // Get invoice details
    $sql = mysqli_query($mysqli,"SELECT client_id, client_name, contact_email, contact_extension, contact_mobile,
        contact_mobile_country_code, contact_name, contact_phone, contact_phone_country_code,
        invoice_amount, invoice_currency_code, invoice_number, invoice_prefix, invoice_status,
        invoice_url_key FROM invoices
            LEFT JOIN clients ON invoice_client_id = client_id
            LEFT JOIN contacts ON client_id = contact_client_id AND contact_primary = 1
            WHERE invoice_id = $invoice_id"
    );
    $row = mysqli_fetch_assoc($sql);
    $invoice_number = intval($row['invoice_number']);
    $invoice_status = escapeSql($row['invoice_status']);
    $invoice_amount = floatval($row['invoice_amount']);
    $invoice_prefix = escapeSql($row['invoice_prefix']);
    $invoice_number = intval($row['invoice_number']);
    $invoice_url_key = escapeSql($row['invoice_url_key']);
    $invoice_currency_code = escapeSql($row['invoice_currency_code']);
    $client_id = intval($row['client_id']);
    $client_name = escapeSql($row['client_name']);
    $contact_name = escapeSql($row['contact_name']);
    $contact_email = escapeSql($row['contact_email']);
    $contact_phone = escapeSql(formatPhoneNumber($row['contact_phone'], $row['contact_phone_country_code']));
    $contact_extension = preg_replace("/[^0-9]/", '',$row['contact_extension']);
    $contact_mobile = escapeSql(formatPhoneNumber($row['contact_mobile'], $row['contact_mobile_country_code']));

    $stripe_provider = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT payment_provider_private_key FROM payment_providers WHERE payment_provider_name = 'Stripe' LIMIT 1"));
    if (!$stripe_provider) {
        exit("Stripe not enabled / configured");
    }
    $stripe_secret_key = $stripe_provider['payment_provider_private_key'];

    // Initialize Stripe
    try {
        require_once __DIR__ . '/../../includes/stripe_init.php';
        $stripe = new \Stripe\StripeClient($stripe_secret_key);

        // Refund
        $refund = $stripe->refunds->create([
            'payment_intent' => $stripe_payment_intent_id,
            'amount' => $refund_cents,
        ]);
    } catch (Exception $e) {
        $error = $e->getMessage();
        error_log("Stripe refund error - encountered exception during refund for invoice ID $invoice_id / $invoice_prefix$invoice_number: $error");
        logApp("Stripe", "error", "Exception during refund for invoice ID $invoice_id: $error");
        flashAlert("Stripe refund failed: $error", 'error');
        redirect();
    }

    mysqli_query($mysqli,"DELETE FROM payments WHERE payment_id = $payment_id");

    // Recalculate from what is left rather than from the pre-delete total
    $invoice_status = updateInvoiceStatusFromPayments($invoice_id);

    mysqli_query($mysqli,"INSERT INTO history SET history_status = '$invoice_status', history_description = 'Payment $stripe_payment_intent_id deleted and refunded', history_invoice_id = $invoice_id");
  
    logAudit("Invoice", "Edit", "$session_name refunded and deleted Payment $stripe_payment_intent_id on Invoice $invoice_prefix$invoice_number", $client_id, $invoice_id);

    flashAlert("Payment deleted and refunded in Stripe", 'error');

    redirect();

}

/* Email a payment's receipt to the client's billing contacts again - from its quick look. */
if (isset($_GET['payment_send_receipt'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);
    enforceUserPermission('module_financial');

    $row = incomePaymentRow(intval($_GET['payment_send_receipt']));
    if (!$row || $row['invoice_number'] === null) {
        flashAlert('That payment no longer exists, or is not one you can see', 'error');
        redirect();
    }
    $receipt_to = billingPaymentSend($row['invoice_id'], floatval($row['payment_amount']), [
        'date' => $row['payment_date'],
        'method' => (string) $row['payment_method'],
        'reference' => (string) $row['payment_reference'],
    ]);
    if (!$receipt_to) {
        flashAlert(escapeHtml($row['client_name']) . ' has no billing contact with an email to send the receipt to', 'error');
        redirect();
    }

    logAudit("Invoice", "Payment", "Payment receipt for invoice " . escapeSql($row['invoice_prefix'] . $row['invoice_number']) . " queued to " . escapeSql(implode(', ', $receipt_to)), intval($row['invoice_client_id']), intval($row['invoice_id']));

    flashAlert("Receipt for <strong>" . escapeHtml(incomePaymentLabel($row)) . "</strong> queued to " . escapeHtml(implode(', ', $receipt_to)));

    redirect();

}
