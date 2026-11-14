<?php
/*
 * API - Contracts - Create
 * POST /api/v1/contracts/create.php
 *
 * Add a client's contract, as New Contract does (contractFromInput() and
 * contractCreate()) - as a draft, or active from its start date.
 *
 * Parameters (POST, JSON body):
 *   api_key          required - Your API key
 *   client_id        required - The client
 *   contract_name    required
 *   contract_type    optional - managed (the default), break_fix or the other kinds (CONTRACT_TYPES)
 *   contract_status  optional - Draft (the default) or Active
 *   contract_start_date, contract_end_date, contract_term_months, contract_auto_renew, contract_notice_days,
 *   contract_contact_id, contract_owner_id, contract_template_id, contract_details, contract_notes,
 *   contract_net_terms, contract_rate_standard, contract_rate_after_hours, contract_support_hours,
 *   contract_sla_low_id, contract_sla_medium_id, contract_sla_high_id, contract_sla_urgent_id,
 *   contract_monthly_hours, contract_hours_alert   optional
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once 'contract_map.php';

$insert_id = false;

if (!contractsReady()) {
    apiFormRefuse('Contracts need the database update - run it from Maintenance > Update first');
}

$in = apiFormInput($contract_map);
$in['client_id'] = $client_id;
$in['status'] = (string) ($_POST['contract_status'] ?? 'Draft');
[$fields, $error] = contractFromInput($in, 'contract');
if ($error !== '') {
    apiFormRefuse($error);
}
if (!apiUserCanAccessClient($fields['client_id'])) {
    apiDeny("The user linked to this API key does not have access to the target client for this write.");
}

$contract_id = intval(contractCreate($fields));
if ($contract_id) {
    contractHoursSync($contract_id);
    $insert_id = $contract_id;
    $label = escapeSql($fields['name']);
    logAudit("Contract", "Create", "Added " . strtolower($fields['status']) . " contract $label (" . escapeSql(contractTypeLabel($fields['type'])) . ") via API ($api_key_name)", intval($fields['client_id']), $contract_id);
    logAudit("API", "Success", "Added contract $label via API ($api_key_name)", intval($fields['client_id']));
}

// Output
require_once '../create_output.php';
