<?php
/*
 * API - Accounts - Create
 * POST /api/v1/accounts/create.php
 *
 * Add an account, as New Account does (accountSave()) - a name no other account
 * has, its type, its currency (yours if left out) and what was in it when you
 * started.
 *
 * Parameters (POST, JSON body):
 *   api_key                required - Your API key
 *   account_name           required
 *   account_type           required - 1 Checking, 2 Savings, 3 Credit card, 4 Cash, 5 Payment processor or 6 Other
 *   account_currency_code  optional - Such as USD
 *   opening_balance        optional - What was in it when you started; owed on a card is below 0
 *   account_description, account_notes   optional
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once 'account_map.php';

$insert_id = false;

$error = '';
$in = apiFormInput($account_map);
$in['currency'] = $in['currency'] ?? (string) $session_company_currency;
$account_id = intval(accountSave($in, 0, $error));
if (!$account_id) {
    apiFormRefuse($error);
}

$insert_id = $account_id;
$name = escapeSql(accountRow($account_id)['account_name']);
logAudit("Account", "Create", "Created account $name via API ($api_key_name)", 0, $account_id);
logAudit("API", "Success", "Created account $name via API ($api_key_name)", 0);

// Output
require_once '../create_output.php';
