<?php

/*
 * ITFlow - income: the money that comes in.
 *
 * Two kinds. A payment is money against an invoice (payments) - its client
 * and category are the invoice's, and it is made and changed from the
 * invoice. Other income is money in that isn't for an invoice (revenues) - a
 * deposit, a rebate, interest, a referral fee - with its own client and
 * category, made here. The money coming into one account from another in a
 * transfer is a revenue too, but isn't income (incomeRevenueSql()).
 *
 * Every list, total, report and dashboard counts income the same way: the
 * payments, and the other income that isn't a transfer, categorised or not.
 *
 * Who sees what: Financial access, in the agent's client scope. Other income
 * is added and changed with write access to Financial and deleted with full
 * access; a payment keeps the invoice's own rules (Sales and Financial).
 */

/** The SQL that makes a revenue income - not the money coming in from a transfer between our accounts. $column is the revenue id. */
function incomeRevenueSql($column = 'revenues.revenue_id') {
    return "NOT EXISTS (SELECT 1 FROM transfers WHERE transfer_revenue_id = $column)";
}

/** May this agent add and change other income? */
function incomeCanEdit() {
    return lookupUserPermission('module_financial') >= 2;
}

/** May this agent delete other income? */
function incomeCanDelete() {
    return lookupUserPermission('module_financial') >= 3;
}

/** May this agent change payments - Financial at Modify, with Sales at Read (billingMoneyCan()). */
function incomeCanEditPayments() {
    return billingMoneyCan('edit');
}

/** May this agent delete payments - Financial at Full, with Sales at Read. */
function incomeCanDeletePayments() {
    return billingMoneyCan('delete');
}

/** Was a payment made online - through a payment provider? */
function incomeOnlineSql($reference = 'payment_reference') {
    return "$reference LIKE 'Stripe - pi\\_%'";
}

/**
 * Every payment and every piece of other income as one list, in the agent's
 * client scope - a row each with income_type (payment or revenue),
 * income_id, income_date, income_created_at, income_invoice_id,
 * invoice_prefix, invoice_number, invoice_status, income_description,
 * income_category_id, category_name, category_color, category_icon, income_client_id,
 * client_name, income_amount, income_currency_code, income_method,
 * income_reference, income_account_id, account_name, account_archived_at
 * and income_online. Select from it as a derived table.
 */
function incomeUnionSql() {
    return "SELECT 'payment' AS income_type, payment_id AS income_id, payment_date AS income_date, payment_created_at AS income_created_at,
            payment_invoice_id AS income_invoice_id, invoice_prefix, invoice_number, invoice_status, invoice_scope AS income_description,
            COALESCE(invoice_category_id, 0) AS income_category_id, category_name, category_color, category_icon,
            COALESCE(invoice_client_id, 0) AS income_client_id, client_name, payment_amount AS income_amount, payment_currency_code AS income_currency_code,
            payment_method AS income_method, payment_reference AS income_reference, payment_account_id AS income_account_id, account_name, account_archived_at,
            IF(" . incomeOnlineSql() . ", 1, 0) AS income_online
        FROM payments
        LEFT JOIN invoices ON invoice_id = payment_invoice_id
        LEFT JOIN clients ON client_id = invoice_client_id
        LEFT JOIN accounts ON account_id = payment_account_id
        LEFT JOIN categories ON category_id = invoice_category_id
        WHERE payment_archived_at IS NULL " . clientScopeSql('COALESCE(invoice_client_id, 0)') . "
        UNION ALL
        SELECT 'revenue', revenue_id, revenue_date, revenue_created_at,
            0, NULL, NULL, NULL, revenue_description,
            revenue_category_id, category_name, category_color, category_icon,
            revenue_client_id, client_name, revenue_amount, revenue_currency_code,
            revenue_payment_method, revenue_reference, revenue_account_id, account_name, account_archived_at,
            0
        FROM revenues
        LEFT JOIN clients ON client_id = revenue_client_id
        LEFT JOIN accounts ON account_id = revenue_account_id
        LEFT JOIN categories ON category_id = revenue_category_id
        WHERE revenue_archived_at IS NULL AND " . incomeRevenueSql() . ' ' . clientScopeSql('revenue_client_id');
}

/** The ways the list can be cut - ?show= (and the old ?type=Payment / Revenue). */
function incomeShows() {
    return [
        '' => ['label' => 'All', 'where' => ''],
        'payments' => ['label' => 'Invoice payments', 'icon' => 'fa-file-invoice-dollar', 'where' => "income.income_type = 'payment'", 'title' => 'Paid against an invoice'],
        'other' => ['label' => 'Other income', 'icon' => 'fa-hand-holding-usd', 'where' => "income.income_type = 'revenue'", 'title' => "Money in that isn't for an invoice"],
        'online' => ['label' => 'Paid online', 'icon' => 'fa-globe', 'where' => 'income.income_online = 1', 'title' => 'Paid by card through the payment provider'],
        'uncategorised' => ['label' => 'No category', 'icon' => 'fa-question-circle', 'where' => 'income.income_category_id = 0', 'title' => 'Not in a category - the reports show it as No category'],
    ];
}

/** The ?show= a request asks for - ?show=, or the ?type=payment / revenue older links use. */
function incomeShowFrom(array $src) {
    $show = (string) ($src['show'] ?? '');
    $type = strtolower((string) ($src['type'] ?? ''));
    if ($show === '' && $type !== '') {
        $show = $type === 'payment' ? 'payments' : ($type === 'revenue' ? 'other' : '');
    }
    return isset(incomeShows()[$show]) ? $show : '';
}

/**
 * The WHERE over incomeUnionSql() AS income for a set of filters, as the list
 * and the export take them: q, client, category, account, method, show (or
 * type), dtf and dtt. $summary gets what each is, by name, for the export.
 * $with_show false leaves the pill out, for the pills' own counts.
 */
function incomeFiltersSql(array $src, &$summary = [], $with_show = true) {
    $summary = [];
    $sql = '1 = 1';
    $q = trim((string) ($src['q'] ?? ''));
    if ($q !== '') {
        $like = escapeSql($q);
        $amount_text = str_replace([',', '$'], '', $q);
        $amount = preg_match('/^-?\d+(\.\d{1,2})?$/', $amount_text) ? round(floatval($amount_text), 2) : null;
        $sql .= " AND (income.income_description LIKE '%$like%' OR income.income_reference LIKE '%$like%' OR income.client_name LIKE '%$like%'
            OR income.category_name LIKE '%$like%' OR income.account_name LIKE '%$like%' OR income.income_method LIKE '%$like%'
            OR CONCAT(COALESCE(income.invoice_prefix, ''), COALESCE(income.invoice_number, '')) LIKE '%$like%'" . ($amount !== null ? " OR income.income_amount = $amount" : '') . ")";
        $summary['Search'] = $q;
    }
    foreach (['client' => ['income.income_client_id', 'clients', 'client_name', 'Client'], 'category' => ['income.income_category_id', 'categories', 'category_name', 'Category'],
        'account' => ['income.income_account_id', 'accounts', 'account_name', 'Account']] as $key => $f) {
        $id = intval($src[$key] ?? 0);
        if ($id) {
            $sql .= " AND {$f[0]} = $id";
            $summary[$f[3]] = (string) getFieldById($f[1], $id, $f[2]);
        }
    }
    $method = trim((string) ($src['method'] ?? ''));
    if ($method !== '') {
        $sql .= " AND income.income_method = '" . escapeSql($method) . "'";
        $summary['Paid by'] = $method;
    }
    $show = incomeShowFrom($src);
    if ($with_show && $show !== '') {
        $sql .= ' AND ' . incomeShows()[$show]['where'];
        $summary['Showing'] = incomeShows()[$show]['label'];
    }
    $dtf = (string) ($src['dtf'] ?? '');
    $dtt = (string) ($src['dtt'] ?? '');
    $dtf = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dtf) ? $dtf : '1970-01-01';
    $dtt = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dtt) ? $dtt : '2099-12-31';
    if ($dtf !== '1970-01-01' || $dtt !== '2099-12-31') {
        $sql .= " AND income.income_date BETWEEN '$dtf' AND '$dtt'";
        $range = formatExportDateRange($dtf, $dtt);
        if ($range) {
            $summary['Dated'] = $range;
        }
    }
    return $sql;
}

/** What came in between two dates, in the agent's client scope - optionally one client's. */
function incomeTotal($from, $to, $client_id = 0) {
    global $mysqli;
    $client_id = intval($client_id);
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COALESCE(SUM(income_amount), 0) FROM (" . incomeUnionSql() . ") AS income
        WHERE income.income_date BETWEEN '" . escapeSql($from) . "' AND '" . escapeSql($to) . "'" . ($client_id ? " AND income.income_client_id = $client_id" : '')));
    return round(floatval($row[0]), 2);
}

/**
 * The money along the top of the list, in the agent's client scope (one
 * client's on their page): this month against the same days of last month,
 * the year so far with its monthly average, what the running recurring
 * invoices bill a month, and what is still owed on invoices (and how much
 * of it is late).
 */
function incomeStats($client_id = 0) {
    global $mysqli;
    $client_id = intval($client_id);
    $month_start = date('Y-m-01');
    $today = date('Y-m-d');
    $last_start = date('Y-m-01', strtotime('first day of last month'));
    $last_to_date = date('Y-m-d', min(strtotime(date('Y-m-t', strtotime($last_start))), strtotime($last_start . ' +' . (intval(date('j')) - 1) . ' days')));
    $year_start = date('Y-01-01');
    $stats = [
        'month_start' => $month_start, 'last_start' => $last_start, 'year_start' => $year_start,
        'month' => incomeTotal($month_start, $today, $client_id),
        'last_to_date' => incomeTotal($last_start, $last_to_date, $client_id),
        'year' => incomeTotal($year_start, $today, $client_id),
    ];
    $stats['change'] = $stats['last_to_date'] > 0 ? round(($stats['month'] - $stats['last_to_date']) / $stats['last_to_date'] * 100) : null;
    $stats['year_average'] = round($stats['year'] / max(1, intval(date('n')) - 1 + intval(date('j')) / intval(date('t'))), 2);

    $client_sql = $client_id ? " AND recurring_invoice_client_id = $client_id" : '';
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COALESCE(SUM(" . recurringInvoiceMonthlySql() . "), 0), COUNT(*) FROM recurring_invoices
        WHERE recurring_invoice_status = 1 AND recurring_invoice_archived_at IS NULL $client_sql " . clientScopeSql('recurring_invoice_client_id')));
    $stats['recurring'] = round(floatval($row[0]), 2);
    $stats['recurring_count'] = intval($row[1]);

    $open = dashboardOpenInvoicesSql(($client_id ? " AND invoice_client_id = $client_id" : '') . ' ' . clientScopeSql('invoice_client_id'));
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COALESCE(SUM(balance), 0), COUNT(*), COALESCE(SUM(CASE WHEN invoice_due < CURDATE() THEN balance END), 0),
            COALESCE(SUM(invoice_due < CURDATE()), 0)
        FROM ($open) AS open_invoices WHERE balance > 0"));
    $stats['owed'] = round(floatval($row[0]), 2);
    $stats['owed_count'] = intval($row[1]);
    $stats['overdue'] = round(floatval($row[2]), 2);
    $stats['overdue_count'] = intval($row[3]);
    return $stats;
}

/** The income categories - id => row, archived ones left out but $keep_id, and the old Account Transfer one unless kept (categoryPicker()). */
function incomeCategories($keep_id = 0) {
    return categoryPicker('Income', [$keep_id], ['exclude' => ['Account Transfer']]);
}

/* ---------------------------------------------------------- Other income */

/** One piece of other income with its client, category, account and who entered it - raw, or null. Scoped; a transfer's leg isn't income. */
function incomeRevenueRow($revenue_id, $scoped = true) {
    global $mysqli;
    $revenue_id = intval($revenue_id);
    if (!$revenue_id) {
        return null;
    }
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT revenues.*, client_name, client_abbreviation, category_name, category_color, category_icon, account_name,
            account_archived_at, account_currency_code, creator.user_name AS created_by_name
        FROM revenues
        LEFT JOIN clients ON client_id = revenue_client_id
        LEFT JOIN categories ON category_id = revenue_category_id
        LEFT JOIN accounts ON account_id = revenue_account_id
        LEFT JOIN users AS creator ON creator.user_id = revenue_created_by
        WHERE revenue_id = $revenue_id AND revenue_archived_at IS NULL AND " . incomeRevenueSql('revenue_id') . ' ' . ($scoped ? clientScopeSql('revenue_client_id') : '') . "
        LIMIT 1"));
    return $row ?: null;
}

/** Other income in a phrase - "Referral fee - $250.00 on 2026-10-05". Raw. */
function incomeRevenueLabel(array $row) {
    $what = trim(strtok((string) ($row['revenue_description'] ?? ''), "\n"));
    if ($what === '') {
        $what = trim((string) ($row['category_name'] ?? '')) ?: 'Other income';
    }
    return $what . ' - ' . billingMoney($row['revenue_amount'], $row['revenue_currency_code']) . ' on ' . $row['revenue_date'];
}

/**
 * The one path that writes other income from a form - New, Edit and Copy.
 * $in: date, amount, account, category, client_id, payment_method,
 * reference and description. The amount can't be nothing; the currency is
 * the account's. Returns the revenue id, or 0 with $error.
 */
function incomeRevenueSave(array $in, $revenue_id = 0, &$error = '') {
    global $mysqli, $session_user_id, $session_company_currency;
    $error = '';
    $revenue_id = intval($revenue_id);
    $existing = $revenue_id ? incomeRevenueRow($revenue_id) : null;
    if ($revenue_id && !$existing) {
        $error = 'That income no longer exists, or is not one you can see';
        return 0;
    }

    $date = trim((string) ($in['date'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !checkdate(intval(substr($date, 5, 2)), intval(substr($date, 8, 2)), intval(substr($date, 0, 4)))) {
        $error = 'Enter the date it came in';
        return 0;
    }
    $amount = billingNumberInput($in['amount'] ?? '');
    if ($amount === null || $amount == 0 || abs($amount) >= 1e12) {
        $error = 'Enter the amount';
        return 0;
    }
    $accounts = paymentAccounts($existing ? $existing['revenue_account_id'] : 0);
    $account_id = intval($in['account'] ?? 0);
    if (!isset($accounts[$account_id])) {
        $error = 'Pick the account it went into';
        return 0;
    }
    $categories = incomeCategories($existing ? $existing['revenue_category_id'] : 0);
    $category_id = intval($in['category'] ?? 0);
    if (!isset($categories[$category_id])) {
        $error = 'Pick a category';
        return 0;
    }
    $client_id = intval($in['client_id'] ?? 0);
    if ($client_id && !mysqli_num_rows(mysqli_query($mysqli, "SELECT client_id FROM clients WHERE client_id = $client_id " . clientScopeSql('client_id')))) {
        $error = "That client isn't one you can open";
        return 0;
    }
    $method = trim((string) ($in['payment_method'] ?? ''));
    if ($method !== '' && !in_array($method, paymentMethods(), true) && !($existing && $method === (string) $existing['revenue_payment_method'])) {
        $error = 'Pick how it was paid';
        return 0;
    }
    $reference = mb_substr(trim((string) ($in['reference'] ?? '')), 0, 200);
    $description = trim(str_replace("\r\n", "\n", (string) ($in['description'] ?? '')));
    $currency = (string) ($accounts[$account_id]['account_currency_code'] ?: $session_company_currency);

    $set = "revenue_date = '$date', revenue_amount = $amount, revenue_currency_code = '" . escapeSql($currency) . "', revenue_account_id = $account_id,
        revenue_category_id = $category_id, revenue_client_id = $client_id, revenue_payment_method = " . ($method === '' ? 'NULL' : "'" . escapeSql($method) . "'") . ",
        revenue_reference = '" . escapeSql($reference) . "', revenue_description = " . ($description === '' ? 'NULL' : "'" . escapeSql($description) . "'");
    if ($existing) {
        // Cleared on a reconciled statement - its date, amount and account stay
        if (!reconcileCheckChange('revenue', $revenue_id, $date, $amount, $account_id, $error)) {
            return 0;
        }
        transferDepositRelease('revenue', $revenue_id, $account_id);
        mysqli_query($mysqli, "UPDATE revenues SET $set WHERE revenue_id = $revenue_id");
        return $revenue_id;
    }
    mysqli_query($mysqli, "INSERT INTO revenues SET $set, revenue_created_by = " . intval($session_user_id));
    return mysqli_insert_id($mysqli);
}

/** Delete other income this agent may see - the row it deleted, or null with $error. */
function incomeRevenueDelete($revenue_id, &$error = '') {
    global $mysqli;
    $error = '';
    $row = incomeRevenueRow($revenue_id);
    if (!$row) {
        $error = 'That income no longer exists, or is not one you can see';
        return null;
    }
    if (!reconcileCheckDelete('revenue', $row['revenue_id'], $error)) {
        return null;
    }
    mysqli_query($mysqli, "DELETE FROM revenues WHERE revenue_id = " . intval($row['revenue_id']));
    return $row;
}

/* -------------------------------------------------------------- Payments */

/**
 * A payment with its invoice (number, status, amount, client, category),
 * account, what the invoice has been paid in all, the other payments on it
 * and, for one made online, the payment provider's fee expense - raw, or
 * null. Scoped to the invoice's client.
 */
function incomePaymentRow($payment_id) {
    global $mysqli;
    $payment_id = intval($payment_id);
    if (!$payment_id) {
        return null;
    }
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT payments.*, invoice_id, invoice_prefix, invoice_number, invoice_status, invoice_amount, invoice_currency_code,
            invoice_date, invoice_due, invoice_scope, invoice_client_id, client_name, category_name, category_color, category_icon, invoice_category_id, account_name, account_archived_at
        FROM payments
        LEFT JOIN invoices ON invoice_id = payment_invoice_id
        LEFT JOIN clients ON client_id = invoice_client_id
        LEFT JOIN categories ON category_id = invoice_category_id
        LEFT JOIN accounts ON account_id = payment_account_id
        WHERE payment_id = $payment_id AND payment_archived_at IS NULL " . clientScopeSql('COALESCE(invoice_client_id, 0)') . "
        LIMIT 1"));
    if (!$row) {
        return null;
    }
    $row['invoice_paid'] = $row['invoice_id'] ? invoicePaid($row['invoice_id']) : 0.0;
    $row['others'] = [];
    if ($row['invoice_id']) {
        $sql = mysqli_query($mysqli, "SELECT payment_id, payment_date, payment_amount, payment_currency_code, payment_method FROM payments
            WHERE payment_invoice_id = " . intval($row['invoice_id']) . " AND payment_id <> $payment_id AND payment_archived_at IS NULL ORDER BY payment_date ASC, payment_id ASC");
        while ($other = mysqli_fetch_assoc($sql)) {
            $row['others'][] = $other;
        }
    }
    $row['fee'] = null;
    if (preg_match('/^Stripe - pi_/', (string) $row['payment_reference'])) {
        $row['fee'] = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT expense_id, expense_amount, expense_currency_code FROM expenses
            WHERE LOCATE('" . escapeSql($row['payment_reference']) . "', expense_reference) = 1 ORDER BY expense_id ASC LIMIT 1")) ?: null;
    }
    return $row;
}

/** A payment in a phrase - "INV-12 - $500.00 on 2026-10-05". Raw. */
function incomePaymentLabel(array $row) {
    return ($row['invoice_number'] !== null ? $row['invoice_prefix'] . $row['invoice_number'] : 'A payment') . ' - ' . billingMoney($row['payment_amount'], $row['payment_currency_code'])
        . ' on ' . $row['payment_date'];
}

/* ---------------------------------------------------------------- Bulk */

/**
 * The ticked rows of the Income list - "payment-12" / "revenue-7" - as
 * ['payment' => ids, 'revenue' => ids]. Anything else is left out.
 */
function incomeBulkIds($refs) {
    $ids = ['payment' => [], 'revenue' => []];
    foreach ((array) $refs as $ref) {
        if (is_string($ref) && preg_match('/^(payment|revenue)-([1-9]\d*)$/i', $ref, $m)) {
            $ids[strtolower($m[1])][intval($m[2])] = intval($m[2]);
        }
    }
    return $ids;
}
