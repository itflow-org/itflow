<?php
/*
 * API - Transfers - Create
 * POST /api/v1/transfers/create.php
 *
 * Move money between two of your accounts, as New Transfer does (transferSave()) -
 * two different accounts, an amount and the day it moved.
 *
 * Parameters (POST, JSON body):
 *   api_key                   required - Your API key
 *   transfer_date             required - Y-m-d
 *   transfer_amount           required
 *   transfer_from_account_id  required - Where it came out of
 *   transfer_to_account_id    required - Where it went in
 *   transfer_method           optional - One of your payment methods
 *   transfer_notes            optional
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once 'transfer_map.php';

$insert_id = false;

$error = '';
$transfer_id = intval(transferSave(apiFormInput($transfer_map), 0, $error));
if (!$transfer_id) {
    apiFormRefuse($error);
}

$insert_id = $transfer_id;
$label = escapeSql(transferLabel(transferRow($transfer_id)));
logAudit("Transfer", "Create", "Transferred $label via API ($api_key_name)", 0, $transfer_id);
logAudit("API", "Success", "Transferred $label via API ($api_key_name)", 0);

// Output
require_once '../create_output.php';
