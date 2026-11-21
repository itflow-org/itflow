<?php

if (!function_exists('apiDeny')) {
    http_response_code(404);
    exit();
}

require_once '../form_input.php';
require_once "../../../includes/load_global_settings.php";
require_once "../../../includes/load_company_settings.php";

// Transfers are between the business's own accounts - no client's
$transfer_map = [
    'transfer_date' => ['date', 'transfer_date'],
    'transfer_amount' => ['amount', 'transfer_amount'],
    'transfer_from_account_id' => ['from', 'transfer_from_id'],
    'transfer_to_account_id' => ['to', 'transfer_to_id'],
    'transfer_method' => ['method', 'transfer_method'],
    'transfer_notes' => ['notes', 'transfer_notes'],
];
