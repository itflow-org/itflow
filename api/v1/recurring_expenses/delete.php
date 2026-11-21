<?php
/*
 * API - Recurring Expenses - Delete
 * POST /api/v1/recurring_expenses/delete.php
 *
 * Delete a recurring expense, as Delete does - the expenses it recorded stay.
 *
 * Parameters (POST, JSON body): api_key, recurring_expense_id, client_id (its client -
 * needed when the key's user is limited to certain clients)
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once 'recurring_expense_map.php';

$delete_count = false;

require_once 're_find.php';

if ($re_row) {
    $error = '';
    $row = recurringExpenseDelete($recurring_expense_id, $error);
    if (!$row) {
        apiFormRefuse($error);
    }
    $delete_count = 1;
    $label = escapeSql(recurringExpenseLabel($row));
    logAudit("Recurring Expense", "Delete", "Deleted the recurring expense $label via API ($api_key_name)", intval($row['recurring_expense_client_id']));
    logAudit("API", "Success", "Deleted the recurring expense $label via API ($api_key_name)", intval($row['recurring_expense_client_id']));
}

// Output
require_once '../delete_output.php';
