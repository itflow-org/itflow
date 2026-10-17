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

    // Billable expenses - the markup they start with, a percent
    $config_expense_markup = billingNumberInput(str_replace('%', '', (string) ($_POST['config_expense_markup'] ?? '0')));
    if ($config_expense_markup === null || $config_expense_markup < 0 || $config_expense_markup > 1000) {
        flashAlert('The default markup is a percent, from 0 to 1000', 'error');
        redirect();
    }

    // Trips and mileage - see tripRules()
    $config_mileage_unit = ($_POST['config_mileage_unit'] ?? '') === 'km' ? 'km' : 'mi';
    $config_mileage_rate = tripRateInput($_POST['config_mileage_rate'] ?? '');
    $config_trip_bill_rate = tripRateInput($_POST['config_trip_bill_rate'] ?? '');
    $config_trip_charge = billingNumberInput($_POST['config_trip_charge'] ?? '');
    $config_trip_included = billingNumberInput($_POST['config_trip_included'] ?? '');
    if ($config_mileage_rate === null || $config_trip_bill_rate === null) {
        flashAlert('The mileage rates are amounts, such as 0.70', 'error');
        redirect();
    }
    if ($config_trip_charge === null || $config_trip_charge < 0 || $config_trip_charge >= 1000000 || $config_trip_included === null || $config_trip_included < 0 || $config_trip_included >= 100000) {
        flashAlert('The trip charge is an amount, and the distance it covers a number', 'error');
        redirect();
    }
    $config_mileage_category_id = intval($_POST['config_mileage_category_id'] ?? 0);
    if ($config_mileage_category_id && !mysqli_num_rows(mysqli_query($mysqli, "SELECT category_id FROM categories WHERE category_id = $config_mileage_category_id AND category_type = 'Expense'"))) {
        $config_mileage_category_id = 0;
    }
    $config_trip_item_name = trim((string) ($_POST['config_trip_item_name'] ?? ''));
    $config_trip_item_name = escapeSql(mb_substr($config_trip_item_name !== '' ? $config_trip_item_name : 'Travel', 0, 200));
    mysqli_query($mysqli, "UPDATE settings SET config_mileage_unit = '$config_mileage_unit', config_mileage_rate = $config_mileage_rate, config_mileage_category_id = $config_mileage_category_id,
        config_trip_charge = $config_trip_charge, config_trip_bill_rate = $config_trip_bill_rate, config_trip_included = " . round($config_trip_included, 1) . ",
        config_trip_item_name = '$config_trip_item_name' WHERE company_id = 1");

    // Reminders and statements - see billingReminderRules(). Days before the due date
    // are stored negative, the due date itself as 0, days late positive.
    $config_send_invoice_reminders = intval(!empty($_POST['config_send_invoice_reminders']));
    $reminder_days = array_merge(
        array_map(function ($day) { return -$day; }, array_filter(billingReminderDaysParse($_POST['reminder_before'] ?? ''), function ($day) { return $day > 0 && $day <= 60; })),
        !empty($_POST['reminder_on_due']) ? [0] : [],
        array_filter(billingReminderDaysParse($_POST['reminder_after'] ?? ''), function ($day) { return $day > 0; })
    );
    $config_invoice_reminder_days = escapeSql(implode(',', billingReminderDaysParse(implode(',', $reminder_days))));
    $config_invoice_reminder_repeat = max(0, min(365, intval($_POST['reminder_repeat'] ?? 0)));
    $config_statement_monthly = intval(!empty($_POST['config_statement_monthly']));
    $config_statement_day = max(1, min(28, intval($_POST['config_statement_day'] ?? 1)));
    $config_billing_email_attach_pdf = intval(!empty($_POST['config_billing_email_attach_pdf']));

    mysqli_query($mysqli,"UPDATE settings SET config_send_invoice_reminders = $config_send_invoice_reminders, config_invoice_reminder_days = '$config_invoice_reminder_days',
        config_invoice_reminder_repeat = $config_invoice_reminder_repeat, config_statement_monthly = $config_statement_monthly, config_statement_day = $config_statement_day,
        config_billing_email_attach_pdf = $config_billing_email_attach_pdf WHERE company_id = 1");

    mysqli_query($mysqli,"UPDATE settings SET config_time_increment = $config_time_increment, config_time_rounding = '$config_time_rounding', config_time_minimum = $config_time_minimum, config_time_item_name = '$config_time_item_name', config_expense_markup = $config_expense_markup WHERE company_id = 1");

    mysqli_query($mysqli,"UPDATE settings SET config_invoice_prefix = '$config_invoice_prefix', config_invoice_next_number = $config_invoice_next_number, config_invoice_footer = '$config_invoice_footer', config_invoice_show_tax_id = $config_invoice_show_tax_id, config_invoice_late_fee_enable = $config_invoice_late_fee_enable, config_invoice_late_fee_percent = $config_invoice_late_fee_percent, config_invoice_paid_notification_email = '$config_invoice_paid_notification_email', config_recurring_invoice_prefix = '$config_recurring_invoice_prefix', config_recurring_invoice_next_number = $config_recurring_invoice_next_number WHERE company_id = 1");

    logAudit("Settings", "Edit", "$session_name edited invoice settings");

    flashAlert("Invoice Settings edited");

    redirect();

}
