<?php

/*
 * Tax Package - what an accountant asks for at year end, for one tax year.
 *
 * The page is the summary: income and spending by category, a quarter of the
 * tax year at a time; the sales tax charged, on the payments received and on
 * the invoices sent; each account from the first day of the year to the last;
 * the invoices still unpaid at the end of it; for a US company, the vendors
 * paid enough to need a 1099; the mileage; and the expenses with no receipt.
 * Download package makes a ZIP of this page as a PDF, each of its tables as a
 * CSV, every payment, expense, invoice and trip of the year as spreadsheets,
 * and the expenses' receipts (taxPackageSend()).
 *
 * The year starts in the month picked - January, or where the tax year
 * usually starts elsewhere: April in the UK, New Zealand and India, July in
 * Australia, March in South Africa. Cash basis, as Profit & Loss: income is
 * invoice payments and other income, spending is vendor expenses, and
 * transfers between accounts are neither. The figures are the company's;
 * a client the agent cannot open is not named.
 */

require_once "includes/inc_report.php";
reportEnforce('tax_package');

// The tax year: the month it starts in, and the year it starts in
const TAX_PACKAGE_START = ['United Kingdom' => 4, 'Australia' => 7, 'New Zealand' => 4, 'India' => 4, 'South Africa' => 3];
$start_month = intval($_GET['start'] ?? 0);
if ($start_month < 1 || $start_month > 12) {
    $start_month = TAX_PACKAGE_START[$session_company_country ?? ''] ?? 1;
}

/** "2025", or "Apr 2025 – Mar 2026" for a year that starts after January. */
function taxPackageYear($year, $start_month) {
    if ($start_month === 1) {
        return (string) $year;
    }
    return date('M Y', mktime(0, 0, 0, $start_month, 1, $year)) . ' – ' . date('M Y', mktime(0, 0, 0, $start_month - 1, 1, $year + 1));
}

/** A client's name - or not, when the agent cannot open it. */
function taxPackageClient($client_id, $name) {
    $client_id = intval($client_id);
    if (!$client_id) {
        return '';
    }
    return hasClientAccess($client_id) ? (string) $name : 'A client you cannot open';
}

/**
 * SQL: what was owed on an invoice at the end of $to. An invoice marked Paid that
 * no payment covers was settled some other way, so only what was paid on it
 * after $to was still owed then.
 */
function taxPackageOwedSql($to) {
    $by = "COALESCE((SELECT SUM(payment_amount) FROM payments WHERE payment_invoice_id = invoice_id AND payment_date <= '$to'), 0)";
    $after = "COALESCE((SELECT SUM(payment_amount) FROM payments WHERE payment_invoice_id = invoice_id AND payment_date > '$to'), 0)";
    return "CASE WHEN invoice_status = 'Paid' THEN LEAST(invoice_amount - $by, $after) ELSE invoice_amount - $by END";
}

$this_year = intval(date('Y')) - (intval(date('n')) < $start_month ? 1 : 0);
$first_year = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT MIN(y) FROM (SELECT MIN(YEAR(payment_date)) AS y FROM payments
    UNION ALL SELECT MIN(YEAR(revenue_date)) FROM revenues UNION ALL SELECT MIN(YEAR(expense_date)) FROM expenses) AS years"))[0] ?? 0) ?: $this_year;
// A year that starts after January takes in the start of the next calendar year
$first_year = max(1990, min($this_year, $first_year - ($start_month > 1 ? 1 : 0)));
$years = [];
for ($y = $this_year; $y >= $first_year; $y--) {
    $years[$y] = taxPackageYear($y, $start_month) . ($y === $this_year ? ' (so far)' : '');
}
// The year to file for is the last one that has ended
$year = intval($_GET['year'] ?? ($this_year > $first_year ? $this_year - 1 : $this_year));
if (!isset($years[$year])) {
    $year = $this_year > $first_year ? $this_year - 1 : $this_year;
}

$from = sprintf('%04d-%02d-01', $year, $start_month);
$end = date('Y-m-d', strtotime("$from +1 year -1 day"));
$today = date('Y-m-d');
$to = min($end, $today);
$ended = $end < $today;
$label = taxPackageYear($year, $start_month);
$range = reportRangeText($from, $to);

// The year's months in order, and its quarters
$months = [];
for ($i = 0; $i < 12; $i++) {
    $months[] = ($start_month - 1 + $i) % 12 + 1;
}
$quarters = function (array $by_month) use ($months) {
    $out = [];
    foreach (array_chunk($months, 3) as $q => $three) {
        $out['q' . ($q + 1)] = array_sum(array_map(function ($m) use ($by_month) { return $by_month[$m] ?? 0; }, $three));
    }
    return $out;
};
$quarter_columns = [];
foreach (array_chunk($months, 3) as $q => $three) {
    $quarter_columns['q' . ($q + 1)] = ['label' => 'Q' . ($q + 1), 'type' => 'money',
        'title' => date('M', mktime(0, 0, 0, $three[0], 1)) . ' – ' . date('M', mktime(0, 0, 0, $three[2], 1))];
}

/*
 * ---------- profit and loss ----------
 */

$income = reportIncomeByCategory($from, $to);
$spending = reportExpensesByCategory($from, $to);
$income_categories = reportCategoryRows('Income', $income);
$expense_categories = reportCategoryRows('Expense', $spending);
$income_total = reportGridTotals($income);
$expense_total = reportGridTotals($spending);
$profit = $income_total['total'] - $expense_total['total'];

$pl_line = function ($section, $name, array $by_month, $class = '') use ($quarters) {
    return ['section' => $section, 'name' => $name] + $quarters($by_month) + ['total' => array_sum($by_month), '_class' => $class];
};
$pl_rows = [['_heading' => 'Income']];
foreach ($income_categories as $cat => $category) {
    $row = reportGridRow($income, $cat);
    if (abs($row['total']) >= 0.005) {
        $pl_rows[] = $pl_line('Income', $category['category_name'], $row['months']);
    }
}
$pl_rows[] = $pl_line('Income', 'Total income', $income_total['months'], 'fw-semibold');
$pl_rows[] = ['_heading' => 'Expenses'];
foreach ($expense_categories as $cat => $category) {
    $row = reportGridRow($spending, $cat);
    if (abs($row['total']) >= 0.005) {
        $pl_rows[] = $pl_line('Expenses', $category['category_name'], $row['months']);
    }
}
$pl_rows[] = $pl_line('Expenses', 'Total expenses', $expense_total['months'], 'fw-semibold');
$profit_months = [];
foreach ($income_total['months'] as $m => $amount) {
    $profit_months[$m] = $amount - $expense_total['months'][$m];
}
$pl_totals = $pl_line('Net profit', 'Net profit', $profit_months);
$pl_columns = ['section' => ['label' => 'Section', 'screen' => false], 'name' => ['label' => 'Category']] + $quarter_columns
    + ['total' => ['label' => $label, 'type' => 'money', 'class' => 'fw-semibold']];

/*
 * ---------- sales tax ----------
 */

$tax_paid = taxReportSales($from, $to, 'paid');
$tax_invoiced = taxReportSales($from, $to, 'invoiced');
$tax_purchases = taxReportPurchases($from, $to);
$tax_line = function ($section, $name, $paid, $invoiced, $note = '', $registration = '', $class = '') {
    return ['section' => $section, 'name' => $name, 'note' => $note, 'registration' => $registration, 'paid' => $paid, 'invoiced' => $invoiced, '_class' => $class];
};
$tax_rows = [];
$parts = array_unique(array_merge(array_keys($tax_paid['parts']), array_keys($tax_invoiced['parts'])));
natcasesort($parts);
$numbers = $tax_invoiced['numbers'] + $tax_paid['numbers'];
$charged_paid = array_sum(array_column($tax_paid['parts'], 'total'));
$charged_invoiced = array_sum(array_column($tax_invoiced['parts'], 'total'));
if ($parts) {
    $tax_rows[] = ['_heading' => 'Tax charged'];
    foreach ($parts as $part) {
        $tax_rows[] = $tax_line('Tax charged', $part, $tax_paid['parts'][$part]['total'] ?? 0, $tax_invoiced['parts'][$part]['total'] ?? 0, '', $numbers[$part] ?? '');
    }
    $tax_rows[] = $tax_line('Tax charged', 'Total tax charged', $charged_paid, $charged_invoiced, '', '', 'fw-semibold');
}
$tax_rows[] = ['_heading' => 'Sales, before tax and net of discounts'];
$tax_rows[] = $tax_line('Sales', 'Total sales', array_sum($tax_paid['sales']), array_sum($tax_invoiced['sales']), '', '', 'fw-semibold');
$tax_rows[] = $tax_line('Sales', 'Taxable', array_sum($tax_paid['taxable']), array_sum($tax_invoiced['taxable']), 'Lines with tax on them');
$tax_rows[] = $tax_line('Sales', 'Exempt', array_sum($tax_paid['exempt']), array_sum($tax_invoiced['exempt']), 'No tax, for clients who are tax exempt');
$tax_rows[] = $tax_line('Sales', 'Non-taxable', array_sum($tax_paid['nontaxable']), array_sum($tax_invoiced['nontaxable']), 'No tax, for clients who are not exempt');
$tax_on_purchases = array_sum(array_column($tax_purchases['parts'], 'total'));
if ($tax_purchases['parts']) {
    $tax_rows[] = ['_heading' => 'Tax paid on purchases'];
    foreach ($tax_purchases['parts'] as $part => $amounts) {
        $tax_rows[] = $tax_line('Tax paid', $part, $amounts['total'], $amounts['total']);
    }
    $tax_rows[] = $tax_line('Tax paid', 'Total tax paid', $tax_on_purchases, $tax_on_purchases, '', '', 'fw-semibold');
    $purchased = array_sum($tax_purchases['purchases']);
    $tax_rows[] = $tax_line('Tax paid', 'Purchases it was paid on', $purchased, $purchased, 'Before tax');
    $tax_rows[] = ['_heading' => 'Charged less paid'];
    $tax_rows[] = $tax_line('Net', 'Net tax', $charged_paid - $tax_on_purchases, $charged_invoiced - $tax_on_purchases, 'For GST, HST and VAT returns that take off tax paid', '', 'fw-semibold');
}
$tax_columns = [
    'section' => ['label' => 'Section', 'screen' => false],
    'name' => ['label' => 'Figure', 'html' => function ($row) {
        return escapeHtml($row['name']) . ($row['registration'] !== '' ? ' <span class="small text-body-secondary">No. ' . escapeHtml($row['registration']) . '</span>' : '')
            . ($row['note'] !== '' ? '<div class="small text-body-secondary">' . escapeHtml($row['note']) . '</div>' : '');
    }],
    'registration' => ['label' => 'Registration', 'screen' => false],
    'paid' => ['label' => 'On payments received', 'type' => 'money', 'title' => 'Each payment carries its share of its invoice - cash basis'],
    'invoiced' => ['label' => 'On invoices sent', 'type' => 'money', 'title' => 'Each invoice in the month of its date - accrual basis'],
];

/*
 * ---------- accounts ----------
 */

$day_before = date('Y-m-d', strtotime("$from -1 day"));
$accounts = [];
$sql = mysqli_query($mysqli, "SELECT account_id, account_name, account_currency_code, account_archived_at,
        (SELECT MAX(reconciliation_statement_date) FROM account_reconciliations
            WHERE reconciliation_account_id = accounts.account_id AND reconciliation_finished_at IS NOT NULL) AS reconciled_to,
        " . accountBalanceSql('accounts.account_id', $day_before) . " AS opening,
        " . accountBalanceSql('accounts.account_id', $to) . " AS closing,
        COALESCE((SELECT SUM(payment_amount) FROM payments WHERE payment_account_id = accounts.account_id AND payment_archived_at IS NULL
            AND payment_date BETWEEN '$from' AND '$to'), 0)
        + COALESCE((SELECT SUM(revenue_amount) FROM revenues WHERE revenue_account_id = accounts.account_id AND revenue_archived_at IS NULL
            AND revenue_date BETWEEN '$from' AND '$to'), 0) AS money_in,
        COALESCE((SELECT SUM(expense_amount) FROM expenses WHERE expense_account_id = accounts.account_id AND expense_archived_at IS NULL
            AND expense_date BETWEEN '$from' AND '$to'), 0) AS money_out
    FROM accounts
    ORDER BY account_archived_at IS NOT NULL, account_name");
while ($row = mysqli_fetch_assoc($sql)) {
    foreach (['opening', 'closing', 'money_in', 'money_out'] as $key) {
        $row[$key] = round(floatval($row[$key]), 2);
    }
    // An archived account with nothing in it this year has nothing to say
    if ($row['account_archived_at'] && !$row['opening'] && !$row['closing'] && !$row['money_in'] && !$row['money_out']) {
        continue;
    }
    $accounts[] = $row;
}
$account_money = function ($key) {
    return function ($row) use ($key) {
        return billingMoney($row[$key], $row['account_currency_code']);
    };
};
$account_columns = [
    'account_name' => ['label' => 'Account', 'html' => function ($row) {
        return '<span class="fw-semibold">' . escapeHtml($row['account_name']) . '</span>' . ($row['account_archived_at'] ? ' <span class="badge bg-secondary-subtle text-secondary-emphasis fw-normal">Archived</span>' : '');
    }],
    'opening' => ['label' => 'On ' . date('M j, Y', strtotime($from)), 'type' => 'money', 'csv_label' => 'Balance at the start', 'title' => 'The balance at the start of the day', 'html' => $account_money('opening')],
    'money_in' => ['label' => 'Money in', 'type' => 'money', 'html' => $account_money('money_in')],
    'money_out' => ['label' => 'Money out', 'type' => 'money', 'html' => $account_money('money_out')],
    'closing' => ['label' => 'On ' . date('M j, Y', strtotime($to)), 'type' => 'money', 'csv_label' => 'Balance at the end', 'class' => 'fw-semibold', 'title' => 'The balance at the end of the day', 'html' => $account_money('closing')],
    'account_currency_code' => ['label' => 'Currency', 'screen' => false],
    'reconciled_to' => ['label' => 'Reconciled to', 'type' => 'date', 'html' => function ($row) use ($to) {
        if (!$row['reconciled_to']) {
            return '<span class="itflow-report-zero">Not yet</span>';
        }
        return '<span class="' . ($row['reconciled_to'] < $to ? 'text-warning-emphasis' : '') . '">' . escapeHtml(date('M j, Y', strtotime($row['reconciled_to']))) . '</span>';
    }],
];

/*
 * ---------- unpaid at the end of the year ----------
 */

$unpaid = [];
$sql = mysqli_query($mysqli, "SELECT invoice_id, invoice_prefix, invoice_number, invoice_date, invoice_due, invoice_amount, invoice_client_id, client_name, client_abbreviation,
        " . taxPackageOwedSql($to) . " AS owed
    FROM invoices
    LEFT JOIN clients ON client_id = invoice_client_id
    WHERE invoice_status IN ('Sent', 'Viewed', 'Partial', 'Paid') AND invoice_date <= '$to'
    HAVING owed > 0.004
    ORDER BY invoice_date, invoice_id");
while ($row = mysqli_fetch_assoc($sql)) {
    $row['invoice'] = $row['invoice_prefix'] . $row['invoice_number'];
    $row['client'] = taxPackageClient($row['invoice_client_id'], $row['client_name']);
    $row['days_late'] = max(0, intval((strtotime($to) - strtotime($row['invoice_due'])) / 86400));
    $unpaid[] = $row;
}
$unpaid_total = array_sum(array_column($unpaid, 'owed'));
$unpaid_columns = [
    'invoice' => ['label' => 'Invoice', 'html' => function ($row) {
        return hasClientAccess($row['invoice_client_id'])
            ? '<a class="font-monospace text-nowrap" href="/agent/invoice.php?client_id=' . intval($row['invoice_client_id']) . '&invoice_id=' . intval($row['invoice_id']) . '">' . escapeHtml($row['invoice']) . '</a>'
            : '<span class="font-monospace">' . escapeHtml($row['invoice']) . '</span>';
    }],
    'client' => ['label' => 'Client', 'html' => function ($row) {
        return hasClientAccess($row['invoice_client_id']) ? reportClientCell($row['invoice_client_id'], $row['client_name'], $row['client_abbreviation'])
            : '<span class="text-body-secondary fst-italic">' . escapeHtml($row['client']) . '</span>';
    }],
    'invoice_date' => ['label' => 'Date', 'type' => 'date', 'class' => 'text-nowrap'],
    'invoice_due' => ['label' => 'Due', 'type' => 'date', 'class' => 'text-nowrap'],
    'invoice_amount' => ['label' => 'Total', 'type' => 'money'],
    'owed' => ['label' => 'Owed', 'type' => 'money', 'class' => 'fw-semibold'],
    'days_late' => ['label' => 'Days late', 'type' => 'count', 'title' => 'At the end of the year', 'html' => function ($row) {
        return $row['days_late'] ? '<span class="' . ($row['days_late'] > 90 ? 'text-danger-emphasis fw-semibold' : '') . '">' . reportCount($row['days_late']) . '</span>' : '<span class="itflow-report-zero">–</span>';
    }],
];

/*
 * ---------- vendors for 1099s ----------
 *
 * US only, and by calendar year as 1099s are. $600 for payments up to the end
 * of 2025 and $2,000 from 2026 - adjusted for inflation from 2027, so it can
 * be changed. Payments by card go on the card company's 1099-K instead.
 */

$show_1099 = in_array($session_company_country ?? '', ['United States', 'USA', 'US'], true) && $start_month === 1;
$threshold = isset($_GET['over']) ? max(0, round(floatval(str_replace(',', '', (string) $_GET['over'])), 2)) : ($year >= 2026 ? 2000 : 600);
$vendors = [];
if ($show_1099) {
    $sql = mysqli_query($mysqli, "SELECT expense_vendor_id AS vendor_id, vendor_name, vendor_email, vendor_phone, vendor_phone_country_code, vendor_contact_name,
            COUNT(*) AS n, SUM(expense_amount) AS total,
            SUM(CASE WHEN LOWER(COALESCE(expense_payment_method, '')) LIKE '%card%' THEN expense_amount ELSE 0 END) AS by_card
        FROM expenses
        LEFT JOIN vendors ON vendor_id = expense_vendor_id
        WHERE expense_vendor_id > 0 AND expense_date BETWEEN '$from' AND '$to'
        GROUP BY expense_vendor_id, vendor_name, vendor_email, vendor_phone, vendor_phone_country_code, vendor_contact_name
        HAVING total >= $threshold
        ORDER BY total DESC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $row['vendor'] = $row['vendor_name'] ?? 'Deleted vendor';
        $row['total'] = floatval($row['total']);
        $row['by_card'] = floatval($row['by_card']);
        $row['not_by_card'] = $row['total'] - $row['by_card'];
        $row['phone'] = $row['vendor_phone'] ? formatPhoneNumber($row['vendor_phone'], $row['vendor_phone_country_code']) : '';
        $vendors[] = $row;
    }
}
$vendor_columns = [
    'vendor' => ['label' => 'Vendor', 'html' => function ($row) {
        return '<span class="fw-semibold">' . escapeHtml($row['vendor']) . '</span>' . ($row['vendor_contact_name'] ? '<div class="small text-body-secondary">' . escapeHtml($row['vendor_contact_name']) . '</div>' : '');
    }],
    'n' => ['label' => 'Payments', 'type' => 'count'],
    'total' => ['label' => 'Paid', 'type' => 'money'],
    'by_card' => ['label' => 'By card', 'type' => 'money', 'class' => 'text-body-secondary'],
    'not_by_card' => ['label' => 'Not by card', 'type' => 'money', 'class' => 'fw-semibold', 'title' => 'What counts toward a 1099-NEC or 1099-MISC'],
    'vendor_email' => ['label' => 'Email'],
    'phone' => ['label' => 'Phone', 'class' => 'text-nowrap'],
];

/*
 * ---------- mileage ----------
 */

// Mileage with Trips on, the accounts with Bookkeeping (Admin > Settings > Modules)
$show_mileage = moduleOn('trips');
$show_accounts = moduleOn('bookkeeping');

$rate = isset($_GET['rate']) ? round(max(0, floatval(str_replace(',', '.', (string) $_GET['rate']))), 4) : tripRules()['rate'];
$unit = ucfirst(tripUnit());
$unit_one = tripUnitOne();
$mileage = [];
$sql = mysqli_query($mysqli, "SELECT trip_user_id, user_name, COUNT(*) AS trips, SUM(trip_miles) AS miles,
        SUM(CASE WHEN trip_own_vehicle = 1 THEN trip_miles ELSE 0 END) AS own
    FROM trips
    LEFT JOIN users ON user_id = trip_user_id
    WHERE trip_archived_at IS NULL AND trip_date BETWEEN '$from' AND '$to'
    GROUP BY trip_user_id, user_name
    ORDER BY miles DESC");
while ($row = mysqli_fetch_assoc($sql)) {
    $row['agent'] = $row['user_name'] ?? 'Nobody';
    $row['miles'] = floatval($row['miles']);
    $row['own'] = floatval($row['own']);
    $row['value'] = $rate ? $row['miles'] * $rate : null;
    $mileage[] = $row;
}
$mileage_totals = ['agent' => 'Total', 'trips' => array_sum(array_column($mileage, 'trips')), 'miles' => array_sum(array_column($mileage, 'miles')),
    'own' => array_sum(array_column($mileage, 'own')), 'value' => $rate ? array_sum(array_column($mileage, 'value')) : null];
$mileage_columns = [
    'agent' => ['label' => 'Agent'],
    'trips' => ['label' => 'Trips', 'type' => 'count'],
    'miles' => ['label' => $unit, 'type' => 'decimal'],
    'own' => ['label' => 'In their own vehicle', 'type' => 'decimal', 'csv_label' => "$unit in their own vehicle"],
    'value' => ['label' => $rate ? 'At ' . tripRateText($rate) : 'At your rate', 'type' => 'money', 'csv_label' => 'Amount'],
];

/*
 * ---------- receipts ----------
 */

$expense_count = 0;
$receipt_count = 0;
$missing = [];
$sql = mysqli_query($mysqli, "SELECT expense_id, expense_date, expense_amount, expense_description, expense_receipt, vendor_name, category_name
    FROM expenses
    LEFT JOIN vendors ON vendor_id = expense_vendor_id
    LEFT JOIN categories ON category_id = expense_category_id
    WHERE expense_vendor_id > 0 AND expense_amount > 0 AND expense_date BETWEEN '$from' AND '$to'
    ORDER BY expense_date, expense_id");
while ($row = mysqli_fetch_assoc($sql)) {
    $expense_count++;
    if ((string) $row['expense_receipt'] !== '' && expenseReceiptPath($row['expense_receipt']) !== '') {
        $receipt_count++;
        continue;
    }
    $row['vendor'] = $row['vendor_name'] ?? 'Deleted vendor';
    $missing[] = $row;
}
$missing_total = array_sum(array_column($missing, 'expense_amount'));
$missing_columns = [
    'expense_date' => ['label' => 'Date', 'type' => 'date', 'class' => 'text-nowrap'],
    'vendor' => ['label' => 'Vendor', 'class' => 'fw-semibold'],
    'category_name' => ['label' => 'Category'],
    'expense_description' => ['label' => 'Description', 'class' => 'itflow-report-text'],
    'expense_amount' => ['label' => 'Amount', 'type' => 'money'],
];

// The section tables, as the CSVs in the package and on Export
$tables = [
    'Profit and loss' => [$pl_columns, $pl_rows, $pl_totals],
    'Sales tax' => [$tax_columns, $tax_rows, null],
    'Accounts' => [$account_columns, $accounts, null],
    'Unpaid at year end' => [$unpaid_columns, $unpaid, ['invoice' => 'Total', 'owed' => $unpaid_total]],
];
if (!$show_accounts) {
    unset($tables['Accounts']);
}
if ($show_1099) {
    $tables['Vendors for 1099s'] = [$vendor_columns, $vendors, null];
}
if ($show_mileage) {
    $tables['Mileage by agent'] = [$mileage_columns, $mileage, $mileage_totals];
}
$tables['Expenses with no receipt'] = [$missing_columns, $missing, ['expense_date' => 'Total', 'expense_amount' => $missing_total]];
$table_keys = ['Profit and loss' => 'pl', 'Sales tax' => 'tax', 'Accounts' => 'accounts', 'Unpaid at year end' => 'unpaid', 'Vendors for 1099s' => 'vendors',
    'Mileage by agent' => 'mileage', 'Expenses with no receipt' => 'missing'];
foreach ($tables as $file => [$columns, $rows, $totals]) {
    if (reportExporting($table_keys[$file])) {
        reportCsv("Tax package $label $file", $columns, $rows, $totals);
    }
}

// What else goes in the package - counted here, gathered as it is made
$counts = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT
    (SELECT COUNT(*) FROM payments WHERE payment_date BETWEEN '$from' AND '$to')
        + (SELECT COUNT(*) FROM revenues WHERE " . dashboardRevenueSql() . " AND revenue_date BETWEEN '$from' AND '$to') AS income,
    (SELECT COUNT(*) FROM expenses WHERE expense_vendor_id > 0 AND expense_date BETWEEN '$from' AND '$to') AS expenses,
    (SELECT COUNT(*) FROM invoices WHERE invoice_status IN ('Sent', 'Viewed', 'Partial', 'Paid') AND invoice_date BETWEEN '$from' AND '$to') AS invoices,
    (SELECT COUNT(*) FROM trips WHERE trip_archived_at IS NULL AND trip_date BETWEEN '$from' AND '$to') AS trips"));
// File names keep to plain characters - "Apr 2025 to Mar 2026"
$package_name = "Tax package " . str_replace(" – ", " to ", $label) . " - " . stripslashes($session_company_name);

if (($_GET['export'] ?? '') === 'package') {
    if (!class_exists('ZipArchive')) {
        flashAlert("The package can't be made on this server - PHP's zip extension isn't installed.", 'error');
        redirect(reportUrl(['export' => null]));
    }
    // This page as the summary, made into the ZIP with the rest as the page ends
    reportPdfStart(function ($writer) use ($tables, $from, $to, $rate, $label, $package_name) {
        taxPackageSend($writer, $tables, $from, $to, $rate, $label, $package_name);
    });
}

require_once "includes/inc_report_layout.php";

/**
 * Make the ZIP and send it: the summary PDF, the section tables, every
 * payment and other income, expense, invoice sent and trip of the year, and
 * the receipts, each named by its date, vendor and amount.
 */
function taxPackageSend($writer, array $tables, $from, $to, $rate, $label, $package_name) {
    global $mysqli, $session_name;

    @set_time_limit(600);
    $tmp_dir = __DIR__ . '/../../uploads/tmp';
    mkdirMissing($tmp_dir);
    $zip_path = $tmp_dir . '/tax-package-' . bin2hex(random_bytes(12)) . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        flashAlert('The package could not be made - the server could not write to uploads/tmp.', 'error');
        redirect(reportUrl(['export' => null]));
    }
    // Whatever ends the request, the package does not stay on the server
    register_shutdown_function(function () use ($zip_path) {
        if (is_file($zip_path)) {
            @unlink($zip_path);
        }
    });
    $root = clientExportSafeName($package_name, 120) . '/';

    $zip->addFromString($root . 'Summary.pdf', $writer->pdf->Output('', 'S'));
    $zip->setCompressionName($root . 'Summary.pdf', ZipArchive::CM_STORE);
    foreach ($tables as $file => [$columns, $rows, $totals]) {
        $zip->addFromString($root . "$file.csv", reportCsvData($columns, $rows, $totals));
    }

    // Every payment and other income - what Profit & Loss counts as income
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT 'Payment' AS kind, payment_date AS date, CONCAT(COALESCE(invoice_prefix, ''), COALESCE(invoice_number, '')) AS invoice,
            invoice_client_id AS client_id, client_name, category_name, invoice_scope AS description, payment_method AS method, payment_reference AS reference,
            account_name, payment_amount AS amount, payment_currency_code AS currency
        FROM payments
        LEFT JOIN invoices ON invoice_id = payment_invoice_id
        LEFT JOIN clients ON client_id = invoice_client_id
        LEFT JOIN categories ON category_id = invoice_category_id
        LEFT JOIN accounts ON account_id = payment_account_id
        WHERE payment_date >= '$from' AND payment_date < '$to' + INTERVAL 1 DAY
        UNION ALL
        SELECT 'Other income', revenue_date, '', revenue_client_id, client_name, category_name, revenue_description, revenue_payment_method, revenue_reference,
            account_name, revenue_amount, revenue_currency_code
        FROM revenues
        LEFT JOIN clients ON client_id = revenue_client_id
        LEFT JOIN categories ON category_id = revenue_category_id
        LEFT JOIN accounts ON account_id = revenue_account_id
        WHERE " . dashboardRevenueSql() . " AND revenue_date >= '$from' AND revenue_date < '$to' + INTERVAL 1 DAY
        ORDER BY date");
    while ($row = mysqli_fetch_assoc($sql)) {
        $row['client'] = taxPackageClient($row['client_id'], $row['client_name']);
        $row['category_name'] = $row['category_name'] ?? 'No category';
        $rows[] = $row;
    }
    $zip->addFromString($root . 'Income.csv', reportCsvData(array_diff_key([
        'date' => ['label' => 'Date', 'type' => 'date'], 'kind' => ['label' => 'Type'], 'invoice' => ['label' => 'Invoice'], 'client' => ['label' => 'Client'],
        'category_name' => ['label' => 'Category'], 'description' => ['label' => 'Description'], 'method' => ['label' => 'Method'], 'reference' => ['label' => 'Reference'],
        'account_name' => ['label' => 'Account'], 'amount' => ['label' => 'Amount', 'type' => 'money'], 'currency' => ['label' => 'Currency'],
    ], $show_accounts ? [] : ['account_name' => 1]), $rows, ['date' => 'Total', 'amount' => array_sum(array_column($rows, 'amount'))]));

    // Every expense - what Profit & Loss counts as spending - with its receipt beside it
    $rows = [];
    $used = [];
    $receipts = 0;
    $sql = mysqli_query($mysqli, "SELECT expense_id, expense_date, vendor_name, category_name, expense_description, expense_reference, expense_payment_method,
            account_name, expense_amount, expense_tax_amount, tax_name, expense_currency_code, expense_client_id, client_name, expense_receipt
        FROM expenses
        LEFT JOIN vendors ON vendor_id = expense_vendor_id
        LEFT JOIN categories ON category_id = expense_category_id
        LEFT JOIN accounts ON account_id = expense_account_id
        LEFT JOIN taxes ON tax_id = expense_tax_id
        LEFT JOIN clients ON client_id = expense_client_id
        WHERE expense_vendor_id > 0 AND expense_date >= '$from' AND expense_date < '$to' + INTERVAL 1 DAY
        ORDER BY expense_date, expense_id");
    while ($row = mysqli_fetch_assoc($sql)) {
        $row['vendor'] = $row['vendor_name'] ?? 'Deleted vendor';
        $row['client'] = taxPackageClient($row['expense_client_id'], $row['client_name']);
        $row['receipt'] = '';
        $path = (string) $row['expense_receipt'] !== '' ? expenseReceiptPath($row['expense_receipt']) : '';
        if ($path !== '') {
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $base = clientExportSafeName($row['expense_date'] . ' ' . $row['vendor'] . ' ' . number_format(floatval($row['expense_amount']), 2, '.', ''), 100);
            $name = "$base.$extension";
            for ($n = 2; isset($used[mb_strtolower($name)]); $n++) {
                $name = "$base ($n).$extension";
            }
            if ($zip->addFile($path, $root . 'Receipts/' . $name)) {
                $zip->setCompressionName($root . 'Receipts/' . $name, ZipArchive::CM_STORE);
                $used[mb_strtolower($name)] = true;
                $row['receipt'] = 'Receipts/' . $name;
                $receipts++;
            }
        }
        $rows[] = $row;
    }
    $zip->addFromString($root . 'Expenses.csv', reportCsvData(array_diff_key([
        'expense_date' => ['label' => 'Date', 'type' => 'date'], 'vendor' => ['label' => 'Vendor'], 'category_name' => ['label' => 'Category'],
        'expense_description' => ['label' => 'Description'], 'expense_reference' => ['label' => 'Reference'], 'expense_payment_method' => ['label' => 'Method'],
        'account_name' => ['label' => 'Account'], 'expense_amount' => ['label' => 'Amount', 'type' => 'money'],
        'expense_tax_amount' => ['label' => 'Tax in it', 'type' => 'money'], 'tax_name' => ['label' => 'Tax'], 'expense_currency_code' => ['label' => 'Currency'],
        'client' => ['label' => 'For client'], 'receipt' => ['label' => 'Receipt'],
    ], $show_accounts ? [] : ['account_name' => 1]), $rows, ['expense_date' => 'Total', 'expense_amount' => array_sum(array_column($rows, 'expense_amount')), 'expense_tax_amount' => array_sum(array_column($rows, 'expense_tax_amount'))]));

    // Every invoice sent in the year, with what was still owed on it at the end
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT invoice_id, invoice_prefix, invoice_number, invoice_date, invoice_due, invoice_status, invoice_client_id, client_name,
            invoice_discount_amount, invoice_amount, invoice_currency_code,
            COALESCE((SELECT SUM(item_subtotal) FROM invoice_items WHERE item_invoice_id = invoice_id), 0) AS subtotal,
            COALESCE((SELECT SUM(item_tax) FROM invoice_items WHERE item_invoice_id = invoice_id), 0) AS tax,
            COALESCE((SELECT SUM(payment_amount) FROM payments WHERE payment_invoice_id = invoice_id AND payment_date <= '$to'), 0) AS paid,
            " . taxPackageOwedSql($to) . " AS owed
        FROM invoices
        LEFT JOIN clients ON client_id = invoice_client_id
        WHERE invoice_status IN ('Sent', 'Viewed', 'Partial', 'Paid') AND invoice_date BETWEEN '$from' AND '$to'
        ORDER BY invoice_date, invoice_id");
    while ($row = mysqli_fetch_assoc($sql)) {
        $row['invoice'] = $row['invoice_prefix'] . $row['invoice_number'];
        $row['client'] = taxPackageClient($row['invoice_client_id'], $row['client_name']);
        $row['owed'] = max(0, floatval($row['owed']));
        $rows[] = $row;
    }
    $sum = function ($key) use (&$rows) {
        return array_sum(array_column($rows, $key));
    };
    $zip->addFromString($root . 'Invoices.csv', reportCsvData([
        'invoice' => ['label' => 'Invoice'], 'invoice_date' => ['label' => 'Date', 'type' => 'date'], 'invoice_due' => ['label' => 'Due', 'type' => 'date'],
        'client' => ['label' => 'Client'], 'invoice_status' => ['label' => 'Status now'], 'subtotal' => ['label' => 'Subtotal', 'type' => 'money'],
        'invoice_discount_amount' => ['label' => 'Discount', 'type' => 'money'], 'tax' => ['label' => 'Tax', 'type' => 'money'],
        'invoice_amount' => ['label' => 'Total', 'type' => 'money'], 'paid' => ['label' => 'Paid by year end', 'type' => 'money'],
        'owed' => ['label' => 'Owed at year end', 'type' => 'money'], 'invoice_currency_code' => ['label' => 'Currency'],
    ], $rows, ['invoice' => 'Total', 'subtotal' => $sum('subtotal'), 'invoice_discount_amount' => $sum('invoice_discount_amount'), 'tax' => $sum('tax'),
        'invoice_amount' => $sum('invoice_amount'), 'paid' => $sum('paid'), 'owed' => $sum('owed')]));

    // Every trip - with Trips on
    if ($show_mileage) {
        $rows = [];
        $sql = mysqli_query($mysqli, "SELECT trip_date, user_name, trip_client_id, client_name, trip_purpose, trip_source, trip_destination, trip_miles, round_trip, trip_own_vehicle
            FROM trips
            LEFT JOIN users ON user_id = trip_user_id
            LEFT JOIN clients ON client_id = trip_client_id
            WHERE trip_archived_at IS NULL AND trip_date BETWEEN '$from' AND '$to'
            ORDER BY trip_date, trip_id");
        while ($row = mysqli_fetch_assoc($sql)) {
            $row['client'] = taxPackageClient($row['trip_client_id'], $row['client_name']);
            $row['value'] = $rate ? floatval($row['trip_miles']) * $rate : null;
            $rows[] = $row;
        }
        $unit = ucfirst(tripUnit());
        $zip->addFromString($root . 'Trips.csv', reportCsvData([
            'trip_date' => ['label' => 'Date', 'type' => 'date'], 'user_name' => ['label' => 'Agent'], 'client' => ['label' => 'Client'],
            'trip_purpose' => ['label' => 'Purpose'], 'trip_source' => ['label' => 'From'], 'trip_destination' => ['label' => 'To'],
            'trip_miles' => ['label' => $unit, 'type' => 'decimal'],
            'round_trip' => ['label' => 'Round trip', 'csv' => function ($row) { return intval($row['round_trip']) ? 'Yes' : 'No'; }],
            'trip_own_vehicle' => ['label' => 'Own vehicle', 'csv' => function ($row) { return intval($row['trip_own_vehicle']) ? 'Yes' : 'No'; }],
            'value' => ['label' => $rate ? 'At ' . tripRateText($rate) : 'Amount', 'type' => 'money'],
        ], $rows, ['trip_date' => 'Total', 'trip_miles' => $sum('trip_miles'), 'value' => $rate ? $sum('value') : null]));
    }

    if (!$zip->close()) {
        flashAlert('The package could not be made - the server could not write to uploads/tmp.', 'error');
        redirect(reportUrl(['export' => null]));
    }

    // $session_name is SQL-safe already - see reportCsv()
    logAudit('Report', 'Export', "$session_name downloaded the tax package for " . escapeSql($label) . " with $receipts receipts");

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . sanitizeFilename($package_name . '.zip') . '"');
    header('Content-Length: ' . filesize($zip_path));
    readfile($zip_path);
}

// The figures
$missing_count = count($missing);
$stats = [
    ['label' => 'Income', 'value' => reportMoney($income_total['total']), 'sub' => '<span class="text-body-tertiary">Payments and other income</span>'],
    ['label' => 'Expenses', 'value' => reportMoney($expense_total['total']), 'sub' => '<span class="text-body-tertiary">Paid to vendors</span>'],
    ['label' => 'Net profit', 'value' => reportMoney($profit), 'tone' => $profit < 0 ? 'danger' : '',
        'sub' => reportPercent(reportShare($profit, $income_total['total'], 1)) . ' <span class="text-body-tertiary">of income</span>'],
    ['label' => 'Sales tax charged', 'value' => reportMoney($charged_paid),
        'sub' => reportMoney($charged_invoiced) . ' <span class="text-body-tertiary">on invoices sent</span>'],
    ['label' => $ended ? 'Unpaid at year end' : 'Unpaid now', 'value' => reportMoney($unpaid_total),
        'sub' => reportCount(count($unpaid)) . ' <span class="text-body-tertiary">' . (count($unpaid) === 1 ? 'invoice' : 'invoices') . '</span>'],
    ['label' => 'Receipts', 'value' => reportCount($receipt_count) . ' of ' . reportCount($expense_count), 'tone' => $missing_count ? 'warning' : 'success',
        'sub' => $missing_count ? reportCount($missing_count) . ' <span class="text-body-tertiary">missing, ' . reportMoney($missing_total) . '</span>' : '<span class="text-body-tertiary">Every expense has one</span>'],
];

$export = [];
foreach ($tables as $file => $table) {
    $export[$table_keys[$file]] = $file;
}

$controls = reportSelectControl('year', $year, $years, 'fa-calendar-alt')
    . reportSelectControl('start', $start_month, array_combine(range(1, 12), array_map(function ($m) {
        return 'Year starts in ' . date('F', mktime(0, 0, 0, $m, 1));
    }, range(1, 12))), 'fa-calendar-day')
    . (!$show_mileage ? '' : '<div class="col-sm-6 col-lg-auto"><div class="input-group">'
    . '<span class="input-group-text"><i class="fas fa-fw fa-route"></i></span>'
    . '<input type="text" inputmode="decimal" class="form-control" style="width: 9rem" name="rate" value="' . ($rate ? escapeHtml(rtrim(rtrim(number_format($rate, 4, '.', ''), '0'), '.')) : '') . '" placeholder="Rate a ' . $unit_one . '" aria-label="Mileage rate a ' . $unit_one . '" data-report-autosubmit title="The mileage rate a ' . $unit_one . ' - your tax deduction rate, say - press Enter">'
    . '</div></div>');
if ($show_1099) {
    $controls .= '<div class="col-sm-6 col-lg-auto"><div class="input-group">'
        . '<span class="input-group-text">1099 at</span>'
        . '<input type="text" inputmode="decimal" class="form-control" style="width: 6rem" name="over" value="' . escapeHtml(rtrim(rtrim(number_format($threshold, 2, '.', ''), '0'), '.')) . '" aria-label="1099 threshold" data-report-autosubmit title="What a vendor is paid in the year that needs a 1099 - press Enter">'
        . '</div></div>';
}

?>

<div class="itflow-report">

    <?php reportHeader('tax_package', [
        'subtitle' => $label . ($ended ? '' : ' so far'),
        'controls' => $controls,
        'export' => $export,
        'print_meta' => $range . ($ended ? '' : ' (the year so far)') . ' · Cash basis',
        'actions' => '<a class="btn btn-success" href="' . escapeHtml(reportUrl(['export' => 'package', 'table' => null])) . '" title="The summary, every table, payment, expense, invoice and trip, and the receipts, as one ZIP">'
            . '<i class="fas fa-fw fa-file-archive"></i><span class="d-none d-md-inline ms-2">Download package</span></a>',
    ]); ?>

    <?php if (!$ended) { ?>
        <div class="alert alert-warning d-flex gap-2 align-items-start">
            <i class="fas fa-fw fa-exclamation-triangle mt-1"></i>
            <div>This tax year runs to <?= escapeHtml(date('M j, Y', strtotime($end))) ?> - the figures are the year so far. Pick the year before for the one to file.</div>
        </div>
    <?php } ?>

    <?= reportStats($stats) ?>

    <?= reportSectionOpen('Profit and loss', 'By quarter of the tax year') ?>
        <?= reportTable($pl_columns, $pl_rows, ['sortable' => false, 'totals' => $pl_totals, 'export_table' => 'pl']) ?>
        <div class="card-footer small text-body-secondary">
            Cash basis - money counts when it arrives or leaves. Payments are filed under the category of the invoice they paid, expenses are what was paid to vendors,
            and transfers between your own accounts are left out of both sides. Every line is in Income.csv and Expenses.csv in the package.
        </div>
    </div>

    <?= reportSectionOpen('Sales tax', 'GST, HST, VAT or sales tax, by rate') ?>
        <?php if ($parts || $tax_purchases['parts']) { ?>
            <?= reportTable($tax_columns, $tax_rows, ['sortable' => false]) ?>
            <div class="card-footer small text-body-secondary">
                On payments received, each payment carries its share of its invoice's tax (cash basis); on invoices sent, each invoice counts in full on its date (accrual basis).
                Sales are before tax and net of discounts. Tax Collected has it month by month.
            </div>
        <?php } else { ?>
            <?= reportEmpty('No tax was charged on sales or paid on purchases in this year.', 'fa-percent', 'secondary') ?>
        <?php } ?>
    </div>

    <?php if ($show_accounts) { ?>
    <?= reportSectionOpen('Accounts', 'From the first day of the year to the last') ?>
        <?= reportTable($account_columns, $accounts, ['sortable' => false, 'empty' => 'No accounts.']) ?>
        <div class="card-footer small text-body-secondary">
            Money in and out include transfers between your accounts - it is each account's own money. Check each balance at the end against the bank's statement;
            an account reconciled to a date before the end of the year still has lines to check.
        </div>
    <?php } ?>
    </div>

    <?= reportSectionOpen($ended ? 'Unpaid at the end of the year' : 'Unpaid now', $unpaid ? reportCount(count($unpaid)) . ' ' . (count($unpaid) === 1 ? 'invoice' : 'invoices') . ', ' . reportMoney($unpaid_total) : '') ?>
        <?= reportTable($unpaid_columns, $unpaid, ['totals' => ['invoice' => 'Total', 'owed' => $unpaid_total], 'empty' => reportEmpty('Every invoice was paid by ' . escapeHtml(date('M j, Y', strtotime($to))) . '.'), 'export_table' => 'unpaid']) ?>
        <div class="card-footer small text-body-secondary">
            Invoices sent by <?= escapeHtml(date('M j, Y', strtotime($to))) ?> and not paid in full by then - what clients owed you. On a cash basis this isn't income yet;
            on an accrual basis it is, and one that will never be paid may be a bad debt. Every invoice sent in the year is in Invoices.csv in the package.
        </div>
    </div>

    <?php if ($show_1099) { ?>
        <?= reportSectionOpen('Vendors for 1099s', 'Paid ' . escapeHtml(reportMoneyWhole($threshold)) . ' or more in ' . $year) ?>
            <?= reportTable($vendor_columns, $vendors, ['empty' => reportEmpty('No vendor was paid ' . escapeHtml(reportMoneyWhole($threshold)) . ' or more in ' . $year . '.', 'fa-check-circle', 'success'), 'export_table' => 'vendors']) ?>
            <div class="card-footer small text-body-secondary">
                A 1099-NEC or 1099-MISC is due for a vendor paid <?= escapeHtml(reportMoneyWhole($threshold)) ?> or more in the year for services, rent and the like - $600 for
                payments up to the end of 2025, $2,000 from 2026, adjusted for inflation from 2027. Payments by card go on the card company's 1099-K instead, so Not by card is
                what counts. Corporations and goods usually need none - your accountant decides. ITFlow doesn't keep vendors' tax IDs or addresses: ask each one listed for a W-9.
            </div>
        </div>
    <?php } ?>

    <?php if ($show_mileage) { ?>
    <?= reportSectionOpen('Mileage', $mileage ? reportCount($mileage_totals['trips']) . ' trips, ' . escapeHtml(tripMilesText($mileage_totals['miles'])) : '') ?>
        <?= reportTable($mileage_columns, $mileage, ['totals' => $mileage_totals, 'empty' => reportEmpty('No trips were logged in this year.', 'fa-route', 'secondary')]) ?>
        <div class="card-footer small text-body-secondary">
            <?= $unit ?> as entered on each trip - the whole trip, both ways for a round trip. Trips in their own vehicle are the ones an agent may be paid back for, or a
            sole trader may deduct. The rate starts at the mileage rate in Settings; put your tax deduction rate above to see what the trips come to. Every trip is in Trips.csv.
        </div>
    <?php } ?>
    </div>

    <?= reportSectionOpen('Expenses with no receipt', $missing ? reportCount($missing_count) . ' of ' . reportCount($expense_count) . ', ' . reportMoney($missing_total) : '') ?>
        <?= reportTable($missing_columns, $missing, ['totals' => ['expense_date' => 'Total', 'expense_amount' => $missing_total], 'limit' => 25, 'pdf_whole' => false, 'export_table' => 'missing',
            'empty' => reportEmpty($expense_count ? 'Every expense in this year has its receipt.' : 'No expenses in this year.')]) ?>
        <div class="card-footer small text-body-secondary">
            Attach a receipt from the expense's window on the Expenses list, then download the package again. Every receipt there is goes in the package's Receipts folder,
            named by date, vendor and amount, and Expenses.csv says which is whose.
        </div>
    </div>

    <?= reportSectionOpen('In the package', 'Download package, at the top') ?>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 itflow-report-table">
                <thead class="table-light"><tr><th>File</th><th>What it is</th></tr></thead>
                <tbody>
                    <tr><td class="fw-semibold text-nowrap">Summary.pdf</td><td>This page</td></tr>
                    <?php foreach ($tables as $file => [$columns, $rows]) { ?>
                        <tr><td class="fw-semibold text-nowrap"><?= escapeHtml($file) ?>.csv</td><td>The table above, as a spreadsheet</td></tr>
                    <?php } ?>
                    <tr><td class="fw-semibold text-nowrap">Income.csv</td><td><?= reportCount($counts['income']) ?> invoice payments and other income - date, client, invoice, category, method, account and amount</td></tr>
                    <tr><td class="fw-semibold text-nowrap">Expenses.csv</td><td><?= reportCount($counts['expenses']) ?> expenses - date, vendor, category, method, account, amount, the tax in it and its receipt</td></tr>
                    <tr><td class="fw-semibold text-nowrap">Invoices.csv</td><td><?= reportCount($counts['invoices']) ?> invoices sent - subtotal, discount, tax and total, and what was paid and owed at year end</td></tr>
                    <?php if ($show_mileage) { ?><tr><td class="fw-semibold text-nowrap">Trips.csv</td><td><?= reportCount($counts['trips']) ?> trips - who, where, why and how far</td></tr><?php } ?>
                    <tr><td class="fw-semibold text-nowrap">Receipts</td><td><?= reportCount($receipt_count) ?> receipts, named by date, vendor and amount</td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php require_once "../../includes/footer.php"; ?>
<script src="<?= staticFileUrl('/js/reports.js') ?>"></script>
