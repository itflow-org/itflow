<?php

// Credit Related Functions

function applyCredit(
    $credit_id
) {

    // Access global variables
    global $mysqli, $session_user_id, $session_ip, $session_user_agent, $config_base_url, $currency_format, $config_invoice_from_name, $config_invoice_from_email;

    $credit_sql = mysqli_query($mysqli,"SELECT * FROM credits WHERE credit_id = $credit_id");
    $credit_row = mysqli_fetch_array($credit_sql);

    $client_id = intval($credit_row['credit_client_id']);
    $credit_amount = floatval($credit_row['credit_amount']);
    $credit_currency_code = sanitizeInput($credit_row['credit_currency_code']);

    $client_balance = getClientBalance($mysqli, $client_id);

    if ($client_balance < $credit_amount) {
        //create a new credit for the remaining amount
        $new_credit_amount = $credit_amount - $client_balance;
        $new_credit_query = "INSERT INTO credits credit_date = CURDATE(), credit_amount = $new_credit_amount, credit_client_id = $client_id, credit_currency_code = '$credit_currency_code', credit_reference = 'Credit Applied'";
        mysqli_query($mysqli, $new_credit_query);
        $new_credit_id = mysqli_insert_id($mysqli);
    } 
    // Delete the original credit
    mysqli_query($mysqli,"DELETE FROM credits WHERE credit_id = $credit_id");

    // Apply payments similar to add bulk payment

    // Get Invoices
    $sql_invoices = "SELECT * FROM invoices
        WHERE invoice_status != 'Draft'
        AND invoice_status != 'Paid'
        AND invoice_status != 'Cancelled'
        AND invoice_client_id = $client_id
        ORDER BY invoice_number ASC";
    $result_invoices = mysqli_query($mysqli, $sql_invoices);
    $invoice_applied_count = 0;

    $email_body_invoices = "";

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


        if ($credit_amount <= 0) {
            break; // Exit the loop if no credit amount is left
        }

        if ($invoice_balance <= 0) {
            continue; // Skip the invoice if it's already paid
        }

        if ($credit_amount >= $invoice_balance) {
            $payment_amount = $invoice_balance;
            $invoice_status = "Paid";
        } else {
            $payment_amount = $credit_amount;
            $invoice_status = "Partial";
        }

        $invoice_applied_count++;
        
        // Subtract the payment amount from the credit amount
        $credit_amount -= $payment_amount;

        // Get Invoice Remain Balance
        $remaining_invoice_balance = $invoice_balance - $payment_amount;

        // Add Payment
        $payment_query = "INSERT INTO payments SET payment_date = CURDATE(), payment_amount = $payment_amount, payment_invoice_id = $invoice_id, payment_account_id = 1, payment_currency_code = '{$credit_row['credit_currency_code']}', payment_reference = 'Credit Applied'";
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

        $email_body_invoices .= "<br>Invoice <a href=\'https://$config_base_url/guest_view_invoice.php?invoice_id=$invoice_id&url_key=$invoice_url_key\'>$invoice_prefix$invoice_number</a> - Outstanding Amount: " . numfmt_format_currency($currency_format, $invoice_balance, $credit_currency_code) . " - Payment Applied: " . numfmt_format_currency($currency_format, $payment_amount, $credit_currency_code) . " - New Balance: " . numfmt_format_currency($currency_format, $remaining_invoice_balance, $credit_currency_code);

    } // End Invoice Loop

    //Todo add option to send receipts
    $email_receipt = 1;

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

        $sql_company = mysqli_query($mysqli,"SELECT company_name, company_phone FROM companies WHERE company_id = 1");
        $row = mysqli_fetch_array($sql_company);

        $company_name = sanitizeInput($row['company_name']);
        $company_phone = sanitizeInput(formatPhoneNumber($row['company_phone']));

        // Sanitize Config vars from get_settings.php
        $config_invoice_from_name = sanitizeInput($config_invoice_from_name);
        $config_invoice_from_email = sanitizeInput($config_invoice_from_email);

        $subject = "Payment Received - Multiple Invoices";
        $body = "Hello $contact_name,<br><br>Thank you for your payment of " . numfmt_format_currency($currency_format, $credit_amount, $credit_currency_code) . " We\'ve applied your payment to the following invoices, updating their balances accordingly:<br><br>$email_body_invoices<br><br><br>We appreciate your continued business!<br><br>Sincerely,<br>$company_name - Billing<br>$config_invoice_from_email<br>$company_phone";

        // Queue Mail
        mysqli_query($mysqli, "INSERT INTO email_queue SET email_recipient = '$contact_email', email_recipient_name = '$contact_name', email_from = '$config_invoice_from_email', email_from_name = '$config_invoice_from_name', email_subject = '$subject', email_content = '$body'");

        // Get Email ID for reference
        $email_id = mysqli_insert_id($mysqli);

        // Email Logging
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Payment', log_action = 'Email', log_description = 'Bulk Payment receipt for multiple Invoices queued to $contact_email Email ID: $email_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id");

        $_SESSION['alert_message'] .= "Email receipt sent and ";

    } // End Email

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Payment', log_action = 'Create', log_description = 'Bulk Payment of $credit_amount', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id");

}

function deleteCredit(
    $credit_id
){

    // Access global variables
    global $mysqli, $session_user_id, $session_ip, $session_user_agent;

    mysqli_query($mysqli,"DELETE FROM credits WHERE credit_id = $credit_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Credit', log_action = 'Delete', log_description = 'Credit $credit_id deleted', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");
}