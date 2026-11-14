<?php
/*
 * API - Contracts - Delete
 * POST /api/v1/contracts/delete.php
 *
 * Delete a contract, as Delete does - not once tickets drew on its prepaid hours,
 * or it was signed by both sides.
 *
 * Parameters (POST, JSON body): api_key, contract_id, client_id (its client - needed
 * when the key's user is limited to certain clients)
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once "../../../includes/load_global_settings.php";
require_once "../../../includes/load_company_settings.php";
require_once 'contract_find.php';

$delete_count = false;

if ($contract) {
    $blocker = contractDeleteBlocker($contract);
    if ($blocker !== '') {
        apiFormRefuse("Contract {$contract['contract_name']} wasn't deleted. $blocker");
    }
    mysqli_query($mysqli, "DELETE FROM contracts WHERE contract_id = $contract_id");
    $delete_count = mysqli_affected_rows($mysqli);
    if ($delete_count > 0) {
        logAudit("Contract", "Delete", "Deleted contract $contract_label via API ($api_key_name)", $client_id, $contract_id);
        logAudit("API", "Success", "Deleted contract $contract_label via API ($api_key_name)", $client_id);
    }
}

// Output
require_once '../delete_output.php';
