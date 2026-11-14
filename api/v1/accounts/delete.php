<?php
/*
 * API - Accounts - Delete
 * POST /api/v1/accounts/delete.php
 *
 * Delete an account, as Delete does - only one with no money or history on it.
 *
 * Parameters (POST, JSON body): api_key, account_id
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once "../../../includes/load_global_settings.php";

$delete_count = false;

$account_id = intval($_POST['account_id'] ?? 0);

if ($account_id && accountRow($account_id)) {
    require_once '../form_input.php';
    $error = '';
    $row = accountDelete($account_id, $error);
    if (!$row) {
        apiFormRefuse($error);
    }
    $delete_count = 1;
    $name = escapeSql($row['account_name']);
    logAudit("Account", "Delete", "Deleted account $name via API ($api_key_name)", 0);
    logAudit("API", "Success", "Deleted account $name via API ($api_key_name)", 0);
}

// Output
require_once '../delete_output.php';
