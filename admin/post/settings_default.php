<?php

/*
 * Admin > Settings > Defaults (admin/settings_default.php):
 * edit_default_settings saves the start page, calendar, the default net terms,
 * hourly rate and after-hours rate - what a client is billed on when no
 * contract in force sets it - and the accounts and payment methods. A start page
 * that isn't a page of ITFlow, a calendar or account that isn't there (an
 * archived account only if it is the one saved), a payment method not in
 * Admin > Payment Methods (but the one saved) or a rate that isn't one comes
 * back to the page saying so - nothing is saved. A setting not sent at all
 * keeps what it is - the accounts aren't on the page with accounting off.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['edit_default_settings'])) {

    validateCSRFToken();

    $accounts = ['config_default_payment_account', 'config_default_expense_account', 'config_default_transfer_from_account', 'config_default_transfer_to_account'];
    $methods = ['config_default_payment_method', 'config_default_expense_payment_method'];
    $before = settingsCurrent(array_merge(['config_start_page', 'config_default_calendar', 'config_default_net_terms', 'config_default_hourly_rate'], $accounts, $methods));

    // What was sent, and what is saved for anything that wasn't
    $in = $_POST + array_map(function ($value) { return (string) $value; }, $before);

    $errors = [];
    $after = ['config_start_page' => trim((string) $in['config_start_page'])];
    if ($after['config_start_page'] !== (string) $before['config_start_page'] && ($problem = settingsStartPageProblem($after['config_start_page']))) {
        $errors['config_start_page'] = $problem;
    }

    $after['config_default_calendar'] = intval($in['config_default_calendar']);
    if ($after['config_default_calendar'] && !mysqli_num_rows(mysqli_query($mysqli, "SELECT calendar_id FROM calendars WHERE calendar_id = {$after['config_default_calendar']}"))) {
        $errors['config_default_calendar'] = 'That calendar isn\'t there any more.';
    }

    $after['config_default_net_terms'] = intval($in['config_default_net_terms']);
    if (!isset(settingsNetTerms()[$after['config_default_net_terms']]) && $after['config_default_net_terms'] !== intval($before['config_default_net_terms'])) {
        $errors['config_default_net_terms'] = 'Pick the terms from the list.';
    }

    $rate = billingNumberInput($in['config_default_hourly_rate']);
    if ($rate === null || $rate < 0 || $rate >= 1000000) {
        $errors['config_default_hourly_rate'] = 'An amount, such as 125.00 - 0 for none.';
    }
    $after['config_default_hourly_rate'] = $rate;

    // The after-hours rate (database 3.5.0) - empty is none, and after-hours time bills at the hourly rate
    if (billingAfterHoursReady() && array_key_exists('config_default_after_hours_rate', $_POST)) {
        $before += settingsCurrent(['config_default_after_hours_rate']);
        $after_hours_text = trim((string) $_POST['config_default_after_hours_rate']);
        $after_hours = $after_hours_text === '' ? null : billingNumberInput($after_hours_text);
        if ($after_hours_text !== '' && ($after_hours === null || $after_hours < 0 || $after_hours >= 1000000)) {
            $errors['config_default_after_hours_rate'] = 'An amount, such as 175.00 - or leave it empty for the hourly rate.';
        }
        $after['config_default_after_hours_rate'] = $after_hours !== null && round($after_hours, 2) > 0 ? round($after_hours, 2) : null;
    }

    foreach ($accounts as $account) {
        $after[$account] = intval($in[$account]);
        if ($after[$account] && $after[$account] !== intval($before[$account])
            && !mysqli_num_rows(mysqli_query($mysqli, "SELECT account_id FROM accounts WHERE account_id = {$after[$account]} AND account_archived_at IS NULL"))) {
            $errors[$account] = 'That account isn\'t there any more, or is archived.';
        }
    }

    foreach ($methods as $method) {
        $after[$method] = trim((string) $in[$method]);
        // The payments default from the methods for money in, the expenses one from those for money out
        $payment_methods = paymentMethods($method === 'config_default_expense_payment_method' ? 'out' : 'in');
        if ($after[$method] !== '' && $after[$method] !== (string) $before[$method] && !in_array($after[$method], $payment_methods, true)) {
            $errors[$method] = 'Pick one from the list - they are in Payment methods.';
        }
    }

    if ($errors) {
        settingsRefuse('default', $errors);
    }

    settingsSave('default', $before, $after);

    redirect();

}
