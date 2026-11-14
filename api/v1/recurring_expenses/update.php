<?php
/*
 * API - Recurring Expenses - Update
 * POST /api/v1/recurring_expenses/update.php
 *
 * Change a recurring expense, as Edit does - only what is sent. A moved next date
 * is the day it keeps to from then; an end date that has passed ends it.
 *
 * Parameters (POST, JSON body): api_key, recurring_expense_id, client_id (its client -
 * needed when the key's user is limited to certain clients), and the fields of
 * recurring_expenses/create.php
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once 'recurring_expense_map.php';

$update_count = false;

require_once 're_find.php';

if ($re_row) {
    $in = apiFormInput($recurring_expense_map, $re_row);
    $in['billing'] = $recurring_expense_billing($re_row);
    if (intval($in['client_id'] ?? 0) && !apiUserCanAccessClient($in['client_id'])) {
        apiDeny("The user linked to this API key does not have access to the target client for this write.");
    }
    $error = '';
    if (!recurringExpenseSave($in, $recurring_expense_id, $error)) {
        apiFormRefuse($error);
    }
    $update_count = 1;
    $row = recurringExpenseRow($recurring_expense_id, false);
    $label = escapeSql(recurringExpenseLabel($row));
    logAudit("Recurring Expense", "Edit", "Edited the recurring expense $label via API ($api_key_name)", intval($row['recurring_expense_client_id']), $recurring_expense_id);
    logAudit("API", "Success", "Edited the recurring expense $label via API ($api_key_name)", intval($row['recurring_expense_client_id']));
}

// Output
require_once '../update_output.php';
