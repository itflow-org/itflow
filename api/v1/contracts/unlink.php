<?php
/*
 * API - Contracts - Unlink
 * POST /api/v1/contracts/unlink.php
 *
 * Unlink the recurring invoice that bills a contract, as Unlink does - it carries
 * on as it is, running or paused.
 *
 * Parameters (POST, JSON body): api_key, contract_id, client_id (its client - needed
 * when the key's user is limited to certain clients)
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once "../../../includes/load_global_settings.php";
require_once "../../../includes/load_company_settings.php";
require_once 'contract_find.php';

$update_count = false;

if ($contract) {
    $recurring = contractBillingReady() ? contractRecurringRow($contract) : null;
    if (!$recurring) {
        apiFormRefuse('It has no recurring invoice to unlink');
    }
    $number = escapeSql(billingNumber('recurring', $recurring));
    contractRecurringUnlink($contract, 'the API (' . html_entity_decode($api_key_name, ENT_QUOTES, 'UTF-8') . ')');
    $update_count = 1;
    logAudit("Contract", "Edit", "Unlinked recurring invoice $number from contract $contract_label via API ($api_key_name)", $client_id, $contract_id);
    logAudit("API", "Success", "Unlinked recurring invoice $number from contract $contract_label via API ($api_key_name)", $client_id);
}

// Output
require_once '../update_output.php';
