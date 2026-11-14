<?php
/*
 * API - Transfers - Delete
 * POST /api/v1/transfers/delete.php
 *
 * Delete a transfer, as Delete does - both its sides. Not once either has cleared
 * on a reconciled statement.
 *
 * Parameters (POST, JSON body): api_key, transfer_id
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once 'transfer_map.php';

$delete_count = false;

$transfer_id = intval($_POST['transfer_id'] ?? 0);

if ($transfer_id && transferRow($transfer_id)) {
    $error = '';
    $row = transferDelete($transfer_id, $error);
    if (!$row) {
        apiFormRefuse($error);
    }
    $delete_count = 1;
    $label = escapeSql(transferLabel($row));
    logAudit("Transfer", "Delete", "Deleted the transfer $label via API ($api_key_name)", 0);
    logAudit("API", "Success", "Deleted the transfer $label via API ($api_key_name)", 0);
}

// Output
require_once '../delete_output.php';
