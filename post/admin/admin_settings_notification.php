<?php

if (isset($_POST['edit_notification_settings'])) {

    validateCSRFToken($_POST['csrf_token']);

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

    $key = randomString(32);

    mysqli_query($mysqli,"UPDATE settings SET config_cron_key = '$key' WHERE company_id = 1");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Modify', log_description = '$session_name regenerated cron key', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Cron key regenerated!";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}
