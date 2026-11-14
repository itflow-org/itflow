<?php
/*
 * API - Contracts - Withdraw
 * POST /api/v1/contracts/withdraw.php
 *
 * Withdraw a signing request still waiting, as Withdraw does - its link no longer
 * signs anything.
 *
 * Parameters (POST, JSON body): api_key, contract_id, client_id (its client - needed
 * when the key's user is limited to certain clients), contract_signature_id
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once "../../../includes/load_global_settings.php";
require_once "../../../includes/load_company_settings.php";
require_once 'contract_find.php';

$update_count = false;

if ($contract) {
    $round = contractSignaturesReady() ? contractSignatureRow($_POST['contract_signature_id'] ?? 0) : null;
    if (!$round || intval($round['contract_id']) !== $contract_id || !contractSignaturePending($round)
        || !contractSignatureWithdraw($round, 'Withdrawn by ' . stripslashes($session_name) . ' through the API')) {
        apiFormRefuse("That request isn't waiting on anyone any more");
    }
    $update_count = 1;
    logAudit("Contract", "Withdraw", "Withdrew the signing request for contract $contract_label sent to " . escapeSql($round['contract_signature_sent_to_name']) . " via API ($api_key_name)", $client_id, $contract_id);
    logAudit("API", "Success", "Withdrew a signing request for contract $contract_label via API ($api_key_name)", $client_id);
}

// Output
require_once '../update_output.php';
