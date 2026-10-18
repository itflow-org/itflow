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
            category_name, category_color, category_icon, category_archived_at, accounts.account_name, accounts.account_archived_at, accounts.account_currency_code,
            client_name, client_abbreviation, creator.user_name AS created_by_name, transfer_id, taxes.tax_name, taxes.tax_percent,
            recurring_expense_frequency, recurring_expense_amount, recurring_expense_currency_code,
            invoice_prefix, invoice_number, invoice_status, item_subtotal AS billed_subtotal,
            refunded.expense_date AS refund_of_date, refunded.expense_amount AS refund_of_amount, refunded.expense_description AS refund_of_description,
            payer.user_name AS paid_by_name, paid_back.reimbursement_date, paid_back_account.account_name AS reimbursed_account_name,
            paying_out.reimbursement_id AS reimbursement_out_id, (SELECT u.user_name FROM users AS u WHERE u.user_id = paying_out.reimbursement_user_id) AS reimbursed_to_name,
            (SELECT COALESCE(SUM(-r.expense_amount), 0) FROM expenses AS r WHERE r.expense_refund_of_id = expenses.expense_id) AS refunded_amount,
            (SELECT COUNT(*) FROM expenses AS r WHERE r.expense_refund_of_id = expenses.expense_id) AS refund_count
        FROM expenses
        LEFT JOIN vendors ON vendor_id = expenses.expense_vendor_id
        LEFT JOIN categories ON category_id = expenses.expense_category_id
        LEFT JOIN accounts ON accounts.account_id = expenses.expense_account_id
        LEFT JOIN clients ON client_id = expenses.expense_client_id
        LEFT JOIN taxes ON taxes.tax_id = expenses.expense_tax_id
        LEFT JOIN users AS creator ON creator.user_id = expenses.expense_created_by
        LEFT JOIN users AS payer ON payer.user_id = expenses.expense_paid_by
        LEFT JOIN transfers ON transfer_expense_id = expenses.expense_id
        LEFT JOIN expenses AS refunded ON refunded.expense_id = expenses.expense_refund_of_id
        LEFT JOIN recurring_expenses ON recurring_expense_id = expenses.expense_recurring_expense_id
        LEFT JOIN invoices ON invoice_id = expenses.expense_invoice_id
        LEFT JOIN invoice_items ON item_id = expenses.expense_invoice_item_id
        LEFT JOIN reimbursements AS paid_back ON paid_back.reimbursement_id = expenses.expense_reimbursement_id
        LEFT JOIN accounts AS paid_back_account ON paid_back_account.account_id = paid_back.reimbursement_account_id
        LEFT JOIN reimbursements AS paying_out ON paying_out.reimbursement_expense_id = expenses.expense_id
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
        'to_bill' => ['label' => 'To bill', 'icon' => 'fa-file-invoice-dollar', 'where' => expenseUnbilledSql(), 'title' => 'Billable to a client, and on no invoice yet'],
        'billed' => ['label' => 'Billed', 'icon' => 'fa-check-circle', 'where' => 'expense_invoice_id IS NOT NULL', 'title' => 'Billed on to a client on an invoice'],
        'to_reimburse' => ['label' => 'To reimburse', 'icon' => 'fa-hand-holding-usd', 'where' => expenseToReimburseSql(), 'title' => 'Paid personally by an agent, not paid back yet'],
        'refunds' => ['label' => 'Refunds', 'icon' => 'fa-undo-alt', 'where' => 'expense_amount < 0', 'title' => 'Money that came back - refunds and credits'],
    ];
}

/**
 * The WHERE for a set of filters, as the list and the export take them
 * from the query string or the form: q, vendor, category, account, client,
 * method, paid_by, recurring_expense_id, show, dtf and dtt. Starts with the vendor-expenses-only rule and
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
    $paid_by = intval($src['paid_by'] ?? 0);
    if ($paid_by) {
        $sql .= " AND expense_paid_by = $paid_by";
        $summary['Paid personally by'] = (string) getFieldById('users', $paid_by, 'user_name');
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
 * The last expense with each vendor - its category, account, payment
 * method and tax, by vendor id. New Expense fills these in when a vendor is
 * picked.
 */
function expenseVendorDefaults() {
    global $mysqli;
    $defaults = [];
    $sql = mysqli_query($mysqli, "SELECT e.expense_vendor_id, e.expense_category_id, e.expense_account_id, e.expense_payment_method, e.expense_tax_id
        FROM expenses AS e
        JOIN (SELECT expense_vendor_id, MAX(expense_id) AS last_id FROM expenses WHERE expense_vendor_id > 0 AND expense_amount > 0 GROUP BY expense_vendor_id) AS last
            ON last.last_id = e.expense_id");
    while ($row = mysqli_fetch_assoc($sql)) {
        $defaults[intval($row['expense_vendor_id'])] = [
            'category' => intval($row['expense_category_id']),
            'account' => intval($row['expense_account_id']),
            'method' => (string) $row['expense_payment_method'],
            'tax' => intval($row['expense_tax_id']),
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

/** Expense categories for a picker - not archived, plus $keep_id (categoryPicker()). */
function expenseCategories($keep_id = 0) {
    return categoryPicker('Expense', [$keep_id]);
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
/**
 * The tax included in what an expense cost, from a form - [rate id, amount],
 * or null with $error. tax_id is one of the taxes (0 for none, or a tax with
 * no rate of ours); tax_amount is worked out from the rate when left empty.
 * The tax takes the amount's sign, so money back gives its tax back too.
 * Without either field, $keep stands - unless the amount changed and the tax
 * came from the rate, when it is worked out again.
 */
function expenseTaxFromInput(array $in, $amount, array $keep = [0, 0.0], &$error = '') {
    $amount = round(floatval($amount), 2);
    if (!array_key_exists('tax_id', $in) && !array_key_exists('tax_amount', $in)) {
        [$tax_id, $tax_amount] = $keep;
        $tax_id = intval($tax_id);
        $tax_amount = round(floatval($tax_amount), 2);
        if ($tax_id && taxRow($tax_id) && abs($tax_amount) >= abs($amount)) {
            $tax_amount = taxIncluded(abs($amount), $tax_id) * ($amount < 0 ? -1 : 1);
        }
        return [$tax_id, $tax_amount];
    }
    $tax_id = intval($in['tax_id'] ?? 0);
    if ($tax_id && !taxRow($tax_id)) {
        $error = 'Pick one of your taxes';
        return null;
    }
    $text = str_replace([',', ' ', '$'], '', trim((string) ($in['tax_amount'] ?? '')));
    if ($text === '') {
        $tax_amount = $tax_id ? taxIncluded(abs($amount), $tax_id) : 0.0;
    } elseif (!is_numeric($text)) {
        $error = 'The tax is an amount, such as 12.50';
        return null;
    } else {
        $tax_amount = round(abs(floatval($text)), 2);
    }
    if ($tax_amount >= abs($amount)) {
        $error = 'The tax is part of what it cost, so less than the amount';
        return null;
    }
    return [$tax_id, $amount < 0 ? -$tax_amount : $tax_amount];
}

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
    if ($existing && $existing['reimbursement_out_id']) {
        $error = 'That is money paid back to ' . $existing['reimbursed_to_name'] . ' - undo the reimbursement to change it';
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
    // From one of our accounts, or paid personally by an agent - on no account
    // until they are paid back. Once paid back, who paid stays as it was.
    $personal = (string) ($in['account'] ?? '') === 'personal';
    $account = $personal ? 0 : intval($in['account'] ?? 0);
    $accounts = paymentAccounts($existing ? $existing['expense_account_id'] : 0);
    $paid_by = $personal ? intval($in['paid_by'] ?? 0) : 0;
    if ($existing && $existing['expense_reimbursement_id'] && $paid_by !== intval($existing['expense_paid_by'])) {
        $error = 'It was paid back to ' . $existing['paid_by_name'] . ' - undo that reimbursement before changing who paid it';
        return 0;
    }
    if ($personal) {
        if ($amount < 0) {
            $error = 'Money back goes to one of your accounts - pick which';
            return 0;
        }
        if (!isset(expensePayers($existing ? $existing['expense_paid_by'] : 0)[$paid_by])) {
            $error = 'Pick who paid it';
            return 0;
        }
    } elseif (!isset($accounts[$account])) {
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

    // The tax included in it - money back starts on the rate of what it refunds
    $tax_keep = $existing ? [$existing['expense_tax_id'], $existing['expense_tax_amount']] : ($refund_of ? [$original['expense_tax_id'], -taxIncluded(abs($amount), $original['expense_tax_id'])] : [0, 0.0]);
    $tax = expenseTaxFromInput($in, $amount, $tax_keep, $error);
    if ($tax === null) {
        return 0;
    }
    [$tax_id, $tax_amount] = $tax;

    // Billed on to the client - only an expense for a client, not a refund. One
    // already on an invoice stays billed, to that client, until it comes off it.
    $billed = $existing && !empty($existing['expense_invoice_id']);
    if ($billed && $client_id !== intval($existing['expense_client_id'])) {
        $error = 'It is billed on an invoice for ' . $existing['client_name'] . ' - take it off that invoice before moving it to another client';
        return 0;
    }
    $billable = $billed ? 1 : intval(!empty($in['billable']) && $client_id > 0 && $amount > 0);
    $markup_text = str_replace(['%', ' '], '', trim((string) ($in['markup'] ?? '')));
    $markup = $markup_text === '' ? ($existing ? floatval($existing['expense_markup']) : expenseMarkupDefault()) : floatval($markup_text);
    if ($billable && ($markup_text !== '' && !is_numeric($markup_text) || $markup < 0 || $markup > 1000)) {
        $error = 'The markup is a percent, from 0 to 1000';
        return 0;
    }
    $markup = round(max(0, min(1000, $markup)), 2);

    $set = "expense_date = '$date', expense_amount = $amount, expense_tax_id = $tax_id, expense_tax_amount = $tax_amount, expense_vendor_id = $vendor, expense_category_id = $category,
        expense_account_id = $account, expense_paid_by = $paid_by, expense_client_id = $client_id, expense_payment_method = " . ($method === '' ? 'NULL' : "'" . escapeSql($method) . "'") . ",
        expense_reference = '" . escapeSql($reference) . "', expense_description = '" . escapeSql($description) . "',
        expense_billable = $billable, expense_markup = $markup";

    if ($existing) {
        // Cleared on a reconciled statement - its date, amount and account stay
        if (!reconcileCheckChange('expense', $expense_id, $date, $amount, $account, $error)) {
            return 0;
        }
        mysqli_query($mysqli, "UPDATE expenses SET $set WHERE expense_id = $expense_id");
        return $expense_id;
    }
    $currency = escapeSql((string) ($personal ? $session_company_currency : ($accounts[$account]['account_currency_code'] ?: $session_company_currency)));
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
    if ($row['reimbursement_out_id']) {
        $error = 'That is money paid back to ' . $row['reimbursed_to_name'] . ' - undo the reimbursement instead';
        return null;
    }
    $expense_id = intval($row['expense_id']);
    if (!reconcileCheckDelete('expense', $expense_id, $error)) {
        return null;
    }
    expenseReceiptDeleteFile($row['expense_receipt']);
    mysqli_query($mysqli, "UPDATE product_stock SET stock_expense_id = NULL WHERE stock_expense_id = $expense_id");
    mysqli_query($mysqli, "UPDATE expenses SET expense_refund_of_id = NULL WHERE expense_refund_of_id = $expense_id");
    // A mileage expense's trips are to pay back for again
    mysqli_query($mysqli, "UPDATE trips SET trip_expense_id = 0 WHERE trip_expense_id = $expense_id");
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
            client_name, client_abbreviation, recurring_invoice_prefix, recurring_invoice_number, recurring_invoice_amount, recurring_invoice_frequency,
            recurring_invoice_status, recurring_invoice_archived_at, tax_name, tax_percent,
            (SELECT COUNT(*) FROM expenses WHERE expense_recurring_expense_id = $id) AS made_count,
            (SELECT COALESCE(SUM(expense_amount), 0) FROM expenses WHERE expense_recurring_expense_id = $id AND YEAR(expense_date) = YEAR(CURDATE())) AS made_year
        FROM recurring_expenses
        LEFT JOIN vendors ON vendor_id = recurring_expense_vendor_id
        LEFT JOIN categories ON category_id = recurring_expense_category_id
        LEFT JOIN accounts ON account_id = recurring_expense_account_id
        LEFT JOIN clients ON client_id = recurring_expense_client_id
        LEFT JOIN recurring_invoices ON recurring_invoice_id = recurring_expense_recurring_invoice_id
        LEFT JOIN taxes ON tax_id = recurring_expense_tax_id
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
    $tax = expenseTaxFromInput($in, $amount, $existing ? [$existing['recurring_expense_tax_id'], $existing['recurring_expense_tax_amount']] : [0, 0.0], $error);
    if ($tax === null) {
        return 0;
    }
    [$tax_id, $tax_amount] = $tax;

    // For a client: billed on as each one is recorded, or covered by one of their recurring invoices
    $billing = $client_id ? (string) ($in['billing'] ?? '') : '';
    $billable = $billing === 'bill' ? 1 : 0;
    $markup_text = str_replace(['%', ' '], '', trim((string) ($in['markup'] ?? '')));
    $markup = $markup_text === '' ? ($existing ? floatval($existing['recurring_expense_markup']) : expenseMarkupDefault()) : floatval($markup_text);
    if ($billable && ($markup_text !== '' && !is_numeric($markup_text) || $markup < 0 || $markup > 1000)) {
        $error = 'The markup is a percent, from 0 to 1000';
        return 0;
    }
    $markup = round(max(0, min(1000, $markup)), 2);
    $covered_by = 0;
    if ($billing === 'covered') {
        $covered_by = intval($in['recurring_invoice_id'] ?? 0);
        if (!isset(recurringExpenseCoverOptions($client_id, $existing ? intval($existing['recurring_expense_recurring_invoice_id']) : 0)[$covered_by])) {
            $error = "Pick the client's recurring invoice that covers it";
            return 0;
        }
    }

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

    $set = "recurring_expense_vendor_id = $vendor, recurring_expense_amount = $amount, recurring_expense_tax_id = $tax_id, recurring_expense_tax_amount = $tax_amount, recurring_expense_frequency = '$frequency', recurring_expense_anchor_day = $anchor,
        recurring_expense_next_date = '$next', recurring_expense_end_date = " . ($end === '' ? 'NULL' : "'$end'") . ", recurring_expense_status = $status,
        recurring_expense_category_id = $category, recurring_expense_account_id = $account, recurring_expense_client_id = $client_id,
        recurring_expense_payment_method = " . ($method === '' ? 'NULL' : "'" . escapeSql($method) . "'") . ",
        recurring_expense_reference = '" . escapeSql($reference) . "', recurring_expense_description = '" . escapeSql($description) . "',
        recurring_expense_billable = $billable, recurring_expense_markup = $markup, recurring_expense_recurring_invoice_id = " . ($covered_by ?: 'NULL');

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
        expense_tax_id = " . intval($row['recurring_expense_tax_id']) . ", expense_tax_amount = " . floatval($row['recurring_expense_tax_amount']) . ",
        expense_currency_code = '" . escapeSql($row['recurring_expense_currency_code']) . "', expense_vendor_id = " . intval($row['recurring_expense_vendor_id']) . ",
        expense_client_id = " . intval($row['recurring_expense_client_id']) . ", expense_category_id = " . intval($row['recurring_expense_category_id']) . ",
        expense_account_id = " . intval($row['recurring_expense_account_id']) . ", expense_payment_method = " . ($method === '' ? 'NULL' : "'" . escapeSql($method) . "'") . ",
        expense_reference = '" . escapeSql($row['recurring_expense_reference']) . "', expense_description = '" . escapeSql($row['recurring_expense_description']) . "',
        expense_created_by = " . intval($by_user_id) . ", expense_recurring_expense_id = $id,
        expense_billable = " . (intval($row['recurring_expense_billable']) && intval($row['recurring_expense_client_id']) ? 1 : 0) . ", expense_markup = " . floatval($row['recurring_expense_markup']));
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

/* ------------------------------------------------- Billing expenses on */

/*
 * An expense for a client can be billed on to them: billable, with a markup
 * on its cost (what it came to, less anything refunded). Until it is on an
 * invoice it waits in Unbilled Time and the invoice's Expenses card; once
 * billed it points at the invoice and line (expense_invoice_id,
 * expense_invoice_item_id). Taking the line off - or deleting the invoice -
 * makes it to bill again. A client's recurring expense can be billed on as
 * each one is recorded, or be covered by one of their recurring invoices,
 * which then shows its cost against what it bills.
 */

/** The markup a billable expense starts with, as a percent - Admin > Settings > Invoice. */
function expenseMarkupDefault() {
    global $mysqli;
    static $markup = null;
    if ($markup !== null) {
        return $markup;
    }
    try {
        $markup = floatval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT config_expense_markup FROM settings WHERE company_id = 1"))[0] ?? 0);
    } catch (mysqli_sql_exception $e) {
        $markup = 0.0;
    }
    return $markup;
}

/** What a cost bills at with its markup, to the cent. */
function expenseBillPrice($cost, $markup) {
    return round(floatval($cost) * (1 + floatval($markup) / 100), 2);
}

/** The SQL that makes an expense to bill: billable, for a client, with a vendor and an amount, on no invoice yet. */
function expenseUnbilledSql() {
    return "expense_billable = 1 AND expense_invoice_id IS NULL AND expense_client_id > 0 AND expense_vendor_id > 0 AND expense_amount > 0 AND expense_archived_at IS NULL";
}

/**
 * Expenses to bill - one client's ($client_id) or every client the agent
 * can open (0), optionally only $ids - oldest first. Each with its vendor,
 * category and client, its cost (less refunds), the price its markup makes
 * of that, and the days since it was paid. Fully refunded ones are left out.
 */
function expenseUnbilled($client_id = 0, array $ids = []) {
    global $mysqli;
    $client_id = intval($client_id);
    $ids = array_filter(array_map('intval', $ids));
    $where = expenseUnbilledSql() . ($client_id ? " AND expense_client_id = $client_id" : '') . ($ids ? ' AND expense_id IN (' . implode(',', $ids) . ')' : '')
        . ' ' . clientScopeSql('expense_client_id');
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT expenses.*, vendor_name, category_name, client_name, client_abbreviation,
            (SELECT COALESCE(SUM(-r.expense_amount), 0) FROM expenses AS r WHERE r.expense_refund_of_id = expenses.expense_id) AS refunded,
            DATEDIFF(CURDATE(), expense_date) AS waiting
        FROM expenses
        JOIN clients ON client_id = expense_client_id
        LEFT JOIN vendors ON vendor_id = expense_vendor_id
        LEFT JOIN categories ON category_id = expense_category_id
        WHERE $where
        ORDER BY client_name ASC, expense_date ASC, expense_id ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $row['cost'] = round(floatval($row['expense_amount']) - floatval($row['refunded']), 2);
        if ($row['cost'] <= 0) {
            continue;
        }
        $row['price'] = expenseBillPrice($row['cost'], $row['expense_markup']);
        $row['waiting'] = max(0, intval($row['waiting']));
        $rows[intval($row['expense_id'])] = $row;
    }
    return $rows;
}

/** The same, by client - each with its expenses, their cost and price, and how long the oldest has waited. */
function expenseUnbilledByClient($client_id = 0) {
    $clients = [];
    foreach (expenseUnbilled($client_id) as $id => $expense) {
        $cid = intval($expense['expense_client_id']);
        if (!isset($clients[$cid])) {
            $clients[$cid] = ['client_id' => $cid, 'client_name' => $expense['client_name'], 'client_abbreviation' => $expense['client_abbreviation'],
                'expenses' => [], 'cost' => 0.0, 'value' => 0.0, 'oldest' => 0];
        }
        $clients[$cid]['expenses'][$id] = $expense;
        $clients[$cid]['cost'] += $expense['cost'];
        $clients[$cid]['value'] += $expense['price'];
        $clients[$cid]['oldest'] = max($clients[$cid]['oldest'], $expense['waiting']);
    }
    return $clients;
}

/** How many expenses are waiting to be billed, and what they come to - every client the agent can open, or one - for the menu, the dashboard and the client's overview. */
function expenseUnbilledCount($client_id = 0) {
    global $mysqli;
    $client_id = intval($client_id);
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*), COALESCE(SUM(ROUND((expense_amount - refunded) * (1 + expense_markup / 100), 2)), 0)
        FROM (SELECT expense_amount, expense_markup,
                (SELECT COALESCE(SUM(-r.expense_amount), 0) FROM expenses AS r WHERE r.expense_refund_of_id = expenses.expense_id) AS refunded
            FROM expenses
            WHERE " . expenseUnbilledSql() . ($client_id ? " AND expense_client_id = $client_id" : '') . ' ' . clientScopeSql('expense_client_id') . ") AS waiting
        WHERE expense_amount - refunded > 0"));
    return ['count' => intval($row[0]), 'value' => round(floatval($row[1]), 2)];
}

/** The invoice line an expense goes on as - [name, description]. Raw. */
function expenseBillLine(array $expense) {
    $lines = preg_split('/\R/', trim((string) $expense['expense_description']));
    $name = trim((string) array_shift($lines));
    if ($name === '') {
        $name = trim((string) ($expense['category_name'] ?? '')) ?: 'Expense';
    }
    $description = implode(' - ', array_filter([
        $expense['vendor_name'] ?? '',
        date('m/d/Y', strtotime((string) $expense['expense_date'])),
        trim((string) $expense['expense_reference']) !== '' ? '#' . trim((string) $expense['expense_reference']) : '',
    ]));
    $rest = trim(implode("\n", $lines));
    return [mb_substr($name, 0, 200), $rest !== '' ? "$rest\n$description" : $description];
}

/**
 * Bill expenses on an invoice, a line each. $prices is expense id => price
 * (null: the cost with its markup). Only the client's expenses still to
 * bill are taken, and each is claimed first, so two agents can't bill one
 * twice. $opts:
 *   invoice_id   one of the client's invoices whose lines can change, or 0 for a new draft
 *   date, category_id, scope   for a new draft
 *   tax_id       the tax on the lines
 *   by           who, for the history and audit log
 * Returns [invoice_id, number, new, expenses (count), amount, cost, lines], or null with $error.
 */
function expenseBillInvoice($client_id, array $prices, array $opts, &$error = '') {
    global $mysqli;
    $error = '';
    $client_id = intval($client_id);
    $by = (string) ($opts['by'] ?? 'ITFlow');
    $expenses = $prices ? expenseUnbilled($client_id, array_keys($prices)) : [];
    if (!$client_id || !$expenses) {
        $error = 'Nothing to bill - those expenses are already on an invoice, or no longer billable.';
        return null;
    }

    $bill = [];
    foreach ($expenses as $id => $expense) {
        $value = $prices[$id] ?? null;
        $price = ($value === null || trim((string) $value) === '') ? $expense['price'] : billingNumberInput($value);
        if ($price === null || $price < 0 || $price >= 1e9) {
            $error = 'The price for ' . ($expense['vendor_name'] ?: 'an expense') . ' on ' . $expense['expense_date'] . ' has to be a number, zero or more.';
            return null;
        }
        $bill[$id] = round($price, 2);
    }
    $tax_id = taxChoice($opts['tax_id'] ?? 0, $client_id, 'expense');
    if ($tax_id && !taxRow($tax_id)) {
        $error = 'That tax no longer exists.';
        return null;
    }

    $invoice_id = intval($opts['invoice_id'] ?? 0);
    $new = false;
    if ($invoice_id) {
        $invoice = billingRow('invoice', $invoice_id);
        if (!$invoice || intval($invoice['client_id']) !== $client_id || !billingCanEditItems('invoice', $invoice)) {
            $error = "That invoice isn't one of the client's you can still add to.";
            return null;
        }
    } else {
        $invoice_id = billingInvoiceCreate($client_id, $opts['date'] ?? '', $opts['category_id'] ?? 0, $opts['scope'] ?? '', $by);
        if (!$invoice_id) {
            $error = "The invoice couldn't be made.";
            return null;
        }
        $new = true;
    }
    $number = billingNumber('invoice', billingRow('invoice', $invoice_id, false));

    // Claim them - only those still to bill go on
    $in = implode(',', array_keys($bill));
    mysqli_query($mysqli, "UPDATE expenses SET expense_invoice_id = $invoice_id WHERE expense_id IN ($in) AND expense_client_id = $client_id AND " . expenseUnbilledSql());
    $claimed = [];
    $sql = mysqli_query($mysqli, "SELECT expense_id FROM expenses WHERE expense_invoice_id = $invoice_id AND expense_id IN ($in) AND expense_invoice_item_id IS NULL");
    while ($row = mysqli_fetch_row($sql)) {
        $claimed[intval($row[0])] = true;
    }
    if (!$claimed) {
        if ($new) {
            mysqli_query($mysqli, "DELETE FROM history WHERE history_invoice_id = $invoice_id");
            mysqli_query($mysqli, "DELETE FROM invoices WHERE invoice_id = $invoice_id");
        }
        $error = 'Nothing to bill - someone else billed those expenses just now.';
        return null;
    }

    $lines = 0;
    $amount = 0.0;
    $cost = 0.0;
    foreach ($bill as $id => $price) {
        if (!isset($claimed[$id])) {
            continue;
        }
        $expense = $expenses[$id];
        [$name, $description] = expenseBillLine($expense);
        $item_id = billingItemSave('invoice', $invoice_id, ['name' => $name, 'description' => $description, 'qty' => 1, 'price' => $price, 'tax_id' => $tax_id], 0, $line_error);
        if (!$item_id) {
            mysqli_query($mysqli, "UPDATE expenses SET expense_invoice_id = NULL WHERE expense_id = $id AND expense_invoice_id = $invoice_id");
            continue;
        }
        mysqli_query($mysqli, "UPDATE expenses SET expense_invoice_item_id = " . intval($item_id) . " WHERE expense_id = $id");
        $lines++;
        $amount += $price;
        $cost += $expense['cost'];
        logAudit('Expense', 'Bill', escapeSql("$by billed " . expenseLabel($expense) . " on invoice $number at " . billingMoney($price, $expense['expense_currency_code'])), $client_id, $id);
    }
    billingRecalc('invoice', $invoice_id);

    $invoice = billingRow('invoice', $invoice_id, false);
    $currency = (string) $invoice['invoice_currency_code'];
    $what = $lines === 1 ? '1 expense' : "$lines expenses";
    billingLog('invoice', $invoice_id, $invoice['invoice_status'], ucfirst($what) . " billed by $by - " . billingMoney($amount, $currency) . ' on ' . billingMoney($cost, $currency) . ' of cost');
    logAudit('Invoice', $new ? 'Create' : 'Edit', escapeSql("$by billed $what on invoice $number - " . billingMoney($amount, $currency)), $client_id, $invoice_id);
    if ($new) {
        triggerCustomAction('invoice_create', $invoice_id);
    }

    return ['invoice_id' => $invoice_id, 'number' => $number, 'new' => $new, 'expenses' => $lines, 'amount' => round($amount, 2), 'cost' => round($cost, 2), 'lines' => $lines];
}

/**
 * Can an expense come off an invoice in this status: while its lines can
 * change, or once it is cancelled - not once it is paid or non-billable.
 */
function expenseCanUnbill($invoice_status) {
    return $invoice_status !== null && lookupUserPermission('module_sales') >= 2 && !in_array($invoice_status, ['Paid', 'Non-Billable'], true);
}

/**
 * Take an expense off the invoice it was billed on - its line comes off,
 * and it is to bill again. On a cancelled invoice the line stays, as the
 * invoice was, and only the link goes. Refused once the invoice is paid or
 * non-billable. The invoice number it came off, or '' with $error.
 */
function expenseUnbill($expense_id, &$error = '') {
    global $mysqli;
    $error = '';
    $row = expenseRow($expense_id);
    if (!$row || !$row['expense_invoice_id']) {
        $error = "That expense isn't on an invoice";
        return '';
    }
    $invoice = billingRow('invoice', intval($row['expense_invoice_id']), false);
    $id = intval($row['expense_id']);
    if (!$invoice) {
        mysqli_query($mysqli, "UPDATE expenses SET expense_invoice_id = NULL, expense_invoice_item_id = NULL WHERE expense_id = $id");
        return 'an invoice since deleted';
    }
    $number = billingNumber('invoice', $invoice);
    if (!expenseCanUnbill($invoice['invoice_status'])) {
        $error = "$number is " . strtolower($invoice['invoice_status']) . " - its lines can't change any more";
        return '';
    }
    if ($row['expense_invoice_item_id'] && $invoice['invoice_status'] !== 'Cancelled') {
        billingItemDelete('invoice', intval($invoice['invoice_id']), intval($row['expense_invoice_item_id']));
    }
    mysqli_query($mysqli, "UPDATE expenses SET expense_invoice_id = NULL, expense_invoice_item_id = NULL WHERE expense_id = $id");
    return $number;
}

/** The expenses billed on an invoice - each with its cost (less refunds) and what its line bills before tax. */
function expenseOnInvoice($invoice_id) {
    global $mysqli;
    $invoice_id = intval($invoice_id);
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT expenses.*, vendor_name, category_name, item_subtotal,
            (SELECT COALESCE(SUM(-r.expense_amount), 0) FROM expenses AS r WHERE r.expense_refund_of_id = expenses.expense_id) AS refunded
        FROM expenses
        LEFT JOIN vendors ON vendor_id = expense_vendor_id
        LEFT JOIN categories ON category_id = expense_category_id
        LEFT JOIN invoice_items ON item_id = expense_invoice_item_id
        WHERE expense_invoice_id = $invoice_id
        ORDER BY expense_date ASC, expense_id ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $row['cost'] = round(floatval($row['expense_amount']) - floatval($row['refunded']), 2);
        $row['billed'] = $row['item_subtotal'] !== null ? floatval($row['item_subtotal']) : null;
        $rows[] = $row;
    }
    return $rows;
}

/** "18% margin" - what is left of a price after its cost, as a share of the price. */
function expenseMarginText($price, $cost) {
    $price = floatval($price);
    if ($price <= 0) {
        return '';
    }
    return round(($price - floatval($cost)) / $price * 100) . '% margin';
}

/** A client's recurring invoices a recurring expense can be covered by - id => row. */
function recurringExpenseCoverOptions($client_id, $keep_id = 0) {
    global $mysqli;
    $client_id = intval($client_id);
    $keep_id = intval($keep_id);
    $rows = [];
    if (!$client_id) {
        return $rows;
    }
    $sql = mysqli_query($mysqli, "SELECT recurring_invoice_id, recurring_invoice_prefix, recurring_invoice_number, recurring_invoice_scope, recurring_invoice_amount,
            recurring_invoice_frequency, recurring_invoice_currency_code, recurring_invoice_status
        FROM recurring_invoices
        WHERE recurring_invoice_client_id = $client_id AND (recurring_invoice_archived_at IS NULL OR recurring_invoice_id = $keep_id)
        ORDER BY recurring_invoice_status DESC, recurring_invoice_number ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[intval($row['recurring_invoice_id'])] = $row;
    }
    return $rows;
}

/** The recurring expenses a recurring invoice covers - each with what it costs a month, and whether it is running (not paused or ended). */
function recurringExpensesCovering($recurring_invoice_id) {
    global $mysqli;
    $id = intval($recurring_invoice_id);
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT recurring_expenses.*, vendor_name, category_name, category_color, " . expenseRecurringMonthlySql() . " AS monthly
        FROM recurring_expenses
        LEFT JOIN vendors ON vendor_id = recurring_expense_vendor_id
        LEFT JOIN categories ON category_id = recurring_expense_category_id
        WHERE recurring_expense_recurring_invoice_id = $id AND recurring_expense_archived_at IS NULL
        ORDER BY recurring_expense_status DESC, vendor_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $row['monthly'] = floatval($row['monthly']);
        $row['running'] = recurringExpenseState($row) === 'active';
        $rows[] = $row;
    }
    return $rows;
}

/**
 * A recurring invoice against the recurring expenses it covers, a month:
 * revenue (what it bills a month before tax), cost (the running ones), the
 * margin left and as a percent of revenue, and the rows.
 */
function recurringInvoiceCosts($recurring_invoice_id) {
    global $mysqli;
    $id = intval($recurring_invoice_id);
    $invoice = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT recurring_invoice_amount, recurring_invoice_frequency,
            (SELECT COALESCE(SUM(item_tax), 0) FROM recurring_invoice_items WHERE item_recurring_invoice_id = $id) AS tax
        FROM recurring_invoices WHERE recurring_invoice_id = $id"));
    $rows = recurringExpensesCovering($id);
    $revenue = $invoice ? round(recurringInvoiceMonthly(floatval($invoice['recurring_invoice_amount']) - floatval($invoice['tax']), $invoice['recurring_invoice_frequency']), 2) : 0.0;
    $cost = 0.0;
    foreach ($rows as $row) {
        if ($row['running']) {
            $cost += $row['monthly'];
        }
    }
    $cost = round($cost, 2);
    return ['rows' => $rows, 'revenue' => $revenue, 'cost' => $cost, 'margin' => round($revenue - $cost, 2),
        'percent' => $revenue > 0 ? round(($revenue - $cost) / $revenue * 100) : null];
}

/* ------------------------------------------------- Unbilled Time, with expenses */

/**
 * Unbilled Time's clients (billingUnbilledByClient()) with their expenses to
 * bill as well: each gets expenses, expense_cost and expense_value, a client
 * with only expenses waiting is added (no tickets, with their rate and
 * drafts), and the oldest counts the expenses too. Sorted by what it all
 * comes to. One client's ($client_id) or every client the agent can open.
 */
function expenseUnbilledMerge(array $clients, $client_id = 0) {
    global $mysqli;
    foreach ($clients as $id => $c) {
        $clients[$id] += ['expenses' => [], 'expense_cost' => 0.0, 'expense_value' => 0.0];
    }
    $added = [];
    foreach (expenseUnbilledByClient($client_id) as $id => $e) {
        if (!isset($clients[$id])) {
            $client_rate = getFieldById('clients', $id, 'client_rate');
            $clients[$id] = ['client_id' => $id, 'client_name' => $e['client_name'], 'client_abbreviation' => $e['client_abbreviation'],
                'rate' => billingTimeRate($client_rate), 'default_rate' => floatval($client_rate) <= 0, 'tickets' => [], 'seconds' => 0, 'hours' => 0.0,
                'value' => 0.0, 'oldest' => 0, 'no_time' => 0, 'drafts' => []];
            $added[] = intval($id);
        }
        $clients[$id]['expenses'] = $e['expenses'];
        $clients[$id]['expense_cost'] = round($e['cost'], 2);
        $clients[$id]['expense_value'] = round($e['value'], 2);
        $clients[$id]['oldest'] = max($clients[$id]['oldest'], $e['oldest']);
    }
    if ($added) {
        $sql = mysqli_query($mysqli, "SELECT invoice_id, invoice_prefix, invoice_number, invoice_scope, invoice_date, invoice_amount, invoice_client_id FROM invoices
            WHERE invoice_status = 'Draft' AND invoice_archived_at IS NULL AND invoice_client_id IN (" . implode(',', $added) . ")
            ORDER BY invoice_date DESC, invoice_id DESC");
        while ($row = mysqli_fetch_assoc($sql)) {
            $clients[intval($row['invoice_client_id'])]['drafts'][intval($row['invoice_id'])] = $row;
        }
    }
    uasort($clients, function ($a, $b) {
        return ($b['value'] + $b['expense_value']) <=> ($a['value'] + $a['expense_value'])
            ?: (count($b['tickets']) + count($b['expenses'])) <=> (count($a['tickets']) + count($a['expenses']));
    });
    return $clients;
}

/**
 * Bill a client's tickets ($hours: ticket id => hours, null for what the
 * time comes to), expenses ($prices: expense id => price, null for the
 * cost with its markup) and trips ($trip_prices: trip id => price, null for
 * what tripBillPrice() makes it) on one invoice - the time first, then the
 * expenses and trips on the same invoice. Any can be empty. $opts as
 * billingTimeInvoice() takes them (the tax goes on the expense and trip
 * lines too). Returns [invoice_id, number, new, time (billingTimeInvoice's
 * result or null), expenses (expenseBillInvoice's or null), trips
 * (tripBillInvoice's or null), error (what didn't go on)], or null with
 * $error when nothing went on.
 */
function expenseBillWithTime($client_id, array $hours, array $prices, array $opts, &$error = '', array $trip_prices = []) {
    $error = '';
    if (!$hours && !$prices && !$trip_prices) {
        $error = 'Nothing to bill - tick the tickets, expenses or trips to bill.';
        return null;
    }
    $time = null;
    $time_error = '';
    if ($hours) {
        $time = billingTimeInvoice($client_id, $hours, $opts, $time_error);
        if (!$time && !$prices && !$trip_prices) {
            $error = $time_error;
            return null;
        }
    }
    $expenses = null;
    $expense_error = '';
    if ($prices) {
        $expense_opts = $opts;
        if ($time) {
            $expense_opts['invoice_id'] = $time['invoice_id'];
        }
        $expenses = expenseBillInvoice($client_id, $prices, $expense_opts, $expense_error);
    }
    $trips = null;
    $trip_error = '';
    if ($trip_prices) {
        $trip_opts = $opts;
        if ($time || $expenses) {
            $trip_opts['invoice_id'] = ($time ?: $expenses)['invoice_id'];
        }
        $trips = tripBillInvoice($client_id, $trip_prices, $trip_opts, $trip_error);
    }
    if (!$time && !$expenses && !$trips) {
        $error = trim($time_error . ' ' . $expense_error . ' ' . $trip_error);
        return null;
    }
    $first = $time ?: ($expenses ?: $trips);
    return ['invoice_id' => $first['invoice_id'], 'number' => $first['number'], 'new' => !empty($first['new']), 'time' => $time, 'expenses' => $expenses, 'trips' => $trips,
        'error' => trim(($hours && !$time ? $time_error : '') . ' ' . ($prices && !$expenses ? $expense_error : '') . ' ' . ($trip_prices && !$trips ? $trip_error : ''))];
}

/** The client's ticked expenses to bill aren't billable after all - their labels. */
function expenseWriteOff($client_id, array $expense_ids, $by) {
    global $mysqli;
    $expenses = $expense_ids ? expenseUnbilled($client_id, $expense_ids) : [];
    $done = [];
    foreach ($expenses as $expense_id => $expense) {
        mysqli_query($mysqli, "UPDATE expenses SET expense_billable = 0 WHERE expense_id = $expense_id AND expense_invoice_id IS NULL AND expense_billable = 1");
        if (mysqli_affected_rows($mysqli) === 1) {
            logAudit('Expense', 'Edit', escapeSql("$by marked the expense " . expenseLabel($expense) . ' not billable'), intval($client_id), $expense_id);
            $done[] = expenseLabel($expense);
        }
    }
    return $done;
}

/* ------------------------------------------------------- Paid personally */

/*
 * An agent can pay for something themselves - a cable run to the store, a
 * hotel - and be paid back. Such an expense (expense_paid_by) counts as
 * spending on its date like any other, but is on none of our accounts, so
 * no balance moves, until a reimbursement pays the agent back: one payment
 * from an account, on its own date, for one or more of their expenses. That
 * payment is an expense with no vendor (reimbursement_expense_id), the way
 * the out side of a transfer is, so it moves the balance and stays out of
 * the spending totals.
 */

/** The SQL that makes an expense to reimburse: paid personally, not paid back yet. */
function expenseToReimburseSql() {
    return "expense_paid_by > 0 AND expense_reimbursement_id IS NULL AND expense_vendor_id > 0 AND expense_archived_at IS NULL";
}

/** Who can have paid for an expense personally - active agents, and $keep_id - id => name. */
function expensePayers($keep_id = 0) {
    global $mysqli;
    $keep_id = intval($keep_id);
    $payers = [];
    $sql = mysqli_query($mysqli, "SELECT user_id, user_name FROM users WHERE (user_type = 1 AND user_archived_at IS NULL) OR user_id = $keep_id ORDER BY user_name ASC");
    while ($row = mysqli_fetch_row($sql)) {
        $payers[intval($row[0])] = $row[1];
    }
    return $payers;
}

/**
 * Expenses waiting to be paid back - one agent's ($user_id) or everyone's,
 * optionally only $ids, in the agent's client scope - by agent: user_id =>
 * [user_id, user_name, expenses (id => row), total, count, oldest (days)].
 */
function expenseToReimburse($user_id = 0, array $ids = []) {
    global $mysqli;
    $user_id = intval($user_id);
    $ids = array_filter(array_map('intval', $ids));
    $sql = mysqli_query($mysqli, "SELECT expenses.*, vendor_name, category_name, client_name, user_name, DATEDIFF(CURDATE(), expense_date) AS waiting
        FROM expenses
        JOIN users ON user_id = expense_paid_by
        LEFT JOIN vendors ON vendor_id = expense_vendor_id
        LEFT JOIN categories ON category_id = expense_category_id
        LEFT JOIN clients ON client_id = expense_client_id
        WHERE " . expenseToReimburseSql() . ($user_id ? " AND expense_paid_by = $user_id" : '') . ($ids ? ' AND expense_id IN (' . implode(',', $ids) . ')' : '')
        . ' ' . clientScopeSql('expense_client_id') . "
        ORDER BY user_name ASC, expense_date ASC, expense_id ASC");
    $agents = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $uid = intval($row['expense_paid_by']);
        if (!isset($agents[$uid])) {
            $agents[$uid] = ['user_id' => $uid, 'user_name' => $row['user_name'], 'expenses' => [], 'total' => 0.0, 'count' => 0, 'oldest' => 0];
        }
        $agents[$uid]['expenses'][intval($row['expense_id'])] = $row;
        $agents[$uid]['total'] = round($agents[$uid]['total'] + floatval($row['expense_amount']), 2);
        $agents[$uid]['count']++;
        $agents[$uid]['oldest'] = max($agents[$uid]['oldest'], intval($row['waiting']));
    }
    return $agents;
}

/** How many expenses are waiting to be paid back, and what they come to - for the list and the dashboard. */
function expenseToReimburseCount() {
    global $mysqli;
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*), COALESCE(SUM(expense_amount), 0), COUNT(DISTINCT expense_paid_by) FROM expenses
        WHERE " . expenseToReimburseSql() . ' ' . clientScopeSql('expense_client_id')));
    return ['count' => intval($row[0]), 'total' => round(floatval($row[1]), 2), 'agents' => intval($row[2])];
}

/**
 * Pay an agent back for their ticked expenses, from one of our accounts.
 * $opts: account_id, date, payment_method, reference, by (who, for the log).
 * The expenses are claimed first, so two agents can't pay one back twice.
 * Returns [reimbursement_id, amount, count, user_name, currency], or null
 * with $error.
 */
function expenseReimburse($user_id, array $expense_ids, array $opts, &$error = '') {
    global $mysqli, $session_user_id, $session_company_currency;
    $error = '';
    $user_id = intval($user_id);
    $waiting = expenseToReimburse($user_id, $expense_ids)[$user_id] ?? null;
    if (!$user_id || !$expense_ids || !$waiting) {
        $error = 'Nothing to pay back - those expenses are already paid back, or not paid personally.';
        return null;
    }
    $account = intval($opts['account_id'] ?? 0);
    $accounts = paymentAccounts();
    if (!isset($accounts[$account])) {
        $error = 'Pick the account the money comes from';
        return null;
    }
    $date = (string) ($opts['date'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !checkdate(intval(substr($date, 5, 2)), intval(substr($date, 8, 2)), intval(substr($date, 0, 4)))) {
        $error = 'Enter the date they were paid back';
        return null;
    }
    $method = trim((string) ($opts['payment_method'] ?? ''));
    if ($method !== '' && !in_array($method, paymentMethods(), true)) {
        $error = 'Pick how they were paid back';
        return null;
    }
    $reference = mb_substr(trim((string) ($opts['reference'] ?? '')), 0, 200);
    $by = (string) ($opts['by'] ?? 'ITFlow');
    $currency = (string) ($accounts[$account]['account_currency_code'] ?: $session_company_currency);

    // Claim them, then pay back what was claimed
    mysqli_query($mysqli, "INSERT INTO reimbursements SET reimbursement_user_id = $user_id, reimbursement_date = '$date', reimbursement_amount = 0,
        reimbursement_currency_code = '" . escapeSql($currency) . "', reimbursement_account_id = $account,
        reimbursement_payment_method = " . ($method === '' ? 'NULL' : "'" . escapeSql($method) . "'") . ", reimbursement_reference = '" . escapeSql($reference) . "',
        reimbursement_expense_id = 0, reimbursement_created_by = " . intval($session_user_id));
    $reimbursement_id = mysqli_insert_id($mysqli);
    mysqli_query($mysqli, "UPDATE expenses SET expense_reimbursement_id = $reimbursement_id
        WHERE expense_id IN (" . implode(',', array_keys($waiting['expenses'])) . ") AND expense_paid_by = $user_id AND " . expenseToReimburseSql());
    $claimed = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*), COALESCE(SUM(expense_amount), 0) FROM expenses WHERE expense_reimbursement_id = $reimbursement_id"));
    $count = intval($claimed[0]);
    $amount = round(floatval($claimed[1]), 2);
    if (!$count) {
        mysqli_query($mysqli, "DELETE FROM reimbursements WHERE reimbursement_id = $reimbursement_id");
        $error = 'Nothing to pay back - someone else paid those back just now.';
        return null;
    }

    // The money out of the account - an expense with no vendor, like a transfer's
    $description = "Reimbursement to {$waiting['user_name']} - " . ($count === 1 ? '1 expense' : "$count expenses");
    mysqli_query($mysqli, "INSERT INTO expenses SET expense_date = '$date', expense_amount = $amount, expense_currency_code = '" . escapeSql($currency) . "',
        expense_vendor_id = 0, expense_category_id = 0, expense_account_id = $account, expense_client_id = 0,
        expense_payment_method = " . ($method === '' ? 'NULL' : "'" . escapeSql($method) . "'") . ", expense_reference = '" . escapeSql($reference) . "',
        expense_description = '" . escapeSql($description) . "', expense_created_by = " . intval($session_user_id));
    $cash_id = mysqli_insert_id($mysqli);
    mysqli_query($mysqli, "UPDATE reimbursements SET reimbursement_amount = $amount, reimbursement_expense_id = $cash_id WHERE reimbursement_id = $reimbursement_id");

    logAudit('Expense', 'Reimburse', escapeSql("$by paid {$waiting['user_name']} back " . billingMoney($amount, $currency) . ' for ' . ($count === 1 ? '1 expense' : "$count expenses")
        . " from {$accounts[$account]['account_name']}"), 0, $reimbursement_id);

    return ['reimbursement_id' => $reimbursement_id, 'amount' => $amount, 'count' => $count, 'user_name' => $waiting['user_name'], 'currency' => $currency];
}

/** A reimbursement with who was paid back, the account and who entered it - or null. */
function reimbursementRow($reimbursement_id) {
    global $mysqli;
    $id = intval($reimbursement_id);
    return mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT reimbursements.*, payee.user_name, account_name, creator.user_name AS created_by_name
        FROM reimbursements
        LEFT JOIN users AS payee ON payee.user_id = reimbursement_user_id
        LEFT JOIN users AS creator ON creator.user_id = reimbursement_created_by
        LEFT JOIN accounts ON account_id = reimbursement_account_id
        WHERE reimbursement_id = $id")) ?: null;
}

/** The expenses a reimbursement paid back, oldest first. */
function reimbursementExpenses($reimbursement_id) {
    global $mysqli;
    $id = intval($reimbursement_id);
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT expenses.*, vendor_name, category_name, client_name FROM expenses
        LEFT JOIN vendors ON vendor_id = expense_vendor_id
        LEFT JOIN categories ON category_id = expense_category_id
        LEFT JOIN clients ON client_id = expense_client_id
        WHERE expense_reimbursement_id = $id ORDER BY expense_date ASC, expense_id ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[] = $row;
    }
    return $rows;
}

/**
 * Undo a reimbursement - the money out of the account goes, and its
 * expenses are to reimburse again. Not once that money out has cleared on a
 * reconciled statement. The row it undid, or null with $error.
 */
function reimbursementDelete($reimbursement_id, &$error = '') {
    global $mysqli;
    $error = '';
    $row = reimbursementRow($reimbursement_id);
    if (!$row) {
        $error = 'That reimbursement no longer exists';
        return null;
    }
    if (!reconcileCheckDelete('expense', $row['reimbursement_expense_id'], $error)) {
        return null;
    }
    $id = intval($row['reimbursement_id']);
    mysqli_query($mysqli, "UPDATE expenses SET expense_reimbursement_id = NULL WHERE expense_reimbursement_id = $id");
    mysqli_query($mysqli, "DELETE FROM expenses WHERE expense_id = " . intval($row['reimbursement_expense_id']) . " AND expense_vendor_id = 0");
    mysqli_query($mysqli, "DELETE FROM reimbursements WHERE reimbursement_id = $id");
    return $row;
}
