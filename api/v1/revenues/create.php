<?php
/*
 * API - Revenues - Create
 * POST /api/v1/revenues/create.php
 *
 * Record other income, as New Income does (incomeRevenueSave()) - an amount, the
 * day it came in, one of your accounts and an income category. Its currency is
 * the account's.
 *
 * Parameters (POST, JSON body):
 *   api_key                 required - Your API key
 *   revenue_date            required - Y-m-d
 *   revenue_amount          required
 *   revenue_account_id      required - The account it went into
 *   revenue_category_id     required - An income category
 *   revenue_client_id       optional - The client it came from
 *   revenue_payment_method, revenue_reference, revenue_description   optional
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once 'revenue_map.php';

$insert_id = false;

// The client it is for has to be one the key's user can reach
$in = apiFormInput($revenue_map);
if (intval($in['client_id'] ?? 0) && !apiUserCanAccessClient($in['client_id'])) {
    apiDeny("The user linked to this API key does not have access to the target client for this write.");
}

$error = '';
$revenue_id = intval(incomeRevenueSave($in, 0, $error));
if (!$revenue_id) {
    apiFormRefuse($error);
}

$insert_id = $revenue_id;
$row = incomeRevenueRow($revenue_id, false);
$label = escapeSql(incomeRevenueLabel($row));
logAudit("Revenue", "Create", "Added other income $label via API ($api_key_name)", intval($row['revenue_client_id']), $revenue_id);
logAudit("API", "Success", "Added other income $label via API ($api_key_name)", intval($row['revenue_client_id']));

// Output
require_once '../create_output.php';
