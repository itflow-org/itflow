<?php
/*
 * API - Accounts - Update
 * POST /api/v1/accounts/update.php
 *
 * Change an account, as Edit does - only what is sent. Its currency stays once
 * money is on it.
 *
 * Parameters (POST, JSON body): api_key, account_id, and the fields of accounts/create.php
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once 'account_map.php';

$update_count = false;

$account_id = intval($_POST['account_id'] ?? 0);
$before = $account_id ? accountRow($account_id) : null;

if ($before) {
    $error = '';
    if (!accountSave(apiFormInput($account_map, $before), $account_id, $error)) {
        apiFormRefuse($error);
    }
    $update_count = 1;
    $row = accountRow($account_id);
    $changed = abs(floatval($before['opening_balance']) - floatval($row['opening_balance'])) >= 0.005
        ? ', opening balance ' . billingMoney($before['opening_balance'], $row['account_currency_code']) . ' to ' . billingMoney($row['opening_balance'], $row['account_currency_code']) : '';
    $name = escapeSql($row['account_name'] . $changed);
    logAudit("Account", "Edit", "Edited account $name via API ($api_key_name)", 0, $account_id);
    logAudit("API", "Success", "Edited account $name via API ($api_key_name)", 0);
}

// Output
require_once '../update_output.php';
