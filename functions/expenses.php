<?php

/*
 * Expenses - what the business spends with its own vendors. One definition
 * of an expense for the list, its quick look, the windows, the export and
 * search.
 *
 * An expense with no vendor is the out side of a transfer between accounts
 * (transfers.transfer_expense_id), so everything here works on vendor
 * expenses only, as the reports and dashboard do. A negative amount is a
 * refund or credit; one made with Refund points at the expense it gives
 * back (expense_refund_of_id).
 *
 * Receipts live in uploads/expenses/ under a random name and are served by
 * agent/expense_receipt.php after the login and client checks - never
 * straight from uploads/.
 */

/** The receipt file types an expense takes. */
function expenseReceiptTypes() {
    return ['jpg', 'jpeg', 'gif', 'png', 'webp', 'pdf'];
}

/**
 * One expense with its vendor, category, account, client, who entered it,
 * the transfer it belongs to (if any), the expense it refunds and what has
 * been refunded against it - or null. Scoped to the agent's clients unless
 * $scoped is false.
 */
function expenseRow($expense_id, $scoped = true) {
    global $mysqli;
    $expense_id = intval($expense_id);
    if (!$expense_id) {
        return null;
    }
    $scope = $scoped ? clientScopeSql('expenses.expense_client_id') : '';
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT expenses.*, vendor_name, vendor_description, vendor_archived_at,
            category_name, category_color, category_icon, category_archived_at, account_name, account_archived_at, account_currency_code,
            client_name, client_abbreviation, user_name AS created_by_name, transfer_id,
            recurring_expense_frequency, recurring_expense_amount, recurring_expense_currency_code,
            refunded.expense_date AS refund_of_date, refunded.expense_amount AS refund_of_amount, refunded.expense_description AS refund_of_description,
            (SELECT COALESCE(SUM(-r.expense_amount), 0) FROM expenses AS r WHERE r.expense_refund_of_id = expenses.expense_id) AS refunded_amount,
            (SELECT COUNT(*) FROM expenses AS r WHERE r.expense_refund_of_id = expenses.expense_id) AS refund_count
        FROM expenses
        LEFT JOIN vendors ON vendor_id = expenses.expense_vendor_id
        LEFT JOIN categories ON category_id = expenses.expense_category_id
        LEFT JOIN accounts ON account_id = expenses.expense_account_id
        LEFT JOIN clients ON client_id = expenses.expense_client_id
        LEFT JOIN users ON user_id = expenses.expense_created_by
        LEFT JOIN transfers ON transfer_expense_id = expenses.expense_id
        LEFT JOIN expenses AS refunded ON refunded.expense_id = expenses.expense_refund_of_id
        LEFT JOIN recurring_expenses ON recurring_expense_id = expenses.expense_recurring_expense_id
        WHERE expenses.expense_id = $expense_id $scope
        LIMIT 1"));
    return $row ?: null;
}

/** The joins the list and export read through. */
function expenseJoinsSql() {
    return "LEFT JOIN categories ON expense_category_id = category_id
        LEFT JOIN vendors ON expense_vendor_id = vendor_id
        LEFT JOIN accounts ON expense_account_id = account_id
        LEFT JOIN clients ON expense_client_id = client_id
        LEFT JOIN users ON expense_created_by = user_id";
}

/** The ways the list can be cut, for its pills - ?show= on the list. */
function expenseShows() {
    return [
        '' => ['label' => 'All', 'where' => ''],
        'client' => ['label' => 'For a client', 'icon' => 'fa-user', 'where' => 'expense_client_id > 0', 'title' => 'Filed against a client - their direct costs'],
        'no_receipt' => ['label' => 'No receipt', 'icon' => 'fa-paperclip', 'where' => "COALESCE(expense_receipt, '') = '' AND expense_amount > 0", 'title' => 'No receipt attached yet'],
        'refunds' => ['label' => 'Refunds', 'icon' => 'fa-undo-alt', 'where' => 'expense_amount < 0', 'title' => 'Money that came back - refunds and credits'],
    ];
}

/**
 * The WHERE for a set of filters, as the list and the export take them
 * from the query string or the form: q, vendor, category, account, client,
 * method, recurring_expense_id, show, dtf and dtt. Starts with the vendor-expenses-only rule and
 * the agent's client scope. $summary is filled with what each filter is,
 * by name, for the export's heading.
 */
function expenseFiltersSql(array $src, &$summary = []) {
    global $mysqli;
    $summary = [];
    $sql = "expense_vendor_id > 0 " . clientScopeSql('expense_client_id');

    $q = trim((string) ($src['q'] ?? ''));
    if ($q !== '') {
        $like = escapeSql($q);
        $sql .= " AND (vendor_name LIKE '%$like%' OR client_name LIKE '%$like%' OR category_name LIKE '%$like%' OR account_name LIKE '%$like%'
            OR expense_description LIKE '%$like%' OR expense_reference LIKE '%$like%' OR expense_payment_method LIKE '%$like%' OR expense_amount LIKE '%$like%')";
        $summary['Search'] = $q;
    }
    foreach (['vendor' => ['expense_vendor_id', 'vendors', 'vendor_name', 'Vendor'], 'category' => ['expense_category_id', 'categories', 'category_name', 'Category'],
        'account' => ['expense_account_id', 'accounts', 'account_name', 'Account'], 'client' => ['expense_client_id', 'clients', 'client_name', 'Client']] as $key => $f) {
        $id = intval($src[$key] ?? 0);
        if ($id) {
            $sql .= " AND {$f[0]} = $id";
            $summary[$f[3]] = (string) getFieldById($f[1], $id, $f[2]);
        }
    }
    $method = trim((string) ($src['method'] ?? ''));
    if ($method !== '') {
        $sql .= " AND expense_payment_method = '" . escapeSql($method) . "'";
        $summary['Paid by'] = $method;
    }
    $recurring_id = intval($src['recurring_expense_id'] ?? 0);
    if ($recurring_id) {
        $sql .= " AND expense_recurring_expense_id = $recurring_id";
        $recurring = recurringExpenseRow($recurring_id, false);
        $summary['Recorded by'] = $recurring ? recurringExpenseLabel($recurring) : 'a recurring expense';
    }
    $shows = expenseShows();
    $show = (string) ($src['show'] ?? '');
    if (isset($shows[$show]) && $shows[$show]['where']) {
        $sql .= " AND {$shows[$show]['where']}";
        $summary['Showing'] = $shows[$show]['label'];
    }
    $dtf = (string) ($src['dtf'] ?? '');
    $dtt = (string) ($src['dtt'] ?? '');
    $dtf = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dtf) ? $dtf : '1970-01-01';
    $dtt = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dtt) ? $dtt : '2099-12-31';
    if ($dtf !== '1970-01-01' || $dtt !== '2099-12-31') {
        $sql .= " AND expense_date BETWEEN '$dtf' AND '$dtt'";
        $range = formatExportDateRange($dtf, $dtt);
        if ($range) {
            $summary['Dated'] = $range;
        }
    }
    return $sql;
}

/**
 * What a recurring expense costs a month, as SQL - quarterly ones count as a
 * third, yearly ones as a twelfth. Use it in sums over recurring_expenses.
 */
function expenseRecurringMonthlySql() {
    return recurringInvoiceMonthlySql('recurring_expense_amount', 'recurring_expense_frequency');
}

/**
 * The money along the top of the list, in the agent's client scope: spent
 * this month and last, the year to date, what the running recurring
 * expenses come to a month, and the last 90 days' expenses with no receipt.
 */
function expenseStats() {
    global $mysqli;
    $scope = clientScopeSql('expense_client_id');
    $month_start = date('Y-m-01');
    $last_start = date('Y-m-01', strtotime("$month_start -1 month"));
    $last_same_day = date('Y-m-d', min(strtotime(date('Y-m-t', strtotime($last_start))), strtotime($last_start . ' +' . (intval(date('j')) - 1) . ' days')));
    $year_start = date('Y-01-01');
    $since_90 = date('Y-m-d', strtotime('-90 days'));
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT
            COALESCE(SUM(CASE WHEN expense_date >= '$month_start' AND expense_date <= CURDATE() THEN expense_amount END), 0) AS month,
            COALESCE(SUM(CASE WHEN expense_date >= '$month_start' AND expense_date <= CURDATE() THEN 1 END), 0) AS month_count,
            COALESCE(SUM(CASE WHEN expense_date >= '$last_start' AND expense_date < '$month_start' THEN expense_amount END), 0) AS last_month,
            COALESCE(SUM(CASE WHEN expense_date >= '$last_start' AND expense_date <= '$last_same_day' THEN expense_amount END), 0) AS last_to_date,
            COALESCE(SUM(CASE WHEN expense_date >= '$year_start' AND expense_date <= CURDATE() THEN expense_amount END), 0) AS year,
            COALESCE(SUM(CASE WHEN expense_date >= '$year_start' AND expense_date <= CURDATE() THEN 1 END), 0) AS year_count,
            COALESCE(SUM(CASE WHEN expense_date >= '$since_90' AND COALESCE(expense_receipt, '') = '' AND expense_amount > 0 THEN 1 END), 0) AS no_receipt,
            COALESCE(SUM(CASE WHEN expense_date >= '$since_90' AND COALESCE(expense_receipt, '') = '' AND expense_amount > 0 THEN expense_amount END), 0) AS no_receipt_amount
        FROM expenses WHERE expense_vendor_id > 0 $scope"));
    $recurring = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COALESCE(SUM(" . expenseRecurringMonthlySql() . "), 0) AS monthly, COUNT(*) AS running
        FROM recurring_expenses WHERE " . recurringExpenseRunningSql() . " " . clientScopeSql('recurring_expense_client_id')));
    $months_in = max(1, intval(date('n')) - 1 + intval(date('j')) / intval(date('t')));
    return [
        'month' => floatval($row['month']), 'month_count' => intval($row['month_count']), 'month_start' => $month_start,
        'last_month' => floatval($row['last_month']), 'last_to_date' => floatval($row['last_to_date']), 'last_start' => $last_start,
        'last_end' => date('Y-m-t', strtotime($last_start)), 'year' => floatval($row['year']), 'year_count' => intval($row['year_count']),
        'year_start' => $year_start, 'year_average' => floatval($row['year']) / $months_in,
        'no_receipt' => intval($row['no_receipt']), 'no_receipt_amount' => floatval($row['no_receipt_amount']), 'since_90' => $since_90,
        'recurring_monthly' => floatval($recurring['monthly']), 'recurring_count' => intval($recurring['running']),
    ];
}

/** A category's colour as a small dot - names like "lightblue" or a hex value; nothing for anything else. */
function expenseCategoryDot($color) {
    $color = trim((string) $color);
    if (!preg_match('/^(#[0-9a-f]{3,8}|[a-z]{3,24})$/i', $color)) {
        return '<span class="itflow-category-dot" aria-hidden="true"></span>';
    }
    return '<span class="itflow-category-dot" style="background-color: ' . $color . '" aria-hidden="true"></span>';
}

/**
 * The last expense with each vendor - its category, account and payment
 * method, by vendor id. New Expense fills these in when a vendor is picked.
 */
function expenseVendorDefaults() {
    global $mysqli;
    $defaults = [];
    $sql = mysqli_query($mysqli, "SELECT e.expense_vendor_id, e.expense_category_id, e.expense_account_id, e.expense_payment_method
        FROM expenses AS e
        JOIN (SELECT expense_vendor_id, MAX(expense_id) AS last_id FROM expenses WHERE expense_vendor_id > 0 AND expense_amount > 0 GROUP BY expense_vendor_id) AS last
            ON last.last_id = e.expense_id");
    while ($row = mysqli_fetch_assoc($sql)) {
        $defaults[intval($row['expense_vendor_id'])] = [
            'category' => intval($row['expense_category_id']),
            'account' => intval($row['expense_account_id']),
            'method' => (string) $row['expense_payment_method'],
        ];
    }
    return $defaults;
}

/** Our own vendors for a picker - not archived, plus $keep_id. */
function expenseVendors($keep_id = 0) {
    global $mysqli;
    $keep_id = intval($keep_id);
    $vendors = [];
    $sql = mysqli_query($mysqli, "SELECT vendor_id, vendor_name, vendor_description, vendor_archived_at FROM vendors
        WHERE vendor_client_id = 0 AND (vendor_archived_at IS NULL OR vendor_id = $keep_id)
        ORDER BY vendor_favorite DESC, vendor_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $vendors[intval($row['vendor_id'])] = $row;
    }
    return $vendors;
}

/** Expense categories for a picker - not archived, plus $keep_id. */
function expenseCategories($keep_id = 0) {
    global $mysqli;
    $keep_id = intval($keep_id);
    $categories = [];
    $sql = mysqli_query($mysqli, "SELECT category_id, category_name, category_color, category_icon, category_archived_at FROM categories
        WHERE category_type = 'Expense' AND (category_archived_at IS NULL OR category_id = $keep_id)
        ORDER BY category_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $categories[intval($row['category_id'])] = $row;
    }
    return $categories;
}

/**
 * Add an expense, or change one - the one path that writes an expense from
 * a form: New, Edit, Copy and Refund. $in: date, amount, vendor, category,
 * account, client_id, payment_method, reference, description and, for a
 * new refund, refund_of (the expense it gives back - the amount is made
 * negative). Checks each against what can be picked; an archived vendor,
 * category or account is only kept when it is already the expense's.
 * Returns the expense id, or 0 with $error.
 */
function expenseSave(array $in, $expense_id = 0, &$error = '') {
    global $mysqli, $session_company_currency, $session_user_id;
    $error = '';
    $expense_id = intval($expense_id);
    $existing = $expense_id ? expenseRow($expense_id) : null;
    if ($expense_id && !$existing) {
        $error = 'That expense no longer exists';
        return 0;
    }
    if ($existing && $existing['transfer_id']) {
        $error = 'That is the out side of a transfer between accounts - change it from Transfers';
        return 0;
    }

    $date = trim((string) ($in['date'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !checkdate(intval(substr($date, 5, 2)), intval(substr($date, 8, 2)), intval(substr($date, 0, 4)))) {
        $error = 'Enter the date it was paid';
        return 0;
    }
    $amount_text = str_replace([',', ' ', '$'], '', trim((string) ($in['amount'] ?? '')));
    if (!is_numeric($amount_text) || round(floatval($amount_text), 2) == 0) {
        $error = 'Enter the amount';
        return 0;
    }
    $amount = round(floatval($amount_text), 2);
    if (abs($amount) > 9999999999999.99) {
        $error = 'That amount is too large';
        return 0;
    }

    $refund_of = $existing ? intval($existing['expense_refund_of_id']) : intval($in['refund_of'] ?? 0);
    if (!$existing && $refund_of) {
        $original = expenseRow($refund_of);
        if (!$original || floatval($original['expense_amount']) <= 0 || $original['transfer_id']) {
            $error = 'The expense being refunded no longer exists';
            return 0;
        }
        $amount = -abs($amount);
    }

    $vendor = intval($in['vendor'] ?? 0);
    $vendors = expenseVendors($existing ? $existing['expense_vendor_id'] : 0);
    if (!isset($vendors[$vendor])) {
        $error = 'Pick one of your vendors';
        return 0;
    }
    $category = intval($in['category'] ?? 0);
    $categories = expenseCategories($existing ? $existing['expense_category_id'] : 0);
    if (!isset($categories[$category])) {
        $error = 'Pick an expense category';
        return 0;
    }
    $account = intval($in['account'] ?? 0);
    $accounts = paymentAccounts($existing ? $existing['expense_account_id'] : 0);
    if (!isset($accounts[$account])) {
        $error = 'Pick the account it was paid from';
        return 0;
    }
    $client_id = intval($in['client_id'] ?? 0);
    if ($client_id && !mysqli_num_rows(mysqli_query($mysqli, "SELECT client_id FROM clients WHERE client_id = $client_id " . clientScopeSql('client_id')))) {
        $error = "That client isn't one you can file expenses against";
        return 0;
    }
    $method = trim((string) ($in['payment_method'] ?? ''));
    if ($method !== '' && !in_array($method, paymentMethods(), true) && !($existing && $method === (string) $existing['expense_payment_method'])) {
        $error = 'Pick how it was paid';
        return 0;
    }
    $reference = mb_substr(trim((string) ($in['reference'] ?? '')), 0, 200);
    $description = trim((string) ($in['description'] ?? ''));

    $set = "expense_date = '$date', expense_amount = $amount, expense_vendor_id = $vendor, expense_category_id = $category,
        expense_account_id = $account, expense_client_id = $client_id, expense_payment_method = " . ($method === '' ? 'NULL' : "'" . escapeSql($method) . "'") . ",
        expense_reference = '" . escapeSql($reference) . "', expense_description = '" . escapeSql($description) . "'";

    if ($existing) {
        mysqli_query($mysqli, "UPDATE expenses SET $set WHERE expense_id = $expense_id");
        return $expense_id;
    }
    $currency = escapeSql((string) ($accounts[$account]['account_currency_code'] ?: $session_company_currency));
    mysqli_query($mysqli, "INSERT INTO expenses SET $set, expense_currency_code = '$currency', expense_created_by = " . intval($session_user_id)
        . ($refund_of ? ", expense_refund_of_id = $refund_of" : ''));
    return mysqli_insert_id($mysqli);
}

/**
 * Delete an expense - its receipt, the stock deliveries that named it (they
 * stay, unlinked) and the link from any refund of it. The out side of a
 * transfer is refused. Returns the row it deleted, or null with $error.
 */
function expenseDelete($expense_id, &$error = '') {
    global $mysqli;
    $error = '';
    $row = expenseRow($expense_id);
    if (!$row) {
        $error = 'That expense no longer exists';
        return null;
    }
    if ($row['transfer_id']) {
        $error = 'That is the out side of a transfer between accounts - delete the transfer instead';
        return null;
    }
    $expense_id = intval($row['expense_id']);
    expenseReceiptDeleteFile($row['expense_receipt']);
    mysqli_query($mysqli, "UPDATE product_stock SET stock_expense_id = NULL WHERE stock_expense_id = $expense_id");
    mysqli_query($mysqli, "UPDATE expenses SET expense_refund_of_id = NULL WHERE expense_refund_of_id = $expense_id");
    mysqli_query($mysqli, "DELETE FROM expenses WHERE expense_id = $expense_id");
    return $row;
}

/** The receipt's place on disk, if it is there - '' otherwise. */
function expenseReceiptPath($file_name) {
    $file_name = basename((string) $file_name);
    if ($file_name === '' || $file_name === 'index.php' || $file_name[0] === '.') {
        return '';
    }
    $base = realpath(dirname(__DIR__) . '/uploads/expenses');
    $path = realpath(dirname(__DIR__) . "/uploads/expenses/$file_name");
    if ($base === false || $path === false || strpos($path, $base . DIRECTORY_SEPARATOR) !== 0 || !is_file($path)) {
        return '';
    }
    return $path;
}

/** What a browser can show of the receipt in a page: 'image', 'pdf' or ''. */
function expenseReceiptKind($file_name) {
    $ext = strtolower(pathinfo((string) $file_name, PATHINFO_EXTENSION));
    if ($ext === 'pdf') {
        return 'pdf';
    }
    return in_array($ext, ['jpg', 'jpeg', 'gif', 'png', 'webp'], true) ? 'image' : '';
}

/** The name a receipt downloads as - "2026-10-14 Pax8 - Software - 1234.pdf". Raw. */
function expenseReceiptName(array $row) {
    $ext = strtolower(pathinfo((string) $row['expense_receipt'], PATHINFO_EXTENSION));
    $name = implode(' - ', array_filter([$row['expense_date'] . ' ' . ($row['vendor_name'] ?? ''), $row['category_name'] ?? '', $row['expense_id']]));
    $name = trim(preg_replace('/[^\p{L}\p{N} ._()&-]+/u', '', $name));
    return ($name !== '' ? $name : 'Receipt') . ($ext !== '' ? ".$ext" : '');
}

/** Remove a receipt file from uploads/expenses/. */
function expenseReceiptDeleteFile($file_name) {
    $path = expenseReceiptPath($file_name);
    if ($path !== '') {
        @unlink($path);
    }
}

/**
 * Attach an uploaded receipt to an expense, replacing the one it had.
 * Returns the stored name, or '' with $error. A missing upload is not an
 * error - $error stays empty.
 */
function expenseReceiptStore($expense_id, $file, &$error = '') {
    global $mysqli;
    $error = '';
    $expense_id = intval($expense_id);
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE || empty($file['name'])) {
        return '';
    }
    $new_name = checkFileUpload($file, expenseReceiptTypes());
    if (!$new_name) {
        $error = 'The receipt was not saved - it has to be a photo (JPG, PNG, GIF or WebP) or a PDF';
        return '';
    }
    $dir = dirname(__DIR__) . '/uploads/expenses';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    if (!move_uploaded_file($file['tmp_name'], "$dir/$new_name")) {
        $error = 'The receipt could not be saved';
        return '';
    }
    $old = getFieldById('expenses', $expense_id, 'expense_receipt');
    mysqli_query($mysqli, "UPDATE expenses SET expense_receipt = '" . escapeSql($new_name) . "' WHERE expense_id = $expense_id");
    if ($old && $old !== $new_name) {
        expenseReceiptDeleteFile($old);
    }
    return $new_name;
}

/** Take the receipt off an expense, and its file. */
function expenseReceiptRemove($expense_id) {
    global $mysqli;
    $expense_id = intval($expense_id);
    $old = getFieldById('expenses', $expense_id, 'expense_receipt');
    mysqli_query($mysqli, "UPDATE expenses SET expense_receipt = NULL WHERE expense_id = $expense_id");
    if ($old) {
        expenseReceiptDeleteFile($old);
    }
}

/**
 * How an expense reads in a log or message - "Pax8 $149.99 on 2026-10-14".
 * Raw.
 */
function expenseLabel(array $row) {
    return trim(($row['vendor_name'] ?? 'Expense') . ' ' . billingMoney($row['expense_amount'], $row['expense_currency_code']) . ' on ' . $row['expense_date']);
}

/* ------------------------------------------------------- Recurring expenses */

/*
 * A recurring expense runs on the same schedule rules as a recurring invoice
 * (functions/billing.php): monthly, quarterly or yearly, on the day of the
 * month it started on (recurring_expense_anchor_day) - a shorter month's last
 * day when that month has fewer. The nightly run records each one due today,
 * or missed by up to RECURRING_EXPENSE_GRACE_DAYS, as an expense dated the
 * day it was due and linked back by expense_recurring_expense_id. One with
 * an end date stops after its last run: paused, with its next date past the
 * end. One further behind than the grace is left for someone to look at.
 */

/** How long a missed run is still recorded by the nightly run - a night or a few the cron didn't run. */
const RECURRING_EXPENSE_GRACE_DAYS = 7;

/** Running recurring expenses, as a WHERE - not paused or ended, not archived, with a vendor. */
function recurringExpenseRunningSql() {
    return "recurring_expense_status = 1 AND recurring_expense_archived_at IS NULL AND recurring_expense_vendor_id > 0";
}

/** What the running recurring expenses come to a month, company-wide. */
function recurringExpenseMonthlyTotal() {
    global $mysqli;
    return floatval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COALESCE(SUM(" . expenseRecurringMonthlySql() . "), 0) FROM recurring_expenses WHERE " . recurringExpenseRunningSql()))[0]);
}

/** Has it ended - an end date its next run is past. */
function recurringExpenseEnded(array $row) {
    return !empty($row['recurring_expense_end_date']) && (string) $row['recurring_expense_next_date'] > (string) $row['recurring_expense_end_date'];
}

/** Where it stands: active, paused or ended. */
function recurringExpenseState(array $row) {
    if (recurringExpenseEnded($row)) {
        return 'ended';
    }
    return intval($row['recurring_expense_status']) === 1 ? 'active' : 'paused';
}

/**
 * One recurring expense with its vendor, category, account and client, and
 * how many expenses it has made and what they came to this year - or null.
 * Scoped to the agent's clients unless $scoped is false.
 */
function recurringExpenseRow($recurring_expense_id, $scoped = true) {
    global $mysqli;
    $id = intval($recurring_expense_id);
    if (!$id) {
        return null;
    }
    $scope = $scoped ? clientScopeSql('recurring_expense_client_id') : '';
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT recurring_expenses.*, vendor_name, vendor_description, vendor_archived_at,
            category_name, category_color, category_icon, category_archived_at, account_name, account_archived_at, account_currency_code,
            client_name, client_abbreviation,
            (SELECT COUNT(*) FROM expenses WHERE expense_recurring_expense_id = $id) AS made_count,
            (SELECT COALESCE(SUM(expense_amount), 0) FROM expenses WHERE expense_recurring_expense_id = $id AND YEAR(expense_date) = YEAR(CURDATE())) AS made_year
        FROM recurring_expenses
        LEFT JOIN vendors ON vendor_id = recurring_expense_vendor_id
        LEFT JOIN categories ON category_id = recurring_expense_category_id
        LEFT JOIN accounts ON account_id = recurring_expense_account_id
        LEFT JOIN clients ON client_id = recurring_expense_client_id
        WHERE recurring_expense_id = $id $scope
        LIMIT 1"));
    return $row ?: null;
}

/** Its run date one period on from $date. */
function recurringExpenseAfter(array $row, $date) {
    return recurringInvoiceStep($date, $row['recurring_expense_frequency'], intval($row['recurring_expense_anchor_day'] ?? 0));
}

/** The next few dates it will be recorded on - none when paused, and none past its end date. */
function recurringExpenseUpcoming(array $row, $count = 3) {
    $dates = [];
    if (recurringExpenseState($row) !== 'active') {
        return $dates;
    }
    $date = (string) $row['recurring_expense_next_date'];
    $end = (string) ($row['recurring_expense_end_date'] ?? '');
    for ($i = 0; $i < $count && $date && ($end === '' || $date <= $end); $i++) {
        $dates[] = $date;
        $date = recurringExpenseAfter($row, $date);
    }
    return $dates;
}

/** Its runs from the next one up to $to (missed ones included) - none when not running. */
function recurringExpenseRunsUntil(array $row, $to) {
    $dates = [];
    if (recurringExpenseState($row) !== 'active') {
        return $dates;
    }
    $date = (string) $row['recurring_expense_next_date'];
    $end = (string) ($row['recurring_expense_end_date'] ?? '');
    for ($guard = 0; $date <= $to && ($end === '' || $date <= $end) && $guard < 400; $guard++) {
        $dates[] = $date;
        $date = recurringExpenseAfter($row, $date);
    }
    return $dates;
}

/** The first run date from its next date on that hasn't passed - the missed runs skipped. */
function recurringExpenseCatchUp(array $row) {
    $date = (string) $row['recurring_expense_next_date'];
    $today = date('Y-m-d');
    for ($guard = 0; $date < $today && $guard < 1200; $guard++) {
        $date = recurringExpenseAfter($row, $date);
    }
    return $date;
}

/** 1st, 2nd, 3rd, 4th ... 31st. */
function recurringExpenseOrdinal($n) {
    $n = intval($n);
    $suffix = ($n % 100 >= 11 && $n % 100 <= 13) ? 'th' : (['th', 'st', 'nd', 'rd'][$n % 10] ?? 'th');
    return $n . $suffix;
}

/** "Every month on the 14th", "Every year on Mar 3", with "until Jun 30, 2027" when it ends. Raw. */
function recurringExpenseScheduleText(array $row) {
    $frequency = (string) $row['recurring_expense_frequency'];
    $next = (string) $row['recurring_expense_next_date'];
    $day = intval($row['recurring_expense_anchor_day'] ?? 0) ?: intval(date('j', strtotime($next)));
    if ($frequency === 'year') {
        $text = 'Every year on ' . date('M', strtotime($next)) . ' ' . $day;
    } else {
        $text = 'Every ' . ($frequency === 'quarter' ? 'quarter' : 'month') . ' on the ' . recurringExpenseOrdinal($day);
    }
    if (!empty($row['recurring_expense_end_date'])) {
        $text .= ', until ' . date('M j, Y', strtotime($row['recurring_expense_end_date']));
    }
    return $text;
}

/** "In 5 days", "Today", "3 days late", "Paused" or "Ended" - for its next run. */
function recurringExpenseNextPill(array $row) {
    $state = recurringExpenseState($row);
    if ($state === 'ended') {
        return '<span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis fw-normal"><i class="fas fa-flag-checkered me-1"></i>Ended</span>';
    }
    if ($state === 'paused') {
        return '<span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis fw-normal"><i class="fas fa-pause me-1"></i>Paused</span>';
    }
    $days = intval(round((strtotime((string) $row['recurring_expense_next_date']) - strtotime(date('Y-m-d'))) / 86400));
    if ($days < 0) {
        $title = -$days <= RECURRING_EXPENSE_GRACE_DAYS ? 'Missed - the next nightly run records it' : 'Too far behind to be recorded by itself - record the missed runs, or move it on';
        return '<span class="badge rounded-pill text-bg-danger fw-normal" title="' . $title . '"><i class="fas fa-exclamation-triangle me-1"></i>' . (-$days) . ' ' . (-$days === 1 ? 'day' : 'days') . ' late</span>';
    }
    if ($days === 0) {
        return '<span class="badge rounded-pill text-bg-info fw-normal">Today</span>';
    }
    return '<span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis fw-normal">In ' . $days . ' ' . ($days === 1 ? 'day' : 'days') . '</span>';
}

/** The pills on the recurring expenses list - ?status=. */
function recurringExpenseShows() {
    $ended = "(recurring_expense_end_date IS NOT NULL AND recurring_expense_next_date > recurring_expense_end_date)";
    return [
        'active' => ['label' => 'Active', 'icon' => 'fa-play', 'where' => 'recurring_expense_status = 1'],
        'soon' => ['label' => 'Next 7 days', 'icon' => 'fa-calendar-day', 'where' => 'recurring_expense_status = 1 AND recurring_expense_next_date BETWEEN CURDATE() AND CURDATE() + INTERVAL 7 DAY',
            'title' => 'Running, and recorded in the next week'],
        'late' => ['label' => 'Late', 'icon' => 'fa-exclamation-triangle', 'where' => 'recurring_expense_status = 1 AND recurring_expense_next_date < CURDATE()',
            'title' => 'Running, but the next date has passed'],
        'paused' => ['label' => 'Paused', 'icon' => 'fa-pause', 'where' => "recurring_expense_status = 0 AND NOT $ended"],
        'ended' => ['label' => 'Ended', 'icon' => 'fa-flag-checkered', 'where' => "recurring_expense_status = 0 AND $ended", 'title' => 'Past their end date - leases and financing that are paid off'],
        'all' => ['label' => 'All', 'where' => ''],
    ];
}

/**
 * The WHERE for the recurring expenses list: q, vendor, category, client,
 * with the agent's client scope, archived ones left out. Reads through
 * the vendor, category, account and client joins.
 */
function recurringExpenseFiltersSql(array $src) {
    $sql = "recurring_expense_archived_at IS NULL " . clientScopeSql('recurring_expense_client_id');
    $q = trim((string) ($src['q'] ?? ''));
    if ($q !== '') {
        $like = escapeSql($q);
        $sql .= " AND (vendor_name LIKE '%$like%' OR client_name LIKE '%$like%' OR category_name LIKE '%$like%' OR account_name LIKE '%$like%'
            OR recurring_expense_description LIKE '%$like%' OR recurring_expense_reference LIKE '%$like%' OR recurring_expense_amount LIKE '%$like%')";
    }
    foreach (['vendor' => 'recurring_expense_vendor_id', 'category' => 'recurring_expense_category_id', 'client' => 'recurring_expense_client_id'] as $key => $column) {
        $id = intval($src[$key] ?? 0);
        if ($id) {
            $sql .= " AND $column = $id";
        }
    }
    return $sql;
}

/**
 * Add a recurring expense, or change one. $in: vendor, amount, frequency,
 * next_date (the first one, for a new one), end_date (optional - the last
 * run is on or before it), category, account, payment_method, client_id,
 * reference and description. The day of the month it runs on follows the
 * next date when that changes. Setting an end date before the next date
 * ends it; moving it past brings an ended one back. Returns the id, or 0
 * with $error.
 */
function recurringExpenseSave(array $in, $recurring_expense_id = 0, &$error = '') {
    global $mysqli, $session_company_currency;
    $error = '';
    $id = intval($recurring_expense_id);
    $existing = $id ? recurringExpenseRow($id) : null;
    if ($id && !$existing) {
        $error = 'That recurring expense no longer exists';
        return 0;
    }

    $vendor = intval($in['vendor'] ?? 0);
    if (!isset(expenseVendors($existing ? $existing['recurring_expense_vendor_id'] : 0)[$vendor])) {
        $error = 'Pick one of your vendors';
        return 0;
    }
    $amount_text = str_replace([',', ' ', '$'], '', trim((string) ($in['amount'] ?? '')));
    if (!is_numeric($amount_text) || round(floatval($amount_text), 2) <= 0 || floatval($amount_text) > 9999999999999.99) {
        $error = 'Enter the amount each time';
        return 0;
    }
    $amount = round(floatval($amount_text), 2);
    $frequency = (string) ($in['frequency'] ?? '');
    if (!isset(recurringInvoiceFrequencies()[$frequency])) {
        $error = 'Pick how often it comes round';
        return 0;
    }
    $valid_date = function ($date) {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && checkdate(intval(substr($date, 5, 2)), intval(substr($date, 8, 2)), intval(substr($date, 0, 4)));
    };
    $next = trim((string) ($in['next_date'] ?? ''));
    if (!$valid_date($next)) {
        $error = $existing ? 'Enter the date of the next one' : 'Enter the date of the first one';
        return 0;
    }
    $end = trim((string) ($in['end_date'] ?? ''));
    if ($end !== '' && !$valid_date($end)) {
        $error = 'Enter the end date as a date, or leave it empty';
        return 0;
    }
    if (!$existing && $end !== '' && $end < $next) {
        $error = 'The end date is before the first one';
        return 0;
    }
    $category = intval($in['category'] ?? 0);
    if (!isset(expenseCategories($existing ? $existing['recurring_expense_category_id'] : 0)[$category])) {
        $error = 'Pick an expense category';
        return 0;
    }
    $account = intval($in['account'] ?? 0);
    $accounts = paymentAccounts($existing ? $existing['recurring_expense_account_id'] : 0);
    if (!isset($accounts[$account])) {
        $error = 'Pick the account it is paid from';
        return 0;
    }
    $client_id = intval($in['client_id'] ?? 0);
    if ($client_id && !mysqli_num_rows(mysqli_query($mysqli, "SELECT client_id FROM clients WHERE client_id = $client_id " . clientScopeSql('client_id')))) {
        $error = "That client isn't one you can file expenses against";
        return 0;
    }
    $method = trim((string) ($in['payment_method'] ?? ''));
    if ($method !== '' && !in_array($method, paymentMethods(), true) && !($existing && $method === (string) $existing['recurring_expense_payment_method'])) {
        $error = 'Pick how it is paid';
        return 0;
    }
    $reference = mb_substr(trim((string) ($in['reference'] ?? '')), 0, 255);
    $description = trim((string) ($in['description'] ?? ''));

    // The day it runs on follows the next date when that is set or moved
    $anchor = (!$existing || $next !== (string) $existing['recurring_expense_next_date']) ? intval(substr($next, 8, 2)) : intval($existing['recurring_expense_anchor_day']);
    $ended = $end !== '' && $next > $end;
    if (!$existing) {
        $status = 1;
    } elseif ($ended) {
        $status = 0;
    } elseif (recurringExpenseState($existing) === 'ended') {
        $status = 1;
    } else {
        $status = intval($existing['recurring_expense_status']);
    }

    $set = "recurring_expense_vendor_id = $vendor, recurring_expense_amount = $amount, recurring_expense_frequency = '$frequency', recurring_expense_anchor_day = $anchor,
        recurring_expense_next_date = '$next', recurring_expense_end_date = " . ($end === '' ? 'NULL' : "'$end'") . ", recurring_expense_status = $status,
        recurring_expense_category_id = $category, recurring_expense_account_id = $account, recurring_expense_client_id = $client_id,
        recurring_expense_payment_method = " . ($method === '' ? 'NULL' : "'" . escapeSql($method) . "'") . ",
        recurring_expense_reference = '" . escapeSql($reference) . "', recurring_expense_description = '" . escapeSql($description) . "'";

    if ($existing) {
        mysqli_query($mysqli, "UPDATE recurring_expenses SET $set WHERE recurring_expense_id = $id");
        return $id;
    }
    $currency = escapeSql((string) ($accounts[$account]['account_currency_code'] ?: $session_company_currency));
    mysqli_query($mysqli, "INSERT INTO recurring_expenses SET $set, recurring_expense_currency_code = '$currency'");
    return mysqli_insert_id($mysqli);
}

/**
 * Record a recurring expense's next run as an expense - the one path, used
 * by the nightly run and Record Now. $run is the run date the caller means
 * (what the nightly run picked, or what the page showed); when the schedule
 * has moved on since, nothing is made, so a run is never recorded twice.
 * The expense is dated the run's date, or today when that is still to come
 * (Record Now ahead of time). The schedule moves one period on from the run,
 * and stops after the last one before the end date. $by_user_id is who
 * pressed it (0 for the nightly run), $by their name for the audit log.
 * Returns the expense id, or 0.
 */
function recurringExpenseRecord($recurring_expense_id, $by_user_id = 0, $run = null, $by = 'Cron') {
    global $mysqli;
    $row = recurringExpenseRow($recurring_expense_id, false);
    if (!$row || $row['recurring_expense_archived_at'] || recurringExpenseEnded($row)) {
        return 0;
    }
    $id = intval($row['recurring_expense_id']);
    $due = (string) $row['recurring_expense_next_date'];
    if ($run !== null && (string) $run !== $due) {
        return 0;
    }
    $today = date('Y-m-d');
    $date = $due > $today ? $today : $due;
    $next = recurringExpenseAfter($row, $due);
    $ends = !empty($row['recurring_expense_end_date']) && $next > (string) $row['recurring_expense_end_date'];

    // Claim the run
    mysqli_query($mysqli, "UPDATE recurring_expenses SET recurring_expense_next_date = '$next', recurring_expense_last_sent = '$date'" . ($ends ? ', recurring_expense_status = 0' : '') . "
        WHERE recurring_expense_id = $id AND recurring_expense_next_date = '" . escapeSql($due) . "'");
    if (mysqli_affected_rows($mysqli) !== 1) {
        return 0;
    }

    $method = (string) $row['recurring_expense_payment_method'];
    mysqli_query($mysqli, "INSERT INTO expenses SET expense_date = '$date', expense_amount = " . floatval($row['recurring_expense_amount']) . ",
        expense_currency_code = '" . escapeSql($row['recurring_expense_currency_code']) . "', expense_vendor_id = " . intval($row['recurring_expense_vendor_id']) . ",
        expense_client_id = " . intval($row['recurring_expense_client_id']) . ", expense_category_id = " . intval($row['recurring_expense_category_id']) . ",
        expense_account_id = " . intval($row['recurring_expense_account_id']) . ", expense_payment_method = " . ($method === '' ? 'NULL' : "'" . escapeSql($method) . "'") . ",
        expense_reference = '" . escapeSql($row['recurring_expense_reference']) . "', expense_description = '" . escapeSql($row['recurring_expense_description']) . "',
        expense_created_by = " . intval($by_user_id) . ", expense_recurring_expense_id = $id");
    $expense_id = mysqli_insert_id($mysqli);

    $label = recurringExpenseLabel($row);
    logAudit("Expense", "Create", escapeSql("$by recorded $label for $due from the recurring expense"), intval($row['recurring_expense_client_id']), $expense_id);
    if ($ends) {
        logAudit("Recurring Expense", "End", escapeSql("$label ended after its last run on $due"), intval($row['recurring_expense_client_id']), $id);
    }
    return $expense_id;
}

/** The expenses a recurring expense has made, newest first. */
function recurringExpenseMade($recurring_expense_id, $limit = 12) {
    global $mysqli;
    $id = intval($recurring_expense_id);
    $limit = max(1, intval($limit));
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT expense_id, expense_date, expense_amount, expense_currency_code, expense_receipt
        FROM expenses WHERE expense_recurring_expense_id = $id ORDER BY expense_date DESC, expense_id DESC LIMIT $limit");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[] = $row;
    }
    return $rows;
}

/** Delete a recurring expense - the expenses it made stay, unlinked. Returns the row, or null with $error. */
function recurringExpenseDelete($recurring_expense_id, &$error = '') {
    global $mysqli;
    $error = '';
    $row = recurringExpenseRow($recurring_expense_id);
    if (!$row) {
        $error = 'That recurring expense no longer exists';
        return null;
    }
    $id = intval($row['recurring_expense_id']);
    mysqli_query($mysqli, "UPDATE expenses SET expense_recurring_expense_id = NULL WHERE expense_recurring_expense_id = $id");
    mysqli_query($mysqli, "DELETE FROM recurring_expenses WHERE recurring_expense_id = $id");
    return $row;
}

/**
 * How a recurring expense reads in a log or message - "Three Rivers
 * Colocation $495.00 monthly - Colocation, power and transit". Raw.
 */
function recurringExpenseLabel(array $row) {
    $description = trim((string) ($row['recurring_expense_description'] ?? ''));
    return trim(($row['vendor_name'] ?? 'Recurring expense') . ' ' . billingMoney($row['recurring_expense_amount'], $row['recurring_expense_currency_code']) . ' '
        . strtolower(recurringInvoiceFrequencies()[$row['recurring_expense_frequency']] ?? 'monthly')
        . ($description !== '' ? ' - ' . mb_strimwidth($description, 0, 60, '...') : ''));
}
