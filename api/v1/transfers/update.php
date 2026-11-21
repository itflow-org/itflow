<?php
/*
 * API - Transfers - Update
 * POST /api/v1/transfers/update.php
 *
 * Change a transfer, as Edit does - only what is sent. A side cleared on a
 * reconciled statement keeps its date, amount and account. A deposit that moves
 * to come out of another account lets go of the payments it carried.
 *
 * Parameters (POST, JSON body): api_key, transfer_id, and the fields of transfers/create.php
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once 'transfer_map.php';

$update_count = false;

$transfer_id = intval($_POST['transfer_id'] ?? 0);
$before = $transfer_id ? transferRow($transfer_id) : null;

if ($before) {
    $in = apiFormInput($transfer_map, $before);
    $error = '';
    if (!transferSave($in, $transfer_id, $error)) {
        apiFormRefuse($error);
    }
    $update_count = 1;
    $label = escapeSql(transferLabel(transferRow($transfer_id)));
    logAudit("Transfer", "Edit", "Edited the transfer $label via API ($api_key_name)", 0, $transfer_id);
    logAudit("API", "Success", "Edited the transfer $label via API ($api_key_name)", 0);
}

// Output
require_once '../update_output.php';
