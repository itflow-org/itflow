<?php

/*
 * ITFlow - GET/POST request handler for recurring expenses
 *
 * New and Edit post through recurringExpenseSave(), Record Now through
 * recurringExpenseRecord() - the same code the nightly run uses - in
 * functions/expenses.php.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['create_recurring_expense'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 2);

    $recurring_expense_id = recurringExpenseSave($_POST, 0, $error);
    if (!$recurring_expense_id) {
        flashAlert($error, 'error');
        redirect();
    }
    $row = recurringExpenseRow($recurring_expense_id, false);
    $label = recurringExpenseLabel($row);

    logAudit("Recurring Expense", "Create", "$session_name set up the recurring expense " . escapeSql($label), intval($row['recurring_expense_client_id']), $recurring_expense_id);

    flashAlert("Recurring expense <strong>" . escapeHtml($label) . "</strong> set up - the first one is recorded on " . escapeHtml(date('M j, Y', strtotime($row['recurring_expense_next_date']))));

    redirect();

}

if (isset($_POST['edit_recurring_expense'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 2);

    $recurring_expense_id = intval($_POST['recurring_expense_id'] ?? 0);
    $before = recurringExpenseRow($recurring_expense_id);

    if (!recurringExpenseSave($_POST, $recurring_expense_id, $error)) {
        flashAlert($error, 'error');
        redirect();
    }
    $row = recurringExpenseRow($recurring_expense_id, false);
    $label = recurringExpenseLabel($row);
    $state = recurringExpenseState($row);

    $changes = [];
    if (floatval($before['recurring_expense_amount']) != floatval($row['recurring_expense_amount'])) {
        $changes[] = 'amount from ' . billingMoney($before['recurring_expense_amount'], $before['recurring_expense_currency_code']) . ' to ' . billingMoney($row['recurring_expense_amount'], $row['recurring_expense_currency_code']);
    }
    if ($before['recurring_expense_next_date'] !== $row['recurring_expense_next_date']) {
        $changes[] = "next date from {$before['recurring_expense_next_date']} to {$row['recurring_expense_next_date']}";
    }
    logAudit("Recurring Expense", "Edit", "$session_name edited the recurring expense " . escapeSql($label) . ($changes ? ' - ' . escapeSql(implode(', ', $changes)) : ''), intval($row['recurring_expense_client_id']), $recurring_expense_id);

    if ($state === 'ended' && recurringExpenseState($before) !== 'ended') {
        flashAlert("Recurring expense <strong>" . escapeHtml($label) . "</strong> saved - its end date is before the next one, so it has ended", 'warning');
    } elseif ($state === 'active' && recurringExpenseState($before) === 'ended') {
        flashAlert("Recurring expense <strong>" . escapeHtml($label) . "</strong> saved and running again - the next one is on " . escapeHtml(date('M j, Y', strtotime($row['recurring_expense_next_date']))));
    } else {
        flashAlert("Recurring expense <strong>" . escapeHtml($label) . "</strong> saved");
    }

    redirect();

}

// Pause (status=0) or Resume (status=1)
if (isset($_GET['recurring_expense_set_status'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 2);

    $row = recurringExpenseRow(intval($_GET['recurring_expense_set_status']));
    if (!$row) {
        flashAlert('That recurring expense no longer exists', 'error');
        redirect();
    }
    $recurring_expense_id = intval($row['recurring_expense_id']);
    $status = intval($_GET['status'] ?? 0) === 1 ? 1 : 0;
    $label = recurringExpenseLabel($row);

    if ($status === 1 && recurringExpenseEnded($row)) {
        flashAlert("<strong>" . escapeHtml($label) . "</strong> ended on " . escapeHtml($row['recurring_expense_end_date']) . " - change its end date in Edit to run it again", 'error');
        redirect();
    }

    mysqli_query($mysqli, "UPDATE recurring_expenses SET recurring_expense_status = $status WHERE recurring_expense_id = $recurring_expense_id");

    logAudit("Recurring Expense", $status ? "Resume" : "Pause", "$session_name " . ($status ? 'resumed' : 'paused') . " the recurring expense " . escapeSql($label), intval($row['recurring_expense_client_id']), $recurring_expense_id);

    if ($status && $row['recurring_expense_next_date'] < date('Y-m-d', strtotime('-' . RECURRING_EXPENSE_GRACE_DAYS . ' days'))) {
        flashAlert("<strong>" . escapeHtml($label) . "</strong> resumed - its next date has passed, so record the missed runs or move it on", 'warning');
    } else {
        flashAlert("<strong>" . escapeHtml($label) . "</strong> " . ($status ? 'resumed' : 'paused'));
    }

    redirect();

}

// Record Now - the next run, or the one &run= names (what the page showed), so pressing it twice records it once
if (isset($_GET['recurring_expense_record'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 2);

    $row = recurringExpenseRow(intval($_GET['recurring_expense_record']));
    if (!$row) {
        flashAlert('That recurring expense no longer exists', 'error');
        redirect();
    }
    if (recurringExpenseEnded($row)) {
        flashAlert("<strong>" . escapeHtml(recurringExpenseLabel($row)) . "</strong> has ended - there is nothing more to record", 'error');
        redirect();
    }
    $run = isset($_GET['run']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['run']) ? $_GET['run'] : $row['recurring_expense_next_date'];

    $expense_id = recurringExpenseRecord(intval($row['recurring_expense_id']), intval($session_user_id), $run, stripslashes($session_name));
    if (!$expense_id) {
        flashAlert("That run of <strong>" . escapeHtml(recurringExpenseLabel($row)) . "</strong> is already recorded", 'warning');
        redirect();
    }
    $after = recurringExpenseRow(intval($row['recurring_expense_id']), false);

    flashAlert("Recorded <strong>" . escapeHtml(expenseLabel(expenseRow($expense_id, false))) . "</strong>" . (recurringExpenseEnded($after)
        ? ' - that was the last one, it has ended'
        : ' - the next one is on ' . escapeHtml(date('M j, Y', strtotime($after['recurring_expense_next_date'])))));

    redirect();

}

// Move On - skip the missed runs without recording them
if (isset($_GET['recurring_expense_catch_up'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 2);

    $row = recurringExpenseRow(intval($_GET['recurring_expense_catch_up']));
    if (!$row) {
        flashAlert('That recurring expense no longer exists', 'error');
        redirect();
    }
    $recurring_expense_id = intval($row['recurring_expense_id']);
    $from = $row['recurring_expense_next_date'];
    $next = recurringExpenseCatchUp($row);
    $ends = !empty($row['recurring_expense_end_date']) && $next > $row['recurring_expense_end_date'];

    mysqli_query($mysqli, "UPDATE recurring_expenses SET recurring_expense_next_date = '$next'" . ($ends ? ', recurring_expense_status = 0' : '') . "
        WHERE recurring_expense_id = $recurring_expense_id AND recurring_expense_next_date = '" . escapeSql($from) . "'");

    logAudit("Recurring Expense", "Move On", "$session_name moved the recurring expense " . escapeSql(recurringExpenseLabel($row)) . " on from $from to $next without recording the missed runs", intval($row['recurring_expense_client_id']), $recurring_expense_id);

    flashAlert("<strong>" . escapeHtml(recurringExpenseLabel($row)) . "</strong> moved on to " . escapeHtml(date('M j, Y', strtotime($next))) . ($ends ? ' - past its end date, so it has ended' : ''));

    redirect();

}

if (isset($_GET['delete_recurring_expense'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 3);

    $row = recurringExpenseDelete(intval($_GET['delete_recurring_expense']), $error);
    if (!$row) {
        flashAlert($error, 'error');
        redirect();
    }

    logAudit("Recurring Expense", "Delete", "$session_name deleted the recurring expense " . escapeSql(recurringExpenseLabel($row)), intval($row['recurring_expense_client_id']));

    flashAlert("Recurring expense <strong>" . escapeHtml(recurringExpenseLabel($row)) . "</strong> deleted - the expenses it recorded stay", 'error');

    redirect();

}
