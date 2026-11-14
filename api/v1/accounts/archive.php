<?php
/*
 * API - Accounts - Archive
 * POST /api/v1/accounts/archive.php
 *
 * Archive an account, as Archive does - its money and history stay.
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
    $row = accountArchive($account_id, $error);
    if (!$row) {
        apiFormRefuse($error);
    }
    $update_count = 1;
    $name = escapeSql($row['account_name']);
    logAudit("Account", "Archive", "Archived account $name via API ($api_key_name)", 0, $account_id);
    logAudit("API", "Success", "Archived account $name via API ($api_key_name)", 0);
}

// Output
require_once '../update_output.php';
