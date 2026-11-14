<?php

// The expense's fields from POST as expenseSave() takes them - on an update,
// what isn't sent stays as the expense has it ($expense_row)

$expense_row = $expense_row ?? null;

$expense_fields = [
    'expense_date' => 'date',
    'expense_amount' => 'amount',
    'expense_vendor_id' => 'vendor',
    'expense_category_id' => 'category',
    'expense_account_id' => 'account',
    'expense_paid_by' => 'paid_by',
    'expense_payment_method' => 'payment_method',
    'expense_reference' => 'reference',
    'expense_description' => 'description',
    'expense_billable' => 'billable',
    'expense_markup' => 'markup',
];

$expense_in = [];
foreach ($expense_fields as $api_field => $key) {
    if (array_key_exists($api_field, $_POST)) {
        $expense_in[$key] = is_scalar($_POST[$api_field]) ? (string) $_POST[$api_field] : '';
    } elseif ($expense_row) {
        $expense_in[$key] = (string) ($expense_row[$api_field] ?? '');
    }
}

// Paid personally by an agent: on no account, until they are paid back
if ($expense_row && !array_key_exists('expense_account_id', $_POST) && intval($expense_row['expense_account_id']) === 0 && intval($expense_row['expense_paid_by']) > 0) {
    $expense_in['account'] = 'personal';
}

// The tax only when sent - left out, the expense keeps its own (expenseTaxFromInput())
foreach (['expense_tax_id' => 'tax_id', 'expense_tax_amount' => 'tax_amount'] as $api_field => $key) {
    if (array_key_exists($api_field, $_POST)) {
        $expense_in[$key] = is_scalar($_POST[$api_field]) ? (string) $_POST[$api_field] : '';
    }
}
