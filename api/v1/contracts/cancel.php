<?php
/*
 * API - Contracts - Cancel
 * POST /api/v1/contracts/cancel.php
 *
 * Cancel an active contract, as Cancel does - in force until its last day. Its
 * recurring invoice stops once that day has passed, and a signing request still
 * out is withdrawn.
 *
 * Parameters (POST, JSON body): api_key, contract_id, client_id (its client - needed
 * when the key's user is limited to certain clients), last_day (Y-m-d),
 * reason (optional)
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once "../../../includes/load_global_settings.php";
require_once "../../../includes/load_company_settings.php";
require_once 'contract_find.php';

$update_count = false;

if ($contract) {
    $last_day = trim((string) ($_POST['last_day'] ?? ''));
    $reason = mb_substr(trim(strip_tags((string) ($_POST['reason'] ?? ''))), 0, 255);
    if ($contract['contract_status'] !== 'Active') {
        apiFormRefuse("Contract {$contract['contract_name']} isn't active - it is " . strtolower($contract['contract_status']));
    }
    if (!contractDateValid($last_day) || ($contract['contract_start_date'] && $last_day < $contract['contract_start_date'])) {
        apiFormRefuse('last_day is its last day in force - not before it started');
    }
    contractCancel($contract, $last_day, $reason);
    $return_arr['recurring'] = contractRecurringFollow($contract_id, 'The API');
    $withdrawn = contractSignatureWithdrawStale(0, $contract_id);
    $return_arr['withdrawn'] = $withdrawn ? 1 : 0;
    $update_count = 1;
    logAudit("Contract", "Edit", "Cancelled contract $contract_label, last day " . escapeSql($last_day) . ($reason !== '' ? " - " . escapeSql($reason) : '') . " via API ($api_key_name)", $client_id, $contract_id);
    logAudit("API", "Success", "Cancelled contract $contract_label via API ($api_key_name)", $client_id);
}

// Output
require_once '../update_output.php';
