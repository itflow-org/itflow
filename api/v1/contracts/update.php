<?php
/*
 * API - Contracts - Update
 * POST /api/v1/contracts/update.php
 *
 * Change a contract, as Edit does - only what is sent. It stays with its client
 * and in its status - those change through activate, renew and cancel. One out
 * for signature whose wording changes has that request withdrawn.
 *
 * Parameters (POST, JSON body): api_key, contract_id, client_id (its client - needed
 * when the key's user is limited to certain clients), and the fields of contracts/create.php
 *
 * Answer: withdrawn - 1 when a signing request was withdrawn.
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once 'contract_map.php';
require_once 'contract_find.php';

$update_count = false;

if ($contract) {
    $in = apiFormInput($contract_map, $contract);
    $in['client_id'] = $contract['contract_client_id'];
    $in['status'] = $contract['contract_status'] === 'Draft' ? 'Draft' : 'Active';
    [$fields, $error] = contractFromInput($in, 'contract');
    if ($error !== '') {
        apiFormRefuse($error);
    }
    $changes = contractUpdate($contract, $fields);
    contractHoursSync($contract_id);
    $withdrawn = contractSignaturesReady() ? contractSignatureCheckChanged(contractRow($contract_id)) : null;

    $update_count = 1;
    $return_arr['withdrawn'] = $withdrawn ? 1 : 0;
    if ($changes) {
        logAudit("Contract", "Edit", "Changed contract $contract_label: " . escapeSql(implode('; ', $changes)) . " via API ($api_key_name)", $client_id, $contract_id);
    }
    if ($withdrawn) {
        logAudit("Contract", "Withdraw", "The signing request for contract $contract_label sent to " . escapeSql($withdrawn['contract_signature_sent_to_name']) . " was withdrawn - the contract changed", $client_id, $contract_id);
    }
    logAudit("API", "Success", "Edited contract $contract_label via API ($api_key_name)", $client_id);
}

// Output
require_once '../update_output.php';
