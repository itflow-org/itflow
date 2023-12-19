<?php
if(isset($_GET['email_invoice'])){
    $invoice_id = intval($_GET['email_invoice']);

    $sql = mysqli_query($mysqli,"SELECT * FROM invoices
        LEFT JOIN clients ON invoice_client_id = client_id
        LEFT JOIN contacts ON contact_id = primary_contact
        WHERE invoice_id = $invoice_id"
    );
    $row = mysqli_fetch_array($sql);

    $invoice_id = intval($row['invoice_id']);
    $invoice_prefix = $row['invoice_prefix'];
    $invoice_number = intval($row['invoice_number']);
    $invoice_status = $row['invoice_status'];
    $invoice_date = $row['invoice_date'];
    $invoice_due = $row['invoice_due'];
    $invoice_amount = floatval($row['invoice_amount']);
    $invoice_url_key = $row['invoice_url_key'];
    $invoice_currency_code = $row['invoice_currency_code'];
    $client_id = intval($row['client_id']);
    $client_name = $row['client_name'];
    $contact_name = $row['contact_name'];
    $contact_email = $row['contact_email'];
    $invoice_prefix_escaped = sanitizeInput($row['invoice_prefix']);
    $contact_name_escaped = sanitizeInput($row['contact_name']);
    $contact_email_escaped = sanitizeInput($row['contact_email']);

    $sql = mysqli_query($mysqli,"SELECT * FROM companies WHERE company_id = 1");
    $row = mysqli_fetch_array($sql);

    $company_name = $row['company_name'];
    $company_country = $row['company_country'];
    $company_address = $row['company_address'];
    $company_city = $row['company_city'];
    $company_state = $row['company_state'];
    $company_zip = $row['company_zip'];
    $company_phone = formatPhoneNumber($row['company_phone']);
    $company_email = $row['company_email'];
    $company_website = $row['company_website'];
    $company_logo = $row['company_logo'];

    // Sanitize Config vars from get_settings.php
    $config_invoice_from_name_escaped = sanitizeInput($config_invoice_from_name);
    $config_invoice_from_email_escaped = sanitizeInput($config_invoice_from_email);

    $sql_payments = mysqli_query($mysqli,"SELECT * FROM payments, accounts WHERE payment_account_id = account_id AND payment_invoice_id = $invoice_id ORDER BY payment_id DESC");

    // Add up all the payments for the invoice and get the total amount paid to the invoice
    $sql_amount_paid = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS amount_paid FROM payments WHERE payment_invoice_id = $invoice_id");
    $row = mysqli_fetch_array($sql_amount_paid);
    $amount_paid = floatval($row['amount_paid']);

    $balance = $invoice_amount - $amount_paid;

    if ($invoice_status == 'Paid') {
        $subject = sanitizeInput("Invoice $invoice_prefix$invoice_number Copy");
        $body    = mysqli_real_escape_string($mysqli, "Hello $contact_name,<br><br>Please click on the link below to see your invoice marked <b>paid</b>.<br><br><a href='https://$config_base_url/guest_view_invoice.php?invoice_id=$invoice_id&url_key=$invoice_url_key'>Invoice Link</a><br><br><br>~<br>$company_name<br>Billing Department<br>$config_invoice_from_email<br>$company_phone");
    } else {
        $subject = sanitizeInput("Invoice $invoice_prefix$invoice_number");
        $body    = mysqli_real_escape_string($mysqli, "Hello $contact_name,<br><br>Please view the details of the invoice below.<br><br>Invoice: $invoice_prefix$invoice_number<br>Issue Date: $invoice_date<br>Total: " . numfmt_format_currency($currency_format, $invoice_amount, $invoice_currency_code) . "<br>Balance Due: " . numfmt_format_currency($currency_format, $balance, $invoice_currency_code) . "<br>Due Date: $invoice_due<br><br><br>To view your invoice click <a href='https://$config_base_url/guest_view_invoice.php?invoice_id=$invoice_id&url_key=$invoice_url_key'>here</a><br><br><br>~<br>$company_name<br>Billing Department<br>$config_invoice_from_email<br>$company_phone");
    }

    // Queue Mail
    $data = [
        [
            'recipient' => $contact_email_escaped,
            'recipient_name' => $contact_name_escaped,
            'subject' => $subject,
            'body' => $body,
        ]
    ];
    addToMailQueue($mysqli, $data);


    $_SESSION['alert_message'] = "Invoice has been sent";
    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Sent', history_description = 'Invoice sent to the mail queue.', history_invoice_id = $invoice_id");

    // Don't change the status to sent if the status is anything but draft
    if($invoice_status == 'Draft'){
        mysqli_query($mysqli,"UPDATE invoices SET invoice_status = 'Sent' WHERE invoice_id = $invoice_id");
    }

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Invoice', log_action = 'Email', log_description = 'Invoice $invoice_prefix_escaped$invoice_number queued to $contact_email_escaped Email ID: $email_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $invoice_id");

    // Send copies of the invoice to any additional billing contacts
    $sql_billing_contacts = mysqli_query(
        $mysqli,
        "SELECT contact_name, contact_email FROM contacts
        WHERE contact_billing = 1
        AND contact_email != '$contact_email_escaped'
        AND contact_email != ''
        AND contact_client_id = $client_id"
    );
    while ($billing_contact = mysqli_fetch_array($sql_billing_contacts)) {
        $billing_contact_name = sanitizeInput($billing_contact['contact_name']);
        $billing_contact_email = sanitizeInput($billing_contact['contact_email']);

        // Queue Mail
        $data = [
            [
                'recipient' => $billing_contact_email,
                'recipient_name' => $billing_contact_name,
                'subject' => $subject,
                'body' => $body,
            ]
        ];
        addToMailQueue($mysqli, $data);
        
        // Get Email ID for reference
        $email_id = mysqli_insert_id($mysqli);

        // Logging
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Invoice', log_action = 'Email', log_description = 'Invoice $invoice_prefix_escaped$invoice_number queued to $billing_contact_email Email ID: $email_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $invoice_id");

    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

?>