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

    logAction("Settings", "Edit", "$session_name edited SMTP mail settings");

    flash_alert("SMTP Mail Settings updated");

    redirect();

}

if (isset($_POST['edit_mail_imap_settings'])) {

    validateCSRFToken($_POST['csrf_token']);

    // Provider ('' -> NULL allowed)
    $config_imap_provider = sanitizeInput($_POST['config_imap_provider']);
    $allowed_providers = ['standard_imap','google_oauth','microsoft_oauth'];
    if ($config_imap_provider !== '' && !in_array($config_imap_provider, $allowed_providers, true)) {
        $config_imap_provider = 'standard_imap'; // fallback
    }

    // Standard IMAP fields (kept for all providers; OAuth still needs these endpoints)
    $config_imap_host        = sanitizeInput($_POST['config_imap_host']);
    $config_imap_port        = (int) sanitizeInput($_POST['config_imap_port']);
    $config_imap_encryption  = sanitizeInput($_POST['config_imap_encryption']); // '', 'tls', 'ssl'
    $config_imap_username    = sanitizeInput($_POST['config_imap_username']);
    $config_imap_password    = sanitizeInput($_POST['config_imap_password']);   // ignored if OAuth selected

    // Shared OAuth fields (may or may not be present in your form yet)
    $config_mail_oauth_client_id                = sanitizeInput($_POST['config_mail_oauth_client_id']);
    $config_mail_oauth_client_secret            = sanitizeInput($_POST['config_mail_oauth_client_secret']);
    $config_mail_oauth_tenant_id                = sanitizeInput($_POST['config_mail_oauth_tenant_id']); // M365 only; harmless to keep when Google
    $config_mail_oauth_refresh_token            = sanitizeInput($_POST['config_mail_oauth_refresh_token']);
    $config_mail_oauth_access_token             = sanitizeInput($_POST['config_mail_oauth_access_token']); // optional manual paste
    $config_mail_oauth_access_token_expires_at  = sanitizeInput($_POST['config_mail_oauth_access_token_expires_at']); // 'YYYY-mm-dd HH:ii:ss' optional

    // If provider is not OAuth, purge OAuth values on save
    $is_oauth = ($config_imap_provider === 'google_oauth' || $config_imap_provider === 'microsoft_oauth');

    // Detect refresh token change to invalidate access token cache
    // (Relies on $config_mail_oauth_refresh_token loaded earlier with settings)
    $refresh_changed = false;
    if ($is_oauth) {
        $prev_refresh = isset($config_mail_oauth_refresh_token_current) ? $config_mail_oauth_refresh_token_current : ($config_mail_oauth_refresh_token ?? '');
        // If you already load settings into $config_mail_oauth_refresh_token, use that:
        if (isset($config_mail_oauth_refresh_token)) {
            $prev_refresh = $config_mail_oauth_refresh_token;
        }
        $refresh_changed = ($config_mail_oauth_refresh_token !== '' && $config_mail_oauth_refresh_token !== $prev_refresh)
                           || ($config_mail_oauth_refresh_token === '' && $prev_refresh !== '');
    }

    // If OAuth refresh changed or provider just switched to non-OAuth, clear access token values
    if (!$is_oauth || $refresh_changed) {
        $config_mail_oauth_access_token = '';
        $config_mail_oauth_access_token_expires_at = '';
    }

    // Helper for NULL / quoted values
    $q = fn($v) => ($v !== '' ? "'" . mysqli_real_escape_string($mysqli, $v) . "'" : "NULL");

    // Build UPDATE with correct NULL handling
    $sql = "
        UPDATE settings SET
            config_imap_provider = " . ($config_imap_provider !== '' ? $q($config_imap_provider) : "NULL") . ",
            config_imap_host = " . $q($config_imap_host) . ",
            config_imap_port = " . (int)$config_imap_port . ",
            config_imap_encryption = " . $q($config_imap_encryption) . ",
            config_imap_username = " . $q($config_imap_username) . ",
            config_imap_password = " . ($is_oauth ? "NULL" : $q($config_imap_password)) . ",

            -- Shared OAuth fields (kept even if provider is Google or Microsoft; NULL if not used)
            config_mail_oauth_client_id = " . ($is_oauth ? $q($config_mail_oauth_client_id) : "NULL") . ",
            config_mail_oauth_client_secret = " . ($is_oauth ? $q($config_mail_oauth_client_secret) : "NULL") . ",
            config_mail_oauth_tenant_id = " . ($is_oauth ? $q($config_mail_oauth_tenant_id) : "NULL") . ",
            config_mail_oauth_refresh_token = " . ($is_oauth ? $q($config_mail_oauth_refresh_token) : "NULL") . ",
            config_mail_oauth_access_token = " . ($is_oauth ? $q($config_mail_oauth_access_token) : "NULL") . ",
            config_mail_oauth_access_token_expires_at = " . ($is_oauth ? $q($config_mail_oauth_access_token_expires_at) : "NULL") . "
        WHERE company_id = 1
    ";

    mysqli_query($mysqli, $sql);

    logAction("Settings", "Edit", "$session_name edited IMAP/OAuth mail settings");

    // Friendly hint about what was saved
    if ($config_imap_provider === '') {
        flash_alert("IMAP monitoring disabled (provider not configured).");
    } elseif ($config_imap_provider === 'standard_imap') {
        flash_alert("IMAP settings updated (standard username/password).");
    } elseif ($config_imap_provider === 'google_oauth') {
        flash_alert("IMAP settings updated for Google Workspace (OAuth).");
    } elseif ($config_imap_provider === 'microsoft_oauth') {
        flash_alert("IMAP settings updated for Microsoft 365 (OAuth).");
    } else {
        flash_alert("IMAP settings updated.");
    }

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
