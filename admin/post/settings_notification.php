<?php

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['edit_notification_settings'])) {

    validateCSRFToken();

    // config_enable_cron is NOT set here - the master cron switch moved to Maintenance > Cron
    // in 26.08. Leaving it in this UPDATE would switch cron off every time somebody saved
    // this form, because the checkbox that fed it is gone from the page.
    $config_enable_alert_domain_expire = intval($_POST['config_enable_alert_domain_expire'] ?? 0);
    $config_send_invoice_reminders = intval($_POST['config_send_invoice_reminders'] ?? 0);
    $config_recurring_auto_send_invoice = intval($_POST['config_recurring_auto_send_invoice'] ?? 0);
    $config_ticket_client_general_notifications = intval($_POST['config_ticket_client_general_notifications'] ?? 0);

    mysqli_query($mysqli,"UPDATE settings SET config_send_invoice_reminders = $config_send_invoice_reminders, config_recurring_auto_send_invoice = $config_recurring_auto_send_invoice, config_enable_alert_domain_expire = $config_enable_alert_domain_expire, config_ticket_client_general_notifications = $config_ticket_client_general_notifications WHERE company_id = 1");

    logAudit("Settings", "Edit", "$session_name edited notification settings");

    flashAlert("Notification Settings updated");

    redirect();

}
