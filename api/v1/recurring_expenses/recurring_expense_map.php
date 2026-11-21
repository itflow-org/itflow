<?php

if (!function_exists('apiDeny')) {
    http_response_code(404);
    exit();
}

require_once '../form_input.php';
require_once "../../../includes/load_global_settings.php";
require_once "../../../includes/load_company_settings.php";

$recurring_expense_map = [
    'recurring_expense_vendor_id' => ['vendor', 'recurring_expense_vendor_id'],
    'recurring_expense_amount' => ['amount', 'recurring_expense_amount'],
    'recurring_expense_frequency' => ['frequency', 'recurring_expense_frequency'],
    'recurring_expense_next_date' => ['next_date', 'recurring_expense_next_date'],
    'recurring_expense_end_date' => ['end_date', 'recurring_expense_end_date'],
    'recurring_expense_account_id' => ['account', 'recurring_expense_account_id'],
    'recurring_expense_category_id' => ['category', 'recurring_expense_category_id'],
    'recurring_expense_client_id' => ['client_id', 'recurring_expense_client_id'],
    'recurring_expense_payment_method' => ['payment_method', 'recurring_expense_payment_method'],
    'recurring_expense_reference' => ['reference', 'recurring_expense_reference'],
    'recurring_expense_description' => ['description', 'recurring_expense_description'],
    'recurring_expense_markup' => ['markup', 'recurring_expense_markup'],
    'recurring_expense_recurring_invoice_id' => ['recurring_invoice_id', 'recurring_expense_recurring_invoice_id'],
    // The tax only when sent - left out, it keeps its own
    'recurring_expense_tax_id' => ['tax_id', null],
    'recurring_expense_tax_amount' => ['tax_amount', null],
];

// Its billing: bill (to the client, at the markup), covered (by one of the client's
// recurring invoices) or none - from what it has, unless sent
$recurring_expense_billing = function ($row) {
    if (array_key_exists('recurring_expense_billing', $_POST)) {
        $billing = (string) $_POST['recurring_expense_billing'];
        return $billing === 'none' ? '' : $billing;
    }
    if (!$row) {
        return '';
    }
    return intval($row['recurring_expense_billable']) ? 'bill' : (intval($row['recurring_expense_recurring_invoice_id']) ? 'covered' : '');
};
