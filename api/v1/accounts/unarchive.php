<?php
/*
 * API - Accounts - Unarchive
 * POST /api/v1/accounts/unarchive.php
 *
 * Bring an archived account back, as Restore does.
 *
 * Parameters (POST, JSON body): api_key, account_id
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once "../../../includes/load_global_settings.php";

$update_count = false;

$account_id = intval($_POST['account_id'] ?? 0);

if ($account_id && accountRow($account_id)) {
    require_once '../form_input.php';
    $error = '';
    $row = accountRestore($account_id, $error);
    if (!$row) {
        apiFormRefuse($error);
    }
    $update_count = 1;
    $name = escapeSql($row['account_name']);
    logAudit("Account", "Restore", "Restored account $name via API ($api_key_name)", 0, $account_id);
    logAudit("API", "Success", "Unarchived account $name via API ($api_key_name)", 0);
}

// Output
require_once '../update_output.php';
