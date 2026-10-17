<?php

/*
 * ITFlow - reconciling an account against a statement (functions/reconcile.php
 * does the work; ticking lines is agent/ajax.php's reconcile_tick). Write
 * access to Financial starts, changes, finishes and discards one; undoing a
 * finished one takes full access.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

/** The statement fields as reconcileStart() and reconcileUpdate() take them. */
function reconcilePostInput() {
    return [
        'statement_date' => (string) ($_POST['statement_date'] ?? ''),
        'ending_balance' => (string) ($_POST['ending_balance'] ?? ''),
        'notes' => (string) ($_POST['notes'] ?? ''),
    ];
}

/** "Operating Checking statement to Sep 30, 2026" */
function reconcileLabel(array $row) {
    return $row['account']['account_name'] . ' statement to ' . date('M j, Y', strtotime($row['reconciliation_statement_date']));
}

if (isset($_POST['reconcile_start'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 2);

    $account_id = intval($_POST['account_id'] ?? 0);
    $id = reconcileStart($account_id, reconcilePostInput(), $error);
    if (!$id) {
        flashAlert(escapeHtml($error), 'error');
        redirect("reconcile.php?account=$account_id");
    }
    $row = reconcileRow($id);
    $totals = reconcileTotals($row);

    logAudit("Reconciliation", "Create", "$session_name started reconciling the " . escapeSql(reconcileLabel($row)), 0, $id);

    flashAlert("Reconciling the <strong>" . escapeHtml(reconcileLabel($row)) . "</strong>" . ($totals['count'] ? " - {$totals['count']} line" . ($totals['count'] === 1 ? '' : 's') . ' from bank statements ticked already' : ''));

    redirect("reconcile.php?account=$account_id");

}

if (isset($_POST['reconcile_update'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 2);

    $id = intval($_POST['reconciliation_id'] ?? 0);
    $before = reconcileRow($id);
    if (!reconcileUpdate($id, reconcilePostInput(), $error)) {
        flashAlert(escapeHtml($error), 'error');
        redirect($before ? 'reconcile.php?account=' . intval($before['reconciliation_account_id']) : 'accounts.php');
    }
    $row = reconcileRow($id);

    logAudit("Reconciliation", "Edit", "$session_name changed the statement being reconciled to the " . escapeSql(reconcileLabel($row)) . ", ending at "
        . escapeSql(reconcileBalanceText($row['account'], $row['reconciliation_ending_balance'])), 0, $id);

    flashAlert("Statement changed - <strong>" . escapeHtml(reconcileLabel($row)) . "</strong>");

    redirect('reconcile.php?account=' . intval($row['reconciliation_account_id']));

}

if (isset($_POST['reconcile_finish'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 2);

    $id = intval($_POST['reconciliation_id'] ?? 0);
    $row = reconcileRow($id);
    $totals = $row ? reconcileFinish($id, $error) : null;
    if (!$totals) {
        flashAlert(escapeHtml($row ? $error : 'That reconciliation no longer exists'), 'error');
        redirect($row ? 'reconcile.php?account=' . intval($row['reconciliation_account_id']) : 'accounts.php');
    }

    logAudit("Reconciliation", "Finish", "$session_name reconciled the " . escapeSql(reconcileLabel($row)) . " - {$totals['count']} lines cleared, ending at "
        . escapeSql(reconcileBalanceText($row['account'], $totals['ending'])), 0, $id);

    flashAlert("<strong>" . escapeHtml($row['account']['account_name']) . "</strong> is reconciled to " . escapeHtml(date('M j, Y', strtotime($row['reconciliation_statement_date'])))
        . " - {$totals['count']} line" . ($totals['count'] === 1 ? '' : 's') . " cleared");

    redirect('reconcile.php?account=' . intval($row['reconciliation_account_id']));

}

if (isset($_GET['reconcile_discard'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 2);

    $row = reconcileDiscard(intval($_GET['reconcile_discard']), $error);
    if (!$row) {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }

    logAudit("Reconciliation", "Delete", "$session_name discarded reconciling the " . escapeSql(reconcileLabel($row)), 0, intval($row['reconciliation_id']));

    flashAlert("Reconciling the <strong>" . escapeHtml(reconcileLabel($row)) . "</strong> discarded - its ticks are off", 'error');

    redirect('reconcile.php?account=' . intval($row['reconciliation_account_id']));

}

if (isset($_GET['reconcile_undo'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 3);

    $row = reconcileUndo(intval($_GET['reconcile_undo']), $error);
    if (!$row) {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }

    logAudit("Reconciliation", "Undo", "$session_name undid the reconciliation of the " . escapeSql(reconcileLabel($row)) . " - back to in progress", 0, intval($row['reconciliation_id']));

    flashAlert("The <strong>" . escapeHtml(reconcileLabel($row)) . "</strong> is being reconciled again - its lines can change, and it can be finished or discarded", 'warning');

    redirect('reconcile.php?account=' . intval($row['reconciliation_account_id']));

}
