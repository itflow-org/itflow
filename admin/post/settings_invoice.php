<?php

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['edit_invoice_settings'])) {

    validateCSRFToken();

    $config_invoice_prefix = escapeSql($_POST['config_invoice_prefix']);
    $config_invoice_next_number = intval($_POST['config_invoice_next_number']);
    $config_invoice_footer = escapeSql($_POST['config_invoice_footer']);
    $config_invoice_show_tax_id = intval($_POST['config_invoice_show_tax_id'] ?? 0);
    $config_invoice_late_fee_enable = intval($_POST['config_invoice_late_fee_enable'] ?? 0);
    $config_invoice_late_fee_percent = floatval($_POST['config_invoice_late_fee_percent']);
    $config_recurring_invoice_prefix = escapeSql($_POST['config_recurring_invoice_prefix']);
    $config_recurring_invoice_next_number = intval($_POST['config_recurring_invoice_next_number']);
    $config_invoice_paid_notification_email = '';
    if (filter_var($_POST['config_invoice_paid_notification_email'], FILTER_VALIDATE_EMAIL)) {
        $config_invoice_paid_notification_email = escapeSql($_POST['config_invoice_paid_notification_email']);
    }

    // Billing time - see billingTimeRules()
    $config_time_increment = intval($_POST['config_time_increment'] ?? 15);
    $config_time_increment = isset(billingTimeIncrements()[$config_time_increment]) ? $config_time_increment : 15;
    $config_time_rounding = ($_POST['config_time_rounding'] ?? '') === 'up' ? 'up' : 'nearest';
    $config_time_minimum = max(0, min(1440, intval($_POST['config_time_minimum'] ?? 0)));
    $config_time_item_name = trim((string) ($_POST['config_time_item_name'] ?? ''));
    $config_time_item_name = escapeSql(mb_substr($config_time_item_name !== '' ? $config_time_item_name : 'Support [Hourly]', 0, 200));

    mysqli_query($mysqli,"UPDATE settings SET config_time_increment = $config_time_increment, config_time_rounding = '$config_time_rounding', config_time_minimum = $config_time_minimum, config_time_item_name = '$config_time_item_name' WHERE company_id = 1");

    mysqli_query($mysqli,"UPDATE settings SET config_invoice_prefix = '$config_invoice_prefix', config_invoice_next_number = $config_invoice_next_number, config_invoice_footer = '$config_invoice_footer', config_invoice_show_tax_id = $config_invoice_show_tax_id, config_invoice_late_fee_enable = $config_invoice_late_fee_enable, config_invoice_late_fee_percent = $config_invoice_late_fee_percent, config_invoice_paid_notification_email = '$config_invoice_paid_notification_email', config_recurring_invoice_prefix = '$config_recurring_invoice_prefix', config_recurring_invoice_next_number = $config_recurring_invoice_next_number WHERE company_id = 1");

    logAudit("Settings", "Edit", "$session_name edited invoice settings");

    flashAlert("Invoice Settings edited");

    redirect();

}
