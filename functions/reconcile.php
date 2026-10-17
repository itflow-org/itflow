<?php

/*
 * ITFlow - reconciling an account against its bank or card statement.
 *
 * Pick the statement's date and ending balance, then tick each line on the
 * account that the statement shows. The ticked lines, added to where the
 * last reconciliation ended (or the opening balance, the first time), have
 * to come to the statement's ending balance - the difference is zero - for
 * it to be finished. Lines brought in from a bank statement start ticked.
 *
 * A reconciliation is a row in account_reconciliations; each line ticked on
 * it carries its id (payment_reconciliation_id, revenue_reconciliation_id,
 * expense_reconciliation_id), while it is in progress and once finished. A
 * line on a finished reconciliation is cleared: its amount, date and
 * account are locked, and it can't be deleted, until that reconciliation is
 * undone. Only the latest finished one on an account can be undone, and it
 * goes back to in progress with its ticks, to be fixed and finished again
 * or discarded. An account has one in progress at a time.
 *
 * Reconciling needs write access to Financial; undoing a finished one, full
 * access.
 */

/** The tables behind ledger lines - kind => its columns, and the sign of its amount on the account. */
function reconcileKinds() {
    return [
        'payment' => ['table' => 'payments', 'id' => 'payment_id', 'account' => 'payment_account_id', 'date' => 'payment_date', 'amount' => 'payment_amount', 'sign' => 1,
            'rec' => 'payment_reconciliation_id', 'archived' => 'payment_archived_at', 'import' => 'payment_import_key', 'updated' => 'payment_updated_at'],
        'revenue' => ['table' => 'revenues', 'id' => 'revenue_id', 'account' => 'revenue_account_id', 'date' => 'revenue_date', 'amount' => 'revenue_amount', 'sign' => 1,
            'rec' => 'revenue_reconciliation_id', 'archived' => 'revenue_archived_at', 'import' => 'revenue_import_key', 'updated' => 'revenue_updated_at'],
        'expense' => ['table' => 'expenses', 'id' => 'expense_id', 'account' => 'expense_account_id', 'date' => 'expense_date', 'amount' => 'expense_amount', 'sign' => -1,
            'rec' => 'expense_reconciliation_id', 'archived' => 'expense_archived_at', 'import' => 'expense_import_key', 'updated' => 'expense_updated_at'],
    ];
}

/** 'payment-12', 'revenue-5' or 'expense-9' (a ledger line's txn_line) as [kind, id], or null. */
function reconcileLineRef($key) {
    if (!preg_match('/^(payment|revenue|expense)-(\d+)$/', (string) $key, $m) || !intval($m[2])) {
        return null;
    }
    return [$m[1], intval($m[2])];
}

/** A reconciliation with its account (and that account's balance), who started and finished it, or null. */
function reconcileRow($reconciliation_id) {
    global $mysqli;
    $id = intval($reconciliation_id);
    if (!$id) {
        return null;
    }
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT account_reconciliations.*, creator.user_name AS created_by_name, finisher.user_name AS finished_by_name
        FROM account_reconciliations
        LEFT JOIN users AS creator ON creator.user_id = reconciliation_created_by
        LEFT JOIN users AS finisher ON finisher.user_id = reconciliation_finished_by
        WHERE reconciliation_id = $id"));
    if (!$row) {
        return null;
    }
    $row['account'] = accountRow($row['reconciliation_account_id']);
    if (!$row['account']) {
        return null;
    }
    $row['finished'] = $row['reconciliation_finished_at'] !== null;
    $row['card'] = $row['account']['type'] === 3;
    return $row;
}

/** The reconciliation in progress on an account, or null. */
function reconcileOpen($account_id) {
    global $mysqli;
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT reconciliation_id FROM account_reconciliations
        WHERE reconciliation_account_id = " . intval($account_id) . " AND reconciliation_finished_at IS NULL ORDER BY reconciliation_id DESC LIMIT 1"));
    return $row ? reconcileRow($row[0]) : null;
}

/** The latest finished reconciliation on an account (by statement date), or null. */
function reconcileLast($account_id) {
    global $mysqli;
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT reconciliation_id FROM account_reconciliations
        WHERE reconciliation_account_id = " . intval($account_id) . " AND reconciliation_finished_at IS NOT NULL
        ORDER BY reconciliation_statement_date DESC, reconciliation_id DESC LIMIT 1"));
    return $row ? reconcileRow($row[0]) : null;
}

/** Where the next reconciliation starts: the last one's ending balance, or the account's opening balance. */
function reconcileStartingBalance($account_id) {
    $last = reconcileLast($account_id);
    if ($last) {
        return round(floatval($last['reconciliation_ending_balance']), 2);
    }
    $account = accountRow($account_id);
    return $account ? round(floatval($account['opening_balance']), 2) : 0.0;
}

/**
 * The SQL of the lines ticked on a reconciliation, on its account: one row
 * each with line (payment-12), date and amount (in positive, out negative).
 */
function reconcileTickedSql($reconciliation_id, $account_id) {
    $id = intval($reconciliation_id);
    $account_id = intval($account_id);
    $parts = [];
    foreach (reconcileKinds() as $kind => $k) {
        $parts[] = "SELECT CONCAT('$kind-', {$k['id']}) AS line, {$k['date']} AS line_date, {$k['sign']} * {$k['amount']} AS line_amount
            FROM {$k['table']} WHERE {$k['rec']} = $id AND {$k['account']} = $account_id AND {$k['archived']} IS NULL";
    }
    return implode(' UNION ALL ', $parts);
}

/**
 * Where a reconciliation stands: starting (the last one's ending balance, or
 * the opening balance - what it started from, once finished), ending (the
 * statement's), what is ticked in and out (count and total), cleared
 * (starting plus what is ticked) and difference (ending less cleared).
 */
function reconcileTotals(array $row) {
    global $mysqli;
    $got = mysqli_fetch_row(mysqli_query($mysqli, "SELECT
            COALESCE(SUM(CASE WHEN line_amount > 0 THEN 1 ELSE 0 END), 0), COALESCE(SUM(CASE WHEN line_amount > 0 THEN line_amount ELSE 0 END), 0),
            COALESCE(SUM(CASE WHEN line_amount < 0 THEN 1 ELSE 0 END), 0), COALESCE(SUM(CASE WHEN line_amount < 0 THEN -line_amount ELSE 0 END), 0)
        FROM (" . reconcileTickedSql($row['reconciliation_id'], $row['reconciliation_account_id']) . ") AS ticked"));
    $starting = $row['finished'] ? round(floatval($row['reconciliation_starting_balance']), 2) : reconcileStartingBalance($row['reconciliation_account_id']);
    $in = round(floatval($got[1]), 2);
    $out = round(floatval($got[3]), 2);
    $ending = round(floatval($row['reconciliation_ending_balance']), 2);
    $cleared = round($starting + $in - $out, 2);
    return ['starting' => $starting, 'ending' => $ending, 'in_count' => intval($got[0]), 'in' => $in, 'out_count' => intval($got[2]), 'out' => $out,
        'count' => intval($got[0]) + intval($got[2]), 'cleared' => $cleared, 'difference' => round($ending - $cleared, 2)];
}

/**
 * The lines to tick on a reconciliation in progress, oldest first: every
 * line on the account not cleared on a finished one and dated up to the
 * statement, and any ticked on it whatever their date. Each is a ledger row
 * (accountLedgerSql()) with ticked. Also sets $later to how many uncleared
 * lines are dated after the statement.
 */
function reconcileLines(array $row, &$later = 0) {
    global $mysqli;
    $id = intval($row['reconciliation_id']);
    $date = escapeSql($row['reconciliation_statement_date']);
    $sql = mysqli_query($mysqli, "SELECT ledger.*, IF(ledger.txn_reconciliation_id = $id, 1, 0) AS ticked FROM (" . accountLedgerSql($row['reconciliation_account_id']) . ") AS ledger
        WHERE ledger.txn_reconciliation_id = $id OR (ledger.txn_reconciliation_id IS NULL AND ledger.txn_date <= '$date')
        ORDER BY ledger.txn_date ASC, ledger.txn_created_at ASC, ledger.txn_order ASC, ledger.txn_id ASC");
    $lines = [];
    while ($line = mysqli_fetch_assoc($sql)) {
        $line['ticked'] = intval($line['ticked']) === 1;
        $lines[] = $line;
    }
    $later = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM (" . accountLedgerSql($row['reconciliation_account_id']) . ") AS ledger
        WHERE ledger.txn_reconciliation_id IS NULL AND ledger.txn_date > '$date'"))[0]);
    return $lines;
}

/** The finished reconciliations on an account, newest first, each with its reconcileTotals() as totals. */
function reconcileHistory($account_id) {
    global $mysqli;
    $sql = mysqli_query($mysqli, "SELECT reconciliation_id FROM account_reconciliations
        WHERE reconciliation_account_id = " . intval($account_id) . " AND reconciliation_finished_at IS NOT NULL
        ORDER BY reconciliation_statement_date DESC, reconciliation_id DESC");
    $rows = [];
    while ($id = mysqli_fetch_row($sql)) {
        $row = reconcileRow($id[0]);
        if ($row) {
            $row['totals'] = reconcileTotals($row);
            $rows[] = $row;
        }
    }
    return $rows;
}

/** A statement's ending balance as entered - what a card's statement says is owed comes in as owed - as the account's signed balance, or null. */
function reconcileEndingInput($value, $card) {
    $amount = billingNumberInput($value);
    if ($amount === null || abs($amount) >= 1e12) {
        return null;
    }
    return $card ? -$amount : $amount;
}

/** A Y-m-d date that is a real one, or ''. */
function reconcileDateInput($value) {
    $date = trim((string) $value);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !checkdate(intval(substr($date, 5, 2)), intval(substr($date, 8, 2)), intval(substr($date, 0, 4)))) {
        return '';
    }
    return $date;
}

/** Tick the lines brought in from a bank statement, on the account and not cleared, dated after $from and up to $to. */
function reconcileTickImported(array $row, $from, $to) {
    global $mysqli;
    $id = intval($row['reconciliation_id']);
    $account_id = intval($row['reconciliation_account_id']);
    foreach (reconcileKinds() as $k) {
        mysqli_query($mysqli, "UPDATE {$k['table']} SET {$k['rec']} = $id, {$k['updated']} = {$k['updated']}
            WHERE {$k['account']} = $account_id AND {$k['rec']} IS NULL AND {$k['archived']} IS NULL AND {$k['import']} IS NOT NULL
            AND {$k['date']} > '" . escapeSql($from) . "' AND {$k['date']} <= '" . escapeSql($to) . "'");
    }
}

/**
 * Check a statement's date and ending balance ($in: statement_date,
 * ending_balance, notes) for an account. Returns [date, ending, notes], or
 * null with $error.
 */
function reconcileStatementInput(array $account, array $in, &$error = '') {
    $error = '';
    $date = reconcileDateInput($in['statement_date'] ?? '');
    if ($date === '') {
        $error = "Enter the statement's ending date";
        return null;
    }
    if ($date > date('Y-m-d')) {
        $error = "The statement's ending date can't be after today";
        return null;
    }
    $last = reconcileLast($account['account_id']);
    if ($last && $date <= $last['reconciliation_statement_date']) {
        $error = $account['account_name'] . ' is reconciled to ' . date('M j, Y', strtotime($last['reconciliation_statement_date'])) . ' - the next statement ends after that';
        return null;
    }
    $ending = reconcileEndingInput($in['ending_balance'] ?? '', $account['type'] === 3);
    if ($ending === null || trim((string) ($in['ending_balance'] ?? '')) === '') {
        $error = $account['type'] === 3 ? 'Enter what the statement says is owed - 0.00 for nothing' : "Enter the statement's ending balance";
        return null;
    }
    $notes = mb_substr(trim((string) ($in['notes'] ?? '')), 0, 250);
    return [$date, $ending, $notes];
}

/**
 * Start reconciling an account against a statement. Lines brought in from a
 * bank statement up to its date start ticked. Returns the new id, or 0 with
 * $error.
 */
function reconcileStart($account_id, array $in, &$error = '') {
    global $mysqli, $session_user_id;
    $error = '';
    $account = accountRow($account_id);
    if (!$account || $account['account_archived_at']) {
        $error = $account ? 'Restore the account before reconciling it' : 'That account no longer exists';
        return 0;
    }
    if (reconcileOpen($account['account_id'])) {
        $error = 'A statement is being reconciled on ' . $account['account_name'] . ' already - finish or discard it first';
        return 0;
    }
    $statement = reconcileStatementInput($account, $in, $error);
    if (!$statement) {
        return 0;
    }
    [$date, $ending, $notes] = $statement;
    $account_id = intval($account['account_id']);
    mysqli_query($mysqli, "INSERT INTO account_reconciliations SET reconciliation_account_id = $account_id, reconciliation_statement_date = '$date',
        reconciliation_starting_balance = " . number_format(reconcileStartingBalance($account_id), 2, '.', '') . ", reconciliation_ending_balance = " . number_format($ending, 2, '.', '') . ",
        reconciliation_notes = " . ($notes === '' ? 'NULL' : "'" . escapeSql($notes) . "'") . ", reconciliation_created_by = " . intval($session_user_id));
    $id = mysqli_insert_id($mysqli);
    reconcileTickImported(reconcileRow($id), '1000-01-01', $date);
    return $id;
}

/**
 * Change the statement of one in progress. Lines ticked on it dated after a
 * new, earlier date are unticked; lines brought in from a bank statement up
 * to a new, later date are ticked. Returns true, or false with $error.
 */
function reconcileUpdate($reconciliation_id, array $in, &$error = '') {
    global $mysqli;
    $error = '';
    $row = reconcileRow($reconciliation_id);
    if (!$row || $row['finished']) {
        $error = $row ? 'That statement is reconciled already - undo it to change it' : 'That reconciliation no longer exists';
        return false;
    }
    $statement = reconcileStatementInput($row['account'], $in, $error);
    if (!$statement) {
        return false;
    }
    [$date, $ending, $notes] = $statement;
    $id = intval($row['reconciliation_id']);
    $was = $row['reconciliation_statement_date'];
    mysqli_query($mysqli, "UPDATE account_reconciliations SET reconciliation_statement_date = '$date', reconciliation_ending_balance = " . number_format($ending, 2, '.', '') . ",
        reconciliation_notes = " . ($notes === '' ? 'NULL' : "'" . escapeSql($notes) . "'") . " WHERE reconciliation_id = $id");
    if ($date < $was) {
        foreach (reconcileKinds() as $k) {
            mysqli_query($mysqli, "UPDATE {$k['table']} SET {$k['rec']} = NULL, {$k['updated']} = {$k['updated']} WHERE {$k['rec']} = $id AND {$k['date']} > '$date'");
        }
    } elseif ($date > $was) {
        reconcileTickImported(reconcileRow($id), $was, $date);
    }
    return true;
}

/**
 * Tick or untick lines ($keys: payment-12, revenue-5, expense-9) on one in
 * progress. Only lines on its account that aren't cleared on another are
 * changed, and only those dated up to the statement are ticked. Returns
 * how many changed, or null with $error.
 */
function reconcileTick($reconciliation_id, array $keys, $tick, &$error = '') {
    global $mysqli;
    $error = '';
    $row = reconcileRow($reconciliation_id);
    if (!$row || $row['finished']) {
        $error = $row ? 'That statement is reconciled already - undo it to change what cleared' : 'That reconciliation no longer exists - start again';
        return null;
    }
    $id = intval($row['reconciliation_id']);
    $account_id = intval($row['reconciliation_account_id']);
    $date = escapeSql($row['reconciliation_statement_date']);
    $kinds = reconcileKinds();
    $changed = 0;
    foreach ($keys as $key) {
        $ref = reconcileLineRef($key);
        if (!$ref) {
            continue;
        }
        $k = $kinds[$ref[0]];
        $where = "{$k['id']} = {$ref[1]} AND {$k['account']} = $account_id AND {$k['archived']} IS NULL";
        if ($tick) {
            mysqli_query($mysqli, "UPDATE {$k['table']} SET {$k['rec']} = $id, {$k['updated']} = {$k['updated']} WHERE $where AND {$k['rec']} IS NULL AND {$k['date']} <= '$date'");
        } else {
            mysqli_query($mysqli, "UPDATE {$k['table']} SET {$k['rec']} = NULL, {$k['updated']} = {$k['updated']} WHERE $where AND {$k['rec']} = $id");
        }
        $changed += mysqli_affected_rows($mysqli);
    }
    return $changed;
}

/** Finish one in progress - only when what is ticked comes to the statement's ending balance. Returns its totals, or null with $error. */
function reconcileFinish($reconciliation_id, &$error = '') {
    global $mysqli, $session_user_id;
    $error = '';
    $row = reconcileRow($reconciliation_id);
    if (!$row || $row['finished']) {
        $error = $row ? 'That statement is reconciled already' : 'That reconciliation no longer exists';
        return null;
    }
    $totals = reconcileTotals($row);
    if (abs($totals['difference']) >= 0.005) {
        $error = 'It is ' . billingMoney(abs($totals['difference']), $row['account']['account_currency_code']) . ' off the statement - tick what cleared until the difference is nothing';
        return null;
    }
    mysqli_query($mysqli, "UPDATE account_reconciliations SET reconciliation_starting_balance = " . number_format($totals['starting'], 2, '.', '') . ",
        reconciliation_finished_at = NOW(), reconciliation_finished_by = " . intval($session_user_id) . " WHERE reconciliation_id = " . intval($row['reconciliation_id']));
    return $totals;
}

/** Discard one in progress - its ticks come off. Returns its row, or null with $error. */
function reconcileDiscard($reconciliation_id, &$error = '') {
    global $mysqli;
    $error = '';
    $row = reconcileRow($reconciliation_id);
    if (!$row || $row['finished']) {
        $error = $row ? 'That statement is reconciled - undo it instead' : 'That reconciliation no longer exists';
        return null;
    }
    $id = intval($row['reconciliation_id']);
    foreach (reconcileKinds() as $k) {
        mysqli_query($mysqli, "UPDATE {$k['table']} SET {$k['rec']} = NULL, {$k['updated']} = {$k['updated']} WHERE {$k['rec']} = $id");
    }
    mysqli_query($mysqli, "DELETE FROM account_reconciliations WHERE reconciliation_id = $id");
    return $row;
}

/**
 * Undo a finished reconciliation - only the latest on its account, and not
 * while another is in progress there. It goes back to in progress with what
 * was ticked, its lines free to change. Returns its row, or null with $error.
 */
function reconcileUndo($reconciliation_id, &$error = '') {
    global $mysqli;
    $error = '';
    $row = reconcileRow($reconciliation_id);
    if (!$row || !$row['finished']) {
        $error = $row ? 'That statement is still being reconciled' : 'That reconciliation no longer exists';
        return null;
    }
    $last = reconcileLast($row['reconciliation_account_id']);
    if (!$last || intval($last['reconciliation_id']) !== intval($row['reconciliation_id'])) {
        $error = 'Only the latest statement can be undone - undo the ones after it first';
        return null;
    }
    if (reconcileOpen($row['reconciliation_account_id'])) {
        $error = 'Another statement is being reconciled on ' . $row['account']['account_name'] . ' - discard it first';
        return null;
    }
    mysqli_query($mysqli, "UPDATE account_reconciliations SET reconciliation_finished_at = NULL, reconciliation_finished_by = 0 WHERE reconciliation_id = " . intval($row['reconciliation_id']));
    return $row;
}

/**
 * The finished reconciliation a line is cleared on (reconciliation_id,
 * reconciliation_account_id, reconciliation_statement_date, account_name),
 * or null when it isn't.
 */
function reconcileLock($kind, $id) {
    global $mysqli;
    $kinds = reconcileKinds();
    if (!isset($kinds[$kind])) {
        return null;
    }
    $k = $kinds[$kind];
    return mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT reconciliation_id, reconciliation_account_id, reconciliation_statement_date, account_name
        FROM {$k['table']}
        JOIN account_reconciliations ON reconciliation_id = {$k['rec']} AND reconciliation_finished_at IS NOT NULL
        JOIN accounts ON account_id = reconciliation_account_id
        WHERE {$k['id']} = " . intval($id) . " LIMIT 1")) ?: null;
}

/** What to say about a cleared line - "It cleared on the Operating Checking statement to Sep 30, 2026 - undo that reconciliation to ..." */
function reconcileLockText(array $lock, $to = 'change its amount, date or account') {
    return 'It cleared on the ' . $lock['account_name'] . ' statement to ' . date('M j, Y', strtotime($lock['reconciliation_statement_date']))
        . " - undo that reconciliation to $to";
}

/** A quick look's line for a cleared one - the statement it cleared on, opening the lines it cleared. '' for null. */
function reconcileGlanceHtml($lock) {
    if (!$lock) {
        return '';
    }
    return '<div class="itflow-glance-item" title="Reconciled"><i class="fas fa-fw fa-check-circle text-success-emphasis"></i><span>Cleared on the '
        . escapeHtml($lock['account_name']) . ' statement to <a href="transactions.php?account=' . intval($lock['reconciliation_account_id'])
        . '&amp;reconciliation=' . intval($lock['reconciliation_id']) . '">' . escapeHtml(date('M j, Y', strtotime($lock['reconciliation_statement_date']))) . '</a></span></div>';
}

/** An edit form's note for a cleared one - its amount, date and account are locked. '' for null. */
function reconcileFormNoteHtml($lock) {
    if (!$lock) {
        return '';
    }
    return '<div class="col-12"><div class="alert alert-success py-2 small mb-0"><i class="fas fa-check-circle me-2"></i>' . escapeHtml(reconcileLockText($lock)) . '.</div></div>';
}

/** A quick look's Delete (or Undo) for a cleared one - there, but off, saying why. */
function reconcileLockedButtonHtml(array $lock, $label = 'Delete', $icon = 'fa-trash', $to = 'delete it') {
    return '<span class="d-inline-block" tabindex="0" title="' . escapeHtml(reconcileLockText($lock, $to)) . '"><button type="button" class="btn btn-outline-danger" disabled>'
        . '<i class="fas ' . escapeHtml($icon) . '"></i><span class="d-none d-sm-inline ms-2">' . escapeHtml($label) . '</span></button></span>';
}

/**
 * Before a line's date, amount or account changes: refused when it is
 * cleared and any of them would change ($date, $amount and $account_id are
 * the new values, null for unchanged). A line ticked on one in progress that
 * moves to another account comes off it, unless $release is false (to check
 * both sides of a transfer before either changes). Returns true, or false
 * with $error.
 */
function reconcileCheckChange($kind, $id, $date, $amount, $account_id, &$error = '', $release = true) {
    global $mysqli;
    $error = '';
    $kinds = reconcileKinds();
    if (!isset($kinds[$kind]) || !intval($id)) {
        return true;
    }
    $k = $kinds[$kind];
    $id = intval($id);
    $now = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT {$k['date']} AS d, {$k['amount']} AS a, {$k['account']} AS acct, {$k['rec']} AS rec FROM {$k['table']} WHERE {$k['id']} = $id"));
    if (!$now || $now['rec'] === null) {
        return true;
    }
    $moves = ($date !== null && (string) $date !== (string) $now['d'])
        || ($amount !== null && round(floatval($amount), 2) != round(floatval($now['a']), 2))
        || ($account_id !== null && intval($account_id) !== intval($now['acct']));
    if (!$moves) {
        return true;
    }
    $lock = reconcileLock($kind, $id);
    if ($lock) {
        $error = reconcileLockText($lock);
        return false;
    }
    if ($release && $account_id !== null && intval($account_id) !== intval($now['acct'])) {
        mysqli_query($mysqli, "UPDATE {$k['table']} SET {$k['rec']} = NULL, {$k['updated']} = {$k['updated']} WHERE {$k['id']} = $id");
    }
    return true;
}

/** Before a line is deleted: refused when it is cleared. Returns true, or false with $error. */
function reconcileCheckDelete($kind, $id, &$error = '') {
    $error = '';
    $lock = reconcileLock($kind, $id);
    if ($lock) {
        $error = reconcileLockText($lock, 'delete it');
        return false;
    }
    return true;
}

/** The first cleared payment on an invoice, as reconcileLock() gives it, or null. */
function reconcileInvoiceLock($invoice_id) {
    global $mysqli;
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT payment_id FROM payments
        JOIN account_reconciliations ON reconciliation_id = payment_reconciliation_id AND reconciliation_finished_at IS NOT NULL
        WHERE payment_invoice_id = " . intval($invoice_id) . " LIMIT 1"));
    return $row ? reconcileLock('payment', $row[0]) : null;
}

/** The first cleared payment or other income of a client, as reconcileLock() gives it, or null. */
function reconcileClientLock($client_id) {
    global $mysqli;
    $client_id = intval($client_id);
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT payment_id FROM payments
        JOIN invoices ON invoice_id = payment_invoice_id
        JOIN account_reconciliations ON reconciliation_id = payment_reconciliation_id AND reconciliation_finished_at IS NOT NULL
        WHERE invoice_client_id = $client_id LIMIT 1"));
    if ($row) {
        return reconcileLock('payment', $row[0]);
    }
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT revenue_id FROM revenues
        JOIN account_reconciliations ON reconciliation_id = revenue_reconciliation_id AND reconciliation_finished_at IS NOT NULL
        WHERE revenue_client_id = $client_id LIMIT 1"));
    return $row ? reconcileLock('revenue', $row[0]) : null;
}

/** Has the account a finished reconciliation? Its opening balance is then locked. */
function reconcileAccountReconciled($account_id) {
    global $mysqli;
    return (bool) mysqli_fetch_row(mysqli_query($mysqli, "SELECT 1 FROM account_reconciliations
        WHERE reconciliation_account_id = " . intval($account_id) . " AND reconciliation_finished_at IS NOT NULL LIMIT 1"));
}

/** A statement's balance as the account reads it - "$1,240.00 owed" on a card below zero. */
function reconcileBalanceText(array $account, $balance) {
    $balance = floatval($balance);
    if ($account['type'] === 3 && $balance < 0) {
        return billingMoney(-$balance, $account['account_currency_code']) . ' owed';
    }
    return billingMoney($balance, $account['account_currency_code']);
}
