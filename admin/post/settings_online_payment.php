<?php

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['edit_online_payment_settings'])) {

    validateCSRFToken($_POST['csrf_token']);

    $config_stripe_enable = intval($_POST['config_stripe_enable'] ?? 0);
    $config_stripe_publishable = sanitizeInput($_POST['config_stripe_publishable']);
    $config_stripe_secret = sanitizeInput($_POST['config_stripe_secret']);
    $config_stripe_account = intval($_POST['config_stripe_account']);
    $config_stripe_expense_vendor = intval($_POST['config_stripe_expense_vendor']);
    $config_stripe_expense_category = intval($_POST['config_stripe_expense_category']);
    $config_stripe_percentage_fee = floatval($_POST['config_stripe_percentage_fee']) / 100;
    $config_stripe_flat_fee = floatval($_POST['config_stripe_flat_fee']);

    mysqli_query($mysqli,"UPDATE settings SET config_stripe_enable = $config_stripe_enable, config_stripe_publishable = '$config_stripe_publishable', config_stripe_secret = '$config_stripe_secret', config_stripe_account = $config_stripe_account, config_stripe_expense_vendor = $config_stripe_expense_vendor, config_stripe_expense_category = $config_stripe_expense_category, config_stripe_percentage_fee = $config_stripe_percentage_fee, config_stripe_flat_fee = $config_stripe_flat_fee WHERE company_id = 1");

    logAction("Settings", "Edit", "$session_name edited online payment settings");

    if ($config_stripe_enable && $config_stripe_account == 0) {
        flash_alert("Stripe payment account must be specified!", 'error');
    } else {
        flash_alert("Online Payment Settings updated");
    }

    redirect();

}
