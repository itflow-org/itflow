<?php

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['edit_mail_smtp_settings'])) {

    validateCSRFToken($_POST['csrf_token']);

    $config_smtp_host = sanitizeInput($_POST['config_smtp_host']);
    $config_smtp_port = intval($_POST['config_smtp_port']);
    $config_smtp_encryption = sanitizeInput($_POST['config_smtp_encryption']);
    $config_smtp_username = sanitizeInput($_POST['config_smtp_username']);
    $config_smtp_password = sanitizeInput($_POST['config_smtp_password']);

    mysqli_query($mysqli,"UPDATE settings SET config_smtp_host = '$config_smtp_host', config_smtp_port = $config_smtp_port, config_smtp_encryption = '$config_smtp_encryption', config_smtp_username = '$config_smtp_username', config_smtp_password = '$config_smtp_password' WHERE company_id = 1");

    // Logging
    logAction("Settings", "Edit", "$session_name edited SMTP mail settings");

    $_SESSION['alert_message'] = "SMTP Mail Settings updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_mail_imap_settings'])) {

    validateCSRFToken($_POST['csrf_token']);

    $config_imap_host = sanitizeInput($_POST['config_imap_host']);
    $config_imap_username = sanitizeInput($_POST['config_imap_username']);
    $config_imap_password = sanitizeInput($_POST['config_imap_password']);
    $config_imap_port = intval($_POST['config_imap_port']);
    $config_imap_encryption = sanitizeInput($_POST['config_imap_encryption']);

    mysqli_query($mysqli,"UPDATE settings SET config_imap_host = '$config_imap_host', config_imap_port = $config_imap_port, config_imap_encryption = '$config_imap_encryption', config_imap_username = '$config_imap_username', config_imap_password = '$config_imap_password' WHERE company_id = 1");


    // Logging
    logAction("Settings", "Edit", "$session_name edited IMAP mail settings");

    $_SESSION['alert_message'] = "IMAP Mail Settings updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_mail_from_settings'])) {

    validateCSRFToken($_POST['csrf_token']);

    $config_mail_from_email = sanitizeInput(filter_var($_POST['config_mail_from_email'], FILTER_VALIDATE_EMAIL));
    $config_mail_from_name = sanitizeInput(preg_replace('/[^a-zA-Z0-9\s]/', '', $_POST['config_mail_from_name']));

    $config_invoice_from_email = sanitizeInput(filter_var($_POST['config_invoice_from_email'], FILTER_VALIDATE_EMAIL));
    $config_invoice_from_name = sanitizeInput(preg_replace('/[^a-zA-Z0-9\s]/', '', $_POST['config_invoice_from_name']));

    $config_quote_from_email = sanitizeInput(filter_var($_POST['config_quote_from_email'], FILTER_VALIDATE_EMAIL));
    $config_quote_from_name = sanitizeInput(preg_replace('/[^a-zA-Z0-9\s]/', '', $_POST['config_quote_from_name']));

    $config_ticket_from_email = sanitizeInput(filter_var($_POST['config_ticket_from_email'], FILTER_VALIDATE_EMAIL));
    $config_ticket_from_name = sanitizeInput(preg_replace('/[^a-zA-Z0-9\s]/', '', $_POST['config_ticket_from_name']));

    mysqli_query($mysqli,"UPDATE settings SET config_mail_from_email = '$config_mail_from_email', config_mail_from_name = '$config_mail_from_name', config_invoice_from_email = '$config_invoice_from_email', config_invoice_from_name = '$config_invoice_from_name', config_quote_from_email = '$config_quote_from_email', config_quote_from_name = '$config_quote_from_name', config_ticket_from_email = '$config_ticket_from_email', config_ticket_from_name = '$config_ticket_from_name' WHERE company_id = 1");

    // Logging
    logAction("Settings", "Edit", "$session_name edited mail from settings");

    $_SESSION['alert_message'] = "Mail From Settings updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['test_email_smtp'])) {

    validateCSRFToken($_POST['csrf_token']);

    $test_email = intval($_POST['test_email']);
    if($test_email == 1) {
        $email_from = sanitizeInput($config_mail_from_email);
        $email_from_name = sanitizeInput($config_mail_from_name);
    } elseif ($test_email == 2) {
        $email_from = sanitizeInput($config_invoice_from_email);
        $email_from_name = sanitizeInput($config_invoice_from_name);
    } elseif ($test_email == 3) {
        $email_from = sanitizeInput($config_quote_from_email);
        $email_from_name = sanitizeInput($config_quote_from_name);
    } else {
        $email_from = sanitizeInput($config_ticket_from_email);
        $email_from_name = sanitizeInput($config_ticket_from_name);
    }

    $email_to = sanitizeInput($_POST['email_to']);
    $subject = "Test email from ITFlow";
    $body = "This is a test email from ITFlow. If you are reading this, it worked!";

    $data = [
        [
            'from' => $email_from,
            'from_name' => $email_from_name,
            'recipient' => $email_to,
            'recipient_name' => 'Chap',
            'subject' => $subject,
            'body' => $body
        ]
    ];
    $mail = addToMailQueue($mysqli, $data);

    if ($mail === true) {
        $_SESSION['alert_message'] = "Test email queued successfully! <a class='text-bold text-light' href='admin_mail_queue.php'>Check Admin > Mail queue</a>";
    } else {
        $_SESSION['alert_type'] = "error";
        $_SESSION['alert_message'] = "Failed to add test mail to queue";
    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}


// Test IMAP
// Autoload Composer dependencies
// require_once __DIR__ . '/../plugins/php-imap/vendor/autoload.php';

// Webklex PHP-IMAP
//use Webklex\PHPIMAP\ClientManager;

if (isset($_POST['test_email_imap'])) {
    /*
        validateCSRFToken($_POST['csrf_token']);

        try {
            // Initialize the client manager and create the client
            $clientManager = new ClientManager();
            $client = $clientManager->make([
                'host'          => $config_imap_host,
                'port'          => $config_imap_port,
                'encryption'    => $config_imap_encryption,
                'validate_cert' => true,
                'username'      => $config_imap_username,
                'password'      => $config_imap_password,
                'protocol'      => 'imap'
            ]);

            // Connect to the IMAP server
            $client->connect();

            $_SESSION['alert_message'] = "Connected successfully";
        } catch (Exception $e) {
            $_SESSION['alert_type'] = "error";
            $_SESSION['alert_message'] = "Test IMAP connection failed: " . $e->getMessage();
        }
    */
    $_SESSION['alert_message'] = "Test is Work In Progress";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}
