<?php

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['edit_mail_smtp_settings'])) {
    
    validateCSRFToken($_POST['csrf_token']);

    $config_smtp_provider            = sanitizeInput($_POST['config_smtp_provider'] ?? 'standard_smtp');
    $config_smtp_host                = sanitizeInput($_POST['config_smtp_host']);
    $config_smtp_port                = intval($_POST['config_smtp_port'] ?? 0);
    $config_smtp_encryption          = sanitizeInput($_POST['config_smtp_encryption']);
    $config_smtp_username            = sanitizeInput($_POST['config_smtp_username']);
    $config_smtp_password            = sanitizeInput($_POST['config_smtp_password']);

    // Shared OAuth fields
    $config_mail_oauth_client_id     = sanitizeInput($_POST['config_mail_oauth_client_id']);
    $config_mail_oauth_client_secret = sanitizeInput($_POST['config_mail_oauth_client_secret']);
    $config_mail_oauth_tenant_id     = sanitizeInput($_POST['config_mail_oauth_tenant_id']);
    $config_mail_oauth_refresh_token = sanitizeInput($_POST['config_mail_oauth_refresh_token']);
    $config_mail_oauth_access_token  = sanitizeInput($_POST['config_mail_oauth_access_token']);

    mysqli_query($mysqli, "
        UPDATE settings SET
            config_smtp_provider              = " . ($config_smtp_provider === 'none' ? "NULL" : "'$config_smtp_provider'") . ",
            config_smtp_host                  = '$config_smtp_host',
            config_smtp_port                  = $config_smtp_port,
            config_smtp_encryption            = '$config_smtp_encryption',
            config_smtp_username              = '$config_smtp_username',
            config_smtp_password              = '$config_smtp_password',
            config_mail_oauth_client_id       = '$config_mail_oauth_client_id',
            config_mail_oauth_client_secret   = '$config_mail_oauth_client_secret',
            config_mail_oauth_tenant_id       = '$config_mail_oauth_tenant_id',
            config_mail_oauth_refresh_token   = '$config_mail_oauth_refresh_token',
            config_mail_oauth_access_token    = '$config_mail_oauth_access_token'
        WHERE company_id = 1
    ");

    logAction("Settings", "Edit", "$session_name edited SMTP settings");
    
    flash_alert("SMTP Mail Settings updated");
    
    redirect();

}

if (isset($_POST['edit_mail_imap_settings'])) {
    
    validateCSRFToken($_POST['csrf_token']);

    $config_imap_provider            = sanitizeInput($_POST['config_imap_provider'] ?? 'standard_imap');
    $config_imap_host                = sanitizeInput($_POST['config_imap_host']);
    $config_imap_port                = intval($_POST['config_imap_port'] ?? 0);
    $config_imap_encryption          = sanitizeInput($_POST['config_imap_encryption']);
    $config_imap_username            = sanitizeInput($_POST['config_imap_username']);
    $config_imap_password            = sanitizeInput($_POST['config_imap_password']);

    // Shared OAuth fields
    $config_mail_oauth_client_id     = sanitizeInput($_POST['config_mail_oauth_client_id']);
    $config_mail_oauth_client_secret = sanitizeInput($_POST['config_mail_oauth_client_secret']);
    $config_mail_oauth_tenant_id     = sanitizeInput($_POST['config_mail_oauth_tenant_id']);
    $config_mail_oauth_refresh_token = sanitizeInput($_POST['config_mail_oauth_refresh_token']);
    $config_mail_oauth_access_token  = sanitizeInput($_POST['config_mail_oauth_access_token']);

    mysqli_query($mysqli, "
        UPDATE settings SET
            config_imap_provider              = " . ($config_imap_provider === 'none' ? "NULL" : "'$config_imap_provider'") . ",
            config_imap_host                  = '$config_imap_host',
            config_imap_port                  = $config_imap_port,
            config_imap_encryption            = '$config_imap_encryption',
            config_imap_username              = '$config_imap_username',
            config_imap_password              = '$config_imap_password',
            config_mail_oauth_client_id       = '$config_mail_oauth_client_id',
            config_mail_oauth_client_secret   = '$config_mail_oauth_client_secret',
            config_mail_oauth_tenant_id       = '$config_mail_oauth_tenant_id',
            config_mail_oauth_refresh_token   = '$config_mail_oauth_refresh_token',
            config_mail_oauth_access_token    = '$config_mail_oauth_access_token'
        WHERE company_id = 1
    ");

    logAction("Settings", "Edit", "$session_name edited IMAP settings");
    
    flash_alert("IMAP Mail Settings updated");
    
    redirect();

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

    logAction("Settings", "Edit", "$session_name edited mail from settings");

    flash_alert("Mail From Settings updated");

    redirect();

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
    
    $mail = addToMailQueue($data);

    if ($mail === true) {
        flash_alert("Test email queued! <a class='text-bold text-light' href='admin_mail_queue.php'>Check Admin > Mail queue</a>");
    } else {
        flash_alert("Failed to add test mail to queue", 'error');
    }

    redirect();

}

if (isset($_POST['test_email_imap'])) {
    
    validateCSRFToken($_POST['csrf_token']);

    // Setup your IMAP connection parameters
    $hostname = "{" . $config_imap_host . ":" . $config_imap_port . "/" . $config_imap_encryption . "/novalidate-cert}INBOX";
    $username = $config_imap_username;
    $password = $config_imap_password;

    try {
        $inbox = @imap_open($hostname, $username, $password);

        if ($inbox) {
            imap_close($inbox);
            flash_alert("Connected successfully");
        } else {
            throw new Exception(imap_last_error());
        }
    } catch (Exception $e) {
        flash_alert("<strong>IMAP connection failed:</strong> " . $e->getMessage(), 'error');
    }

    redirect();

}
