<?php
/*
 * API - Contract Hours - Delete
 * POST /api/v1/contract_hours/delete.php
 *
 * Delete a block of prepaid hours nothing was drawn from, as Delete does on it -
 * an invoice made for them stays.
 *
 * Parameters (POST, JSON body): api_key, contract_block_id, client_id (the contract's
 * client - needed when the key's user is limited to certain clients)
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

$delete_count = false;

$block = contractHoursReady() ? contractBlockRow($_POST['contract_block_id'] ?? 0) : null;
$contract_lookup_id = intval($block['contract_id'] ?? 0);
require_once '../contracts/contract_find.php';

if ($block && $contract) {
    if (intval($block['uses']) || !contractHoursBlockDelete($block['contract_block_id'])) {
        apiFormRefuse('Time was drawn from those hours, so they stay - put the tickets back first');
    }
    $delete_count = 1;
    $hours = escapeSql(contractHoursWords($block['contract_block_hours']));
    logAudit("Contract", "Edit", "Deleted $hours from contract $contract_label via API ($api_key_name)", $client_id, $contract_id);
    logAudit("API", "Success", "Deleted $hours from contract $contract_label via API ($api_key_name)", $client_id);
}

// Output
require_once '../delete_output.php';
