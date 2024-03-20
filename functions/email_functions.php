<?php

// Email related functions

// PHP Mailer Libs
require_once $_SERVER['DOCUMENT_ROOT'] . '/plugins/PHPMailer/src/Exception.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/plugins/PHPMailer/src/PHPMailer.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/plugins/PHPMailer/src/SMTP.php';

// Initiate PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


// Send a single email to a single recipient
function sendSingleEmail($config_smtp_host, $config_smtp_username, $config_smtp_password, $config_smtp_encryption, $config_smtp_port, $from_email, $from_name, $to_email, $to_name, $subject, $body, $ics_str)
{

    $mail = new PHPMailer(true);

    if (empty($config_smtp_username)) {
        $smtp_auth = false;
    } else {

        $smtp_auth = true;
    }

    try {
        // Mail Server Settings
        $mail->CharSet = "UTF-8";                                   // Specify UTF-8 charset to ensure symbols ($/£) load correctly
        $mail->SMTPDebug = 0;                                       // No Debugging
        $mail->isSMTP();                                            // Set mailer to use SMTP
        $mail->Host       = $config_smtp_host;                      // Specify SMTP server
        $mail->SMTPAuth   = $smtp_auth;                             // Enable SMTP authentication
        $mail->Username   = $config_smtp_username;                  // SMTP username
        $mail->Password   = $config_smtp_password;                  // SMTP password
        $mail->SMTPSecure = $config_smtp_encryption;                // Enable TLS encryption, `ssl` also accepted
        $mail->Port       = $config_smtp_port;                      // TCP port to connect to

        //Recipients
        $mail->setFrom($from_email, $from_name);
        $mail->addAddress("$to_email", "$to_name");    // Add a recipient

        // Content
        $mail->isHTML(true); // Set email format to HTML
        $mail->Subject = "$subject";                                // Subject
        $mail->Body    = "<html>
        <head>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    color: #333;
                    line-height: 1.6;
                }
                .email-container {
                    max-width: 600px;
                    margin: auto;
                    padding: 20px;
                    border: 1px solid #ddd;
                    border-radius: 5px;
                }
                .header {
                    font-size: 18px;
                    margin-bottom: 20px;
                }
                .link-button {
                    display: inline-block;
                    background-color: #007bff;
                    color: #ffffff;
                    padding: 10px 20px;
                    text-decoration: none;
                    border-radius: 5px;
                    margin: 10px 0;
                }
                .footer {
                    font-size: 14px;
                    color: #666;
                    margin-top: 20px;
                    border-top: 1px solid #ddd;
                    padding-top: 10px;
                }
                .no-reply {
                    color: #999;
                    font-size: 12px;
                }
            </style>
        </head>
        <body>
            <div class='email-container'>
        $body
        </div>
        </body>
        </html>
        ";                                   // Content

        // Attachments - todo
        //$mail->addAttachment('/var/tmp/file.tar.gz');             // Add attachments
        //$mail->addAttachment('/tmp/image.jpg', 'new.jpg');        // Optional name

        if (!empty($ics_str)) {
            $mail->addStringAttachment($ics_str, 'Scheduled_ticket.ics', 'base64', 'text/calendar');
        }

        // Send
        $mail->send();

        // Return true if this was successful
        return true;
    } catch (Exception $e) {
        // If we couldn't send the message return the error, so we can log it in the database (truncated)
        error_log("ITFlow - Failed to send email: " . $mail->ErrorInfo);
        return substr("Mailer Error: $mail->ErrorInfo", 0, 150) . "...";
    }
}

// Add an email to the queue
function addToMailQueue($mysqli, $data)
{

    foreach ($data as $email) {
        $from = strval($email['from']);
        $from_name = strval($email['from_name']);
        $recipient = strval($email['recipient']);
        $recipient_name = strval($email['recipient_name']);
        $subject = strval($email['subject']);
        $body = strval($email['body']);

        $cal_str = '';
        if (isset($email['cal_str'])) {
            $cal_str = mysqli_escape_string($mysqli,$email['cal_str']);
        }

        // Check if 'email_queued_at' is set and not empty
        if (isset($email['queued_at']) && !empty($email['queued_at'])) {
            $queued_at = $email['queued_at'];
        } else {
            // Use the current date and time if 'email_queued_at' is not set or empty
            $queued_at = date('Y-m-d H:i:s');
        }

        mysqli_query($mysqli, "INSERT INTO email_queue SET email_recipient = '$recipient', email_recipient_name = '$recipient_name', email_from = '$from', email_from_name = '$from_name', email_subject = '$subject', email_content = '$body', email_queued_at = '$queued_at', email_cal_str = '$cal_str'");
    }

    return true;
}

function emailInvoice(
    $invoice_id
){
    // Access global variables
    global $mysqli, $session_user_id, $session_ip, $session_user_agent, $config_base_url, $config_invoice_from_name, $config_invoice_from_email, $currency_format;

    $sql = mysqli_query($mysqli,"SELECT * FROM invoices
        LEFT JOIN clients ON invoice_client_id = client_id
        LEFT JOIN contacts ON clients.client_id = contacts.contact_client_id AND contact_primary = 1
        WHERE invoice_id = $invoice_id"
    );
    $row = mysqli_fetch_array($sql);

    $invoice_id = intval($row['invoice_id']);
    $invoice_prefix = sanitizeInput($row['invoice_prefix']);
    $invoice_number = intval($row['invoice_number']);
    $invoice_scope = sanitizeInput($row['invoice_scope']);
    $invoice_status = sanitizeInput($row['invoice_status']);
    $invoice_date = sanitizeInput($row['invoice_date']);
    $invoice_due = sanitizeInput($row['invoice_due']);
    $invoice_amount = floatval($row['invoice_amount']);
    $invoice_url_key = sanitizeInput($row['invoice_url_key']);
    $invoice_currency_code = sanitizeInput($row['invoice_currency_code']);
    $client_id = intval($row['client_id']);
    $client_name = sanitizeInput($row['client_name']);
    $contact_name = sanitizeInput($row['contact_name']);
    $contact_email = sanitizeInput($row['contact_email']);

    $sql = mysqli_query($mysqli,"SELECT * FROM companies WHERE company_id = 1");
    $row = mysqli_fetch_array($sql);

    $company_name = sanitizeInput($row['company_name']);
    $company_phone = sanitizeInput(formatPhoneNumber($row['company_phone']));


    // Sanitize Config vars from get_settings.php
    $config_invoice_from_name = sanitizeInput($config_invoice_from_name);
    $config_invoice_from_email = sanitizeInput($config_invoice_from_email);

    // Add up all the payments for the invoice and get the total amount paid to the invoice
    $sql_amount_paid = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS amount_paid FROM payments WHERE payment_invoice_id = $invoice_id");
    $row = mysqli_fetch_array($sql_amount_paid);
    $amount_paid = floatval($row['amount_paid']);

    $balance = $invoice_amount - $amount_paid;

    if ($invoice_status == 'Paid') {
        $subject = "$company_name Invoice $invoice_prefix$invoice_number Receipt";
        $body = "Hello $contact_name,<br><br>Please click on the link below to see your invoice regarding \"$invoice_scope\" marked <b>paid</b>.<br><br><a href=\'https://$config_base_url/guest_view_invoice.php?invoice_id=$invoice_id&url_key=$invoice_url_key\'>Invoice Link</a><br><br><br>--<br>$company_name - Billing<br>$config_invoice_from_email<br>$company_phone";
    } else {
        $subject = "$company_name Invoice $invoice_prefix$invoice_number";
        $body = "Hello $contact_name,<br><br>Please view the details of your invoice regarding \"$invoice_scope\" below.<br><br>Invoice: $invoice_prefix$invoice_number<br>Issue Date: $invoice_date<br>Total: " . numfmt_format_currency($currency_format, $invoice_amount, $invoice_currency_code) . "<br>Balance Due: " . numfmt_format_currency($currency_format, $balance, $invoice_currency_code) . "<br>Due Date: $invoice_due<br><br><br>To view your invoice, please click <a href=\'https://$config_base_url/guest_view_invoice.php?invoice_id=$invoice_id&url_key=$invoice_url_key\'>here</a>.<br><br><br>--<br>$company_name - Billing<br>$config_invoice_from_email<br>$company_phone";
    }

    // Queue Mail
    $data = [
        [
            'from' => $config_invoice_from_email,
            'from_name' => $config_invoice_from_name,
            'recipient' => $contact_email,
            'recipient_name' => $contact_name,
            'subject' => $subject,
            'body' => $body
        ]
    ];

    addToMailQueue($mysqli, $data);

    // Get Email ID for reference
    $email_id = mysqli_insert_id($mysqli);

    $_SESSION['alert_message'] = "Invoice has been sent";
    mysqli_query($mysqli,"INSERT INTO history SET history_status = 'Sent', history_description = 'Invoice sent to the mail queue ID: $email_id', history_invoice_id = $invoice_id");

    // Don't change the status to sent if the status is anything but draft
    if ($invoice_status == 'Draft') {
        mysqli_query($mysqli,"UPDATE invoices SET invoice_status = 'Sent' WHERE invoice_id = $invoice_id");
    }

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Invoice', log_action = 'Email', log_description = 'Invoice $invoice_prefix$invoice_number queued to $contact_email Email ID: $email_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $invoice_id");

    // Send copies of the invoice to any additional billing contacts
    $sql_billing_contacts = mysqli_query(
        $mysqli,
        "SELECT contact_name, contact_email FROM contacts
        WHERE contact_billing = 1
        AND contact_email != '$contact_email'
        AND contact_email != ''
        AND contact_client_id = $client_id"
    );

    $data = [];

    while ($billing_contact = mysqli_fetch_array($sql_billing_contacts)) {
        $billing_contact_name = sanitizeInput($billing_contact['contact_name']);
        $billing_contact_email = sanitizeInput($billing_contact['contact_email']);

        $data = [
            [
                'from' => $config_invoice_from_email,
                'from_name' => $config_invoice_from_name,
                'recipient' => $billing_contact_email,
                'recipient_name' => $billing_contact_name,
                'subject' => $subject,
                'body' => $body
            ]
        ];

        // Logging
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Invoice', log_action = 'Email', log_description = 'Invoice $invoice_prefix$invoice_number queued to $billing_contact_email Email ID: $email_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $invoice_id");
    }

    addToMailQueue($mysqli, $data);
}