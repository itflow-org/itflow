<?php

/*
 * ITFlow - accounts, their ledger and transfers between them.
 *
 * An account is where money sits: a checking or savings account, a credit
 * card, cash, or a payment processor holding card payments until it pays
 * them out. Its balance is the opening balance, plus the payments and other
 * income paid into it, less the expenses paid from it - one rule
 * (accountBalanceSql()) for the accounts list, the ledger, the dashboard
 * and every account picker. A credit card's balance runs below zero as it
 * is spent on: that is what is owed on it.
 *
 * The ledger (accountLedgerSql()) is every line on an account, one kind
 * each: a payment on an invoice, other income, an expense, a transfer in or
 * out, and a reimbursement paid to an agent. A transfer is stored as an
 * expense on the account it leaves and a revenue on the one it reaches,
 * joined in transfers - neither is spending or income.
 *
 * Accounts are the company's own books, so they are not limited by client
 * access; Financial access reads them, write access changes them and full
 * access deletes.
 */

/** The kinds of account - id => label and icon. Stored in accounts.account_type. */
function accountTypes() {
    return [
        1 => ['label' => 'Checking', 'icon' => 'fa-university'],
        2 => ['label' => 'Savings', 'icon' => 'fa-piggy-bank'],
        3 => ['label' => 'Credit card', 'icon' => 'fa-credit-card'],
        4 => ['label' => 'Cash', 'icon' => 'fa-money-bill-wave'],
        5 => ['label' => 'Payment processor', 'icon' => 'fa-globe'],
        6 => ['label' => 'Other', 'icon' => 'fa-wallet'],
    ];
}

/** What kind an account looks like from its name - for one with none set. */
function accountTypeGuess($name) {
    $name = mb_strtolower((string) $name);
    if (preg_match('/saving|reserve/', $name)) {
        return 2;
    }
    if (preg_match('/card|visa|amex|american express|mastercard|discover|credit/', $name)) {
        return 3;
    }
    if (preg_match('/cash|petty/', $name)) {
        return 4;
    }
    if (preg_match('/stripe|paypal|square|merchant|settlement|processor|gateway/', $name)) {
        return 5;
    }
    return 1;
}

/** An account's kind - the one set, else as its name looks. */
function accountType(array $row) {
    $type = intval($row['account_type'] ?? 0);
    return isset(accountTypes()[$type]) ? $type : accountTypeGuess($row['account_name'] ?? '');
}

/**
 * SQL for an account's balance - $column is the account id. With $to_date,
 * the balance at the end of that day.
 */
function accountBalanceSql($column = 'accounts.account_id', $to_date = null) {
    $to = $to_date !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_date) ? $to_date : null;
    $date = function ($field) use ($to) {
        return $to ? " AND $field <= '$to'" : '';
    };
    return "(SELECT opening_balance FROM accounts AS opening WHERE opening.account_id = $column)
        + COALESCE((SELECT SUM(payment_amount) FROM payments WHERE payment_account_id = $column AND payment_archived_at IS NULL" . $date('payment_date') . "), 0)
        + COALESCE((SELECT SUM(revenue_amount) FROM revenues WHERE revenue_account_id = $column AND revenue_archived_at IS NULL" . $date('revenue_date') . "), 0)
        - COALESCE((SELECT SUM(expense_amount) FROM expenses WHERE expense_account_id = $column AND expense_archived_at IS NULL" . $date('expense_date') . "), 0)";
}

/** An account's balance, optionally at the end of a day. */
function accountBalance($account_id, $to_date = null) {
    global $mysqli;
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT " . accountBalanceSql(intval($account_id), $to_date)));
    return round(floatval($row[0] ?? 0), 2);
}

/**
 * The accounts with what they hold - id => row with balance, month_in and
 * month_out (this month, transfers included - it is that account's money),
 * last_date (its latest line), line_count (how many), type (accountType()) and
 * the defaults it is set as. $archived: false open ones, true archived,
 * null both. $keep_id keeps one in either way.
 */
function accountsList($archived = false, $keep_id = 0) {
    global $mysqli;
    $month = date('Y-m-01');
    $keep_id = intval($keep_id);
    $where = $archived === null ? '1 = 1' : ($archived ? 'account_archived_at IS NOT NULL' : 'account_archived_at IS NULL');
    $sql = mysqli_query($mysqli, "SELECT accounts.*,
            opening_balance + COALESCE(p.total, 0) + COALESCE(r.total, 0) - COALESCE(e.total, 0) AS balance,
            COALESCE(p.month, 0) + COALESCE(r.month, 0) AS month_in, COALESCE(e.month, 0) AS month_out,
            GREATEST(COALESCE(p.last, '1000-01-01'), COALESCE(r.last, '1000-01-01'), COALESCE(e.last, '1000-01-01')) AS last_date,
            COALESCE(p.n, 0) + COALESCE(r.n, 0) + COALESCE(e.n, 0) AS line_count,
            rc.reconciled_to, rc.reconciling_id
        FROM accounts
        LEFT JOIN (SELECT payment_account_id AS id, SUM(payment_amount) AS total, SUM(CASE WHEN payment_date >= '$month' THEN payment_amount ELSE 0 END) AS month,
            MAX(payment_date) AS last, COUNT(*) AS n FROM payments WHERE payment_archived_at IS NULL GROUP BY payment_account_id) AS p ON p.id = account_id
        LEFT JOIN (SELECT revenue_account_id AS id, SUM(revenue_amount) AS total, SUM(CASE WHEN revenue_date >= '$month' THEN revenue_amount ELSE 0 END) AS month,
            MAX(revenue_date) AS last, COUNT(*) AS n FROM revenues WHERE revenue_archived_at IS NULL GROUP BY revenue_account_id) AS r ON r.id = account_id
        LEFT JOIN (SELECT expense_account_id AS id, SUM(expense_amount) AS total, SUM(CASE WHEN expense_date >= '$month' THEN expense_amount ELSE 0 END) AS month,
            MAX(expense_date) AS last, COUNT(*) AS n FROM expenses WHERE expense_archived_at IS NULL GROUP BY expense_account_id) AS e ON e.id = account_id
        LEFT JOIN (SELECT reconciliation_account_id AS id, MAX(CASE WHEN reconciliation_finished_at IS NOT NULL THEN reconciliation_statement_date END) AS reconciled_to,
            MAX(CASE WHEN reconciliation_finished_at IS NULL THEN reconciliation_id END) AS reconciling_id FROM account_reconciliations GROUP BY reconciliation_account_id) AS rc ON rc.id = account_id
        WHERE ($where OR account_id = $keep_id)
        ORDER BY account_name ASC");
    $defaults = accountDefaults();
    $accounts = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $id = intval($row['account_id']);
        $row['type'] = accountType($row);
        $row['balance'] = round(floatval($row['balance']), 2);
        $row['month_in'] = round(floatval($row['month_in']), 2);
        $row['month_out'] = round(floatval($row['month_out']), 2);
        $row['last_date'] = $row['last_date'] === '1000-01-01' ? null : $row['last_date'];
        $row['line_count'] = intval($row['line_count']);
        $row['reconciling_id'] = intval($row['reconciling_id']);
        $row['defaults'] = $defaults[$id] ?? [];
        $accounts[$id] = $row;
    }
    return $accounts;
}

/** One account with what it holds (accountsList()'s row), or null. */
function accountRow($account_id) {
    $account_id = intval($account_id);
    if (!$account_id) {
        return null;
    }
    $accounts = accountsList(null, $account_id);
    return $accounts[$account_id] ?? null;
}

/** What each account is set as - id => list of what: payments, expenses, transfers out and in (Admin > Settings > Defaults), and online payments (a payment provider's). */
function accountDefaults() {
    global $mysqli, $config_default_payment_account, $config_default_expense_account, $config_default_transfer_from_account, $config_default_transfer_to_account;
    $defaults = [];
    foreach ([
        [$config_default_payment_account, 'Payments go in'],
        [$config_default_expense_account, 'Expenses paid from'],
        [$config_default_transfer_from_account, 'Transfers from'],
        [$config_default_transfer_to_account, 'Transfers to'],
    ] as [$id, $label]) {
        if (intval($id)) {
            $defaults[intval($id)][] = $label;
        }
    }
    $sql = mysqli_query($mysqli, "SELECT payment_provider_account, payment_provider_name FROM payment_providers WHERE payment_provider_active = 1 AND payment_provider_account > 0");
    while ($row = mysqli_fetch_row($sql)) {
        $defaults[intval($row[0])][] = $row[1] . ' payments';
    }
    return $defaults;
}

/** An account's balance as it reads - "owed" for a credit card below zero. */
function accountBalanceText(array $account) {
    $balance = floatval($account['balance']);
    if (accountType($account) === 3 && $balance < 0) {
        return billingMoney(-$balance, $account['account_currency_code']) . ' owed';
    }
    return billingMoney($balance, $account['account_currency_code']);
}

/**
 * The totals across the open accounts - cash (what the accounts other than
 * cards hold), owed (what is owed on cards), and what came in and went out
 * this month and last month to the same day, leaving out money moved between
 * the accounts.
 */
function accountsStats(array $accounts) {
    global $mysqli;
    $cash = 0.0;
    $owed = 0.0;
    foreach ($accounts as $account) {
        if ($account['type'] === 3) {
            $owed += $account['balance'] < 0 ? -$account['balance'] : 0;
        } else {
            $cash += $account['balance'];
        }
    }
    $month_start = date('Y-m-01');
    $today = date('Y-m-d');
    $last_start = date('Y-m-01', strtotime('first day of last month'));
    $last_to = date('Y-m-d', min(strtotime(date('Y-m-t', strtotime($last_start))), strtotime($last_start . ' +' . (intval(date('j')) - 1) . ' days')));
    $flow = function ($from, $to) use ($mysqli) {
        $in = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COALESCE((SELECT SUM(payment_amount) FROM payments WHERE payment_archived_at IS NULL AND payment_date BETWEEN '$from' AND '$to'), 0)
            + COALESCE((SELECT SUM(revenue_amount) FROM revenues WHERE revenue_archived_at IS NULL AND revenue_date BETWEEN '$from' AND '$to' AND " . incomeRevenueSql('revenues.revenue_id') . "), 0)"));
        $out = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COALESCE(SUM(expense_amount), 0) FROM expenses WHERE expense_archived_at IS NULL AND expense_account_id > 0
            AND expense_date BETWEEN '$from' AND '$to' AND NOT EXISTS (SELECT 1 FROM transfers WHERE transfer_expense_id = expense_id)"));
        return [round(floatval($in[0]), 2), round(floatval($out[0]), 2)];
    };
    [$in, $out] = $flow($month_start, $today);
    [$last_in, $last_out] = $flow($last_start, $last_to);
    return ['cash' => round($cash, 2), 'owed' => round($owed, 2), 'in' => $in, 'out' => $out, 'last_in' => $last_in, 'last_out' => $last_out,
        'month_start' => $month_start, 'last_start' => $last_start];
}

/** Does anything sit on an account - a payment, income, an expense, a transfer, a recurring expense, a payment provider or a reimbursement? */
function accountInUse($account_id) {
    global $mysqli;
    $id = intval($account_id);
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT
        EXISTS (SELECT 1 FROM payments WHERE payment_account_id = $id)
        OR EXISTS (SELECT 1 FROM revenues WHERE revenue_account_id = $id)
        OR EXISTS (SELECT 1 FROM expenses WHERE expense_account_id = $id)
        OR EXISTS (SELECT 1 FROM recurring_expenses WHERE recurring_expense_account_id = $id)
        OR EXISTS (SELECT 1 FROM payment_providers WHERE payment_provider_account = $id)
        OR EXISTS (SELECT 1 FROM reimbursements WHERE reimbursement_account_id = $id)
        OR EXISTS (SELECT 1 FROM account_reconciliations WHERE reconciliation_account_id = $id)"));
    return intval($row[0]) === 1;
}

/**
 * The one path that writes an account from its form - New and Edit. $in:
 * name, type, currency (only while nothing is on it), opening_balance,
 * description and notes. Returns the account id, or 0 with $error.
 */
function accountSave(array $in, $account_id = 0, &$error = '') {
    global $mysqli, $currencies_array, $session_company_currency;
    $error = '';
    $account_id = intval($account_id);
    $existing = $account_id ? accountRow($account_id) : null;
    if ($account_id && !$existing) {
        $error = 'That account no longer exists';
        return 0;
    }
    $name = mb_substr(trim((string) ($in['name'] ?? '')), 0, 200);
    if ($name === '') {
        $error = 'Name the account';
        return 0;
    }
    $duplicate = mysqli_fetch_row(mysqli_query($mysqli, "SELECT account_id FROM accounts WHERE account_name = '" . escapeSql($name) . "' AND account_id <> $account_id AND account_archived_at IS NULL LIMIT 1"));
    if ($duplicate) {
        $error = "There's an account called $name already";
        return 0;
    }
    $type = intval($in['type'] ?? 0);
    if (!isset(accountTypes()[$type])) {
        $error = 'Pick what kind of account it is';
        return 0;
    }
    $opening = billingNumberInput($in['opening_balance'] ?? '0');
    if ($opening === null || abs($opening) >= 1e12) {
        $error = 'Enter the opening balance - 0.00 for none';
        return 0;
    }
    // Reconciled statements start from it - it stays as it was once one is finished
    if ($existing && round($opening, 2) != round(floatval($existing['opening_balance']), 2) && reconcileAccountReconciled($account_id)) {
        $error = 'The opening balance is where its first reconciled statement starts - undo its reconciliations to change it';
        return 0;
    }
    $currency = strtoupper(trim((string) ($in['currency'] ?? '')));
    $locked = $existing && accountInUse($account_id);
    if ($locked) {
        $currency = (string) $existing['account_currency_code'];
    } elseif (!isset($currencies_array[$currency])) {
        $error = 'Pick the currency';
        return 0;
    }
    $description = mb_substr(trim((string) ($in['description'] ?? '')), 0, 250);
    $notes = trim(str_replace("\r\n", "\n", (string) ($in['notes'] ?? '')));

    $set = "account_name = '" . escapeSql($name) . "', account_type = $type, opening_balance = " . number_format($opening, 2, '.', '') . ",
        account_currency_code = '" . escapeSql($currency ?: $session_company_currency) . "', account_description = " . ($description === '' ? 'NULL' : "'" . escapeSql($description) . "'") . ",
        account_notes = " . ($notes === '' ? 'NULL' : "'" . escapeSql($notes) . "'");
    if ($existing) {
        mysqli_query($mysqli, "UPDATE accounts SET $set WHERE account_id = $account_id");
        return $account_id;
    }
    mysqli_query($mysqli, "INSERT INTO accounts SET $set");
    return mysqli_insert_id($mysqli);
}

/** Archive an account - once nothing is left in it, and when online payments don't go into it. The row, or null with $error. */
function accountArchive($account_id, &$error = '') {
    global $mysqli;
    $row = accountRow($account_id);
    if (!$row || $row['account_archived_at']) {
        $error = $row ? 'That account is archived already' : 'That account no longer exists';
        return null;
    }
    if (abs($row['balance']) >= 0.005) {
        $error = $row['account_name'] . ' still holds ' . accountBalanceText($row) . ' - move it to another account first';
        return null;
    }
    if (mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM payment_providers WHERE payment_provider_active = 1 AND payment_provider_account = " . intval($row['account_id'])))) {
        $error = 'Online payments go into ' . $row['account_name'] . ' - pick another account for them in Admin > Settings > Online Payment first';
        return null;
    }
    mysqli_query($mysqli, "UPDATE accounts SET account_archived_at = NOW() WHERE account_id = " . intval($row['account_id']));
    return $row;
}

/** Bring an archived account back. The row, or null with $error. */
function accountRestore($account_id, &$error = '') {
    global $mysqli;
    $row = accountRow($account_id);
    if (!$row || !$row['account_archived_at']) {
        $error = $row ? "That account isn't archived" : 'That account no longer exists';
        return null;
    }
    mysqli_query($mysqli, "UPDATE accounts SET account_archived_at = NULL WHERE account_id = " . intval($row['account_id']));
    return $row;
}

/** Delete an account nothing has ever been on - it is taken off the default settings too. The row, or null with $error. */
function accountDelete($account_id, &$error = '') {
    global $mysqli;
    $row = accountRow($account_id);
    if (!$row) {
        $error = 'That account no longer exists';
        return null;
    }
    if (accountInUse($row['account_id'])) {
        $error = $row['account_name'] . ' has payments, income or expenses on it - archive it instead, so your books keep it';
        return null;
    }
    $id = intval($row['account_id']);
    mysqli_query($mysqli, "DELETE FROM accounts WHERE account_id = $id");
    foreach (['config_default_payment_account', 'config_default_expense_account', 'config_default_transfer_from_account', 'config_default_transfer_to_account'] as $setting) {
        mysqli_query($mysqli, "UPDATE settings SET $setting = NULL WHERE $setting = $id");
    }
    return $row;
}

/* ---------------------------------------------------------------- Ledger */

/** The kinds of line on an account - kind => label, icon, and which way the money goes. */
function accountLedgerKinds() {
    return [
        'payment' => ['label' => 'Invoice payment', 'icon' => 'fa-file-invoice-dollar', 'in' => true],
        'revenue' => ['label' => 'Other income', 'icon' => 'fa-hand-holding-usd', 'in' => true],
        'transfer_in' => ['label' => 'Transfer in', 'icon' => 'fa-arrow-down', 'in' => true],
        'expense' => ['label' => 'Expense', 'icon' => 'fa-shopping-cart', 'in' => false],
        'transfer_out' => ['label' => 'Transfer out', 'icon' => 'fa-arrow-up', 'in' => false],
        'reimbursement' => ['label' => 'Reimbursement', 'icon' => 'fa-user-check', 'in' => false],
    ];
}

/**
 * Every line on one account (or every account, for 0) as one row set - a row
 * each with txn_kind (accountLedgerKinds()), txn_id (the payment, revenue,
 * expense or reimbursement), txn_transfer_id, txn_account_id, account_name,
 * txn_date, txn_created_at, txn_amount (in positive, out negative), txn_what
 * (who or what - the invoice, the vendor, the agent paid back, the other
 * account), txn_description, txn_category_id, category_name, category_color,
 * txn_client_id, client_name, txn_other_account_id, other_account_name,
 * other_account_type (a transfer's other account's kind), txn_line (the row
 * behind it - payment-12, revenue-5 or expense-9), txn_reconciliation_id (the
 * reconciliation it is ticked on, finished or not), txn_cleared_id and
 * txn_cleared_date (the finished one it cleared on, and its statement date),
 * txn_method, txn_reference, txn_invoice_id, txn_currency, txn_imported and
 * txn_balance - the account's balance after the line, in date order. Select
 * from it as a derived table.
 */
function accountLedgerSql($account_id = 0) {
    $account_id = intval($account_id);
    $on = function ($column) use ($account_id) {
        return $account_id ? "$column = $account_id" : "$column > 0";
    };
    $rows = "
        SELECT 'payment' AS txn_kind, payment_id AS txn_id, 0 AS txn_transfer_id, payment_account_id AS txn_account_id,
            payment_date AS txn_date, payment_created_at AS txn_created_at, payment_amount AS txn_amount,
            CONCAT(COALESCE(invoice_prefix, ''), COALESCE(invoice_number, '')) AS txn_what, invoice_scope AS txn_description,
            COALESCE(invoice_category_id, 0) AS txn_category_id, COALESCE(invoice_client_id, 0) AS txn_client_id, 0 AS txn_other_account_id, '' AS txn_other_name,
            payment_method AS txn_method, payment_reference AS txn_reference, payment_invoice_id AS txn_invoice_id, payment_currency_code AS txn_currency,
            IF(payment_import_key IS NULL, 0, 1) AS txn_imported, 1 AS txn_order,
            CONCAT('payment-', payment_id) AS txn_line, payment_reconciliation_id AS txn_reconciliation_id
        FROM payments LEFT JOIN invoices ON invoice_id = payment_invoice_id
        WHERE payment_archived_at IS NULL AND " . $on('payment_account_id') . "
        UNION ALL
        SELECT IF(transfer_id IS NULL, 'revenue', 'transfer_in'), revenue_id, COALESCE(transfer_id, 0), revenue_account_id,
            revenue_date, revenue_created_at, revenue_amount,
            IF(transfer_id IS NULL, '', from_account.account_name), IF(transfer_id IS NULL, revenue_description, transfer_notes),
            revenue_category_id, revenue_client_id, COALESCE(from_expense.expense_account_id, 0), COALESCE(from_account.account_name, ''),
            IF(transfer_id IS NULL, revenue_payment_method, transfer_method), revenue_reference, 0, revenue_currency_code,
            IF(revenue_import_key IS NULL, 0, 1), IF(transfer_id IS NULL, 2, 3),
            CONCAT('revenue-', revenue_id), revenue_reconciliation_id
        FROM revenues
        LEFT JOIN transfers ON transfer_revenue_id = revenue_id
        LEFT JOIN expenses AS from_expense ON from_expense.expense_id = transfer_expense_id
        LEFT JOIN accounts AS from_account ON from_account.account_id = from_expense.expense_account_id
        WHERE revenue_archived_at IS NULL AND " . $on('revenue_account_id') . "
        UNION ALL
        SELECT CASE WHEN transfer_id IS NOT NULL THEN 'transfer_out' WHEN reimbursement_id IS NOT NULL THEN 'reimbursement' ELSE 'expense' END,
            COALESCE(reimbursement_id, expenses.expense_id), COALESCE(transfer_id, 0), expenses.expense_account_id,
            expenses.expense_date, expenses.expense_created_at, -expenses.expense_amount,
            CASE WHEN transfer_id IS NOT NULL THEN to_account.account_name WHEN reimbursement_id IS NOT NULL THEN payee.user_name ELSE COALESCE(vendor_name, '') END,
            IF(transfer_id IS NULL, expenses.expense_description, transfer_notes),
            expenses.expense_category_id, expenses.expense_client_id, COALESCE(to_revenue.revenue_account_id, 0), COALESCE(to_account.account_name, payee.user_name, ''),
            IF(transfer_id IS NULL, expenses.expense_payment_method, transfer_method), expenses.expense_reference, 0, expenses.expense_currency_code,
            IF(expenses.expense_import_key IS NULL, 0, 1), CASE WHEN transfer_id IS NOT NULL THEN 4 WHEN reimbursement_id IS NOT NULL THEN 6 ELSE 5 END,
            CONCAT('expense-', expenses.expense_id), expenses.expense_reconciliation_id
        FROM expenses
        LEFT JOIN vendors ON vendor_id = expenses.expense_vendor_id
        LEFT JOIN transfers ON transfer_expense_id = expenses.expense_id
        LEFT JOIN revenues AS to_revenue ON to_revenue.revenue_id = transfer_revenue_id
        LEFT JOIN accounts AS to_account ON to_account.account_id = to_revenue.revenue_account_id
        LEFT JOIN reimbursements ON reimbursement_expense_id = expenses.expense_id
        LEFT JOIN users AS payee ON payee.user_id = reimbursement_user_id
        WHERE expenses.expense_archived_at IS NULL AND " . $on('expenses.expense_account_id');
    return "SELECT ledger_lines.*, accounts.account_name, accounts.account_type, accounts.account_currency_code, category_name, category_color, client_name,
            ledger_lines.txn_other_name AS other_account_name, other_account.account_type AS other_account_type,
            cleared.reconciliation_id AS txn_cleared_id, cleared.reconciliation_statement_date AS txn_cleared_date,
            accounts.opening_balance + SUM(ledger_lines.txn_amount) OVER (PARTITION BY ledger_lines.txn_account_id ORDER BY ledger_lines.txn_date ASC, ledger_lines.txn_created_at ASC,
                ledger_lines.txn_order ASC, ledger_lines.txn_id ASC) AS txn_balance
        FROM ($rows) AS ledger_lines
        JOIN accounts ON accounts.account_id = ledger_lines.txn_account_id
        LEFT JOIN accounts AS other_account ON ledger_lines.txn_kind IN ('transfer_in', 'transfer_out') AND other_account.account_id = ledger_lines.txn_other_account_id
        LEFT JOIN account_reconciliations AS cleared ON cleared.reconciliation_id = ledger_lines.txn_reconciliation_id AND cleared.reconciliation_finished_at IS NOT NULL
        LEFT JOIN categories ON category_id = ledger_lines.txn_category_id
        LEFT JOIN clients ON client_id = ledger_lines.txn_client_id";
}

/**
 * A ledger line's who or what, and the rest of its description - "Payment
 * on INV-12", "From Business Savings", "Paid Business Visa" (into a card),
 * "Paid back Tess", the vendor, or what the other income was.
 */
function accountLedgerWhat(array $row) {
    $kind = $row['txn_kind'];
    $what = trim((string) $row['txn_what']);
    $description = trim((string) strtok((string) $row['txn_description'], "\n"));
    if ($kind === 'transfer_in') {
        $what = 'From ' . ($row['other_account_name'] ?: 'an account since deleted');
    } elseif ($kind === 'transfer_out') {
        $what = (accountType(['account_type' => $row['other_account_type'], 'account_name' => $row['other_account_name']]) === 3 ? 'Paid ' : 'To ') . ($row['other_account_name'] ?: 'an account since deleted');
    } elseif ($kind === 'payment') {
        $what = $what !== '' ? 'Payment on ' . $what : 'A payment';
    } elseif ($kind === 'reimbursement') {
        $what = 'Paid back ' . ($what ?: 'an agent');
    } elseif ($what === '') {
        $what = $description !== '' ? $description : (accountLedgerKinds()[$kind]['label'] ?? '');
        $description = '';
    }
    return [$what, $description !== $what ? $description : ''];
}

/**
 * The ways the ledger can be cut - ?show=. Across every account a transfer
 * shows once on Transfers, from the account it left.
 */
function accountLedgerShows($all_accounts = false) {
    return [
        '' => ['label' => 'All', 'where' => ''],
        'in' => ['label' => 'Money in', 'icon' => 'fa-arrow-down', 'where' => 'ledger.txn_amount > 0'],
        'out' => ['label' => 'Money out', 'icon' => 'fa-arrow-up', 'where' => 'ledger.txn_amount < 0'],
        'transfers' => ['label' => 'Transfers', 'icon' => 'fa-exchange-alt', 'where' => $all_accounts ? "ledger.txn_kind = 'transfer_out'" : "ledger.txn_kind IN ('transfer_in', 'transfer_out')"],
        'uncleared' => ['label' => 'Uncleared', 'icon' => 'fa-hourglass-half', 'where' => 'ledger.txn_cleared_id IS NULL'],
    ];
}

/** The older ?type= names of the ledger's kinds. */
function accountLedgerTypeNames() {
    return ['Revenue' => 'revenue', 'Payment' => 'payment', 'Expense' => 'expense', 'Transfer In' => 'transfer_in', 'Transfer Out' => 'transfer_out', 'Reimbursement' => 'reimbursement'];
}

/**
 * The WHERE over accountLedgerSql() AS ledger for a set of filters, as the
 * ledger and its export take them: q, kind (or the older type), category,
 * client, method, amount_min, amount_max, show, dtf and dtt. $summary gets
 * what each is, for the export. $with_show false leaves the pill out, for
 * the pills' own counts; $all_accounts is the ledger of every account.
 */
function accountLedgerFiltersSql(array $src, &$summary = [], $with_show = true, $all_accounts = false) {
    $summary = [];
    $sql = '1 = 1';
    $q = trim((string) ($src['q'] ?? ''));
    if ($q !== '') {
        $like = escapeSql($q);
        $amount_text = str_replace([',', '$'], '', $q);
        $amount = preg_match('/^-?\d+(\.\d{1,2})?$/', $amount_text) ? abs(round(floatval($amount_text), 2)) : null;
        $sql .= " AND (ledger.txn_what LIKE '%$like%' OR ledger.txn_description LIKE '%$like%' OR ledger.txn_reference LIKE '%$like%' OR ledger.category_name LIKE '%$like%'
            OR ledger.client_name LIKE '%$like%' OR ledger.other_account_name LIKE '%$like%' OR ledger.txn_method LIKE '%$like%'" . ($amount !== null ? " OR ABS(ledger.txn_amount) = $amount" : '') . ")";
        $summary['Search'] = $q;
    }
    $kinds = accountLedgerKinds();
    $kind = (string) ($src['kind'] ?? '');
    if ($kind === '' && isset(accountLedgerTypeNames()[(string) ($src['type'] ?? '')])) {
        $kind = accountLedgerTypeNames()[(string) $src['type']];
    }
    if (isset($kinds[$kind])) {
        $sql .= " AND ledger.txn_kind = '$kind'";
        $summary['Type'] = $kinds[$kind]['label'];
    }
    foreach (['category' => ['ledger.txn_category_id', 'categories', 'category_name', 'Category'], 'client' => ['ledger.txn_client_id', 'clients', 'client_name', 'Client']] as $key => $f) {
        $id = intval($src[$key] ?? 0);
        if ($id) {
            $sql .= " AND {$f[0]} = $id";
            $summary[$f[3]] = (string) getFieldById($f[1], $id, $f[2]);
        }
    }
    $method = trim((string) ($src['method'] ?? ($src['payment_method'] ?? '')));
    if ($method !== '') {
        $sql .= " AND ledger.txn_method = '" . escapeSql($method) . "'";
        $summary['Paid by'] = $method;
    }
    foreach (['amount_min' => '>=', 'amount_max' => '<='] as $key => $op) {
        $value = trim((string) ($src[$key] ?? ''));
        if ($value !== '' && is_numeric($value)) {
            $sql .= " AND ABS(ledger.txn_amount) $op " . round(abs(floatval($value)), 2);
            $summary[$key === 'amount_min' ? 'At least' : 'At most'] = billingMoney(abs(floatval($value)));
        }
    }
    $reconciliation_id = intval($src['reconciliation'] ?? 0);
    if ($reconciliation_id) {
        $sql .= " AND ledger.txn_cleared_id = $reconciliation_id";
        $statement = getFieldById('account_reconciliations', $reconciliation_id, 'reconciliation_statement_date');
        $summary['Cleared on'] = $statement ? 'the statement to ' . date('M j, Y', strtotime($statement)) : 'a statement since undone';
    }
    $show = (string) ($src['show'] ?? '');
    $shows = accountLedgerShows($all_accounts);
    if ($with_show && $show !== '' && isset($shows[$show])) {
        $sql .= ' AND ' . $shows[$show]['where'];
        $summary['Showing'] = $shows[$show]['label'];
    }
    $dtf = (string) ($src['dtf'] ?? '');
    $dtt = (string) ($src['dtt'] ?? '');
    $dtf = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dtf) ? $dtf : '1970-01-01';
    $dtt = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dtt) ? $dtt : '2099-12-31';
    if ($dtf !== '1970-01-01' || $dtt !== '2099-12-31') {
        $sql .= " AND ledger.txn_date BETWEEN '$dtf' AND '$dtt'";
        $range = formatExportDateRange($dtf, $dtt);
        if ($range) {
            $summary['Dated'] = $range;
        }
    }
    return $sql;
}

/** Where a ledger line opens - its quick look. */
function accountLedgerModal(array $row) {
    $id = intval($row['txn_id']);
    switch ($row['txn_kind']) {
        case 'payment':
            return "modals/payment/payment.php?id=$id";
        case 'revenue':
            return "modals/revenue/revenue.php?id=$id";
        case 'expense':
            return "modals/expense/expense.php?id=$id";
        case 'reimbursement':
            return "modals/expense/expense_reimbursement.php?id=$id";
        default:
            return 'modals/transfer/transfer.php?id=' . intval($row['txn_transfer_id']);
    }
}

/* ------------------------------------------------------------- Transfers */

/** A transfer with both its accounts - raw, or null. */
function transferRow($transfer_id) {
    global $mysqli;
    $transfer_id = intval($transfer_id);
    if (!$transfer_id) {
        return null;
    }
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT transfers.*, expense_date AS transfer_date, expense_amount AS transfer_amount, expense_currency_code AS transfer_currency_code,
            expense_account_id AS transfer_from_id, revenue_account_id AS transfer_to_id, from_account.account_name AS from_account_name, from_account.account_type AS from_account_type,
            from_account.account_archived_at AS from_archived_at, to_account.account_name AS to_account_name, to_account.account_type AS to_account_type,
            to_account.account_archived_at AS to_archived_at, revenue_import_key, expense_import_key, creator.user_name AS created_by_name
        FROM transfers
        JOIN expenses ON expense_id = transfer_expense_id
        JOIN revenues ON revenue_id = transfer_revenue_id
        LEFT JOIN accounts AS from_account ON from_account.account_id = expense_account_id
        LEFT JOIN accounts AS to_account ON to_account.account_id = revenue_account_id
        LEFT JOIN users AS creator ON creator.user_id = expense_created_by
        WHERE transfer_id = $transfer_id LIMIT 1"));
    return $row ?: null;
}

/** A transfer in a phrase - "$500.00 from Operating Checking to Business Savings on 2026-10-14". Raw. */
function transferLabel(array $row) {
    return billingMoney($row['transfer_amount'], $row['transfer_currency_code']) . ' from ' . ($row['from_account_name'] ?: 'an account since deleted')
        . ' to ' . ($row['to_account_name'] ?: 'an account since deleted') . ' on ' . $row['transfer_date'];
}

/** What a transfer is - paying a credit card when it goes into one. */
function transferWhat(array $row) {
    $to_type = isset(accountTypes()[intval($row['to_account_type'] ?? 0)]) ? intval($row['to_account_type']) : accountTypeGuess($row['to_account_name'] ?? '');
    return $to_type === 3 ? 'Card payment' : 'Transfer';
}

/**
 * The one path that writes a transfer from its form - New and Edit. $in:
 * date, amount, from, to, method and notes. Two different accounts, an
 * amount above nothing and a real date. Returns the transfer id, or 0 with
 * $error.
 */
function transferSave(array $in, $transfer_id = 0, &$error = '') {
    global $mysqli, $session_user_id, $session_company_currency;
    $error = '';
    $transfer_id = intval($transfer_id);
    $existing = $transfer_id ? transferRow($transfer_id) : null;
    if ($transfer_id && !$existing) {
        $error = 'That transfer no longer exists';
        return 0;
    }
    $date = trim((string) ($in['date'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !checkdate(intval(substr($date, 5, 2)), intval(substr($date, 8, 2)), intval(substr($date, 0, 4)))) {
        $error = 'Enter the date it moved';
        return 0;
    }
    $amount = billingNumberInput($in['amount'] ?? '');
    if ($amount === null || $amount <= 0 || $amount >= 1e12) {
        $error = 'Enter the amount moved';
        return 0;
    }
    $keep = $existing ? [intval($existing['transfer_from_id']), intval($existing['transfer_to_id'])] : [];
    $accounts = accountsList(false);
    foreach ($keep as $id) {
        if (!isset($accounts[$id]) && ($row = accountRow($id))) {
            $accounts[$id] = $row;
        }
    }
    $from = intval($in['from'] ?? 0);
    $to = intval($in['to'] ?? 0);
    if (!isset($accounts[$from])) {
        $error = 'Pick the account it came out of';
        return 0;
    }
    if (!isset($accounts[$to])) {
        $error = 'Pick the account it went into';
        return 0;
    }
    if ($from === $to) {
        $error = 'Pick two different accounts';
        return 0;
    }
    $method = trim((string) ($in['method'] ?? ''));
    if ($method !== '' && !in_array($method, paymentMethods(), true) && !($existing && $method === (string) $existing['transfer_method'])) {
        $error = 'Pick how it was moved';
        return 0;
    }
    $notes = trim(str_replace("\r\n", "\n", (string) ($in['notes'] ?? '')));
    $currency = escapeSql((string) ($accounts[$from]['account_currency_code'] ?: $session_company_currency));
    $amount_sql = number_format($amount, 2, '.', '');
    $method_sql = "'" . escapeSql($method) . "'";
    $notes_sql = "'" . escapeSql($notes) . "'";

    if ($existing) {
        // Either side cleared on a reconciled statement - the date, amount and that side's account stay
        $legs = [['expense', $existing['transfer_expense_id'], $from], ['revenue', $existing['transfer_revenue_id'], $to]];
        foreach ([false, true] as $release) {
            foreach ($legs as [$kind, $leg_id, $leg_account]) {
                if (!reconcileCheckChange($kind, $leg_id, $date, $amount, $leg_account, $error, $release)) {
                    return 0;
                }
            }
        }
        mysqli_query($mysqli, "UPDATE expenses SET expense_date = '$date', expense_amount = $amount_sql, expense_account_id = $from, expense_currency_code = '$currency'
            WHERE expense_id = " . intval($existing['transfer_expense_id']));
        mysqli_query($mysqli, "UPDATE revenues SET revenue_date = '$date', revenue_amount = $amount_sql, revenue_account_id = $to, revenue_currency_code = '$currency'
            WHERE revenue_id = " . intval($existing['transfer_revenue_id']));
        mysqli_query($mysqli, "UPDATE transfers SET transfer_method = $method_sql, transfer_notes = $notes_sql WHERE transfer_id = $transfer_id");
        return $transfer_id;
    }
    mysqli_query($mysqli, "INSERT INTO expenses SET expense_date = '$date', expense_amount = $amount_sql, expense_currency_code = '$currency', expense_vendor_id = 0,
        expense_category_id = 0, expense_account_id = $from, expense_created_by = " . intval($session_user_id));
    $expense_id = mysqli_insert_id($mysqli);
    mysqli_query($mysqli, "INSERT INTO revenues SET revenue_date = '$date', revenue_amount = $amount_sql, revenue_currency_code = '$currency', revenue_category_id = 0,
        revenue_account_id = $to, revenue_created_by = " . intval($session_user_id));
    $revenue_id = mysqli_insert_id($mysqli);
    mysqli_query($mysqli, "INSERT INTO transfers SET transfer_expense_id = $expense_id, transfer_revenue_id = $revenue_id, transfer_method = $method_sql, transfer_notes = $notes_sql");
    return mysqli_insert_id($mysqli);
}

/** Delete a transfer - both sides of it. The row it deleted, or null with $error. */
function transferDelete($transfer_id, &$error = '') {
    global $mysqli;
    $error = '';
    $row = transferRow($transfer_id);
    if (!$row) {
        $error = 'That transfer no longer exists';
        return null;
    }
    if (!reconcileCheckDelete('expense', $row['transfer_expense_id'], $error) || !reconcileCheckDelete('revenue', $row['transfer_revenue_id'], $error)) {
        return null;
    }
    mysqli_query($mysqli, "DELETE FROM expenses WHERE expense_id = " . intval($row['transfer_expense_id']));
    mysqli_query($mysqli, "DELETE FROM revenues WHERE revenue_id = " . intval($row['transfer_revenue_id']));
    mysqli_query($mysqli, "DELETE FROM transfers WHERE transfer_id = " . intval($row['transfer_id']));
    return $row;
}
