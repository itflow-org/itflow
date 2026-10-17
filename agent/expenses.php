<?php

/*
 * Expenses - what the business spends with its own vendors.
 *
 * Along the top: spent this month against the same days last month, the
 * year to date with its monthly average, what the running recurring
 * expenses come to a month, and the last 90 days' expenses without a
 * receipt. The pills cut the list to ones for a client, without a receipt,
 * or refunds; the filters by vendor, category, client, account, how it was
 * paid and date. The total of everything the filters leave sits under the
 * list. A row opens the expense's quick look, with its receipt.
 *
 * Takes ?vendor=, ?category=, ?account=, ?client=, ?method=, ?show=
 * (client, no_receipt, refunds) and the date filter - the dashboard,
 * vendor pages and reports link with them - and ?open= to open one.
 */

// Default Column Sortby/Order Filter
$sort = "expense_date";
$order = "DESC";

require_once "includes/inc_all.php";

// Perms
enforceUserPermission('module_financial');

$can_edit = lookupUserPermission('module_financial') >= 2;
$can_delete = lookupUserPermission('module_financial') >= 3;
$can_sales = lookupUserPermission('module_sales') >= 1;

$vendor_filter = intval($_GET['vendor'] ?? 0);
$category_filter = intval($_GET['category'] ?? 0);
$account_filter = intval($_GET['account'] ?? 0);
$client_filter = intval($_GET['client'] ?? 0);
$method_filter = trim((string) ($_GET['method'] ?? ''));
$recurring_filter = intval($_GET['recurring_expense_id'] ?? 0);
$recurring_filter_row = $recurring_filter ? recurringExpenseRow($recurring_filter) : null;
$paid_by_filter = intval($_GET['paid_by'] ?? 0);
$shows = expenseShows();
$show = isset($shows[$_GET['show'] ?? '']) ? (string) ($_GET['show'] ?? '') : '';

// Only these sort - filter_header.php hands through any lowercase word
$sortable_columns = ['expense_date' => 'expense_date', 'vendor_name' => 'vendor_name', 'category_name' => 'category_name', 'client_name' => 'client_name',
    'account_name' => 'account_name', 'expense_amount' => 'expense_amount', 'expense_created_at' => 'expense_created_at'];
if (!isset($sortable_columns[$sort])) {
    $sort = 'expense_date';
}

// Everything but the pill, then the pill - the pill counts are over what the rest leave
$filter_src = array_merge($_GET, ['dtf' => $dtf, 'dtt' => $dtt, 'show' => '']);
$where = expenseFiltersSql($filter_src);
$show_query = $shows[$show]['where'] ? "AND {$shows[$show]['where']}" : '';
$joins = expenseJoinsSql();

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS expenses.*, vendor_name, vendor_archived_at, category_name, category_color, account_name, client_name, user_name,
        invoice_prefix, invoice_number, (SELECT payer.user_name FROM users AS payer WHERE payer.user_id = expense_paid_by) AS paid_by_name
    FROM expenses $joins
    LEFT JOIN invoices ON invoice_id = expense_invoice_id
    WHERE $where $show_query
    ORDER BY {$sortable_columns[$sort]} $order, expense_id DESC LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

$expenses = [];
while ($row = mysqli_fetch_assoc($sql)) {
    $expenses[] = $row;
}

// The pills, and the total of what is listed
$count_sql = [];
foreach ($shows as $key => $s) {
    $count_sql[] = $key === '' ? 'COUNT(*)' : "COALESCE(SUM({$s['where']}), 0)";
}
$row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT " . implode(', ', $count_sql) . ",
        COALESCE(SUM(CASE WHEN 1 $show_query THEN expense_amount END), 0)
    FROM expenses $joins WHERE $where"));
$counts = [];
foreach (array_keys($shows) as $i => $key) {
    $counts[$key] = intval($row[$i]);
}
$listed_total = floatval($row[count($shows)]);

$stats = expenseStats();

$filter_url = function ($changes) {
    $params = array_merge($_GET, $changes);
    unset($params['page']);
    return '?' . http_build_query(array_filter($params, function ($v) { return $v !== '' && $v !== null; }));
};
$date_url = function ($from, $to, $changes = []) use ($filter_url) {
    return $filter_url(array_merge(['canned_date' => 'custom', 'dtf' => $from, 'dtt' => $to], $changes));
};
$stat_money = function ($amount) use ($session_company_currency) {
    return escapeHtml(billingMoney($amount, $session_company_currency));
};

// More filters open when one of them is set
$more_filters = $account_filter || $method_filter !== '';
$any_filter = $q || $vendor_filter || $category_filter || $account_filter || $client_filter || $method_filter !== '' || $recurring_filter || $paid_by_filter || $show !== '' || $dtf !== '1970-01-01';

// Who is owed for what they paid personally - above the list on To reimburse
$owed = $show === 'to_reimburse' ? expenseToReimburse($paid_by_filter) : [];

// This month against the same days of last month
$month_change = $stats['last_to_date'] > 0 ? round(($stats['month'] - $stats['last_to_date']) / $stats['last_to_date'] * 100) : null;

?>

<!-- The spending -->
<div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-3">
        <a class="itflow-billing-stat <?= $dtf === $stats['month_start'] && $show === '' ? 'active' : '' ?>" href="<?= escapeHtml($date_url($stats['month_start'], date('Y-m-t'), ['show' => ''])) ?>">
            <div class="itflow-billing-stat-label"><i class="fas fa-calendar-alt me-1"></i>This month</div>
            <div class="itflow-billing-stat-value"><?= $stat_money($stats['month']) ?></div>
            <div class="itflow-billing-stat-note">
                <?php if ($month_change !== null) { ?>
                    <span class="<?= $month_change > 0 ? 'text-danger-emphasis' : 'text-success-emphasis' ?>"><i class="fas fa-arrow-<?= $month_change > 0 ? 'up' : 'down' ?> me-1"></i><?= abs($month_change) ?>%</span>
                    on the same days of <?= date('F', strtotime($stats['last_start'])) ?>
                <?php } else { ?>
                    <?= $stat_money($stats['last_month']) ?> in <?= date('F', strtotime($stats['last_start'])) ?>
                <?php } ?>
            </div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-3">
        <a class="itflow-billing-stat <?= $dtf === $stats['year_start'] && $show === '' ? 'active' : '' ?>" href="<?= escapeHtml($date_url($stats['year_start'], date('Y-12-31'), ['show' => ''])) ?>">
            <div class="itflow-billing-stat-label"><i class="fas fa-chart-line me-1"></i><?= date('Y') ?> so far</div>
            <div class="itflow-billing-stat-value"><?= $stat_money($stats['year']) ?></div>
            <div class="itflow-billing-stat-note"><?= $stats['year_count'] ?> expense<?= $stats['year_count'] === 1 ? '' : 's' ?> &middot; <?= $stat_money($stats['year_average']) ?> a month</div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-3">
        <a class="itflow-billing-stat" href="recurring_expenses.php">
            <div class="itflow-billing-stat-label"><i class="fas fa-redo-alt me-1"></i>Recurring</div>
            <div class="itflow-billing-stat-value"><?= $stat_money($stats['recurring_monthly']) ?></div>
            <div class="itflow-billing-stat-note">a month from <?= $stats['recurring_count'] ?> running &middot; <?= $stat_money($stats['recurring_monthly'] * 12) ?> a year</div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-3">
        <a class="itflow-billing-stat <?= $show === 'no_receipt' ? 'active' : '' ?>" href="<?= escapeHtml($date_url($stats['since_90'], date('Y-m-d'), ['show' => 'no_receipt'])) ?>">
            <div class="itflow-billing-stat-label"><i class="fas fa-paperclip me-1"></i>No receipt, 90 days</div>
            <div class="itflow-billing-stat-value <?= $stats['no_receipt'] ? 'text-warning-emphasis' : '' ?>"><?= $stats['no_receipt'] ?></div>
            <div class="itflow-billing-stat-note"><?= $stats['no_receipt'] ? $stat_money($stats['no_receipt_amount']) . ' without one' : 'Every one has its receipt' ?></div>
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-shopping-cart me-2"></i>Expenses</h3>
        <div class="card-tools">
            <div class="btn-group">
                <?php if ($can_edit) { ?>
                    <button type="button" class="btn btn-primary ajax-modal" data-modal-size="lg" data-modal-url="modals/expense/expense_add.php<?= $vendor_filter ? "?vendor_id=$vendor_filter" : '' ?>"><i class="fas fa-plus me-2"></i>New Expense</button>
                <?php } ?>
                <button type="button" class="btn btn-primary dropdown-toggle <?= $can_edit ? 'dropdown-toggle-split' : '' ?>" data-bs-toggle="dropdown"><?= $can_edit ? '' : '<i class="fas fa-ellipsis-h"></i>' ?></button>
                <div class="dropdown-menu dropdown-menu-end">
                    <?php if ($can_edit) { ?>
                        <a class="dropdown-item" href="expense_import.php"><i class="fa fa-fw fa-file-import text-secondary me-2"></i>Import Statement</a>
                        <div class="dropdown-divider"></div>
                    <?php } ?>
                    <a class="dropdown-item ajax-modal" href="#"
                        data-modal-url="<?= buildExportModalUrl('modals/expense/expense_export.php', ['account', 'vendor', 'category', 'client', 'method', 'paid_by', 'recurring_expense_id', 'show', 'q'], ['dtf' => $dtf, 'dtt' => $dtt]) ?>">
                        <i class="fa fa-fw fa-download text-secondary me-2"></i>Export
                    </a>
                    <a class="dropdown-item" href="recurring_expenses.php"><i class="fa fa-fw fa-redo-alt text-secondary me-2"></i>Recurring Expenses</a>
                    <a class="dropdown-item" href="reports/expense_summary.php"><i class="fa fa-fw fa-chart-bar text-secondary me-2"></i>Expenses Report</a>
                </div>
            </div>
        </div>
    </div>

    <div class="card-header py-3">
        <form autocomplete="off">
            <input type="hidden" name="show" value="<?= escapeHtml($show) ?>">
            <?php if ($recurring_filter) { ?>
                <input type="hidden" name="recurring_expense_id" value="<?= $recurring_filter ?>">
            <?php } ?>
            <?php if ($paid_by_filter) { ?>
                <input type="hidden" name="paid_by" value="<?= $paid_by_filter ?>">
            <?php } ?>
            <div class="row g-2 align-items-center">
                <div class="col-lg-3 col-md-6">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search expenses" title="Vendor, description, reference, amount, client, category">
                        <button class="btn btn-outline-secondary <?= $more_filters ? 'active' : '' ?>" title="More filters" type="button" data-bs-toggle="collapse" data-bs-target="#expenseMoreFilters"><i class="fas fa-filter"></i></button>
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
                <div class="col-lg-2 col-md-6">
                    <input type="text" id="dateFilter" class="form-control" autocomplete="off" title="Date paid" aria-label="Date paid">
                    <input type="hidden" name="canned_date" id="canned_date" value="<?= escapeHtml($_GET['canned_date'] ?? '') ?>">
                    <input type="hidden" name="dtf" id="dtf" value="<?= escapeHtml($dtf ?? '') ?>">
                    <input type="hidden" name="dtt" id="dtt" value="<?= escapeHtml($dtt ?? '') ?>">
                </div>
                <div class="col-lg-2 col-md-4">
                    <select class="form-select select2" name="vendor" onchange="this.form.submit()" aria-label="Vendor">
                        <option value="">- Any vendor -</option>
                        <?php
                        $sql_filter = mysqli_query($mysqli, "SELECT vendor_id, vendor_name, vendor_description FROM vendors
                            WHERE vendor_client_id = 0 AND EXISTS (SELECT 1 FROM expenses WHERE expense_vendor_id = vendor_id) ORDER BY vendor_name ASC");
                        while ($row = mysqli_fetch_assoc($sql_filter)) {
                            $option_id = intval($row['vendor_id']); ?>
                            <option <?= $vendor_filter === $option_id ? 'selected' : '' ?> value="<?= $option_id ?>"<?= selectOptionVendor($row['vendor_name'], $row['vendor_description']) ?>><?= escapeHtml($row['vendor_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-4">
                    <select class="form-select select2" name="category" onchange="this.form.submit()" aria-label="Category">
                        <option value="">- Any category -</option>
                        <?php
                        $sql_filter = mysqli_query($mysqli, "SELECT category_id, category_name, category_color, category_icon FROM categories
                            WHERE category_type = 'Expense' AND EXISTS (SELECT 1 FROM expenses WHERE expense_category_id = category_id AND expense_vendor_id > 0) ORDER BY category_name ASC");
                        while ($row = mysqli_fetch_assoc($sql_filter)) {
                            $option_id = intval($row['category_id']); ?>
                            <option <?= $category_filter === $option_id ? 'selected' : '' ?> value="<?= $option_id ?>"<?= selectOptionColor($row['category_color'] ?? '', $row['category_icon'] ?? '') ?>><?= escapeHtml($row['category_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-lg-3 col-md-4">
                    <div class="d-flex gap-2">
                        <div class="flex-grow-1 min-w-0">
                            <select class="form-select select2" name="client" onchange="this.form.submit()" aria-label="Client">
                                <option value="">- Any client -</option>
                                <?php
                                $sql_filter = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients
                                    WHERE EXISTS (SELECT 1 FROM expenses WHERE expense_client_id = client_id) " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
                                while ($row = mysqli_fetch_assoc($sql_filter)) {
                                    $option_id = intval($row['client_id']); ?>
                                    <option <?= $client_filter === $option_id ? 'selected' : '' ?> value="<?= $option_id ?>"<?= selectOptionClient($row['client_id'], $row['client_name'], $row['client_abbreviation'] ?? '') ?>><?= escapeHtml($row['client_name']) ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <?php if ($can_edit) { ?>
                            <div class="dropdown" id="bulkActionButton" hidden>
                                <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-fw fa-layer-group"></i><span class="d-none d-xxl-inline ms-1">Bulk</span> (<span id="selectedCount">0</span>)
                                </button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/expense/expense_bulk_edit.php?field=category" data-bulk="true"><i class="fas fa-fw fa-list text-secondary me-2"></i>Set Category</a>
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/expense/expense_bulk_edit.php?field=account" data-bulk="true"><i class="fas fa-fw fa-piggy-bank text-secondary me-2"></i>Set Account</a>
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/expense/expense_bulk_edit.php?field=method" data-bulk="true"><i class="fas fa-fw fa-credit-card text-secondary me-2"></i>Set Payment Method</a>
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/expense/expense_bulk_edit.php?field=client" data-bulk="true"><i class="fas fa-fw fa-user text-secondary me-2"></i>Set Client</a>
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/expense/expense_bulk_edit.php?field=billable" data-bulk="true"><i class="fas fa-fw fa-file-invoice-dollar text-secondary me-2"></i>Set Billable</a>
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/expense/expense_reimburse.php" data-bulk="true"><i class="fas fa-fw fa-hand-holding-usd text-secondary me-2"></i>Reimburse</a>
                                    <?php if ($can_delete) { ?>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger text-bold ajax-modal" href="#" data-modal-url="modals/expense/expense_bulk_delete.php" data-bulk="true"><i class="fas fa-fw fa-trash me-2"></i>Delete</a>
                                    <?php } ?>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
            <div class="collapse <?= $more_filters ? 'show' : '' ?>" id="expenseMoreFilters">
                <div class="row g-2 mt-0">
                    <div class="col-lg-3 col-md-6">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-piggy-bank"></i></span>
                            <select class="form-select select2" name="account" onchange="this.form.submit()" aria-label="Paid from">
                                <option value="">- Any account -</option>
                                <?php
                                $sql_filter = mysqli_query($mysqli, "SELECT account_id, account_name FROM accounts WHERE EXISTS (SELECT 1 FROM expenses WHERE expense_account_id = account_id AND expense_vendor_id > 0) ORDER BY account_name ASC");
                                while ($row = mysqli_fetch_assoc($sql_filter)) {
                                    $option_id = intval($row['account_id']); ?>
                                    <option <?= $account_filter === $option_id ? 'selected' : '' ?> value="<?= $option_id ?>"><?= escapeHtml($row['account_name']) ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-credit-card"></i></span>
                            <select class="form-select select2" name="method" onchange="this.form.submit()" aria-label="Paid by">
                                <option value="">- Any payment method -</option>
                                <?php
                                $sql_filter = mysqli_query($mysqli, "SELECT DISTINCT expense_payment_method FROM expenses WHERE expense_vendor_id > 0 AND COALESCE(expense_payment_method, '') <> '' ORDER BY expense_payment_method ASC");
                                while ($row = mysqli_fetch_row($sql_filter)) { ?>
                                    <option <?= $method_filter === $row[0] ? 'selected' : '' ?> value="<?= escapeHtml($row[0]) ?>"><?= escapeHtml($row[0]) ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        <!-- Cut the list - counted over what the filters above leave -->
        <nav class="nav nav-pills itflow-asset-tabs mt-2 flex-nowrap overflow-auto">
            <?php foreach ($shows as $key => $s) {
                $alarm = in_array($key, ['no_receipt', 'to_reimburse'], true) && $counts[$key]; ?>
                <a class="nav-link text-nowrap <?= $show === $key ? 'active' : '' ?>" href="<?= escapeHtml($filter_url(['show' => $key])) ?>"<?= !empty($s['title']) ? ' title="' . escapeHtml($s['title']) . '"' : '' ?>>
                    <?php if (!empty($s['icon'])) { ?><i class="fas fa-fw <?= $s['icon'] ?> me-1"></i><?php } ?><?= $s['label'] ?>
                    <span class="badge rounded-pill ms-1 <?= $alarm ? 'text-bg-warning' : '' ?>"><?= $counts[$key] ?></span>
                </a>
            <?php } ?>
        </nav>
        <?php if ($recurring_filter) { ?>
            <div class="small text-body-secondary mt-2">
                <i class="fas fa-filter me-1"></i>Recorded by <?= $recurring_filter_row ? '<a href="#" class="ajax-modal" data-modal-size="lg" data-modal-url="modals/recurring_expense/recurring_expense.php?id=' . $recurring_filter . '">' . escapeHtml(recurringExpenseLabel($recurring_filter_row)) . '</a>' : 'a recurring expense' ?>
                <a class="ms-2" href="<?= escapeHtml($filter_url(['recurring_expense_id' => ''])) ?>">Clear</a>
            </div>
        <?php } ?>
        <?php if ($paid_by_filter) { ?>
            <div class="small text-body-secondary mt-2">
                <i class="fas fa-filter me-1"></i>Paid personally by <?= escapeHtml((string) getFieldById('users', $paid_by_filter, 'user_name')) ?>
                <a class="ms-2" href="<?= escapeHtml($filter_url(['paid_by' => ''])) ?>">Clear</a>
            </div>
        <?php } ?>
    </div>

    <?php if ($owed) { ?>
        <!-- Who is owed, to pay back -->
        <div class="list-group list-group-flush border-bottom">
            <?php foreach ($owed as $owed_id => $agent) { ?>
                <div class="list-group-item d-flex flex-wrap align-items-center gap-2 bg-warning-subtle">
                    <i class="fas fa-hand-holding-usd text-warning-emphasis"></i>
                    <span class="flex-grow-1"><strong><?= escapeHtml($agent['user_name']) ?></strong> is owed <strong class="itflow-money"><?= $stat_money($agent['total']) ?></strong>
                        <span class="text-body-secondary">for <?= $agent['count'] ?> expense<?= $agent['count'] === 1 ? '' : 's' ?><?= $agent['oldest'] > 1 ? ', the oldest ' . $agent['oldest'] . ' days ago' : ($agent['oldest'] === 1 ? ', the oldest yesterday' : ' from today') ?></span></span>
                    <?php if ($can_edit) { ?>
                        <button type="button" class="btn btn-sm btn-success ajax-modal" data-modal-size="lg" data-modal-url="modals/expense/expense_reimburse.php?user_id=<?= $owed_id ?>"><i class="fas fa-hand-holding-usd me-1"></i>Reimburse</button>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>
    <?php } ?>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 itflow-billing-table">
            <thead class="<?= $num_rows[0] ? '' : 'd-none' ?> text-nowrap table-light">
            <tr>
                <?php if ($can_edit) { ?>
                    <th class="checkbox-column border-end">
                        <div class="form-check">
                            <input class="form-check-input" id="selectAllCheckbox" type="checkbox" onclick="checkAll(this)" title="Select all on this page">
                        </div>
                    </th>
                <?php } ?>
                <th><a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=expense_date&order=<?= $disp ?>">Date <?php if ($sort == 'expense_date') { echo $order_icon; } ?></a></th>
                <th><a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=vendor_name&order=<?= $disp ?>">Vendor <?php if ($sort == 'vendor_name') { echo $order_icon; } ?></a></th>
                <th><a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=category_name&order=<?= $disp ?>">Category <?php if ($sort == 'category_name') { echo $order_icon; } ?></a></th>
                <th><a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=client_name&order=<?= $disp ?>">Client <?php if ($sort == 'client_name') { echo $order_icon; } ?></a></th>
                <th><a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=account_name&order=<?= $disp ?>">Paid from <?php if ($sort == 'account_name') { echo $order_icon; } ?></a></th>
                <th class="text-end"><a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=expense_amount&order=<?= $disp ?>">Amount <?php if ($sort == 'expense_amount') { echo $order_icon; } ?></a></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($expenses as $row) {
                $expense_id = intval($row['expense_id']);
                $row_amount = floatval($row['expense_amount']);
                $row_currency = (string) $row['expense_currency_code'];
                $row_open = 'data-modal-size="lg" data-modal-url="modals/expense/expense.php?id=' . $expense_id . '"';
                ?>
                <tr>
                    <?php if ($can_edit) { ?>
                        <td class="checkbox-column bg-light border-end">
                            <div class="form-check">
                                <input class="form-check-input bulk-select" type="checkbox" name="expense_ids[]" value="<?= $expense_id ?>" aria-label="Select this expense">
                            </div>
                        </td>
                    <?php } ?>
                    <td class="text-nowrap">
                        <a class="fw-semibold ajax-modal" href="#" <?= $row_open ?>><?= escapeHtml($row['expense_date']) ?></a>
                        <?php if ($row['expense_receipt']) { ?>
                            <a class="text-success ms-1" href="expense_receipt.php?expense_id=<?= $expense_id ?>&amp;action=view" target="_blank" title="Open the receipt"><i class="fas fa-paperclip"></i></a>
                        <?php } ?>
                        <?php if (intval($row['expense_recurring_expense_id'])) { ?>
                            <a class="text-body-secondary small ms-1 ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/recurring_expense/recurring_expense.php?id=<?= intval($row['expense_recurring_expense_id']) ?>" title="Recorded by a recurring expense"><i class="fas fa-redo-alt"></i></a>
                        <?php } ?>
                    </td>
                    <td class="itflow-expense-vendor">
                        <?= vendorLink($row['expense_vendor_id'], (string) $row['vendor_name'], ['page' => true, 'archived' => !empty($row['vendor_archived_at'])]) ?>
                        <div class="small text-body-secondary text-truncate" style="max-width: 16rem;" title="<?= escapeHtml((string) $row['expense_description']) ?>">
                            <?= escapeHtml(implode(' · ', array_filter([truncate((string) $row['expense_description'], 80), $row['expense_reference'] ? '#' . $row['expense_reference'] : '']))) ?: '&nbsp;' ?>
                        </div>
                    </td>
                    <td><div class="text-truncate" style="max-width: 10rem;" title="<?= escapeHtml((string) $row['category_name']) ?>"><?= expenseCategoryDot($row['category_color']) ?><?= escapeHtml($row['category_name'] ?: '-') ?></div></td>
                    <td>
                        <?php if (intval($row['expense_client_id'])) { ?>
                            <div class="text-truncate" style="max-width: 10rem;"><a class="text-reset" href="<?= escapeHtml($filter_url(['client' => intval($row['expense_client_id'])])) ?>" title="<?= escapeHtml((string) $row['client_name']) ?> - their expenses"><?= escapeHtml($row['client_name']) ?></a></div>
                            <?php if ($row['expense_invoice_id']) {
                                $row_invoice = $row['invoice_number'] !== null ? $row['invoice_prefix'] . $row['invoice_number'] : 'an invoice'; ?>
                                <div class="small"><?php if ($can_sales && $row['invoice_number'] !== null) { ?><a class="text-success-emphasis" href="invoice.php?invoice_id=<?= intval($row['expense_invoice_id']) ?>" title="Billed on to them"><i class="fas fa-check-circle me-1"></i>Billed <?= escapeHtml($row_invoice) ?></a><?php } else { ?><span class="text-success-emphasis"><i class="fas fa-check-circle me-1"></i>Billed <?= escapeHtml($row_invoice) ?></span><?php } ?></div>
                            <?php } elseif (intval($row['expense_billable']) && $row_amount > 0) { ?>
                                <div class="small"><?php if ($can_sales) { ?><a class="text-warning-emphasis" href="unbilled_time.php?client_id=<?= intval($row['expense_client_id']) ?>" title="Billable at <?= escapeHtml(rtrim(rtrim(number_format(floatval($row['expense_markup']), 2, '.', ''), '0'), '.')) ?>% markup - bill it from Unbilled Time"><i class="fas fa-file-invoice-dollar me-1"></i>To bill</a><?php } else { ?><span class="text-warning-emphasis"><i class="fas fa-file-invoice-dollar me-1"></i>To bill</span><?php } ?></div>
                            <?php } ?>
                        <?php } else { ?>
                            <span class="text-body-secondary">-</span>
                        <?php } ?>
                    </td>
                    <td class="text-nowrap">
                        <?php if (intval($row['expense_paid_by'])) { ?>
                            <a class="text-reset" href="<?= escapeHtml($filter_url(['paid_by' => intval($row['expense_paid_by'])])) ?>" title="Paid personally - their expenses"><i class="fas fa-hand-holding-usd text-body-secondary me-1"></i><?= escapeHtml($row['paid_by_name'] ?: 'An agent') ?></a>
                            <?php if ($row['expense_reimbursement_id']) { ?>
                                <div class="small"><a class="text-success-emphasis ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/expense/expense_reimbursement.php?id=<?= intval($row['expense_reimbursement_id']) ?>"><i class="fas fa-check-circle me-1"></i>Paid back</a></div>
                            <?php } elseif ($row_amount > 0) { ?>
                                <div class="small text-warning-emphasis">To reimburse</div>
                            <?php } ?>
                        <?php } else { ?>
                            <?= escapeHtml($row['account_name'] ?: '-') ?>
                            <?php if ($row['expense_payment_method']) { ?><div class="small text-body-secondary"><?= escapeHtml($row['expense_payment_method']) ?></div><?php } ?>
                        <?php } ?>
                    </td>
                    <td class="text-end itflow-money text-nowrap">
                        <span class="fw-semibold <?= $row_amount < 0 ? 'text-success-emphasis' : '' ?>"><?= escapeHtml(billingMoney($row_amount, $row_currency)) ?></span>
                        <?php if ($row_amount < 0) { ?><div class="small text-body-secondary">Refund</div><?php } ?>
                    </td>
                    <td class="text-end">
                        <div class="dropdown dropstart">
                            <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item ajax-modal" href="#" <?= $row_open ?>><i class="fas fa-fw fa-eye text-secondary me-2"></i>Open</a>
                                <?php if ($row['expense_receipt']) { ?>
                                    <a class="dropdown-item" href="expense_receipt.php?expense_id=<?= $expense_id ?>"><i class="fas fa-fw fa-download text-secondary me-2"></i>Download Receipt</a>
                                <?php } ?>
                                <?php if ($can_edit) { ?>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/expense/expense_edit.php?id=<?= $expense_id ?>"><i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit</a>
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/expense/expense_copy.php?id=<?= $expense_id ?>"><i class="fas fa-fw fa-copy text-secondary me-2"></i>Copy</a>
                                    <?php if ($row_amount > 0) { ?>
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/expense/expense_refund.php?id=<?= $expense_id ?>"><i class="fas fa-fw fa-undo-alt text-secondary me-2"></i>Refund</a>
                                    <?php } ?>
                                <?php } ?>
                                <?php if ($can_delete) { ?>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_expense=<?= $expense_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                        data-confirm-text="<?= escapeHtml(expenseLabel($row)) ?> goes for good<?= $row['expense_receipt'] ? ', with its receipt' : '' ?>.">
                                        <i class="fas fa-fw fa-trash me-2"></i>Delete
                                    </a>
                                <?php } ?>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
            <?php if ($num_rows[0]) { ?>
                <tfoot>
                <tr class="table-light">
                    <td colspan="<?= $can_edit ? 6 : 5 ?>" class="text-body-secondary small">
                        <?= intval($num_rows[0]) ?> expense<?= intval($num_rows[0]) === 1 ? '' : 's' ?><?= $any_filter ? ' match' . (intval($num_rows[0]) === 1 ? 'es' : '') : '' ?><?= $num_rows[0] > count($expenses) ? ' - the total is for all of them, not just this page' : '' ?>
                    </td>
                    <td class="text-end itflow-money fw-semibold text-nowrap"><?= $stat_money($listed_total) ?></td>
                    <td></td>
                </tr>
                </tfoot>
            <?php } ?>
        </table>
    </div>
    <?php
    $empty_state = [
        'thing' => 'expenses',
        'icon' => 'fa-shopping-cart',
        'filtered' => $q || $vendor_filter || $category_filter || $account_filter || $client_filter || $method_filter !== '' || $recurring_filter || $paid_by_filter || $dtf !== '1970-01-01',
        'views' => ['show'],
        'view' => emptyStatePill($shows, $show, '', ['no_receipt', 'to_bill', 'to_reimburse']),
        'text' => 'What the business spends - enter them as they come, or bring in a bank or card statement.',
        'actions' => $can_edit ? [
            ['label' => 'New Expense', 'modal' => 'modals/expense/expense_add.php' . ($vendor_filter ? "?vendor_id=$vendor_filter" : ''), 'size' => 'lg'],
            ['label' => 'Import Statement', 'href' => 'expense_import.php', 'icon' => 'fa-file-import'],
        ] : [],
    ];
    require_once "../includes/filter_footer.php";
    ?>
</div>

<?php if (!empty($_GET['open'])) { ?>
    <script>
        // Opened from search - straight to that expense's quick look
        document.addEventListener('DOMContentLoaded', function () {
            var open = document.createElement('a');
            open.className = 'ajax-modal';
            open.href = '#';
            open.dataset.modalSize = 'lg';
            open.dataset.modalUrl = 'modals/expense/expense.php?id=<?= intval($_GET['open']) ?>';
            document.body.appendChild(open);
            open.click();
            open.remove();
        });
    </script>
<?php } ?>

<script src="../js/bulk_actions.js"></script>

<?php require_once "../includes/footer.php";
