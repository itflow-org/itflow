<?php

// A payment's fields from POST - date, amount, account_id, payment_method and
// reference - checked as the payment form checks them, each only when sent, into
// $payment_set. On an update $payment_row is the payment, so the account and way
// it already has stay allowed when archived since. $payment_model_error says what was wrong.

if (!function_exists('apiDeny')) {
    http_response_code(404);
    exit();
}

$payment_row = $payment_row ?? null;
$payment_set = [];
$payment_model_error = '';

if (array_key_exists('date', $_POST)) {
    $value = trim((string) $_POST['date']);
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value || $value > '2999-12-31') {
        $payment_model_error = 'date is a date - 2026-11-05.';
    } else {
        $payment_set['date'] = $value;
    }
}

if (array_key_exists('amount', $_POST)) {
    $amount = billingNumberInput(is_scalar($_POST['amount']) ? $_POST['amount'] : '');
    if ($amount === null || $amount <= 0) {
        $payment_model_error = 'amount is what was paid - more than 0, such as 150.00.';
    } else {
        $payment_set['amount'] = $amount;
    }
}

if (array_key_exists('account_id', $_POST)) {
    $accounts = paymentAccounts(intval($payment_row['payment_account_id'] ?? 0));
    $account_id = intval($_POST['account_id']);
    if (!isset($accounts[$account_id])) {
        $payment_model_error = 'account_id is the account it went into, one of: ' . implode(', ', array_map(function ($account) {
            return $account['account_id'] . ' (' . $account['account_name'] . ')';
        }, $accounts)) . '.';
    } else {
        $payment_set['account_id'] = $account_id;
    }
}

if (array_key_exists('payment_method', $_POST)) {
    $methods = paymentMethods('in');
    $current = (string) ($payment_row['payment_method'] ?? '');
    if ($current !== '' && !in_array($current, $methods, true)) {
        $methods[] = $current;
    }
    $posted = trim((string) (is_scalar($_POST['payment_method']) ? $_POST['payment_method'] : ''));
    $match = null;
    foreach ($methods as $method) {
        if (strcasecmp($method, $posted) === 0) {
            $match = $method;
        }
    }
    if ($match === null) {
        $payment_model_error = 'payment_method is how it was paid, one of: ' . implode(', ', $methods) . '.';
    } else {
        $payment_set['payment_method'] = $match;
    }
}

if (array_key_exists('reference', $_POST)) {
    $payment_set['reference'] = mb_substr(trim((string) (is_scalar($_POST['reference']) ? $_POST['reference'] : '')), 0, 200);
}

if ($payment_model_error !== '') {
    http_response_code(400);
    $return_arr['success'] = "False";
    $return_arr['message'] = $payment_model_error;
    echo json_encode($return_arr);
    exit();
}
