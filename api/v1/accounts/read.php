<?php
/*
 * API - Accounts - Read
 * GET /api/v1/accounts/read.php
 *
 * Your bank, card, cash and processor accounts, by name - each with its balance
 * today (the opening balance, with every payment, income, expense and transfer
 * on it) and its type (1 Checking, 2 Savings, 3 Credit card, 4 Cash, 5 Payment
 * processor, 6 Other).
 *
 *   account_id  optional - Just this account, archived or not
 *   archived    optional - 1 to include archived accounts
 */

require_once '../validate_api_key.php';
require_once '../require_get_method.php';

$account_id = intval($_GET['account_id'] ?? 0);
$rows = [];
foreach (accountsList($account_id ? null : (!empty($_GET['archived']) ? null : false)) as $id => $row) {
    if ($account_id && $id !== $account_id) {
        continue;
    }
    $rows[] = [
        'account_id' => $id,
        'account_name' => $row['account_name'],
        'account_type' => intval($row['account_type']),
        'account_currency_code' => $row['account_currency_code'],
        'opening_balance' => round(floatval($row['opening_balance']), 2),
        'balance' => $row['balance'],
        'account_description' => $row['account_description'],
        'account_notes' => $row['account_notes'],
        'account_created_at' => $row['account_created_at'],
        'account_archived_at' => $row['account_archived_at'],
    ];
}

if ($rows) {
    echo json_encode(['success' => 'True', 'count' => count($rows), 'data' => $rows]);
    exit();
}

$sql = false;

// Output
require_once "../read_output.php";
