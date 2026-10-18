<?php

/*
 * Income - the money that comes in: payments against invoices, and other
 * income that isn't for an invoice (a deposit, a rebate, interest, a
 * referral fee). functions/income.php has what counts and how.
 *
 * Along the top: this month against the same days of last month, the year
 * so far with its monthly average, what the running recurring invoices bill
 * a month, and what is still owed on invoices. Pills cut the list to
 * invoice payments, other income, what was paid online and what has no
 * category; filters by client, category, account, payment method and date.
 * A row opens its quick look - a payment's with its invoice, other income's
 * with Edit and Copy.
 *
 * Takes ?client= (or the client pages' ?client_id=), ?category=,
 * ?account=, ?method=, ?show= (payments, other, online, uncategorised; the
 * older ?type=payment and revenue too), the date filter and ?open=
 * (payment-12 or revenue-7) to open one.
 */

// Default Column Sortby/Order Filter
$sort = "income_date";
$order = "DESC";

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $client_page = true;
} else {
    require_once "includes/inc_all.php";
    $client_page = false;
    $client_id = 0;
}

enforceUserPermission('module_financial');

$can_edit = incomeCanEdit();
$can_delete = incomeCanDelete();
$can_bulk = $can_edit;
$can_sales = lookupUserPermission('module_sales') >= 1;

$client_filter = $client_page ? intval($client_id) : intval($_GET['client'] ?? 0);
$category_filter = intval($_GET['category'] ?? 0);
$account_filter = intval($_GET['account'] ?? 0);
$method_filter = (string) ($_GET['method'] ?? '');
$shows = incomeShows();
$show = incomeShowFrom($_GET);
$more_filters = $account_filter || $method_filter !== '';

// Only these sort - filter_header.php hands through any lowercase word
$sortable_columns = ['income_date' => 'income.income_date', 'income_amount' => 'income.income_amount', 'client_name' => 'income.client_name',
    'category_name' => 'income.category_name', 'account_name' => 'income.account_name', 'income_type' => 'income.income_type'];
if (!isset($sortable_columns[$sort])) {
    $sort = 'income_date';
}

// Everything but the pill, then the pill - the pill counts are over what the rest leave
$filter_src = array_merge($_GET, ['dtf' => $dtf, 'dtt' => $dtt, 'client' => $client_filter, 'show' => $show, 'type' => '']);
$where = incomeFiltersSql($filter_src, $summary, false);
$show_query = $show !== '' ? 'AND ' . $shows[$show]['where'] : '';
$union = incomeUnionSql();

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS income.* FROM ($union) AS income
    WHERE $where $show_query
    ORDER BY {$sortable_columns[$sort]} $order, income.income_created_at DESC, income.income_id DESC LIMIT $record_from, $record_to");
$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));
$rows = [];
while ($row = mysqli_fetch_assoc($sql)) {
    $rows[] = $row;
}

// The pills, and the total of what is listed
$count_sql = [];
foreach ($shows as $key => $s) {
    $count_sql[] = $key === '' ? 'COUNT(*)' : "COALESCE(SUM({$s['where']}), 0)";
}
$row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT " . implode(', ', $count_sql) . ", COALESCE(SUM(CASE WHEN 1 $show_query THEN income.income_amount END), 0)
    FROM ($union) AS income WHERE $where"));
$counts = [];
foreach (array_keys($shows) as $i => $key) {
    $counts[$key] = intval($row[$i]);
}
$listed_total = floatval($row[count($shows)]);

$stats = incomeStats($client_page ? $client_id : 0);
$money = function ($amount, $currency = '') {
    return escapeHtml(billingMoney($amount, $currency));
};
$filter_url = function ($changes) use ($client_page, $client_id) {
    $params = array_merge($_GET, $changes);
    unset($params['page'], $params['type']);
    if ($client_page) {
        $params['client_id'] = $client_id;
    }
    return '?' . http_build_query(array_filter($params, function ($v) { return $v !== '' && $v !== null; }));
};
$date_url = function ($from, $to, $changes = []) use ($filter_url) {
    return $filter_url(array_merge(['canned_date' => 'custom', 'dtf' => $from, 'dtt' => $to], $changes));
};
$any_filter = $q || ($client_filter && !$client_page) || $category_filter || $account_filter || $method_filter !== '' || $show !== '' || $dtf !== '1970-01-01';
$add_url = 'modals/revenue/revenue_add.php' . ($client_page ? "?client_id=$client_id" : '');
$invoices_url = 'invoices.php?' . ($client_page ? "client_id=$client_id&" : '') . 'status=Unpaid';

?>

<!-- The money in -->
<div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-3">
        <a class="itflow-billing-stat <?= $dtf === $stats['month_start'] && $show === '' ? 'active' : '' ?>" href="<?= escapeHtml($date_url($stats['month_start'], date('Y-m-t'), ['show' => ''])) ?>">
            <div class="itflow-billing-stat-label"><i class="fas fa-calendar-alt me-1"></i>This month</div>
            <div class="itflow-billing-stat-value text-success-emphasis"><?= $money($stats['month']) ?></div>
            <div class="itflow-billing-stat-note">
                <?php if ($stats['change'] !== null) { ?>
                    <span class="<?= $stats['change'] >= 0 ? 'text-success-emphasis' : 'text-warning-emphasis' ?>"><i class="fas fa-arrow-<?= $stats['change'] >= 0 ? 'up' : 'down' ?> me-1"></i><?= abs($stats['change']) ?>%</span> on the same days of <?= date('F', strtotime($stats['last_start'])) ?>
                <?php } else { ?>
                    Nothing in by now last month
                <?php } ?>
            </div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-3">
        <a class="itflow-billing-stat <?= $dtf === $stats['year_start'] && $show === '' ? 'active' : '' ?>" href="<?= escapeHtml($date_url($stats['year_start'], date('Y-12-31'), ['show' => ''])) ?>">
            <div class="itflow-billing-stat-label"><i class="fas fa-chart-line me-1"></i><?= date('Y') ?> so far</div>
            <div class="itflow-billing-stat-value"><?= $money($stats['year']) ?></div>
            <div class="itflow-billing-stat-note"><?= $money($stats['year_average']) ?> a month on average</div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-3">
        <a class="itflow-billing-stat" href="<?= $can_sales ? 'recurring_invoices.php' . ($client_page ? "?client_id=$client_id" : '') : '#' ?>">
            <div class="itflow-billing-stat-label"><i class="fas fa-redo-alt me-1"></i>Recurring</div>
            <div class="itflow-billing-stat-value"><?= $money($stats['recurring']) ?><span class="fs-6 fw-normal text-body-secondary"> a month</span></div>
            <div class="itflow-billing-stat-note"><?= $stats['recurring_count'] ? 'From ' . $stats['recurring_count'] . ' recurring invoice' . ($stats['recurring_count'] === 1 ? '' : 's') : 'No recurring invoices running' ?></div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-3">
        <a class="itflow-billing-stat" href="<?= $can_sales ? escapeHtml($invoices_url) : '#' ?>">
            <div class="itflow-billing-stat-label"><i class="fas fa-hourglass-half me-1"></i>Still owed</div>
            <div class="itflow-billing-stat-value <?= $stats['overdue'] > 0 ? 'text-danger-emphasis' : '' ?>"><?= $money($stats['owed']) ?></div>
            <div class="itflow-billing-stat-note">
                <?= $stats['owed_count'] ? 'On ' . $stats['owed_count'] . ' invoice' . ($stats['owed_count'] === 1 ? '' : 's') : 'Every invoice is paid' ?><?= $stats['overdue'] > 0 ? ' &middot; <span class="text-danger-emphasis">' . $money($stats['overdue']) . ' late</span>' : '' ?>
            </div>
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-hand-holding-usd me-2"></i>Income</h3>
        <div class="card-tools">
            <div class="btn-group">
                <?php if ($can_edit) { ?>
                    <button type="button" class="btn btn-primary ajax-modal" data-modal-size="lg" data-modal-url="<?= $add_url ?>"><i class="fas fa-plus me-2"></i>New Income</button>
                <?php } ?>
                <button type="button" class="btn <?= $can_edit ? 'btn-primary dropdown-toggle-split' : 'btn-light' ?> dropdown-toggle" data-bs-toggle="dropdown" aria-label="More"><?= $can_edit ? '' : '<i class="fas fa-ellipsis-h"></i>' ?></button>
                <div class="dropdown-menu dropdown-menu-end">
                    <?php if ($can_edit) { ?>
                        <a class="dropdown-item" href="income_import.php"><i class="fa fa-fw fa-file-import text-secondary me-2"></i>Import Deposits</a>
                    <?php } ?>
                    <a class="dropdown-item ajax-modal" href="#"
                        data-modal-url="<?= buildExportModalUrl('modals/income/income_export.php', ['client_id', 'client', 'category', 'account', 'method', 'show', 'type', 'q'], ['dtf' => $dtf, 'dtt' => $dtt]) ?>">
                        <i class="fa fa-fw fa-download text-secondary me-2"></i>Export
                    </a>
                    <?php if (reportCanOpen('income_summary')) { ?>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="reports/income_summary.php"><i class="fa fa-fw fa-chart-bar text-secondary me-2"></i>Income Summary</a>
                        <a class="dropdown-item" href="reports/income_by_client.php<?= $client_page ? "?client_id=$client_id" : '' ?>"><i class="fa fa-fw fa-users text-secondary me-2"></i>Income by Client</a>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card-header py-3">
        <form autocomplete="off">
            <input type="hidden" name="show" value="<?= escapeHtml($show) ?>">
            <?php if ($client_page) { ?>
                <input type="hidden" name="client_id" value="<?= $client_id ?>">
            <?php } ?>
            <div class="row g-2 align-items-center">
                <div class="col-lg-4 col-md-6">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search income" title="What for, invoice number, reference, amount, client, category, account">
                        <button class="btn btn-outline-secondary <?= $more_filters ? 'active' : '' ?>" title="More filters" type="button" data-bs-toggle="collapse" data-bs-target="#incomeMoreFilters"><i class="fas fa-filter"></i></button>
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
                <div class="col-lg-2 col-md-6">
                    <input type="text" id="dateFilter" class="form-control" autocomplete="off" title="Date it came in" aria-label="Date it came in">
                    <input type="hidden" name="canned_date" id="canned_date" value="<?= escapeHtml($_GET['canned_date'] ?? '') ?>">
                    <input type="hidden" name="dtf" id="dtf" value="<?= escapeHtml($dtf ?? '') ?>">
                    <input type="hidden" name="dtt" id="dtt" value="<?= escapeHtml($dtt ?? '') ?>">
                </div>
                <div class="col-lg-<?= $client_page ? 6 : 3 ?> col-md-6">
                    <div class="d-flex gap-2">
                        <div class="flex-grow-1 min-w-0">
                            <select class="form-select select2" name="category" onchange="this.form.submit()" aria-label="Category">
                                <option value="">- Any category -</option>
                                <?= categoryFilterOptionsHtml('Income', $category_filter, "SELECT revenue_category_id FROM revenues UNION SELECT invoice_category_id FROM payments JOIN invoices ON invoice_id = payment_invoice_id", ['exclude' => ['Account Transfer']]) ?>
                            </select>
                        </div>
                        <?php if ($client_page && $can_bulk) { ?>
                            <?php require __DIR__ . '/includes/income_bulk_menu.php'; ?>
                        <?php } ?>
                    </div>
                </div>
                <?php if (!$client_page) { ?>
                    <div class="col-lg-3 col-md-6">
                        <div class="d-flex gap-2">
                            <div class="flex-grow-1 min-w-0">
                                <select class="form-select select2" name="client" onchange="this.form.submit()" aria-label="Client">
                                    <option value="">- Any client -</option>
                                    <?php
                                    $sql_filter = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients
                                        WHERE (EXISTS (SELECT 1 FROM invoices JOIN payments ON payment_invoice_id = invoice_id WHERE invoice_client_id = client_id)
                                            OR EXISTS (SELECT 1 FROM revenues WHERE revenue_client_id = client_id)) " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
                                    while ($row = mysqli_fetch_assoc($sql_filter)) {
                                        $option_id = intval($row['client_id']); ?>
                                        <option <?= $client_filter === $option_id ? 'selected' : '' ?> value="<?= $option_id ?>"<?= selectOptionClient($row['client_id'], $row['client_name'], $row['client_abbreviation'] ?? '') ?>><?= escapeHtml($row['client_name']) ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <?php if ($can_bulk) { ?>
                                <?php require __DIR__ . '/includes/income_bulk_menu.php'; ?>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>
            </div>
            <div class="collapse <?= $more_filters ? 'show' : '' ?>" id="incomeMoreFilters">
                <div class="row g-2 mt-0">
                    <div class="col-lg-3 col-md-6">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-piggy-bank"></i></span>
                            <select class="form-select select2" name="account" onchange="this.form.submit()" aria-label="Paid into">
                                <option value="">- Any account -</option>
                                <?php foreach (paymentAccounts($account_filter) as $option_id => $account) { ?>
                                    <option <?= $account_filter === $option_id ? 'selected' : '' ?> value="<?= $option_id ?>"><?= escapeHtml($account['account_name']) ?></option>
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
                                $sql_filter = mysqli_query($mysqli, "SELECT DISTINCT payment_method AS method FROM payments WHERE COALESCE(payment_method, '') <> ''
                                    UNION SELECT DISTINCT revenue_payment_method FROM revenues WHERE COALESCE(revenue_payment_method, '') <> '' ORDER BY method ASC");
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
                $alarm = $key === 'uncategorised' && $counts[$key]; ?>
                <a class="nav-link text-nowrap <?= $show === $key ? 'active' : '' ?>" href="<?= escapeHtml($filter_url(['show' => $key])) ?>"<?= !empty($s['title']) ? ' title="' . escapeHtml($s['title']) . '"' : '' ?>>
                    <?php if (!empty($s['icon'])) { ?><i class="fas fa-fw <?= $s['icon'] ?> me-1"></i><?php } ?><?= $s['label'] ?>
                    <span class="badge rounded-pill ms-1 <?= $alarm ? 'text-bg-warning' : '' ?>"><?= $counts[$key] ?></span>
                </a>
            <?php } ?>
        </nav>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 itflow-billing-table">
            <thead class="<?= $num_rows[0] ? '' : 'd-none' ?> text-nowrap table-light">
            <tr>
                <?php if ($can_bulk) { ?>
                    <th class="checkbox-column border-end">
                        <div class="form-check">
                            <input class="form-check-input" id="selectAllCheckbox" type="checkbox" onclick="checkAll(this)" title="Select all on this page">
                        </div>
                    </th>
                <?php } ?>
                <th><a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=income_date&order=<?= $disp ?>">Date <?php if ($sort == 'income_date') { echo $order_icon; } ?></a></th>
                <th><a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=income_type&order=<?= $disp ?>">From <?php if ($sort == 'income_type') { echo $order_icon; } ?></a></th>
                <th><a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=category_name&order=<?= $disp ?>">Category <?php if ($sort == 'category_name') { echo $order_icon; } ?></a></th>
                <?php if (!$client_page) { ?>
                    <th><a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=client_name&order=<?= $disp ?>">Client <?php if ($sort == 'client_name') { echo $order_icon; } ?></a></th>
                <?php } ?>
                <th><a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=account_name&order=<?= $disp ?>">Paid into <?php if ($sort == 'account_name') { echo $order_icon; } ?></a></th>
                <th class="text-end"><a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=income_amount&order=<?= $disp ?>">Amount <?php if ($sort == 'income_amount') { echo $order_icon; } ?></a></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row) {
                $is_payment = $row['income_type'] === 'payment';
                $income_id = intval($row['income_id']);
                $ref = $row['income_type'] . '-' . $income_id;
                $row_open = 'data-modal-size="lg" data-modal-url="modals/' . ($is_payment ? 'payment/payment' : 'revenue/revenue') . '.php?id=' . $income_id . '"';
                $row_amount = floatval($row['income_amount']);
                $row_currency = (string) $row['income_currency_code'];
                $row_client_id = intval($row['income_client_id']);
                $first_line = trim(strtok((string) $row['income_description'], "\n"));
                ?>
                <tr>
                    <?php if ($can_bulk) { ?>
                        <td class="checkbox-column bg-light border-end">
                            <div class="form-check">
                                <input class="form-check-input bulk-select" type="checkbox" name="income_ids[]" value="<?= $ref ?>" aria-label="Select this">
                            </div>
                        </td>
                    <?php } ?>
                    <td class="text-nowrap"><a class="fw-semibold ajax-modal" href="#" <?= $row_open ?>><?= escapeHtml($row['income_date']) ?></a></td>
                    <td class="itflow-income-from">
                        <?php if ($is_payment) { ?>
                            <div class="text-truncate">
                                <i class="fas fa-file-invoice-dollar text-body-secondary me-1" title="Invoice payment"></i>
                                <?php if ($row['invoice_number'] !== null) { ?>
                                    <?php if ($can_sales) { ?><a href="invoice.php?invoice_id=<?= intval($row['income_invoice_id']) ?>"><?= escapeHtml($row['invoice_prefix'] . $row['invoice_number']) ?></a><?php } else { ?><?= escapeHtml($row['invoice_prefix'] . $row['invoice_number']) ?><?php } ?>
                                    <?php if ($first_line !== '') { ?><span class="text-body-secondary"> - <?= escapeHtml($first_line) ?></span><?php } ?>
                                <?php } else { ?>
                                    <span class="text-body-secondary">An invoice since deleted</span>
                                <?php } ?>
                            </div>
                        <?php } else { ?>
                            <div class="text-truncate"><i class="fas fa-hand-holding-usd text-body-secondary me-1" title="Other income"></i><?= escapeHtml($first_line !== '' ? $first_line : ($row['category_name'] ?: 'Other income')) ?></div>
                        <?php } ?>
                        <div class="small text-body-secondary text-truncate">
                            <?= escapeHtml(implode(' · ', array_filter([(string) $row['income_method'], $row['income_reference'] ? '#' . $row['income_reference'] : '']))) ?: '&nbsp;' ?>
                            <?php if (intval($row['income_online'])) { ?><span class="badge rounded-pill bg-info-subtle text-info-emphasis fw-normal ms-1"><i class="fas fa-globe me-1"></i>Online</span><?php } ?>
                        </div>
                    </td>
                    <td>
                        <?php if (intval($row['income_category_id']) && $row['category_name'] !== null) { ?>
                            <div class="text-truncate" style="max-width: 10rem;" title="<?= escapeHtml($row['category_name']) ?>"><?= expenseCategoryDot($row['category_color'], $row['category_icon'] ?? '') ?><?= escapeHtml($row['category_name']) ?></div>
                        <?php } else { ?>
                            <span class="text-warning-emphasis small" title="Not in a category - the reports show it as No category">No category</span>
                        <?php } ?>
                    </td>
                    <?php if (!$client_page) { ?>
                        <td>
                            <?php if ($row_client_id && $row['client_name'] !== null) { ?>
                                <div class="text-truncate" style="max-width: 12rem;"><a class="text-reset" href="<?= escapeHtml($filter_url(['client' => $row_client_id])) ?>" title="<?= escapeHtml($row['client_name']) ?> - what came in from them"><?= escapeHtml($row['client_name']) ?></a></div>
                            <?php } else { ?>
                                <span class="text-body-secondary">-</span>
                            <?php } ?>
                        </td>
                    <?php } ?>
                    <td>
                        <div class="text-truncate" style="max-width: 10rem;"><?= escapeHtml($row['account_name'] ?: '-') ?></div>
                        <?php if ($row['account_archived_at']) { ?><div class="small text-body-secondary">Archived</div><?php } ?>
                    </td>
                    <td class="text-end itflow-money text-nowrap">
                        <span class="fw-semibold <?= $row_amount < 0 ? 'text-danger-emphasis' : '' ?>"><?= $money($row_amount, $row_currency) ?></span>
                    </td>
                    <td class="text-end">
                        <div class="dropdown dropstart">
                            <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item ajax-modal" href="#" <?= $row_open ?>><i class="fas fa-fw fa-eye text-secondary me-2"></i>Open</a>
                                <?php if ($is_payment) { ?>
                                    <?php if ($can_sales && $row['invoice_number'] !== null) { ?>
                                        <a class="dropdown-item" href="invoice.php?invoice_id=<?= intval($row['income_invoice_id']) ?>"><i class="fas fa-fw fa-file-invoice text-secondary me-2"></i>Open Invoice</a>
                                    <?php } ?>
                                    <?php if (incomeCanEditPayments() && $row['invoice_number'] !== null) { ?>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/payment/payment_edit.php?id=<?= $income_id ?>"><i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit</a>
                                        <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_payment=<?= $income_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                            data-confirm-text="The payment of <?= $money($row_amount, $row_currency) ?> comes off <?= escapeHtml($row['invoice_prefix'] . $row['invoice_number']) ?>, which is owed again.">
                                            <i class="fas fa-fw fa-trash me-2"></i>Delete
                                        </a>
                                    <?php } ?>
                                <?php } else { ?>
                                    <?php if ($can_edit) { ?>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/revenue/revenue_edit.php?id=<?= $income_id ?>"><i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit</a>
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/revenue/revenue_copy.php?id=<?= $income_id ?>"><i class="fas fa-fw fa-copy text-secondary me-2"></i>Copy</a>
                                    <?php } ?>
                                    <?php if ($can_delete) { ?>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_revenue=<?= $income_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                            data-confirm-text="<?= escapeHtml(($first_line !== '' ? $first_line : 'This income') . ' - ' . billingMoney($row_amount, $row_currency)) ?> goes for good.">
                                            <i class="fas fa-fw fa-trash me-2"></i>Delete
                                        </a>
                                    <?php } ?>
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
                    <td colspan="<?= ($can_bulk ? 5 : 4) + ($client_page ? 0 : 1) ?>" class="text-body-secondary small">
                        <?php $listed = intval($num_rows[0]); ?>
                        <?= $listed ?> <?= $show === 'payments' || $show === 'online' ? ($listed === 1 ? 'payment' : 'payments') : ($show === 'other' ? 'other income' : ($listed === 1 ? 'payment or other income' : 'payments and other income')) ?><?= $listed > count($rows) ? ' - the total is for all of them, not just this page' : '' ?>
                    </td>
                    <td class="text-end fw-semibold text-nowrap itflow-money"><?= $money($listed_total) ?></td>
                    <td></td>
                </tr>
                </tfoot>
            <?php } ?>
        </table>
    </div>
    <?php
    $empty_state = [
        'thing' => 'income',
        'icon' => 'fa-hand-holding-usd',
        'filtered' => $q || ($client_filter && !$client_page) || $category_filter || $account_filter || $method_filter !== '' || $dtf !== '1970-01-01',
        'views' => ['show'],
        'view' => emptyStatePill($shows, $show, '', ['uncategorised']),
        'match_title' => 'No income matches',
        'text' => "A payment is added on its invoice. Money in that isn't for an invoice is New Income here.",
        'actions' => $can_edit ? [
            ['label' => 'New Income', 'modal' => $add_url, 'size' => 'lg'],
            ['label' => 'Import Deposits', 'href' => 'income_import.php', 'icon' => 'fa-file-import'],
        ] : [],
    ];
    require_once "../includes/filter_footer.php";
    ?>
</div>

<?php if (preg_match('/^(payment|revenue)-(\d+)$/i', (string) ($_GET['open'] ?? ''), $open)) { ?>
    <script>
        // Opened from elsewhere - straight to its quick look
        document.addEventListener('DOMContentLoaded', function () {
            var open = document.createElement('a');
            open.className = 'ajax-modal';
            open.href = '#';
            open.dataset.modalSize = 'lg';
            open.dataset.modalUrl = 'modals/<?= strtolower($open[1]) === 'payment' ? 'payment/payment' : 'revenue/revenue' ?>.php?id=<?= intval($open[2]) ?>';
            document.body.appendChild(open);
            open.click();
            open.remove();
        });
    </script>
<?php } ?>

<script src="../js/bulk_actions.js"></script>

<?php require_once "../includes/footer.php";
