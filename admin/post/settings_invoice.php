<?php

/*
 * Admin > Settings > Invoices and billing (admin/settings_invoice.php):
 * edit_invoice_settings saves the numbering, the footer and tax ID, late
 * fees, who is emailed an invoice paid online, payment reminders and
 * statements, how time is billed, the markup billable expenses start with,
 * and trips and mileage.
 *
 * A next number already in use, an email address that isn't one, or an
 * amount, percent or distance that isn't one comes back to the page saying
 * so beside it - nothing is saved. Choices from a list settle to one of
 * them, and reminder days to the ones that can be (billingReminderDaysParse()),
 * as they always have. A setting not sent at all keeps what it is; a switch
 * not sent is off.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['edit_invoice_settings'])) {

    validateCSRFToken();

    $before = settingsCurrent(['config_invoice_prefix', 'config_invoice_next_number', 'config_recurring_invoice_prefix', 'config_recurring_invoice_next_number',
        'config_invoice_footer', 'config_invoice_show_tax_id', 'config_invoice_late_fee_enable', 'config_invoice_late_fee_percent', 'config_invoice_paid_notification_email',
        'config_send_invoice_reminders', 'config_invoice_reminder_days', 'config_invoice_reminder_repeat', 'config_statement_monthly', 'config_statement_day', 'config_billing_email_attach_pdf',
        'config_time_increment', 'config_time_rounding', 'config_time_minimum', 'config_time_item_name', 'config_expense_markup',
        'config_mileage_unit', 'config_mileage_rate', 'config_mileage_category_id', 'config_trip_charge', 'config_trip_bill_rate', 'config_trip_included', 'config_trip_item_name']);

    // What was sent, and what is saved for anything that wasn't
    $in = $_POST + array_map(function ($value) { return (string) $value; }, $before);

    [$invoice_prefix, $invoice_number, $errors] = settingsNumberingCheck('invoice', $in, $before);
    [$recurring_prefix, $recurring_number, $recurring_errors] = settingsNumberingCheck('recurring_invoice', $in, $before);
    $errors += $recurring_errors;

    $after = [
        'config_invoice_prefix' => $invoice_prefix,
        'config_invoice_next_number' => $invoice_number,
        'config_recurring_invoice_prefix' => $recurring_prefix,
        'config_recurring_invoice_next_number' => $recurring_number,
        'config_invoice_footer' => rtrim(str_replace("\r\n", "\n", (string) $in['config_invoice_footer'])),
        'config_invoice_paid_notification_email' => trim((string) $in['config_invoice_paid_notification_email']),
    ];
    foreach (['config_invoice_show_tax_id', 'config_invoice_late_fee_enable', 'config_send_invoice_reminders', 'config_statement_monthly', 'config_billing_email_attach_pdf'] as $switch) {
        $after[$switch] = empty($_POST[$switch]) ? 0 : 1;
    }

    // Late fees - a percent of what is owed
    $late_fee = billingNumberInput(str_replace('%', '', (string) $in['config_invoice_late_fee_percent']));
    if ($late_fee === null || $late_fee < 0 || $late_fee > 100) {
        $errors['config_invoice_late_fee_percent'] = 'A percent, from 0 to 100.';
    }
    $after['config_invoice_late_fee_percent'] = $late_fee;

    if ($problem = settingsEmailProblem($after['config_invoice_paid_notification_email'])) {
        $errors['config_invoice_paid_notification_email'] = $problem;
    }

    // Reminders and statements - see billingReminderRules(). Days before the due date
    // are stored negative, the due date itself as 0, days late positive.
    if (isset($_POST['reminder_before']) || isset($_POST['reminder_after'])) {
        $reminder_days = array_merge(
            array_map(function ($day) { return -$day; }, array_filter(billingReminderDaysParse($_POST['reminder_before'] ?? ''), function ($day) { return $day > 0 && $day <= 60; })),
            !empty($_POST['reminder_on_due']) ? [0] : [],
            array_filter(billingReminderDaysParse($_POST['reminder_after'] ?? ''), function ($day) { return $day > 0; })
        );
        $after['config_invoice_reminder_days'] = implode(',', billingReminderDaysParse(implode(',', $reminder_days)));
    }
    $after['config_invoice_reminder_repeat'] = max(0, min(365, intval($_POST['reminder_repeat'] ?? $before['config_invoice_reminder_repeat'])));
    $after['config_statement_day'] = max(1, min(28, intval($in['config_statement_day'])));

    // Billing time - see billingTimeRules()
    $increment = intval($in['config_time_increment']);
    $after['config_time_increment'] = isset(billingTimeIncrements()[$increment]) ? $increment : 15;
    $after['config_time_rounding'] = $in['config_time_rounding'] === 'up' ? 'up' : 'nearest';
    $after['config_time_minimum'] = max(0, min(1440, intval($in['config_time_minimum'])));
    $time_item = trim((string) $in['config_time_item_name']);
    $after['config_time_item_name'] = mb_substr($time_item !== '' ? $time_item : 'Support [Hourly]', 0, 200);

    // Billable expenses - the markup they start with, a percent
    $markup = billingNumberInput(str_replace('%', '', (string) $in['config_expense_markup']));
    if ($markup === null || $markup < 0 || $markup > 1000) {
        $errors['config_expense_markup'] = 'The markup is a percent, from 0 to 1000.';
    }
    $after['config_expense_markup'] = $markup;

    // Trips and mileage - see tripRules()
    $after['config_mileage_unit'] = $in['config_mileage_unit'] === 'km' ? 'km' : 'mi';
    foreach (['config_mileage_rate', 'config_trip_bill_rate'] as $rate) {
        $after[$rate] = tripRateInput($in[$rate]);
        if ($after[$rate] === null) {
            $errors[$rate] = 'The rates are amounts, such as 0.70.';
        }
    }
    $after['config_trip_charge'] = billingNumberInput($in['config_trip_charge']);
    if ($after['config_trip_charge'] === null || $after['config_trip_charge'] < 0 || $after['config_trip_charge'] >= 1000000) {
        $errors['config_trip_charge'] = 'An amount, such as 45.00.';
    }
    $included = billingNumberInput($in['config_trip_included']);
    if ($included === null || $included < 0 || $included >= 100000) {
        $errors['config_trip_included'] = 'A distance, such as 20.';
    }
    $after['config_trip_included'] = $included === null ? null : round($included, 1);
    $category_id = intval($in['config_mileage_category_id']);
    if ($category_id && !mysqli_num_rows(mysqli_query($mysqli, "SELECT category_id FROM categories WHERE category_id = $category_id AND category_type = 'Expense'"))) {
        $category_id = 0;
    }
    $after['config_mileage_category_id'] = $category_id;
    $trip_item = trim((string) $in['config_trip_item_name']);
    $after['config_trip_item_name'] = mb_substr($trip_item !== '' ? $trip_item : 'Travel', 0, 200);

    if ($errors) {
        settingsRefuse('invoice', $errors);
    }

    settingsSave('invoice', $before, $after);

    redirect();

}
