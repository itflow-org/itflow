<?php

if (!function_exists('apiDeny')) {
    http_response_code(404);
    exit();
}

require_once '../form_input.php';
require_once "../../../includes/load_global_settings.php";
require_once "../../../includes/load_company_settings.php";

$revenue_map = [
    'revenue_date' => ['date', 'revenue_date'],
    'revenue_amount' => ['amount', 'revenue_amount'],
    'revenue_account_id' => ['account', 'revenue_account_id'],
    'revenue_category_id' => ['category', 'revenue_category_id'],
    'revenue_client_id' => ['client_id', 'revenue_client_id'],
    'revenue_payment_method' => ['payment_method', 'revenue_payment_method'],
    'revenue_reference' => ['reference', 'revenue_reference'],
    'revenue_description' => ['description', 'revenue_description'],
];
