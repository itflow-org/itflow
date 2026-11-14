<?php
/*
 * API - Recurring Expenses - Record
 * POST /api/v1/recurring_expenses/record.php
 *
 * Record its next run now, as Record Now does (recurringExpenseRecord(), the same
 * as the nightly run) - or the run on the day run names, which is recorded once
 * however often it is asked for. It moves on to the one after.
 *
 * Parameters (POST, JSON body): api_key, recurring_expense_id, client_id (its client -
 * needed when the key's user is limited to certain clients), run (optional, Y-m-d)
 *
 * Answer: expense_id, and next_date - or null once it has ended.
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once 'recurring_expense_map.php';

$update_count = false;

require_once 're_find.php';

if ($re_row) {
    $label = recurringExpenseLabel($re_row);
    if (recurringExpenseEnded($re_row)) {
        apiFormRefuse("$label has ended - there is nothing more to record");
    }
    $run = isset($_POST['run']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $_POST['run']) ? (string) $_POST['run'] : $re_row['recurring_expense_next_date'];

    $expense_id = intval(recurringExpenseRecord($recurring_expense_id, intval($session_user_id), $run, 'The API (' . html_entity_decode($api_key_name, ENT_QUOTES, 'UTF-8') . ')'));
    if (!$expense_id) {
        apiFormRefuse("That run of $label is already recorded");
    }

    $after = recurringExpenseRow($recurring_expense_id, false);
    $update_count = 1;
    $return_arr['expense_id'] = $expense_id;
    $return_arr['next_date'] = recurringExpenseEnded($after) ? null : $after['recurring_expense_next_date'];
    logAudit("API", "Success", "Recorded the recurring expense " . escapeSql($label) . " via API ($api_key_name)", intval($re_row['recurring_expense_client_id']));
}

// Output
require_once '../update_output.php';
