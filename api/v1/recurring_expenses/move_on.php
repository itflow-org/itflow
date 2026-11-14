<?php
/*
 * API - Recurring Expenses - Move On
 * POST /api/v1/recurring_expenses/move_on.php
 *
 * Skip the runs it missed without recording them, as Move On does - its next date
 * moves on past today. Past its end date, it has ended.
 *
 * Parameters (POST, JSON body): api_key, recurring_expense_id, client_id (its client -
 * needed when the key's user is limited to certain clients)
 *
 * Answer: next_date.
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once 'recurring_expense_map.php';

$update_count = false;

require_once 're_find.php';

if ($re_row) {
    $from = $re_row['recurring_expense_next_date'];
    $next = recurringExpenseCatchUp($re_row);
    $ends = !empty($re_row['recurring_expense_end_date']) && $next > $re_row['recurring_expense_end_date'];

    mysqli_query($mysqli, "UPDATE recurring_expenses SET recurring_expense_next_date = '" . escapeSql($next) . "'" . ($ends ? ', recurring_expense_status = 0' : '') . "
        WHERE recurring_expense_id = $recurring_expense_id AND recurring_expense_next_date = '" . escapeSql($from) . "'");

    $update_count = 1;
    $return_arr['next_date'] = $next;

    $label = escapeSql(recurringExpenseLabel($re_row));
    logAudit("Recurring Expense", "Move On", "Moved the recurring expense $label on from $from to $next without recording the missed runs via API ($api_key_name)", intval($re_row['recurring_expense_client_id']), $recurring_expense_id);
    logAudit("API", "Success", "Moved the recurring expense $label on via API ($api_key_name)", intval($re_row['recurring_expense_client_id']));
}

// Output
require_once '../update_output.php';
