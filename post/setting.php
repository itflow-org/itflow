<?php

/*
 * ITFlow - GET/POST request handler for ITFlow app settings (admin)
 */

if (isset($_POST['edit_company'])) {

    validateCSRFToken($_POST['csrf_token']);
    validateAdminRole();

    require_once 'post/setting_company_model.php';


    $sql = mysqli_query($mysqli,"SELECT company_logo FROM companies WHERE company_id = 1");
    $row = mysqli_fetch_array($sql);
    $existing_file_name = sanitizeInput($row['company_logo']);

    // Check to see if a file is attached
    if ($_FILES['file']['tmp_name'] != '') {
        if ($new_file_name = checkFileUpload($_FILES['file'], array('jpg', 'jpeg', 'gif', 'png'))) {
            $file_tmp_path = $_FILES['file']['tmp_name'];


            // directory in which the uploaded file will be moved
            $upload_file_dir = "uploads/settings/";
            $dest_path = $upload_file_dir . $new_file_name;

            move_uploaded_file($file_tmp_path, $dest_path);

            // Delete old file
            unlink("uploads/settings/$existing_file_name");

            // Set Logo
            mysqli_query($mysqli,"UPDATE companies SET company_logo = '$new_file_name' WHERE company_id = 1");

            $_SESSION['alert_message'] = 'File successfully uploaded.';
        }else{

            $_SESSION['alert_message'] = 'There was an error moving the file to upload directory. Please make sure the upload directory is writable by web server.';
        }
    }

    mysqli_query($mysqli,"UPDATE companies SET company_name = '$name', company_address = '$address', company_city = '$city', company_state = '$state', company_zip = '$zip', company_country = '$country', company_phone = '$phone', company_email = '$email', company_website = '$website' WHERE company_id = 1");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Company', log_action = 'Modify', log_description = '$session_name modified company $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Company <strong>$name</strong> updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_localization'])) {

    validateCSRFToken($_POST['csrf_token']);
    validateAdminRole();

    $locale = sanitizeInput($_POST['locale']);
    $currency_code = sanitizeInput($_POST['currency_code']);
    $timezone = sanitizeInput($_POST['timezone']);

    mysqli_query($mysqli,"UPDATE companies SET company_locale = '$locale', company_currency = '$currency_code' WHERE company_id = 1");

    mysqli_query($mysqli,"UPDATE settings SET config_timezone = '$timezone' WHERE company_id = 1");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Company', log_action = 'Edit', log_description = '$session_name edited company localization settings', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Company localization updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_mail_smtp_settings'])) {

    validateCSRFToken($_POST['csrf_token']);
    validateAdminRole();

    $config_smtp_host = sanitizeInput($_POST['config_smtp_host']);
    $config_smtp_port = intval($_POST['config_smtp_port']);
    $config_smtp_encryption = sanitizeInput($_POST['config_smtp_encryption']);
    $config_smtp_username = sanitizeInput($_POST['config_smtp_username']);
    $config_smtp_password = sanitizeInput($_POST['config_smtp_password']);

    mysqli_query($mysqli,"UPDATE settings SET config_smtp_host = '$config_smtp_host', config_smtp_port = $config_smtp_port, config_smtp_encryption = '$config_smtp_encryption', config_smtp_username = '$config_smtp_username', config_smtp_password = '$config_smtp_password' WHERE company_id = 1");

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Modify', log_description = '$session_name modified SMTP mail settings', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "SMTP Mail Settings updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_mail_imap_settings'])) {

    validateCSRFToken($_POST['csrf_token']);
    validateAdminRole();

    $config_imap_host = sanitizeInput($_POST['config_imap_host']);
    $config_imap_username = sanitizeInput($_POST['config_imap_username']);
    $config_imap_password = sanitizeInput($_POST['config_imap_password']);
    $config_imap_port = intval($_POST['config_imap_port']);
    $config_imap_encryption = sanitizeInput($_POST['config_imap_encryption']);

    mysqli_query($mysqli,"UPDATE settings SET config_imap_host = '$config_imap_host', config_imap_port = $config_imap_port, config_imap_encryption = '$config_imap_encryption', config_imap_username = '$config_imap_username', config_imap_password = '$config_imap_password' WHERE company_id = 1");


    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Modify', log_description = '$session_name modified IMAP mail settings', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "IMAP Mail Settings updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_mail_from_settings'])) {

    validateCSRFToken($_POST['csrf_token']);
    validateAdminRole();

    $config_mail_from_email = sanitizeInput($_POST['config_mail_from_email']);
    $config_mail_from_name = sanitizeInput($_POST['config_mail_from_name']);

    $config_invoice_from_email = sanitizeInput($_POST['config_invoice_from_email']);
    $config_invoice_from_name = sanitizeInput($_POST['config_invoice_from_name']);

    $config_quote_from_email = sanitizeInput($_POST['config_quote_from_email']);
    $config_quote_from_name = sanitizeInput($_POST['config_quote_from_name']);

    $config_ticket_from_email = sanitizeInput($_POST['config_ticket_from_email']);
    $config_ticket_from_name = sanitizeInput($_POST['config_ticket_from_name']);

    mysqli_query($mysqli,"UPDATE settings SET config_mail_from_email = '$config_mail_from_email', config_mail_from_name = '$config_mail_from_name', config_invoice_from_email = '$config_invoice_from_email', config_invoice_from_name = '$config_invoice_from_name', config_quote_from_email = '$config_quote_from_email', config_quote_from_name = '$config_quote_from_name', config_ticket_from_email = '$config_ticket_from_email', config_ticket_from_name = '$config_ticket_from_name' WHERE company_id = 1");

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Modify', log_description = '$session_name modified Mail From settings', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Mail From Settings updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['test_email_smtp'])) {

    validateCSRFToken($_POST['csrf_token']);
    validateAdminRole();

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
    validateAdminRole();

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


if (isset($_POST['edit_invoice_settings'])) {

    validateCSRFToken($_POST['csrf_token']);
    validateAdminRole();

    $config_invoice_prefix = sanitizeInput($_POST['config_invoice_prefix']);
    $config_invoice_next_number = intval($_POST['config_invoice_next_number']);
    $config_invoice_footer = sanitizeInput($_POST['config_invoice_footer']);
    $config_invoice_late_fee_enable = intval($_POST['config_invoice_late_fee_enable']);
    $config_invoice_late_fee_percent = floatval($_POST['config_invoice_late_fee_percent']);
    $config_recurring_prefix = sanitizeInput($_POST['config_recurring_prefix']);
    $config_recurring_next_number = intval($_POST['config_recurring_next_number']);


    mysqli_query($mysqli,"UPDATE settings SET config_invoice_prefix = '$config_invoice_prefix', config_invoice_next_number = $config_invoice_next_number, config_invoice_footer = '$config_invoice_footer', config_invoice_late_fee_enable = $config_invoice_late_fee_enable, config_invoice_late_fee_percent = $config_invoice_late_fee_percent, config_recurring_prefix = '$config_recurring_prefix', config_recurring_next_number = $config_recurring_next_number WHERE company_id = 1");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Edit', log_description = '$session_name edited invoice settings', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Invoice Settings edited";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_quote_settings'])) {

    validateCSRFToken($_POST['csrf_token']);
    validateAdminRole();

    $config_quote_prefix = sanitizeInput($_POST['config_quote_prefix']);
    $config_quote_next_number = intval($_POST['config_quote_next_number']);
    $config_quote_footer = sanitizeInput($_POST['config_quote_footer']);

    mysqli_query($mysqli,"UPDATE settings SET config_quote_prefix = '$config_quote_prefix', config_quote_next_number = $config_quote_next_number, config_quote_footer = '$config_quote_footer' WHERE company_id = 1");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Modify', log_description = '$session_name modified quote settings', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Quote Settings updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_project_settings'])) {

    validateCSRFToken($_POST['csrf_token']);
    validateAdminRole();

    $config_project_prefix = sanitizeInput($_POST['config_project_prefix']);
    $config_project_next_number = intval($_POST['config_project_next_number']);

    mysqli_query($mysqli,"UPDATE settings SET config_project_prefix = '$config_project_prefix', config_project_next_number = $config_project_next_number WHERE company_id = 1");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Modify', log_description = '$session_name modified project settings', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Project Settings updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_ticket_settings'])) {

    validateAdminRole();

    $config_ticket_prefix = sanitizeInput($_POST['config_ticket_prefix']);
    $config_ticket_next_number = intval($_POST['config_ticket_next_number']);
    $config_ticket_email_parse = intval($_POST['config_ticket_email_parse']);
    $config_ticket_default_billable = intval($_POST['config_ticket_default_billable']);
    $config_ticket_autoclose = intval($_POST['config_ticket_autoclose']);
    $config_ticket_autoclose_hours = intval($_POST['config_ticket_autoclose_hours']);
    $config_ticket_new_ticket_notification_email = sanitizeInput($_POST['config_ticket_new_ticket_notification_email']);

    mysqli_query($mysqli,"UPDATE settings SET config_ticket_prefix = '$config_ticket_prefix', config_ticket_next_number = $config_ticket_next_number, config_ticket_email_parse = $config_ticket_email_parse, config_ticket_autoclose = $config_ticket_autoclose, config_ticket_autoclose_hours = $config_ticket_autoclose_hours, config_ticket_new_ticket_notification_email = '$config_ticket_new_ticket_notification_email', config_ticket_default_billable = $config_ticket_default_billable WHERE company_id = 1");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Modify', log_description = '$session_name modified ticket settings', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Ticket Settings updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_default_settings'])) {

    validateCSRFToken($_POST['csrf_token']);
    validateAdminRole();

    $start_page = sanitizeInput($_POST['start_page']);
    $expense_account = intval($_POST['expense_account']);
    $payment_account = intval($_POST['payment_account']);
    $payment_method = sanitizeInput($_POST['payment_method']);
    $expense_payment_method = sanitizeInput($_POST['expense_payment_method']);
    $transfer_from_account = intval($_POST['transfer_from_account']);
    $transfer_to_account = intval($_POST['transfer_to_account']);
    $calendar = intval($_POST['calendar']);
    $net_terms = intval($_POST['net_terms']);
    $hourly_rate = floatval($_POST['hourly_rate']);
    $phone_mask = intval($_POST['phone_mask']);

    mysqli_query($mysqli,"UPDATE settings SET config_start_page = '$start_page', config_default_expense_account = $expense_account, config_default_payment_account = $payment_account, config_default_payment_method = '$payment_method', config_default_expense_payment_method = '$expense_payment_method', config_default_transfer_from_account = $transfer_from_account, config_default_transfer_to_account = $transfer_to_account, config_default_calendar = $calendar, config_default_net_terms = $net_terms, config_default_hourly_rate = $hourly_rate, config_phone_mask = $phone_mask WHERE company_id = 1");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Modify', log_description = '$session_name modified default settings', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Default settings updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['edit_theme_settings'])) {

    validateCSRFToken($_POST['csrf_token']);
    validateAdminRole();

    $theme = preg_replace("/[^0-9a-zA-Z-]/", "", sanitizeInput($_POST['theme']));

    mysqli_query($mysqli,"UPDATE settings SET config_theme = '$theme' WHERE company_id = 1");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Modify', log_description = '$session_name modified theme settings', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Changed theme to <strong>$theme</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['edit_favicon_settings'])) {

    validateCSRFToken($_POST['csrf_token']);

    validateAdminRole();

    // Check to see if a file is attached
    if ($_FILES['file']['tmp_name'] != '') {
        if ($new_file_name = checkFileUpload($_FILES['file'], array('ico'))) {
            $file_tmp_path = $_FILES['file']['tmp_name'];

            // Delete old file
            if(file_exists("uploads/favicon.ico")) {
                unlink("uploads/favicon.ico");
            }

            // directory in which the uploaded file will be moved
            $upload_file_dir = "uploads/";
            //Force File Name
            $new_file_name = "favicon.ico";
            $dest_path = $upload_file_dir . $new_file_name;

            move_uploaded_file($file_tmp_path, $dest_path);

            $_SESSION['alert_message'] = 'File successfully uploaded.';
        }else{

            $_SESSION['alert_message'] = 'There was an error moving the file to upload directory. Please make sure the upload directory is writable by web server.';
        }
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Modify', log_description = '$session_name updated the favicon', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "You updated the favicon";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_notification_settings'])) {

    validateCSRFToken($_POST['csrf_token']);
    validateAdminRole();

    $config_enable_cron = intval($_POST['config_enable_cron']);
    $config_cron_key = sanitizeInput($_POST['config_cron_key']);
    $config_enable_alert_domain_expire = intval($_POST['config_enable_alert_domain_expire']);
    $config_send_invoice_reminders = intval($_POST['config_send_invoice_reminders']);
    $config_recurring_auto_send_invoice = intval($_POST['config_recurring_auto_send_invoice']);
    $config_ticket_client_general_notifications = intval($_POST['config_ticket_client_general_notifications']);

    mysqli_query($mysqli,"UPDATE settings SET config_send_invoice_reminders = $config_send_invoice_reminders, config_recurring_auto_send_invoice = $config_recurring_auto_send_invoice, config_enable_cron = $config_enable_cron, config_enable_alert_domain_expire = $config_enable_alert_domain_expire, config_ticket_client_general_notifications = $config_ticket_client_general_notifications WHERE company_id = 1");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Modify', log_description = '$session_name modified notification settings', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Notification Settings updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['generate_cron_key'])) {
    validateAdminRole();

    $key = randomString(32);

    mysqli_query($mysqli,"UPDATE settings SET config_cron_key = '$key' WHERE company_id = 1");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Modify', log_description = '$session_name regenerated cron key', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Cron key regenerated!";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_online_payment_settings'])) {

    validateCSRFToken($_POST['csrf_token']);
    validateAdminRole();

    $config_stripe_enable = intval($_POST['config_stripe_enable']);
    $config_stripe_publishable = sanitizeInput($_POST['config_stripe_publishable']);
    $config_stripe_secret = sanitizeInput($_POST['config_stripe_secret']);
    $config_stripe_account = intval($_POST['config_stripe_account']);
    $config_stripe_expense_vendor = intval($_POST['config_stripe_expense_vendor']);
    $config_stripe_expense_category = intval($_POST['config_stripe_expense_category']);
    $config_stripe_percentage_fee = floatval($_POST['config_stripe_percentage_fee']) / 100;
    $config_stripe_flat_fee = floatval($_POST['config_stripe_flat_fee']);
    $config_stripe_client_pays_fees = intval($_POST['config_stripe_client_pays_fees']);

    mysqli_query($mysqli,"UPDATE settings SET config_stripe_enable = $config_stripe_enable, config_stripe_publishable = '$config_stripe_publishable', config_stripe_secret = '$config_stripe_secret', config_stripe_account = $config_stripe_account, config_stripe_expense_vendor = $config_stripe_expense_vendor, config_stripe_expense_category = $config_stripe_expense_category, config_stripe_percentage_fee = $config_stripe_percentage_fee, config_stripe_flat_fee = $config_stripe_flat_fee, config_stripe_client_pays_fees = $config_stripe_client_pays_fees WHERE company_id = 1");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Modify', log_description = '$session_name modified online payment settings', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Online Payment Settings updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['edit_integrations_settings'])) {

    validateCSRFToken($_POST['csrf_token']);
    validateAdminRole();

    $azure_client_id = sanitizeInput($_POST['azure_client_id']);
    $azure_client_secret = sanitizeInput($_POST['azure_client_secret']);

    mysqli_query($mysqli,"UPDATE settings SET config_azure_client_id = '$azure_client_id', config_azure_client_secret = '$azure_client_secret' WHERE company_id = 1");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Modify', log_description = '$session_name modified integrations settings', log_ip = '$session_ip', log_user_agent = '$session_user_agent',  log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Integrations Settings updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_ai_settings'])) {

    validateCSRFToken($_POST['csrf_token']);

    validateAdminRole();

    $provider = sanitizeInput($_POST['provider']);
    if($provider){
        $ai_enable = 1;
    } else {
        $ai_enable = 0;
    }
    $model = sanitizeInput($_POST['model']);
    $url = sanitizeInput($_POST['url']);
    $api_key = sanitizeInput($_POST['api_key']);

    mysqli_query($mysqli,"UPDATE settings SET config_ai_enable = $ai_enable, config_ai_provider = '$provider', config_ai_model = '$model', config_ai_url = '$url', config_ai_api_key = '$api_key' WHERE company_id = 1");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Edit', log_description = '$session_name edited AI settings', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "You updated the AI Settings";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_module_settings'])) {

    validateAdminRole();

    $config_module_enable_itdoc = intval($_POST['config_module_enable_itdoc']);
    $config_module_enable_ticketing = intval($_POST['config_module_enable_ticketing']);
    $config_module_enable_accounting = intval($_POST['config_module_enable_accounting']);
    $config_client_portal_enable = intval($_POST['config_client_portal_enable']);

    mysqli_query($mysqli,"UPDATE settings SET config_module_enable_itdoc = $config_module_enable_itdoc, config_module_enable_ticketing = $config_module_enable_ticketing, config_module_enable_accounting = $config_module_enable_accounting, config_client_portal_enable = $config_client_portal_enable WHERE company_id = 1");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Modify', log_description = '$session_name modified module settings', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Module Settings updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_security_settings'])) {

    validateCSRFToken($_POST['csrf_token']);
    validateAdminRole();

    $config_login_message = sanitizeInput($_POST['config_login_message']);
    $config_login_key_required = intval($_POST['config_login_key_required']);
    $config_login_key_secret = sanitizeInput($_POST['config_login_key_secret']);
    $config_login_remember_me_expire = intval($_POST['config_login_remember_me_expire']);

    mysqli_query($mysqli,"UPDATE settings SET config_login_message = '$config_login_message', config_login_key_required = '$config_login_key_required', config_login_key_secret = '$config_login_key_secret', config_login_remember_me_expire = $config_login_remember_me_expire WHERE company_id = 1");

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Modify', log_description = '$session_name modified login key settings', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Login key settings updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['edit_telemetry_settings'])) {

    validateCSRFToken($_POST['csrf_token']);
    validateAdminRole();

    $config_telemetry = intval($_POST['config_telemetry']);

    mysqli_query($mysqli,"UPDATE settings SET config_telemetry = $config_telemetry WHERE company_id = 1");

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Modify', log_description = '$session_name modified telemetry settings', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Telemetry Settings updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['send_failed_mail'])) {

    validateAdminRole();

    $email_id = intval($_GET['send_failed_mail']);

    mysqli_query($mysqli,"UPDATE email_queue SET email_status = 0, email_attempts = 3 WHERE email_id = $email_id");

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Email', log_action = 'Send', log_description = '$session_name attempted to force send email queue id: $email_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, log_entity_id = $email_id");

    $_SESSION['alert_message'] = "Email Force Sent, give it a minute to resend";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['cancel_mail'])) {

    validateTechRole();

    $email_id = intval($_GET['cancel_mail']);

    mysqli_query($mysqli,"UPDATE email_queue SET email_status = 2, email_attempts = 99, email_failed_at = NOW() WHERE email_id = $email_id");

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Email', log_action = 'Cancel', log_description = '$session_name canceled send email queue id: $email_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, log_entity_id = $email_id");

    $_SESSION['alert_message'] = "Email cancelled and marked as failed.";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['bulk_cancel_emails'])) {
    validateAdminRole();
    validateCSRFToken($_POST['csrf_token']);

    $count = 0; // Default 0
    $email_ids = $_POST['email_ids']; // Get array of email IDs to be cancelled

    if (!empty($email_ids)) {

        // Cycle through array and mark each email as failed
        foreach ($email_ids as $email_id) {

            $email_id = intval($email_id);
            mysqli_query($mysqli,"UPDATE email_queue SET email_status = 2, email_attempts = 99, email_failed_at = NOW() WHERE email_id = $email_id");

            $count++;
        }

        // Logging
        mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Email', log_action = 'Cancel', log_description = '$session_name bulk cancelled $count emails from the mail Queue', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

        $_SESSION['alert_message'] = "Cancelled $count email(s)";

    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['bulk_delete_emails'])) {
    validateAdminRole();
    validateCSRFToken($_POST['csrf_token']);

    $count = 0; // Default 0
    $email_ids = $_POST['email_ids']; // Get array of email IDs to be deleted

    if (!empty($email_ids)) {

        // Cycle through array and delete each email
        foreach ($email_ids as $email_id) {

            $email_id = intval($email_id);
            mysqli_query($mysqli,"DELETE FROM email_queue WHERE email_id = $email_id");

            $count++;
        }

        // Logging
        mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Email', log_action = 'Delete', log_description = '$session_name bulk deleted $count emails from the mail Queue', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

        $_SESSION['alert_type'] = "danger";
        $_SESSION['alert_message'] = "Deleted $count email(s)";

    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_GET['download_database'])) {

    validateCSRFToken($_GET['csrf_token']);
    validateAdminRole();

    // Get All Table Names From the Database
    $tables = array();
    $sql = "SHOW TABLES";
    $result = mysqli_query($mysqli, $sql);

    while ($row = mysqli_fetch_row($result)) {
        $tables[] = $row[0];
    }

    $sqlScript = "";
    foreach ($tables as $table) {

        // Prepare SQLscript for creating table structure
        $query = "SHOW CREATE TABLE $table";
        $result = mysqli_query($mysqli, $query);
        $row = mysqli_fetch_row($result);

        $sqlScript .= "\n\n" . $row[1] . ";\n\n";


        $query = "SELECT * FROM $table";
        $result = mysqli_query($mysqli, $query);

        $columnCount = mysqli_num_fields($result);

        // Prepare SQLscript for dumping data for each table
        for ($i = 0; $i < $columnCount; $i ++) {
            while ($row = mysqli_fetch_row($result)) {
                $sqlScript .= "INSERT INTO $table VALUES(";
                for ($j = 0; $j < $columnCount; $j ++) {

                    if (isset($row[$j])) {
                        $sqlScript .= '"' . $row[$j] . '"';
                    } else {
                        $sqlScript .= '""';
                    }
                    if ($j < ($columnCount - 1)) {
                        $sqlScript .= ',';
                    }
                }
                $sqlScript .= ");\n";
            }
        }

        $sqlScript .= "\n";
    }

    if (!empty($sqlScript)) {

        $company_name = $session_company_name;
        // Save the SQL script to a backup file
        $backup_file_name = date('Y-m-d') . '_ITFlow_backup.sql';
        $fileHandler = fopen($backup_file_name, 'w+');
        $number_of_lines = fwrite($fileHandler, $sqlScript);
        fclose($fileHandler);

        // Download the SQL backup file to the browser
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=' . basename($backup_file_name));
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($backup_file_name));
        ob_clean();
        flush();
        readfile($backup_file_name);
        exec('rm ' . $backup_file_name);
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Database', log_action = 'Download', log_description = '$session_name downloaded the database', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Database downloaded";
}

if (isset($_POST['backup_master_key'])) {

    validateCSRFToken($_POST['csrf_token']);
    validateAdminRole();

    $password = $_POST['password'];

    $sql = mysqli_query($mysqli, "SELECT * FROM users WHERE user_id = $session_user_id");
    $userRow = mysqli_fetch_array($sql);

    if (password_verify($password, $userRow['user_password'])) {
        $site_encryption_master_key = decryptUserSpecificKey($userRow['user_specific_encryption_ciphertext'], $password);

        //Logging
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Master Key', log_action = 'Download', log_description = '$session_name retrieved the master encryption key', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");
        mysqli_query($mysqli,"INSERT INTO notifications SET notification_type = 'Settings', notification = '$session_name retrieved the master encryption key'");


        echo "==============================";
        echo "<br>Master encryption key:<br>";
        echo "<b>$site_encryption_master_key</b>";
        echo "<br>==============================";
    } else {
        //Log the failure
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Master Key', log_action = 'Download', log_description = '$session_name attempted to retrieve the master encryption key (failure)', log_ip = '$session_ip', log_user_agent = '$session_user_agent',  log_user_id = $session_user_id");

        $_SESSION['alert_message'] = "Incorrect password.";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    }
}

if (isset($_GET['update'])) {

    validateAdminRole();

    //git fetch downloads the latest from remote without trying to merge or rebase anything. Then the git reset resets the master branch to what you just fetched. The --hard option changes all the files in your working tree to match the files in origin/master

    if(isset($_GET['force_update']) == 1) {
        exec("git fetch --all");
        exec("git reset --hard origin/master");
    } else {
        exec("git pull");
    }
    //header("Location: post.php?update_db");


    // Send Telemetry if enabled during update
    if ($config_telemetry > 0 OR $config_telemetry = 2) {

        $sql = mysqli_query($mysqli,"SELECT * FROM companies WHERE company_id = 1");
        $row = mysqli_fetch_array($sql);

        $company_name = sanitizeInput($row['company_name']);
        $website = sanitizeInput($row['company_website']);
        $city = sanitizeInput($row['company_city']);
        $state = sanitizeInput($row['company_state']);
        $country = sanitizeInput($row['company_country']);
        $currency = sanitizeInput($row['company_currency']);
        $current_version = exec("git rev-parse HEAD");

        // Client Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('client_id') AS num FROM clients"));
        $client_count = $row['num'];

        // Ticket Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('recurring_id') AS num FROM tickets"));
        $ticket_count = $row['num'];

        // Scheduled Ticket Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('scheduled_ticket_id') AS num FROM scheduled_tickets"));
        $scheduled_ticket_count = $row['num'];

        // Calendar Event Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('event_id') AS num FROM events"));
        $calendar_event_count = $row['num'];

        // Quote Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('quote_id') AS num FROM quotes"));
        $quote_count = $row['num'];

        // Invoice Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('invoice_id') AS num FROM invoices"));
        $invoice_count = $row['num'];

        // Revenue Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('revenue_id') AS num FROM revenues"));
        $revenue_count = $row['num'];

        // Recurring Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('recurring_id') AS num FROM recurring"));
        $recurring_count = $row['num'];

        // Account Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('account_id') AS num FROM accounts"));
        $account_count = $row['num'];

        // Tax Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('tax_id') AS num FROM taxes"));
        $tax_count = $row['num'];

        // Product Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('product_id') AS num FROM products"));
        $product_count = $row['num'];

        // Payment Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('payment_id') AS num FROM payments WHERE payment_invoice_id > 0"));
        $payment_count = $row['num'];

        // Company Vendor Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('vendor_id') AS num FROM vendors WHERE vendor_template = 0 AND vendor_client_id = 0"));
        $company_vendor_count = $row['num'];

        // Expense Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('expense_id') AS num FROM expenses WHERE expense_vendor_id > 0"));
        $expense_count = $row['num'];

        // Trip Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('trip_id') AS num FROM trips"));
        $trip_count = $row['num'];

        // Transfer Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('transfer_id') AS num FROM transfers"));
        $transfer_count = $row['num'];

        // Contact Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('contact_id') AS num FROM contacts"));
        $contact_count = $row['num'];

        // Location Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('location_id') AS num FROM locations"));
        $location_count = $row['num'];

        // Asset Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('asset_id') AS num FROM assets"));
        $asset_count = $row['num'];

        // Software Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('software_id') AS num FROM software WHERE software_template = 0"));
        $software_count = $row['num'];

        // Software Template Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('software_id') AS num FROM software WHERE software_template = 1"));
        $software_template_count = $row['num'];

        // Password Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('login_id') AS num FROM logins"));
        $password_count = $row['num'];

        // Network Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('network_id') AS num FROM networks"));
        $network_count = $row['num'];

        // Certificate Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('certificate_id') AS num FROM certificates"));
        $certificate_count = $row['num'];

        // Domain Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('domain_id') AS num FROM domains"));
        $domain_count = $row['num'];

        // Service Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('service_id') AS num FROM services"));
        $service_count = $row['num'];

        // Client Vendor Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('vendor_id') AS num FROM vendors WHERE vendor_template = 0 AND vendor_client_id > 0"));
        $client_vendor_count = $row['num'];

        // Vendor Template Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('vendor_id') AS num FROM vendors WHERE vendor_template = 1"));
        $vendor_template_count = $row['num'];

        // File Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('file_id') AS num FROM files"));
        $file_count = $row['num'];

        // Document Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('document_id') AS num FROM documents WHERE document_template = 0"));
        $document_count = $row['num'];

        // Document Template Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('document_id') AS num FROM documents WHERE document_template = 1"));
        $document_template_count = $row['num'];

        // Shared Item Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('item_id') AS num FROM shared_items"));
        $shared_item_count = $row['num'];

        // Company Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('company_id') AS num FROM companies"));
        $company_count = $row['num'];

        // User Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('user_id') AS num FROM users"));
        $user_count = $row['num'];

        // Category Expense Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('category_id') AS num FROM categories WHERE category_type = 'Expense'"));
        $category_expense_count = $row['num'];

        // Category Income Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('category_id') AS num FROM categories WHERE category_type = 'Income'"));
        $category_income_count = $row['num'];

        // Category Referral Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('category_id') AS num FROM categories WHERE category_type = 'Referral'"));
        $category_referral_count = $row['num'];

        // Category Payment Method Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('category_id') AS num FROM categories WHERE category_type = 'Payment Method'"));
        $category_payment_method_count = $row['num'];

        // Tag Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('tag_id') AS num FROM tags"));
        $tag_count = $row['num'];

        // API Key Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('api_key_id') AS num FROM api_keys"));
        $api_key_count = $row['num'];

        // Log Count
        $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('log_id') AS num FROM logs"));
        $log_count = $row['num'];

        $postdata = http_build_query(
            array(
                'installation_id' => "$installation_id",
                'version' => "$current_version",
                'company_name' => "$company_name",
                'website' => "$website",
                'city' => "$city",
                'state' => "$state",
                'country' => "$country",
                'currency' => "$currency",
                'comments' => "$comments",
                'client_count' => $client_count,
                'ticket_count' => $ticket_count,
                'scheduled_ticket_count' => $scheduled_ticket_count,
                'calendar_event_count' => $calendar_event_count,
                'quote_count' => $quote_count,
                'invoice_count' => $invoice_count,
                'revenue_count' => $revenue_count,
                'recurring_count' => $recurring_count,
                'account_count' => $account_count,
                'tax_count' => $tax_count,
                'product_count' => $product_count,
                'payment_count' => $payment_count,
                'company_vendor_count' => $company_vendor_count,
                'expense_count' => $expense_count,
                'trip_count' => $trip_count,
                'transfer_count' => $transfer_count,
                'contact_count' => $contact_count,
                'location_count' => $location_count,
                'asset_count' => $asset_count,
                'software_count' => $software_count,
                'software_template_count' => $software_template_count,
                'password_count' => $password_count,
                'network_count' => $network_count,
                'certificate_count' => $certificate_count,
                'domain_count' => $domain_count,
                'service_count' => $service_count,
                'client_vendor_count' => $client_vendor_count,
                'vendor_template_count' => $vendor_template_count,
                'file_count' => $file_count,
                'document_count' => $document_count,
                'document_template_count' => $document_template_count,
                'shared_item_count' => $shared_item_count,
                'company_count' => $company_count,
                'user_count' => $user_count,
                'category_expense_count' => $category_expense_count,
                'category_income_count' => $category_income_count,
                'category_referral_count' => $category_referral_count,
                'category_payment_method_count' => $category_payment_method_count,
                'tag_count' => $tag_count,
                'api_key_count' => $api_key_count,
                'log_count' => $log_count,
                'config_theme' => "$config_theme",
                'config_enable_cron' => $config_enable_cron,
                'config_ticket_email_parse' => $config_ticket_email_parse,
                'config_module_enable_itdoc' => $config_module_enable_itdoc,
                'config_module_enable_ticketing' => $config_module_enable_ticketing,
                'config_module_enable_accounting' => $config_module_enable_accounting,
                'config_telemetry' => $config_telemetry,
                'collection_method' => 4
            )
        );

        $opts = array('http' =>
            array(
                'method' => 'POST',
                'header' => 'Content-type: application/x-www-form-urlencoded',
                'content' => $postdata
            )
        );

        $context = stream_context_create($opts);

        $result = file_get_contents('https://telemetry.itflow.org', false, $context);

    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Update', log_description = '$session_name ran updates', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Update successful";

    sleep(1);

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['update_db'])) {

    validateAdminRole();

    // Get the current version
    require_once ('database_version.php');

    // Perform upgrades, if required
    require_once ('database_updates.php');

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Update', log_description = '$session_name updated the database structure', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Database structure update successful";

    sleep(1);

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}
