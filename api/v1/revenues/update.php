<?php
/*
 * API - Revenues - Update
 * POST /api/v1/revenues/update.php
 *
 * Change other income, as Edit does - only what is sent. Cleared on a reconciled
 * statement, its date, amount and account stay.
 *
 * Parameters (POST, JSON body): api_key, revenue_id, client_id (its client - needed when
 * the key's user is limited to certain clients), and the fields of revenues/create.php
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once 'revenue_map.php';

$update_count = false;

$revenue_id = intval($_POST['revenue_id'] ?? 0);
$before = $revenue_id ? incomeRevenueRow($revenue_id) : null;
if ($before && $client_id && intval($before['revenue_client_id']) !== $client_id) {
    $before = null;
}

if ($before) {
    $in = apiFormInput($revenue_map, $before);
    if (intval($in['client_id'] ?? 0) && !apiUserCanAccessClient($in['client_id'])) {
        apiDeny("The user linked to this API key does not have access to the target client for this write.");
    }
    $error = '';
    if (!incomeRevenueSave($in, $revenue_id, $error)) {
        apiFormRefuse($error);
    }
    $update_count = 1;
    $row = incomeRevenueRow($revenue_id, false);
    $label = escapeSql(incomeRevenueLabel($row));
    logAudit("Revenue", "Edit", "Edited other income $label via API ($api_key_name)", intval($row['revenue_client_id']), $revenue_id);
    logAudit("API", "Success", "Edited other income $label via API ($api_key_name)", intval($row['revenue_client_id']));
}

// Output
require_once '../update_output.php';
