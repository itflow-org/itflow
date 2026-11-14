<?php
/*
 * API - Revenues - Delete
 * POST /api/v1/revenues/delete.php
 *
 * Delete other income, as Delete does - not once it has cleared on a reconciled statement.
 *
 * Parameters (POST, JSON body): api_key, revenue_id, client_id (its client - needed when
 * the key's user is limited to certain clients)
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once 'revenue_map.php';

$delete_count = false;

$revenue_id = intval($_POST['revenue_id'] ?? 0);
$before = $revenue_id ? incomeRevenueRow($revenue_id) : null;
if ($before && $client_id && intval($before['revenue_client_id']) !== $client_id) {
    $before = null;
}

if ($before) {
    $error = '';
    $row = incomeRevenueDelete($revenue_id, $error);
    if (!$row) {
        apiFormRefuse($error);
    }
    $delete_count = 1;
    $label = escapeSql(incomeRevenueLabel($row));
    logAudit("Revenue", "Delete", "Deleted other income $label via API ($api_key_name)", intval($row['revenue_client_id']));
    logAudit("API", "Success", "Deleted other income $label via API ($api_key_name)", intval($row['revenue_client_id']));
}

// Output
require_once '../delete_output.php';
