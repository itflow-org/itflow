<?php

if (isset($_POST['edit_invoice_settings'])) {

    validateCSRFToken($_POST['csrf_token']);

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
