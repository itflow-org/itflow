<?php
/*
 * API - Recurring Expenses - Create
 * POST /api/v1/recurring_expenses/create.php
 *
 * Set up a bill that comes round, as New Recurring Expense does
 * (recurringExpenseSave()) - recorded as an expense on each date by the nightly
 * run, and on to the next.
 *
 * Parameters (POST, JSON body):
 *   api_key                                 required - Your API key
 *   recurring_expense_vendor_id             required - One of your vendors
 *   recurring_expense_amount                required - Each time
 *   recurring_expense_frequency             required - month, quarter or year
 *   recurring_expense_next_date             required - The first one, Y-m-d
 *   recurring_expense_account_id            required - The account it is paid from
 *   recurring_expense_category_id           required - An expense category
 *   recurring_expense_end_date              optional - The last day it runs
 *   recurring_expense_client_id             optional - The client it is for
 *   recurring_expense_billing               optional - bill (to the client, at the markup), covered (by recurring_expense_recurring_invoice_id) or none
 *   recurring_expense_markup, recurring_expense_recurring_invoice_id   optional
 *   recurring_expense_tax_id, recurring_expense_tax_amount             optional - The tax in it
 *   recurring_expense_payment_method, recurring_expense_reference, recurring_expense_description   optional
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once 'recurring_expense_map.php';

$insert_id = false;

$in = apiFormInput($recurring_expense_map);
$in['billing'] = $recurring_expense_billing(null);
if (intval($in['client_id'] ?? 0) && !apiUserCanAccessClient($in['client_id'])) {
    apiDeny("The user linked to this API key does not have access to the target client for this write.");
}

$error = '';
$id = intval(recurringExpenseSave($in, 0, $error));
if (!$id) {
    apiFormRefuse($error);
}

$insert_id = $id;
$row = recurringExpenseRow($id, false);
$label = escapeSql(recurringExpenseLabel($row));
logAudit("Recurring Expense", "Create", "Created the recurring expense $label via API ($api_key_name)", intval($row['recurring_expense_client_id']), $id);
logAudit("API", "Success", "Created the recurring expense $label via API ($api_key_name)", intval($row['recurring_expense_client_id']));

// Output
require_once '../create_output.php';
