<?php

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['edit_notification_settings'])) {

    validateCSRFToken($_POST['csrf_token']);

    $config_enable_cron = intval($_POST['config_enable_cron'] ?? 0);
    $config_enable_alert_domain_expire = intval($_POST['config_enable_alert_domain_expire'] ?? 0);
    $config_send_invoice_reminders = intval($_POST['config_send_invoice_reminders'] ?? 0);
    $config_recurring_auto_send_invoice = intval($_POST['config_recurring_auto_send_invoice'] ?? 0);
    $config_ticket_client_general_notifications = intval($_POST['config_ticket_client_general_notifications'] ?? 0);

    mysqli_query($mysqli,"UPDATE settings SET config_send_invoice_reminders = $config_send_invoice_reminders, config_recurring_auto_send_invoice = $config_recurring_auto_send_invoice, config_enable_cron = $config_enable_cron, config_enable_alert_domain_expire = $config_enable_alert_domain_expire, config_ticket_client_general_notifications = $config_ticket_client_general_notifications WHERE company_id = 1");

    // Logging
    logAction("Settings", "Edit", "$session_name edited notification settings");

    $_SESSION['alert_message'] = "Notification Settings updated";

    redirect();

}
