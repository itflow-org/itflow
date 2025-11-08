<?php

/*
 * ITFlow - GET/POST request handler for credits & payments
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_payment'])) {
    
    enforceUserPermission('module_sales', 2);
    enforceUserPermission('module_financial', 2);

    $invoice_id = intval($_POST['invoice_id']);
    $balance = floatval($_POST['balance']);
    $date = sanitizeInput($_POST['date']);
    $amount = floatval($_POST['amount']);
    $account = intval($_POST['account']);
    $currency_code = sanitizeInput($_POST['currency_code']);
    $payment_method = sanitizeInput($_POST['payment_method']);
    $reference = sanitizeInput($_POST['reference']);
    $email_receipt = intval($_POST['email_receipt']);

    //Check to see if amount entered is greater than the balance of the invoice
    if ($amount > $balance) {
        flash_alert("Payment can not be more than the balance", 'error');
        redirect();
    } else {
        mysqli_query($mysqli,"INSERT INTO payments SET payment_date = '$date', payment_amount = $amount, payment_currency_code = '$currency_code', payment_account_id = $account, payment_method = '$payment_method', payment_reference = '$reference', payment_invoice_id = $invoice_id");

        // Get Payment ID for reference
        $payment_id = mysqli_insert_id($mysqli);

        //Add up all the payments for the invoice and get the total amount paid to the invoice
        $sql_total_payments_amount = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS payments_amount FROM payments WHERE payment_invoice_id = $invoice_id");
        $row = mysqli_fetch_array($sql_total_payments_amount);
        $total_payments_amount = floatval($row['payments_amount']);

        //Get the invoice total
        $sql = mysqli_query($mysqli,"SELECT * FROM invoices
            LEFT JOIN clients ON invoice_client_id = client_id
            LEFT JOIN contacts ON clients.client_id = contacts.contact_client_id AND contact_primary = 1
            WHERE invoice_id = $invoice_id"
        );

        $row = mysqli_fetch_array($sql);
        $invoice_amount = floatval($row['invoice_amount']);
        $invoice_prefix = sanitizeInput($row['invoice_prefix']);
        $invoice_number = intval($row['invoice_number']);
        $invoice_url_key = sanitizeInput($row['invoice_url_key']);
        $invoice_currency_code = sanitizeInput($row['invoice_currency_code']);
        $client_id = intval($row['client_id']);
        $client_name = sanitizeInput($row['client_name']);
        $contact_name = sanitizeInput($row['contact_name']);
        $contact_email = sanitizeInput($row['contact_email']);
        $contact_phone = sanitizeInput(formatPhoneNumber($row['contact_phone'], $row['contact_phone_country_code']));
        $contact_extension = preg_replace("/[^0-9]/", '',$row['contact_extension']);
        $contact_mobile = sanitizeInput(formatPhoneNumber($row['contact_mobile'], $row['contact_mobile_country_code']));

        $sql = mysqli_query($mysqli,"SELECT * FROM companies WHERE company_id = 1");
        $row = mysqli_fetch_array($sql);

        $company_name = sanitizeInput($row['company_name']);
        $company_country = sanitizeInput($row['company_country']);
        $company_address = sanitizeInput($row['company_address']);
        $company_city = sanitizeInput($row['company_city']);
        $company_state = sanitizeInput($row['company_state']);
        $company_zip = sanitizeInput($row['company_zip']);
        $company_phone = sanitizeInput(formatPhoneNumber($row['company_phone'], $row['company_phone_country_code']));
        $company_email = sanitizeInput($row['company_email']);
        $company_website = sanitizeInput($row['company_website']);
        $company_logo = sanitizeInput($row['company_logo']);

        // Sanitize Config vars from get_settings.php
        $config_invoice_from_name = sanitizeInput($config_invoice_from_name);
        $config_invoice_from_email = sanitizeInput($config_invoice_from_email);

        //Calculate the Invoice balance
        $invoice_balance = $invoice_amount - $total_payments_amount;

        $email_data = [];

        //Determine if invoice has been paid then set the status accordingly
        if ($invoice_balance == 0) {

            $invoice_status = "Paid";

            if ($email_receipt == 1) {

                $subject = "Payment Received - Invoice $invoice_prefix$invoice_number";
                $body = "Hello $contact_name,<br><br>We have received your payment in full for the amount of " . numfmt_format_currency($currency_format, $amount, $invoice_currency_code) . " for invoice <a href=\'https://$config_base_url/guest/guest_view_invoice.php?invoice_id=$invoice_id&url_key=$invoice_url_key\'>$invoice_prefix$invoice_number</a>. Please keep this email as a receipt for your records.<br><br>Amount Paid: " . numfmt_format_currency($currency_format, $amount, $invoice_currency_code) . "<br>Payment Method: $payment_method<br>Payment Reference: $reference<br><br>Thank you for your business!<br><br><br>--<br>$company_name - Billing Department<br>$config_invoice_from_email<br>$company_phone";

                // Queue Mail
                $email = [
                    'from' => $config_invoice_from_email,
                    'from_name' => $config_invoice_from_name,
                    'recipient' => $contact_email,
                    'recipient_name' => $contact_name,
                    'subject' => $subject,
                    'body' => $body
                ];

                $email_data[] = $email;

                // Add email to queue
                if (!empty($email)) {
                    addToMailQueue($email_data);
                }

                // Get Email ID for reference
                $email_id = mysqli_insert_id($mysqli);

                // Email Logging
                mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Sent', history_description = 'Payment Receipt sent to mail queue ID: $email_id!', history_invoice_id = $invoice_id");
                logAction("Invoice", "Payment", "Payment receipt for invoice $invoice_prefix$invoice_number queued to $contact_email Email ID: $email_id", $client_id, $invoice_id);

            }

        } else {

            $invoice_status = "Partial";

            if ($email_receipt == 1) {

                $subject = "Partial Payment Received - Invoice $invoice_prefix$invoice_number";
                $body = "Hello $contact_name,<br><br>We have received partial payment in the amount of " . numfmt_format_currency($currency_format, $amount, $invoice_currency_code) . " and it has been applied to invoice <a href=\'https://$config_base_url/guest/guest_view_invoice.php?invoice_id=$invoice_id&url_key=$invoice_url_key\'>$invoice_prefix$invoice_number</a>. Please keep this email as a receipt for your records.<br><br>Amount Paid: " . numfmt_format_currency($currency_format, $amount, $invoice_currency_code) . "<br>Payment Method: $payment_method<br>Payment Reference: $reference<br>Invoice Balance: " . numfmt_format_currency($currency_format, $invoice_balance, $invoice_currency_code) . "<br><br>Thank you for your business!<br><br><br>~<br>$company_name - Billing<br>$config_invoice_from_email<br>$company_phone";

                // Queue Mail
                $email = [
                    'from' => $config_invoice_from_email,
                    'from_name' => $config_invoice_from_name,
                    'recipient' => $contact_email,
                    'recipient_name' => $contact_name,
                    'subject' => $subject,
                    'body' => $body
                ];

                $email_data[] = $email;

                // Add email to queue
                if (!empty($email)) {
                    addToMailQueue($email_data);
                }

                // Get Email ID for reference
                $email_id = mysqli_insert_id($mysqli);

                // Email Logging
                mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Sent', history_description = 'Payment Receipt sent to mail queue ID: $email_id!', history_invoice_id = $invoice_id");
                logAction("Invoice", "Payment", "Payment receipt for invoice $invoice_prefix$invoice_number queued to $contact_email Email ID: $email_id", $client_id, $invoice_id);

            }

        }

        //Update Invoice Status
        mysqli_query($mysqli,"UPDATE invoices SET invoice_status = '$invoice_status' WHERE invoice_id = $invoice_id");

        //Add Payment to History
        mysqli_query($mysqli,"INSERT INTO history SET history_status = '$invoice_status', history_description = 'Payment added', history_invoice_id = $invoice_id");

        logAction("Invoice", "Payment", "Payment amount of " . numfmt_format_currency($currency_format, $amount, $invoice_currency_code) . " added to invoice $invoice_prefix$invoice_number", $client_id, $invoice_id);

        customAction('invoice_pay', $invoice_id);

        flash_alert("Payment amount <strong>" . numfmt_format_currency($currency_format, $amount, $invoice_currency_code) . "</strong> added");

        redirect();

    }

}

/*
Apply Credit Not ready for use 2025-08-27 - JQ

if (isset($_POST['apply_credit'])) {
    
    enforceUserPermission('module_sales', 2);
    enforceUserPermission('module_financial', 2);

    $invoice_id = intval($_POST['invoice_id']);
    $credit_amount_applied = floatval($_POST['credit_amount_applied']);

    $sql = mysqli_query($mysqli, "SELECT * FROM invoices LEFT JOIN clients ON invoice_client_id = client_id WHERE invoice_id = $invoice_id");
    $row = mysqli_fetch_array($sql);
    
    $invoice_prefix = sanitizeInput($row['invoice_prefix']);
    $invoice_number = intval($row['invoice_number']);
    $invoice_status = sanitizeInput($row['invoice_status']);
    $invoice_credit_amount = floatval($row['invoice_credit_amount']);
    $invoice_amount = floatval('invoice_amount');
    $client_id = intval($row['invoice_client_id']);

    // Get Credit Balance
    $sql_credit_balance = mysqli_query($mysqli, "SELECT SUM(credit_amount) AS credit_balance FROM credits WHERE credit_client_id = $client_id");
    $row = mysqli_fetch_array($sql_credit_balance);

    $credit_balance = floatval($row['credit_balance']);

    // Get Invoice Balance
    $sql_amount_paid = mysqli_query($mysqli, "SELECT SUM(payment_amount) AS amount_paid FROM payments WHERE payment_invoice_id = $invoice_id");
    $row = mysqli_fetch_array($sql_amount_paid);
    $amount_paid = floatval($row['amount_paid']);

    $invoice_balance = $invoice_amount - $amount_paid;

    // Get Credit Tally applied to invoice
    $sql_credit_tally = mysqli_query($mysqli, "SELECT SUM(credit_tally) AS credit_balance FROM credits WHERE credit_invoice_id = $invoice_id");
    $row = mysqli_fetch_array($sql_credit_tally);

    $credit_tally = floatval($row['credit_tally']);

    // Check to see if amount entered is greater than the balance of the invoice
    if ($credit_amount_applied > $invoice_balance) {
        flash_alert("Credit can not be more than the balance", 'alert');
        redirect();
    }

    // Check to see if amount entered is greater than the credit balance
    if ($credit_amount_applied > $credit_balance) {
        flash_alert("Credit can not be more than the available credit", 'alert');
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

    logAction("Invoice", "Payment", "Credit " . numfmt_format_currency($currency_format, $amount, $session_company_currency) . " applied to invoice $invoice_prefix$invoice_number", $client_id, $invoice_id);

    customAction('invoice_pay', $invoice_id);

    flash_alert("Credit amount <strong>" . numfmt_format_currency($currency_format, $amount, $session_company_currency) . "</strong> applied");

    redirect();

}

*/

if (isset($_POST['add_payment_stripe'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_sales', 2);
    enforceUserPermission('module_financial', 2);

    $invoice_id = intval($_POST['invoice_id']);
    $saved_payment_id = intval($_POST['saved_payment_id']);

    // Get invoice details
    $sql = mysqli_query($mysqli,"SELECT * FROM invoices
            LEFT JOIN clients ON invoice_client_id = client_id
            LEFT JOIN contacts ON client_id = contact_client_id AND contact_primary = 1
            WHERE invoice_id = $invoice_id"
    );
    $row = mysqli_fetch_array($sql);
    $invoice_number = intval($row['invoice_number']);
    $invoice_status = sanitizeInput($row['invoice_status']);
    $invoice_amount = floatval($row['invoice_amount']);
    $invoice_prefix = sanitizeInput($row['invoice_prefix']);
    $invoice_number = intval($row['invoice_number']);
    $invoice_url_key = sanitizeInput($row['invoice_url_key']);
    $invoice_currency_code = sanitizeInput($row['invoice_currency_code']);
    $client_id = intval($row['client_id']);
    $client_name = sanitizeInput($row['client_name']);
    $contact_name = sanitizeInput($row['contact_name']);
    $contact_email = sanitizeInput($row['contact_email']);
    $contact_phone = sanitizeInput(formatPhoneNumber($row['contact_phone'], $row['contact_phone_country_code']));
    $contact_extension = preg_replace("/[^0-9]/", '',$row['contact_extension']);
    $contact_mobile = sanitizeInput(formatPhoneNumber($row['contact_mobile'], $row['contact_mobile_country_code']));

    // Get ITFlow company details
    $sql = mysqli_query($mysqli,"SELECT * FROM companies WHERE company_id = 1");
    $row = mysqli_fetch_array($sql);
    $company_name = sanitizeInput($row['company_name']);
    $company_country = sanitizeInput($row['company_country']);
    $company_address = sanitizeInput($row['company_address']);
    $company_city = sanitizeInput($row['company_city']);
    $company_state = sanitizeInput($row['company_state']);
    $company_zip = sanitizeInput($row['company_zip']);
    $company_phone = sanitizeInput(formatPhoneNumber($row['company_phone'], $row['company_phone_country_code']));
    $company_email = sanitizeInput($row['company_email']);
    $company_website = sanitizeInput($row['company_website']);

    // Sanitize Config vars from get_settings.php
    $config_invoice_from_name = sanitizeInput($config_invoice_from_name);
    $config_invoice_from_email = sanitizeInput($config_invoice_from_email);

    // Get Client Payment Details
    $sql = mysqli_query($mysqli, "SELECT * FROM client_saved_payment_methods LEFT JOIN payment_providers ON saved_payment_provider_id = payment_provider_id LEFT JOIN client_payment_provider ON saved_payment_client_id = client_id WHERE saved_payment_id = $saved_payment_id LIMIT 1");
    $row = mysqli_fetch_array($sql);

    $public_key = sanitizeInput($row['payment_provider_public_key']);
    $private_key = sanitizeInput($row['payment_provider_private_key']);
    $account_id = intval($row['payment_provider_account']);
    $expense_category_id = intval($row['payment_provider_expense_category']);
    $expense_vendor_id = intval($row['payment_provider_expense_vendor']);
    $expense_percentage_fee = floatval($row['payment_provider_expense_percentage_fee']);
    $expense_flat_fee = floatval($row['payment_provider_expense_flat_fee']);
    $payment_provider_client = sanitizeInput($row['payment_provider_client']);
    $saved_payment_method = sanitizeInput($row['saved_payment_provider_method']);
    $saved_payment_description = sanitizeInput($row['saved_payment_description']);

    // Sanity checks
    if (!$payment_provider_client || !$saved_payment_method) {
        flash_alert("Stripe not enabled or no client card saved", 'error');
        redirect();
    } elseif ($invoice_status !== 'Sent' && $invoice_status !== 'Viewed') {
        flash_alert("Invalid invoice state (draft/partial/paid/not billable)", 'error');
        redirect();
    } elseif ($invoice_amount == 0) {
        flash_alert("Invalid invoice amount", 'error');
        redirect();
    }

    // Initialize Stripe
    require_once __DIR__ . '/../../plugins/stripe-php/init.php';
    $stripe = new \Stripe\StripeClient($private_key);

    $balance_to_pay = round($invoice_amount, 2);
    $pi_description = "ITFlow: $client_name payment of $invoice_currency_code $balance_to_pay for $invoice_prefix$invoice_number";

    // Create a payment intent
    try {
        $payment_intent = $stripe->paymentIntents->create([
            'amount' => intval($balance_to_pay * 100), // Times by 100 as Stripe expects values in cents
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
        $pi_id = sanitizeInput($payment_intent->id);
        $pi_date = date('Y-m-d', $payment_intent->created);
        $pi_amount_paid = floatval(($payment_intent->amount_received / 100));
        $pi_currency = strtoupper(sanitizeInput($payment_intent->currency));
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

        // Email receipt
        if (!empty($config_smtp_host)) {
            $subject = "Payment Received - Invoice $invoice_prefix$invoice_number";
            $body = "Hello $contact_name,<br><br>We have received online payment for the amount of " . numfmt_format_currency($currency_format, $invoice_amount, $invoice_currency_code) . " for invoice <a href=\'https://$config_base_url/guest/guest_view_invoice.php?invoice_id=$invoice_id&url_key=$invoice_url_key\'>$invoice_prefix$invoice_number</a>. Please keep this email as a receipt for your records.<br><br>Amount Paid: " . numfmt_format_currency($currency_format, $invoice_amount, $invoice_currency_code) . "<br><br>Thank you for your business!<br><br><br>--<br>$company_name - Billing Department<br>$config_invoice_from_email<br>$company_phone";

            // Queue Mail
            $data = [
                [
                    'from' => $config_invoice_from_email,
                    'from_name' => $config_invoice_from_name,
                    'recipient' => $contact_email,
                    'recipient_name' => $contact_name,
                    'subject' => $subject,
                    'body' => $body,
                ]
            ];

            // Email the internal notification address too
            if (!empty($config_invoice_paid_notification_email)) {
                $subject = "Payment Received - $client_name - Invoice $invoice_prefix$invoice_number";
                $body = "Hello, <br><br>This is a notification that an invoice has been paid in ITFlow. Below is a copy of the receipt sent to the client:-<br><br>--------<br><br>Hello $contact_name,<br><br>We have received online payment for the amount of " . numfmt_format_currency($currency_format, $invoice_amount, $invoice_currency_code) . " for invoice <a href=\'https://$config_base_url/guest/guest_view_invoice.php?invoice_id=$invoice_id&url_key=$invoice_url_key\'>$invoice_prefix$invoice_number</a>. Please keep this email as a receipt for your records.<br><br>Amount Paid: " . numfmt_format_currency($currency_format, $invoice_amount, $invoice_currency_code) . "<br><br>Thank you for your business!<br><br><br>--<br>$company_name - Billing Department<br>$config_invoice_from_email<br>$company_phone";

                $data[] = [
                    'from' => $config_invoice_from_email,
                    'from_name' => $config_invoice_from_name,
                    'recipient' => $config_invoice_paid_notification_email,
                    'recipient_name' => $contact_name,
                    'subject' => $subject,
                    'body' => $body,
                ];
            }

            $mail = addToMailQueue($data);

            // Email Logging
            $email_id = mysqli_insert_id($mysqli);
            mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Sent', history_description = 'Payment Receipt sent to mail queue ID: $email_id!', history_invoice_id = $invoice_id");
            logAction("Invoice", "Payment", "Payment receipt for invoice $invoice_prefix$invoice_number queued to $contact_email Email ID: $email_id", $client_id, $invoice_id);
        }

        // Log info
        $extended_log_desc = '';
        if (!$pi_livemode) {
            $extended_log_desc = '(DEV MODE)';
        }

        // Create Stripe payment gateway fee as an expense (if configured)
        if ($expense_vendor_id > 0 && $expense_category_id > 0) {
            $gateway_fee = round($invoice_amount * $expense_percentage_fee + $expense_flat_fee, 2);
            mysqli_query($mysqli,"INSERT INTO expenses SET expense_date = '$pi_date', expense_amount = $gateway_fee, expense_currency_code = '$invoice_currency_code', expense_account_id = $account_id, expense_vendor_id = $expense_vendor_id, expense_client_id = $client_id, expense_category_id = $expense_category_id, expense_description = 'Stripe Transaction for Invoice $invoice_prefix$invoice_number In the Amount of $balance_to_pay', expense_reference = 'Stripe - $pi_id $extended_log_desc'");
        }

        // Notify/log
        appNotify("Invoice Paid", "Invoice $invoice_prefix$invoice_number automatically paid", "/agent/invoice.php?invoice_id=$invoice_id", $client_id);
        logAction("Invoice", "Payment", "$session_name initiated Stripe payment amount of " . numfmt_format_currency($currency_format, $invoice_amount, $invoice_currency_code) . " added to invoice $invoice_prefix$invoice_number - $pi_id $extended_log_desc", $client_id, $invoice_id);
        customAction('invoice_pay', $invoice_id);

        flash_alert("Payment amount <strong>" . numfmt_format_currency($currency_format, $invoice_amount, $invoice_currency_code) . "</strong> added");
        
        redirect();

    } else {
        mysqli_query($mysqli, "INSERT INTO history SET history_status = 'Payment failed', history_description = 'Stripe pay failed due to payment error', history_invoice_id = $invoice_id");
        
        logAction("Invoice", "Payment", "Failed online payment amount of invoice $invoice_prefix$invoice_number due to Stripe payment error", $client_id, $invoice_id);
        flash_alert("Payment failed", 'error');
        
        redirect();
    }

}

/*
if (isset($_GET['add_payment_stripe'])) {

    validateCSRFToken($_GET['csrf_token']);

    enforceUserPermission('module_sales', 2);
    enforceUserPermission('module_financial', 2);

    $invoice_id = intval($_GET['invoice_id']);

    // Get invoice details
    $sql = mysqli_query($mysqli,"SELECT * FROM invoices
            LEFT JOIN clients ON invoice_client_id = client_id
            LEFT JOIN contacts ON clients.client_id = contacts.contact_client_id AND contact_primary = 1
            WHERE invoice_id = $invoice_id"
    );
    $row = mysqli_fetch_array($sql);
    $invoice_number = intval($row['invoice_number']);
    $invoice_status = sanitizeInput($row['invoice_status']);
    $invoice_amount = floatval($row['invoice_amount']);
    $invoice_prefix = sanitizeInput($row['invoice_prefix']);
    $invoice_number = intval($row['invoice_number']);
    $invoice_url_key = sanitizeInput($row['invoice_url_key']);
    $invoice_currency_code = sanitizeInput($row['invoice_currency_code']);
    $client_id = intval($row['client_id']);
    $client_name = sanitizeInput($row['client_name']);
    $contact_name = sanitizeInput($row['contact_name']);
    $contact_email = sanitizeInput($row['contact_email']);
    $contact_phone = sanitizeInput(formatPhoneNumber($row['contact_phone'], $row['contact_phone_country_code']));
    $contact_extension = preg_replace("/[^0-9]/", '',$row['contact_extension']);
    $contact_mobile = sanitizeInput(formatPhoneNumber($row['contact_mobile'], $row['contact_mobile_country_code']));

    // Get ITFlow company details
    $sql = mysqli_query($mysqli,"SELECT * FROM companies WHERE company_id = 1");
    $row = mysqli_fetch_array($sql);
    $company_name = sanitizeInput($row['company_name']);
    $company_country = sanitizeInput($row['company_country']);
    $company_address = sanitizeInput($row['company_address']);
    $company_city = sanitizeInput($row['company_city']);
    $company_state = sanitizeInput($row['company_state']);
    $company_zip = sanitizeInput($row['company_zip']);
    $company_phone = sanitizeInput(formatPhoneNumber($row['company_phone'], $row['company_phone_country_code']));
    $company_email = sanitizeInput($row['company_email']);
    $company_website = sanitizeInput($row['company_website']);

    // Sanitize Config vars from get_settings.php
    $config_invoice_from_name = sanitizeInput($config_invoice_from_name);
    $config_invoice_from_email = sanitizeInput($config_invoice_from_email);

    // Get Client Stripe details
    $stripe_client_details = mysqli_fetch_array(mysqli_query($mysqli, "SELECT * FROM client_stripe WHERE client_id = $client_id LIMIT 1"));
    $stripe_id = sanitizeInput($stripe_client_details['stripe_id']);
    $stripe_pm = sanitizeInput($stripe_client_details['stripe_pm']);

    // Sanity checks
    if (!$config_stripe_enable || !$stripe_id || !$stripe_pm) {
        flash_alert("Stripe not enabled or no client card saved", 'error');
        redirect();
    } elseif ($invoice_status !== 'Sent' && $invoice_status !== 'Viewed') {
        flash_alert("Invalid invoice state (draft/partial/paid/not billable)", 'error');
        redirect();
    } elseif ($invoice_amount == 0) {
        flash_alert("Invalid invoice amount", 'error');
        redirect();
    }

    // Initialize Stripe
    require_once __DIR__ . '/../plugins/stripe-php/init.php';
    $stripe = new \Stripe\StripeClient($config_stripe_secret);

    $balance_to_pay = round($invoice_amount, 2);
    $pi_description = "ITFlow: $client_name payment of $invoice_currency_code $balance_to_pay for $invoice_prefix$invoice_number";

    // Create a payment intent
    try {
        $payment_intent = $stripe->paymentIntents->create([
            'amount' => intval($balance_to_pay * 100), // Times by 100 as Stripe expects values in cents
            'currency' => $invoice_currency_code,
            'customer' => $stripe_id,
            'payment_method' => $stripe_pm,
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
        $pi_id = sanitizeInput($payment_intent->id);
        $pi_date = date('Y-m-d', $payment_intent->created);
        $pi_amount_paid = floatval(($payment_intent->amount_received / 100));
        $pi_currency = strtoupper(sanitizeInput($payment_intent->currency));
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
        mysqli_query($mysqli, "INSERT INTO payments SET payment_date = '$pi_date', payment_amount = $pi_amount_paid, payment_currency_code = '$pi_currency', payment_account_id = $config_stripe_account, payment_method = 'Stripe', payment_reference = 'Stripe - $pi_id', payment_invoice_id = $invoice_id");
        mysqli_query($mysqli, "INSERT INTO history SET history_status = 'Paid', history_description = 'Online Payment added (agent)', history_invoice_id = $invoice_id");

        // Email receipt
        if (!empty($config_smtp_host)) {
            $subject = "Payment Received - Invoice $invoice_prefix$invoice_number";
            $body = "Hello $contact_name,<br><br>We have received online payment for the amount of " . numfmt_format_currency($currency_format, $invoice_amount, $invoice_currency_code) . " for invoice <a href=\'https://$config_base_url/guest/guest_view_invoice.php?invoice_id=$invoice_id&url_key=$invoice_url_key\'>$invoice_prefix$invoice_number</a>. Please keep this email as a receipt for your records.<br><br>Amount Paid: " . numfmt_format_currency($currency_format, $invoice_amount, $invoice_currency_code) . "<br><br>Thank you for your business!<br><br><br>--<br>$company_name - Billing Department<br>$config_invoice_from_email<br>$company_phone";

            // Queue Mail
            $data = [
                [
                    'from' => $config_invoice_from_email,
                    'from_name' => $config_invoice_from_name,
                    'recipient' => $contact_email,
                    'recipient_name' => $contact_name,
                    'subject' => $subject,
                    'body' => $body,
                ]
            ];

            // Email the internal notification address too
            if (!empty($config_invoice_paid_notification_email)) {
                $subject = "Payment Received - $client_name - Invoice $invoice_prefix$invoice_number";
                $body = "Hello, <br><br>This is a notification that an invoice has been paid in ITFlow. Below is a copy of the receipt sent to the client:-<br><br>--------<br><br>Hello $contact_name,<br><br>We have received online payment for the amount of " . numfmt_format_currency($currency_format, $invoice_amount, $invoice_currency_code) . " for invoice <a href=\'https://$config_base_url/guest/guest_view_invoice.php?invoice_id=$invoice_id&url_key=$invoice_url_key\'>$invoice_prefix$invoice_number</a>. Please keep this email as a receipt for your records.<br><br>Amount Paid: " . numfmt_format_currency($currency_format, $invoice_amount, $invoice_currency_code) . "<br><br>Thank you for your business!<br><br><br>--<br>$company_name - Billing Department<br>$config_invoice_from_email<br>$company_phone";

                $data[] = [
                    'from' => $config_invoice_from_email,
                    'from_name' => $config_invoice_from_name,
                    'recipient' => $config_invoice_paid_notification_email,
                    'recipient_name' => $contact_name,
                    'subject' => $subject,
                    'body' => $body,
                ];
            }

            $mail = addToMailQueue($data);

            // Email Logging
            $email_id = mysqli_insert_id($mysqli);
            mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Sent', history_description = 'Payment Receipt sent to mail queue ID: $email_id!', history_invoice_id = $invoice_id");
            logAction("Invoice", "Payment", "Payment receipt for invoice $invoice_prefix$invoice_number queued to $contact_email Email ID: $email_id", $client_id, $invoice_id);
        }

        // Log info
        $extended_log_desc = '';
        if (!$pi_livemode) {
            $extended_log_desc = '(DEV MODE)';
        }

        // Create Stripe payment gateway fee as an expense (if configured)
        if ($config_stripe_expense_vendor > 0 && $config_stripe_expense_category > 0) {
            $gateway_fee = round($invoice_amount * $config_stripe_percentage_fee + $config_stripe_flat_fee, 2);
            mysqli_query($mysqli,"INSERT INTO expenses SET expense_date = '$pi_date', expense_amount = $gateway_fee, expense_currency_code = '$invoice_currency_code', expense_account_id = $config_stripe_account, expense_vendor_id = $config_stripe_expense_vendor, expense_client_id = $client_id, expense_category_id = $config_stripe_expense_category, expense_description = 'Stripe Transaction for Invoice $invoice_prefix$invoice_number In the Amount of $balance_to_pay', expense_reference = 'Stripe - $pi_id $extended_log_desc'");
        }

        // Notify/log
        appNotify("Invoice Paid", "Invoice $invoice_prefix$invoice_number automatically paid", "invoice.php?invoice_id=$invoice_id", $client_id);
        logAction("Invoice", "Payment", "$session_name initiated Stripe payment amount of " . numfmt_format_currency($currency_format, $invoice_amount, $invoice_currency_code) . " added to invoice $invoice_prefix$invoice_number - $pi_id $extended_log_desc", $client_id, $invoice_id);
        customAction('invoice_pay', $invoice_id);

        flash_alert("Payment amount <strong>" . numfmt_format_currency($currency_format, $invoice_amount, $invoice_currency_code) . "</strong> added");
        
        redirect();

    } else {
        mysqli_query($mysqli, "INSERT INTO history SET history_status = 'Payment failed', history_description = 'Stripe pay failed due to payment error', history_invoice_id = $invoice_id");
        
        logAction("Invoice", "Payment", "Failed online payment amount of invoice $invoice_prefix$invoice_number due to Stripe payment error", $client_id, $invoice_id);
        flash_alert("Payment failed", 'error');
        
        redirect();
    }

}
*/

if (isset($_POST['add_bulk_payment'])) {
    
    enforceUserPermission('module_sales', 2);
    enforceUserPermission('module_financial', 2);

    $client_id = intval($_POST['client_id']);
    $date = sanitizeInput($_POST['date']);
    $bulk_payment_amount = floatval($_POST['amount']);
    $bulk_payment_amount_static = floatval($_POST['amount']);
    $total_account_balance = floatval($_POST['balance']);
    $account = intval($_POST['account']);
    $currency_code = sanitizeInput($_POST['currency_code']);
    $payment_method = sanitizeInput($_POST['payment_method']);
    $reference = sanitizeInput($_POST['reference']);
    $email_receipt = intval($_POST['email_receipt']);

    // Check if bulk_payment_amount exceeds total_account_balance
    if ($bulk_payment_amount > $total_account_balance) {
        flash_alert("Payment exceeds Client Balance.", 'error');
        redirect();
    }

    // Get Invoices
    $sql_invoices = "SELECT * FROM invoices
        WHERE invoice_status != 'Draft'
        AND invoice_status != 'Paid'
        AND invoice_status != 'Cancelled'
        AND invoice_client_id = $client_id
        ORDER BY invoice_number ASC";
    $result_invoices = mysqli_query($mysqli, $sql_invoices);

    // Loop Through Each Invoice
    while ($row = mysqli_fetch_array($result_invoices)) {
        $invoice_id = intval($row['invoice_id']);
        $invoice_prefix = sanitizeInput($row['invoice_prefix']);
        $invoice_number = intval($row['invoice_number']);
        $invoice_amount = floatval($row['invoice_amount']);
        $invoice_url_key = sanitizeInput($row['invoice_url_key']);
        $invoice_balance_query = "SELECT SUM(payment_amount) AS amount_paid FROM payments WHERE payment_invoice_id = $invoice_id";
        $result_amount_paid = mysqli_query($mysqli, $invoice_balance_query);
        $row_amount_paid = mysqli_fetch_array($result_amount_paid);
        $amount_paid = floatval($row_amount_paid['amount_paid']);
        $invoice_balance = $invoice_amount - $amount_paid;

        if ($bulk_payment_amount <= 0) {
            break; // Exit the loop if no payment amount is left
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

        // Add to Email Body Invoice Portion
        $email_body_invoices .= "<br>Invoice <a href=\'https://$config_base_url/guest/guest_view_invoice.php?invoice_id=$invoice_id&url_key=$invoice_url_key\'>$invoice_prefix$invoice_number</a> - Outstanding Amount: " . numfmt_format_currency($currency_format, $invoice_balance, $currency_code) . " - Payment Applied: " . numfmt_format_currency($currency_format, $payment_amount, $currency_code) . " - New Balance: " . numfmt_format_currency($currency_format, $remaining_invoice_balance, $currency_code);

        customAction('invoice_pay', $invoice_id);

    } // End Invoice Loop

    // Send Email
    if ($email_receipt == 1) {

        // Get Client / Contact Info
        $sql_client = mysqli_query($mysqli,"SELECT * FROM clients
            LEFT JOIN contacts ON clients.client_id = contacts.contact_client_id 
            AND contact_primary = 1
            WHERE client_id = $client_id"
        );

        $row = mysqli_fetch_array($sql_client);
        $client_name = sanitizeInput($row['client_name']);
        $contact_name = sanitizeInput($row['contact_name']);
        $contact_email = sanitizeInput($row['contact_email']);

        $sql_company = mysqli_query($mysqli,"SELECT company_name, company_phone, company_phone_country_code FROM companies WHERE company_id = 1");
        $row = mysqli_fetch_array($sql_company);

        $company_name = sanitizeInput($row['company_name']);
        $company_phone = sanitizeInput(formatPhoneNumber($row['company_phone'], $row['company_phone_country_code']));

        // Sanitize Config vars from get_settings.php
        $config_invoice_from_name = sanitizeInput($config_invoice_from_name);
        $config_invoice_from_email = sanitizeInput($config_invoice_from_email);

        $subject = "Payment Received - Multiple Invoices";
        $body = "Hello $contact_name,<br><br>Thank you for your payment of " . numfmt_format_currency($currency_format, $bulk_payment_amount_static, $currency_code) . " We\'ve applied your payment to the following invoices, updating their balances accordingly:<br><br>$email_body_invoices<br><br><br>We appreciate your continued business!<br><br>Sincerely,<br>$company_name - Billing<br>$config_invoice_from_email<br>$company_phone";

        // Queue Mail
        mysqli_query($mysqli, "INSERT INTO email_queue SET email_recipient = '$contact_email', email_recipient_name = '$contact_name', email_from = '$config_invoice_from_email', email_from_name = '$config_invoice_from_name', email_subject = '$subject', email_content = '$body'");

        // Get Email ID for reference
        $email_id = mysqli_insert_id($mysqli);

        // Email Logging
        logAction("Payment", "Email", "Bulk Payment receipt for multiple Invoices queued to $contact_email Email ID: $email_id", $client_id);

        $alert_message .= "Email receipt queued and ";

    } // End Email

    logAction("Invoice", "Payment", "Bulk Payment amount of " . numfmt_format_currency($currency_format, $bulk_payment_amount_static, $currency_code) . " applied to multiple invoices", $client_id);

    flash_alert("$alert_message Bulk Payment added");

    redirect();

}

if (isset($_GET['delete_payment'])) {
    
    enforceUserPermission('module_sales', 2);
    enforceUserPermission('module_financial', 2);

    $payment_id = intval($_GET['delete_payment']);

    $sql = mysqli_query($mysqli,"SELECT * FROM payments WHERE payment_id = $payment_id");
    $row = mysqli_fetch_array($sql);
    $invoice_id = intval($row['payment_invoice_id']);
    $deleted_payment_amount = floatval($row['payment_amount']);

    //Add up all the payments for the invoice and get the total amount paid to the invoice
    $sql_total_payments_amount = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS total_payments_amount FROM payments WHERE payment_invoice_id = $invoice_id");
    $row = mysqli_fetch_array($sql_total_payments_amount);
    $total_payments_amount = floatval($row['total_payments_amount']);

    // Get the invoice total and details
    $sql = mysqli_query($mysqli,"SELECT * FROM invoices WHERE invoice_id = $invoice_id");
    $row = mysqli_fetch_array($sql);
    $invoice_prefix = sanitizeInput($row['invoice_prefix']);
    $invoice_number = intval($row['invoice_number']);
    $client_id = intval($row['invoice_client_id']);
    $invoice_amount = floatval($row['invoice_amount']);

    //Calculate the Invoice balance
    $invoice_balance = $invoice_amount - $total_payments_amount + $deleted_payment_amount;

    //Determine if invoice has been paid
    if ($invoice_balance == 0) {
        $invoice_status = "Paid";
    } else {
        $invoice_status = "Partial";
    }

    //Update Invoice Status
    mysqli_query($mysqli,"UPDATE invoices SET invoice_status = '$invoice_status' WHERE invoice_id = $invoice_id");

    //Add Payment to History
    mysqli_query($mysqli,"INSERT INTO history SET history_status = '$invoice_status', history_description = 'Payment deleted', history_invoice_id = $invoice_id");

    mysqli_query($mysqli,"DELETE FROM payments WHERE payment_id = $payment_id");

    logAction("Invoice", "Edit", "$session_name deleted Payment on Invoice $invoice_prefix$invoice_number", $client_id, $invoice_id);

    flash_alert("Payment deleted", 'error');
    if ($config_stripe_enable) {
       flash_alert("Payment deleted - Stripe payments must be manually refunded in Stripe", 'error');
    }

    redirect();

}

if (isset($_POST['export_payments_csv'])) {
    
    if (isset($_POST['client_id'])) {
        $client_id = intval($_POST['client_id']);
        $client_query = "AND invoice_client_id = $client_id";
        $client_name = getFieldById('clients', $client_id, 'client_name');
        $file_name_prepend = "$client_name-";
    } else {
        $client_query = '';
        $client_name = '';
        $file_name_prepend = "$session_company_name-";
    }

    $sql = mysqli_query($mysqli,"SELECT * FROM payments, invoices WHERE payment_invoice_id = invoice_id $client_query ORDER BY payment_date ASC");
    
    $num_rows = mysqli_num_rows($sql);

    if ($num_rows > 0) {
        $delimiter = ",";
        $enclosure = '"';
        $escape    = '\\';   // backslash
        $filename = sanitize_filename($file_name_prepend . "Payments-" . date('Y-m-d_H-i-s') . ".csv");

        //create a file pointer
        $f = fopen('php://memory', 'w');

        //set column headers
        $fields = array('Payment Date', 'Invoice Date', 'Invoice Number', 'Invoice Amount', 'Payment Amount', 'Payment Method', 'Referrence');
        fputcsv($f, $fields, $delimiter, $enclosure, $escape);

        //output each row of the data, format line as csv and write to file pointer
        while($row = $sql->fetch_assoc()){
            $lineData = array($row['payment_date'], $row['invoice_date'], $row['invoice_prefix'] . $row['invoice_number'], $row['invoice_amount'], $row['payment_amount'], $row['payment_method'], $row['payment_reference']);
            fputcsv($f, $lineData, $delimiter, $enclosure, $escape);
        }

        //move back to beginning of file
        fseek($f, 0);

        //set headers to download file rather than displayed
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '";');

        //output all remaining data on a file pointer
        fpassthru($f);
    }

    logAction("Payments", "Export", "$session_name exported $num_rows payments to CSV file");

    exit;

}
