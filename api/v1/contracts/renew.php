<?php
/*
 * API - Contracts - Renew
 * POST /api/v1/contracts/renew.php
 *
 * Renew a contract to a new last day, as Renew does - after its current one, and
 * not in the past. A recurring invoice it stopped runs again.
 *
 * Parameters (POST, JSON body): api_key, contract_id, client_id (its client - needed
 * when the key's user is limited to certain clients), end_date (Y-m-d)
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once "../../../includes/load_global_settings.php";
require_once "../../../includes/load_company_settings.php";
require_once 'contract_find.php';

$update_count = false;

if ($contract) {
    $end = trim((string) ($_POST['end_date'] ?? ''));
    if ($contract['contract_status'] === 'Draft' || !contractDateValid($end) || $end < date('Y-m-d') || ($contract['contract_end_date'] && $end <= $contract['contract_end_date'])) {
        apiFormRefuse($contract['contract_status'] === 'Draft' ? 'A draft is made active, not renewed' : 'end_date is its new last day - after its current one, and not in the past');
    }
    contractRenew($contract, $end);
    contractHoursSync($contract_id);
    $return_arr['recurring'] = contractRecurringFollow($contract_id, 'The API');
    $update_count = 1;
    logAudit("Contract", "Edit", "Renewed contract $contract_label to " . escapeSql($end) . " via API ($api_key_name)", $client_id, $contract_id);
    logAudit("API", "Success", "Renewed contract $contract_label via API ($api_key_name)", $client_id);
}

// Output
require_once '../update_output.php';
