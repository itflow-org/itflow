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
 * method, show, dtf and dtt. Starts with the vendor-expenses-only rule and
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
 * What a recurring expense costs a month, as SQL - yearly ones count as a
 * twelfth. Use it in sums over recurring_expenses.
 */
function expenseRecurringMonthlySql() {
    return "CASE recurring_expense_frequency WHEN 2 THEN recurring_expense_amount / 12 ELSE recurring_expense_amount END";
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
        FROM recurring_expenses WHERE recurring_expense_status = 1 AND recurring_expense_archived_at IS NULL " . clientScopeSql('recurring_expense_client_id')));
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
