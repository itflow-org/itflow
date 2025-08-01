<?php

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['edit_invoice_settings'])) {

    validateCSRFToken($_POST['csrf_token']);

    $config_invoice_prefix = sanitizeInput($_POST['config_invoice_prefix']);
    $config_invoice_next_number = intval($_POST['config_invoice_next_number']);
    $config_invoice_footer = sanitizeInput($_POST['config_invoice_footer']);
    $config_invoice_show_tax_id = intval($_POST['config_invoice_show_tax_id'] ?? 0);
    $config_invoice_late_fee_enable = intval($_POST['config_invoice_late_fee_enable'] ?? 0);
    $config_invoice_late_fee_percent = floatval($_POST['config_invoice_late_fee_percent']);
    $config_recurring_invoice_prefix = sanitizeInput($_POST['config_recurring_invoice_prefix']);
    $config_recurring_invoice_next_number = intval($_POST['config_recurring_invoice_next_number']);
    $config_invoice_paid_notification_email = '';
    if (filter_var($_POST['config_invoice_paid_notification_email'], FILTER_VALIDATE_EMAIL)) {
        $config_invoice_paid_notification_email = sanitizeInput($_POST['config_invoice_paid_notification_email']);
    }

    mysqli_query($mysqli,"UPDATE settings SET config_invoice_prefix = '$config_invoice_prefix', config_invoice_next_number = $config_invoice_next_number, config_invoice_footer = '$config_invoice_footer', config_invoice_show_tax_id = $config_invoice_show_tax_id, config_invoice_late_fee_enable = $config_invoice_late_fee_enable, config_invoice_late_fee_percent = $config_invoice_late_fee_percent, config_invoice_paid_notification_email = '$config_invoice_paid_notification_email', config_recurring_invoice_prefix = '$config_recurring_invoice_prefix', config_recurring_invoice_next_number = $config_recurring_invoice_next_number WHERE company_id = 1");

    // Logging
    logAction("Settings", "Edit", "$session_name edited invoice settings");

    $_SESSION['alert_message'] = "Invoice Settings edited";

    redirect();

}
