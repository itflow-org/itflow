<?php
/*
 * API - Recurring Expenses - Status
 * POST /api/v1/recurring_expenses/status.php
 *
 * Pause a recurring expense (status 0) or resume it (status 1), as Pause and
 * Resume do. One that has ended runs again only once its end date is moved.
 *
 * Parameters (POST, JSON body): api_key, recurring_expense_id, client_id (its client -
 * needed when the key's user is limited to certain clients), status (0 or 1)
 *
 * Answer: message, when it resumed with its next date already past.
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once 'recurring_expense_map.php';

$update_count = false;

require_once 're_find.php';

if ($re_row && isset($_POST['status'])) {
    $status = intval($_POST['status']) === 1 ? 1 : 0;
    $label = recurringExpenseLabel($re_row);
    if ($status === 1 && recurringExpenseEnded($re_row)) {
        apiFormRefuse("$label ended on {$re_row['recurring_expense_end_date']} - change its end date to run it again");
    }

    mysqli_query($mysqli, "UPDATE recurring_expenses SET recurring_expense_status = $status WHERE recurring_expense_id = $recurring_expense_id");
    $update_count = 1;
    if ($status && $re_row['recurring_expense_next_date'] < date('Y-m-d', strtotime('-' . RECURRING_EXPENSE_GRACE_DAYS . ' days'))) {
        $return_arr['message'] = "$label resumed - its next date has passed, so record the missed runs or move it on.";
    }

    $label = escapeSql($label);
    logAudit("Recurring Expense", $status ? "Resume" : "Pause", ($status ? 'Resumed' : 'Paused') . " the recurring expense $label via API ($api_key_name)", intval($re_row['recurring_expense_client_id']), $recurring_expense_id);
    logAudit("API", "Success", ($status ? 'Resumed' : 'Paused') . " the recurring expense $label via API ($api_key_name)", intval($re_row['recurring_expense_client_id']));
}

// Output
require_once '../update_output.php';
