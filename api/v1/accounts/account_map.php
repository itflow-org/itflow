<?php

if (!function_exists('apiDeny')) {
    http_response_code(404);
    exit();
}

require_once '../form_input.php';
require_once "../../../includes/load_global_settings.php";
require_once "../../../includes/load_company_settings.php";
require_once "../../../includes/settings_localization_array.php";

// Accounts are the business's, not a client's
$account_map = [
    'account_name' => ['name', 'account_name'],
    'account_type' => ['type', 'account_type'],
    'account_currency_code' => ['currency', 'account_currency_code'],
    'opening_balance' => ['opening_balance', 'opening_balance'],
    'account_description' => ['description', 'account_description'],
    'account_notes' => ['notes', 'account_notes'],
];
