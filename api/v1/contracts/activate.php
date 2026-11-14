<?php
/*
 * API - Contracts - Activate
 * POST /api/v1/contracts/activate.php
 *
 * Make a draft contract active from a day, as Make active does - its recurring
 * invoice starts running if it has one.
 *
 * Parameters (POST, JSON body): api_key, contract_id, client_id (its client - needed
 * when the key's user is limited to certain clients), start_date (Y-m-d)
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once "../../../includes/load_global_settings.php";
require_once "../../../includes/load_company_settings.php";
require_once 'contract_find.php';

$update_count = false;

if ($contract) {
    $start = trim((string) ($_POST['start_date'] ?? ''));
    if ($contract['contract_status'] !== 'Draft') {
        apiFormRefuse("Contract {$contract['contract_name']} isn't a draft - it is " . strtolower($contract['contract_status']));
    }
    if (!contractDateValid($start)) {
        apiFormRefuse('start_date is the day it is in force from, such as 2026-12-01');
    }
    if ($contract['contract_end_date'] && $contract['contract_end_date'] < $start) {
        apiFormRefuse('It ends before that day - change its end date first');
    }
    $end = contractActivate($contract, $start);
    contractHoursSync($contract_id);
    $return_arr['recurring'] = contractRecurringFollow($contract_id, 'The API');
    $update_count = 1;
    logAudit("Contract", "Edit", "Made contract $contract_label active from " . escapeSql($start) . ($end !== '' ? " to " . escapeSql($end) : '') . " via API ($api_key_name)", $client_id, $contract_id);
    logAudit("API", "Success", "Made contract $contract_label active via API ($api_key_name)", $client_id);
}

// Output
require_once '../update_output.php';
