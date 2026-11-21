<?php

if (!function_exists('apiDeny')) {
    http_response_code(404);
    exit();
}

require_once '../form_input.php';
require_once "../../../includes/load_global_settings.php";
require_once "../../../includes/load_company_settings.php";

// API field => [the contract form's field, the contracts column]
$contract_map = [
    'contract_name' => ['name', 'contract_name'],
    'contract_type' => ['type', 'contract_type'],
    'contract_details' => ['details', 'contract_details'],
    'contract_notes' => ['notes', 'contract_notes'],
    'contract_contact_id' => ['contact_id', 'contract_contact_id'],
    'contract_owner_id' => ['owner_id', 'contract_owner_id'],
    'contract_template_id' => ['template_id', 'contract_template_id'],
    'contract_start_date' => ['start_date', 'contract_start_date'],
    'contract_end_date' => ['end_date', 'contract_end_date'],
    'contract_term_months' => ['term_months', 'contract_term_months'],
    'contract_auto_renew' => ['auto_renew', 'contract_auto_renew'],
    'contract_notice_days' => ['notice_days', 'contract_notice_days'],
    'contract_net_terms' => ['net_terms', 'contract_net_terms'],
    'contract_rate_standard' => ['rate_standard', 'contract_rate_standard'],
    'contract_rate_after_hours' => ['rate_after_hours', 'contract_rate_after_hours'],
    'contract_support_hours' => ['support_hours', 'contract_support_hours'],
    'contract_sla_low_id' => ['sla_low', 'contract_sla_low_id'],
    'contract_sla_medium_id' => ['sla_medium', 'contract_sla_medium_id'],
    'contract_sla_high_id' => ['sla_high', 'contract_sla_high_id'],
    'contract_sla_urgent_id' => ['sla_urgent', 'contract_sla_urgent_id'],
    'contract_monthly_hours' => ['monthly_hours', 'contract_monthly_hours'],
    'contract_hours_alert' => ['hours_alert', 'contract_hours_alert'],
];

// A kind of contract that isn't one is turned down, not taken as managed
if (array_key_exists('contract_type', $_POST) && !isset(CONTRACT_TYPES[(string) $_POST['contract_type']])) {
    apiFormRefuse('contract_type is one of: ' . implode(', ', array_keys(CONTRACT_TYPES)));
}
