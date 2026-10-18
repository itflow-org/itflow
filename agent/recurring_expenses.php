<?php

/*
 * Recurring Expenses - what the business is committed to paying, month in,
 * month out: rent, colocation, licensing, insurance, leases.
 *
 * Along the top: what the running ones come to a month (quarterly ones as a
 * third, yearly as a twelfth), what is recorded over the next 30 days, how
 * many are running, and how many need a look - running, but with a next
 * date that has passed. Pills: Active (the default), Next 7 days, Late,
 * Paused, Ended and All. Filter by vendor, category and client. A row opens
 * its quick look, with Record Now, Pause and the expenses it recorded.
 */

// Default Column Sortby/Order Filter
$sort = "recurring_expense_next_date";
$order = "ASC";

require_once "includes/inc_all.php";

// Perms
enforceUserPermission('module_financial');

$can_edit = lookupUserPermission('module_financial') >= 2;
$can_delete = lookupUserPermission('module_financial') >= 3;

$vendor_filter = intval($_GET['vendor'] ?? 0);
$category_filter = intval($_GET['category'] ?? 0);
$client_filter = intval($_GET['client'] ?? 0);
$statuses = recurringExpenseShows();
$status = isset($statuses[$_GET['status'] ?? '']) ? (string) $_GET['status'] : 'active';
$status_query = $statuses[$status]['where'] ? 'AND ' . $statuses[$status]['where'] : '';

// Only these sort - filter_header.php hands through any lowercase word
$sortable_columns = ['recurring_expense_next_date' => 'recurring_expense_next_date', 'vendor_name' => 'vendor_name', 'category_name' => 'category_name',
    'client_name' => 'client_name', 'recurring_expense_amount' => 'recurring_expense_amount', 'monthly' => 'monthly', 'recurring_expense_last_sent' => 'recurring_expense_last_sent'];
if (!isset($sortable_columns[$sort])) {
    $sort = 'recurring_expense_next_date';
}

$joins = "LEFT JOIN vendors ON recurring_expense_vendor_id = vendor_id
    LEFT JOIN categories ON recurring_expense_category_id = category_id
    LEFT JOIN accounts ON recurring_expense_account_id = account_id
    LEFT JOIN clients ON recurring_expense_client_id = client_id";
$where = recurringExpenseFiltersSql($_GET);
$monthly_sql = expenseRecurringMonthlySql();

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS recurring_expenses.*, vendor_name, vendor_archived_at, category_name, category_color, category_icon, account_name, client_name,
        $monthly_sql AS monthly, recurring_invoice_prefix, recurring_invoice_number
    FROM recurring_expenses $joins
    LEFT JOIN recurring_invoices ON recurring_invoice_id = recurring_expense_recurring_invoice_id
    WHERE $where $status_query
    ORDER BY {$sortable_columns[$sort]} $order, recurring_expense_id ASC LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

$recurring_expenses = [];
while ($row = mysqli_fetch_assoc($sql)) {
    $recurring_expenses[] = $row;
}

// The pills - over what the filters leave
$count_sql = [];
foreach ($statuses as $key => $s) {
    $count_sql[] = $s['where'] === '' ? 'COUNT(*)' : "COALESCE(SUM({$s['where']}), 0)";
}
$row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT " . implode(', ', $count_sql) . " FROM recurring_expenses $joins WHERE $where"));
$counts = [];
foreach (array_keys($statuses) as $i => $key) {
    $counts[$key] = intval($row[$i]);
}

// The money along the top - every recurring expense the agent can see, not narrowed by search or pill
$scope = "recurring_expense_archived_at IS NULL " . clientScopeSql('recurring_expense_client_id');
$money = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT
        COALESCE(SUM(CASE WHEN recurring_expense_status = 1 THEN $monthly_sql END), 0) AS monthly,
        COALESCE(SUM(recurring_expense_status = 1), 0) AS running,
        COALESCE(SUM(recurring_expense_status = 0), 0) AS stopped,
        COALESCE(SUM(recurring_expense_status = 1 AND recurring_expense_next_date < CURDATE()), 0) AS late
    FROM recurring_expenses WHERE $scope"));
$soon_to = date('Y-m-d', strtotime('+30 days'));
$soon_amount = 0.0;
$soon_runs = 0;
$sql_soon = mysqli_query($mysqli, "SELECT recurring_expenses.* FROM recurring_expenses WHERE $scope AND recurring_expense_status = 1 AND recurring_expense_next_date <= '$soon_to'");
while ($soon = mysqli_fetch_assoc($sql_soon)) {
    $runs = count(recurringExpenseRunsUntil($soon, $soon_to));
    $soon_runs += $runs;
    $soon_amount += $runs * floatval($soon['recurring_expense_amount']);
}

$filter_url = function ($changes) {
    $params = array_merge($_GET, $changes);
    unset($params['page']);
    return '?' . http_build_query(array_filter($params, function ($v) { return $v !== '' && $v !== null; }));
};
$stat_money = function ($amount) use ($session_company_currency) {
    return escapeHtml(billingMoney($amount, $session_company_currency));
};
$any_filter = $q || $vendor_filter || $category_filter || $client_filter;

?>

<!-- What is committed -->
<div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-3">
        <a class="itflow-billing-stat" href="<?= escapeHtml($filter_url(['status' => 'active'])) ?>">
            <div class="itflow-billing-stat-label"><i class="fas fa-sync-alt me-1"></i>A month</div>
            <div class="itflow-billing-stat-value"><?= $stat_money($money['monthly']) ?></div>
            <div class="itflow-billing-stat-note"><?= $stat_money($money['monthly'] * 12) ?> a year</div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-3">
        <a class="itflow-billing-stat <?= $status === 'soon' ? 'active' : '' ?>" href="<?= escapeHtml($filter_url(['status' => 'soon'])) ?>">
            <div class="itflow-billing-stat-label"><i class="fas fa-calendar-alt me-1"></i>Next 30 days</div>
            <div class="itflow-billing-stat-value"><?= $stat_money($soon_amount) ?></div>
            <div class="itflow-billing-stat-note"><?= $soon_runs ?> to be recorded</div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-3">
        <a class="itflow-billing-stat <?= $status === 'active' ? 'active' : '' ?>" href="<?= escapeHtml($filter_url(['status' => 'active'])) ?>">
            <div class="itflow-billing-stat-label"><i class="fas fa-play me-1"></i>Running</div>
            <div class="itflow-billing-stat-value"><?= intval($money['running']) ?></div>
            <div class="itflow-billing-stat-note"><?= intval($money['stopped']) ?> paused or ended</div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-3">
        <a class="itflow-billing-stat <?= $status === 'late' ? 'active' : '' ?>" href="<?= escapeHtml($filter_url(['status' => 'late'])) ?>">
            <div class="itflow-billing-stat-label"><i class="fas fa-exclamation-triangle me-1"></i>Need a look</div>
            <div class="itflow-billing-stat-value <?= $money['late'] ? 'text-danger-emphasis' : '' ?>"><?= intval($money['late']) ?></div>
            <div class="itflow-billing-stat-note"><?= $money['late'] ? 'Next date passed' : 'All on schedule' ?></div>
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-redo-alt me-2"></i>Recurring Expenses</h3>
        <div class="card-tools">
            <div class="btn-group">
                <?php if ($can_edit) { ?>
                    <button type="button" class="btn btn-primary ajax-modal" data-modal-size="lg" data-modal-url="modals/recurring_expense/recurring_expense_add.php<?= $vendor_filter ? "?vendor_id=$vendor_filter" : '' ?>"><i class="fas fa-plus me-2"></i>New Recurring Expense</button>
                <?php } ?>
                <button type="button" class="btn btn-primary dropdown-toggle <?= $can_edit ? 'dropdown-toggle-split' : '' ?>" data-bs-toggle="dropdown"><?= $can_edit ? '' : '<i class="fas fa-ellipsis-h"></i>' ?></button>
                <div class="dropdown-menu dropdown-menu-end">
                    <a class="dropdown-item" href="expenses.php"><i class="fa fa-fw fa-shopping-cart text-secondary me-2"></i>Expenses</a>
                    <?php if (reportCanOpen('expense_by_vendor')) { ?>
                        <a class="dropdown-item" href="reports/expense_by_vendor.php"><i class="fa fa-fw fa-chart-bar text-secondary me-2"></i>Spend by Vendor</a>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card-header py-3">
        <form autocomplete="off">
            <input type="hidden" name="status" value="<?= escapeHtml($status) ?>">
            <div class="row g-2 align-items-center">
                <div class="col-lg-3 col-md-6">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search recurring expenses" title="Vendor, description, reference, amount, client, category">
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <select class="form-select select2" name="vendor" onchange="this.form.submit()" aria-label="Vendor">
                        <option value="">- Any vendor -</option>
                        <?php
                        $sql_filter = mysqli_query($mysqli, "SELECT vendor_id, vendor_name, vendor_description FROM vendors
                            WHERE EXISTS (SELECT 1 FROM recurring_expenses WHERE recurring_expense_vendor_id = vendor_id AND recurring_expense_archived_at IS NULL) ORDER BY vendor_name ASC");
                        while ($row = mysqli_fetch_assoc($sql_filter)) {
                            $option_id = intval($row['vendor_id']); ?>
                            <option <?= $vendor_filter === $option_id ? 'selected' : '' ?> value="<?= $option_id ?>"<?= selectOptionVendor($row['vendor_name'], $row['vendor_description']) ?>><?= escapeHtml($row['vendor_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-lg-3 col-md-6">
                    <select class="form-select select2" name="category" onchange="this.form.submit()" aria-label="Category">
                        <option value="">- Any category -</option>
                        <?= categoryFilterOptionsHtml('Expense', $category_filter, "SELECT DISTINCT recurring_expense_category_id FROM recurring_expenses WHERE recurring_expense_archived_at IS NULL") ?>
                    </select>
                </div>
                <div class="col-lg-3 col-md-6">
                    <select class="form-select select2" name="client" onchange="this.form.submit()" aria-label="Client">
                        <option value="">- Any client -</option>
                        <?php
                        $sql_filter = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients
                            WHERE EXISTS (SELECT 1 FROM recurring_expenses WHERE recurring_expense_client_id = client_id AND recurring_expense_archived_at IS NULL) " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
                        while ($row = mysqli_fetch_assoc($sql_filter)) {
                            $option_id = intval($row['client_id']); ?>
                            <option <?= $client_filter === $option_id ? 'selected' : '' ?> value="<?= $option_id ?>"<?= selectOptionClient($row['client_id'], $row['client_name'], $row['client_abbreviation'] ?? '') ?>><?= escapeHtml($row['client_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
        </form>
        <nav class="nav nav-pills itflow-asset-tabs mt-2 flex-nowrap overflow-auto">
            <?php foreach ($statuses as $key => $s) {
                $alarm = $key === 'late' && $counts[$key]; ?>
                <a class="nav-link text-nowrap <?= $status === $key ? 'active' : '' ?>" href="<?= escapeHtml($filter_url(['status' => $key])) ?>"<?= !empty($s['title']) ? ' title="' . escapeHtml($s['title']) . '"' : '' ?>>
                    <?php if (!empty($s['icon'])) { ?><i class="fas fa-fw <?= $s['icon'] ?> me-1"></i><?php } ?><?= $s['label'] ?>
                    <span class="badge rounded-pill ms-1 <?= $alarm ? 'text-bg-danger' : '' ?>"><?= $counts[$key] ?></span>
                </a>
            <?php } ?>
        </nav>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 itflow-billing-table">
            <thead class="<?= $num_rows[0] ? '' : 'd-none' ?> text-nowrap table-light">
            <tr>
                <th class="ps-3"><a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=vendor_name&order=<?= $disp ?>">Vendor <?php if ($sort == 'vendor_name') { echo $order_icon; } ?></a></th>
                <th><a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=category_name&order=<?= $disp ?>">Category <?php if ($sort == 'category_name') { echo $order_icon; } ?></a></th>
                <th><a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=client_name&order=<?= $disp ?>">Client <?php if ($sort == 'client_name') { echo $order_icon; } ?></a></th>
                <th class="text-end"><a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=recurring_expense_amount&order=<?= $disp ?>">Amount <?php if ($sort == 'recurring_expense_amount') { echo $order_icon; } ?></a></th>
                <th class="text-end"><a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=monthly&order=<?= $disp ?>">A month <?php if ($sort == 'monthly') { echo $order_icon; } ?></a></th>
                <th><a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=recurring_expense_next_date&order=<?= $disp ?>">Next <?php if ($sort == 'recurring_expense_next_date') { echo $order_icon; } ?></a></th>
                <th><a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=recurring_expense_last_sent&order=<?= $disp ?>">Last <?php if ($sort == 'recurring_expense_last_sent') { echo $order_icon; } ?></a></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($recurring_expenses as $row) {
                $id = intval($row['recurring_expense_id']);
                $row_state = recurringExpenseState($row);
                $row_currency = (string) $row['recurring_expense_currency_code'];
                $row_next = (string) $row['recurring_expense_next_date'];
                $row_open = 'data-modal-size="lg" data-modal-url="modals/recurring_expense/recurring_expense.php?id=' . $id . '"';
                $row_post = function ($action, $extra = '') use ($id) {
                    return "post.php?$action=$id$extra&amp;csrf_token=" . $_SESSION['csrf_token'];
                };
                ?>
                <tr class="<?= $row_state === 'active' ? '' : 'text-body-secondary' ?>">
                    <td class="ps-3">
                        <div class="d-flex align-items-center gap-1">
                            <?= vendorTile((string) $row['vendor_name'], 'xs') ?>
                            <a class="fw-semibold ajax-modal text-truncate" style="max-width: 16rem;" href="#" <?= $row_open ?>><?= escapeHtml($row['vendor_name'] ?: 'No vendor') ?></a>
                        </div>
                        <div class="small text-body-secondary text-truncate" style="max-width: 18rem;" title="<?= escapeHtml((string) $row['recurring_expense_description']) ?>"><?= escapeHtml((string) $row['recurring_expense_description']) ?: '&nbsp;' ?></div>
                    </td>
                    <td><div class="text-truncate" style="max-width: 11rem;" title="<?= escapeHtml((string) $row['category_name']) ?>"><?= expenseCategoryDot($row['category_color'], $row['category_icon'] ?? '') ?><?= escapeHtml($row['category_name'] ?: '-') ?></div></td>
                    <td>
                        <?php if (intval($row['recurring_expense_client_id'])) { ?>
                            <div class="text-truncate" style="max-width: 10rem;"><a class="text-reset" href="<?= escapeHtml($filter_url(['client' => intval($row['recurring_expense_client_id'])])) ?>"><?= escapeHtml($row['client_name']) ?></a></div>
                            <?php if ($row['recurring_invoice_number'] !== null) { ?>
                                <div class="small text-success-emphasis text-truncate" style="max-width: 10rem;" title="Covered by their recurring invoice"><i class="fas fa-shield-alt me-1"></i>Covered by <?= escapeHtml($row['recurring_invoice_prefix'] . $row['recurring_invoice_number']) ?></div>
                            <?php } elseif (intval($row['recurring_expense_billable'])) { ?>
                                <div class="small text-warning-emphasis" title="Each one it records is billed on to them at <?= escapeHtml(rtrim(rtrim(number_format(floatval($row['recurring_expense_markup']), 2, '.', ''), '0'), '.')) ?>% markup"><i class="fas fa-file-invoice-dollar me-1"></i>Billed on</div>
                            <?php } ?>
                        <?php } else { ?>
                            <span class="text-body-secondary">-</span>
                        <?php } ?>
                    </td>
                    <td class="text-end itflow-money text-nowrap">
                        <span class="fw-semibold"><?= escapeHtml(billingMoney($row['recurring_expense_amount'], $row_currency)) ?></span>
                        <div class="small text-body-secondary"><?= escapeHtml(recurringInvoiceFrequencies()[$row['recurring_expense_frequency']] ?? 'Monthly') ?></div>
                    </td>
                    <td class="text-end itflow-money text-nowrap"><?= escapeHtml(billingMoney($row['monthly'], $row_currency)) ?></td>
                    <td class="text-nowrap">
                        <?= $row_state === 'ended' ? '' : escapeHtml($row_next) ?>
                        <div><?= recurringExpenseNextPill($row) ?></div>
                        <?php if ($row['recurring_expense_end_date'] && $row_state !== 'ended') { ?><div class="small text-body-secondary" title="Its last run is on or before this date">Ends <?= escapeHtml($row['recurring_expense_end_date']) ?></div><?php } ?>
                    </td>
                    <td class="text-nowrap small">
                        <?php if ($row['recurring_expense_last_sent']) { ?>
                            <a class="text-reset" href="expenses.php?recurring_expense_id=<?= $id ?>" title="The expenses it recorded"><?= escapeHtml($row['recurring_expense_last_sent']) ?></a>
                        <?php } else { ?>
                            <span class="text-body-secondary">Not yet</span>
                        <?php } ?>
                    </td>
                    <td class="text-end">
                        <div class="dropdown dropstart">
                            <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item ajax-modal" href="#" <?= $row_open ?>><i class="fas fa-fw fa-eye text-secondary me-2"></i>Open</a>
                                <a class="dropdown-item" href="expenses.php?recurring_expense_id=<?= $id ?>"><i class="fas fa-fw fa-shopping-cart text-secondary me-2"></i>Expenses Recorded</a>
                                <?php if ($can_edit) { ?>
                                    <div class="dropdown-divider"></div>
                                    <?php if ($row_state !== 'ended') { ?>
                                        <a class="dropdown-item confirm-link" href="<?= $row_post('recurring_expense_record', '&amp;run=' . $row_next) ?>"
                                            data-confirm-title="Record the <?= escapeHtml(date('M j', strtotime($row_next))) ?> run now?"
                                            data-confirm-text="An expense of <?= escapeHtml(billingMoney($row['recurring_expense_amount'], $row_currency)) ?>, and the next one moves to <?= escapeHtml(date('M j, Y', strtotime(recurringExpenseAfter($row, $row_next)))) ?>." data-confirm-button="Record">
                                            <i class="fas fa-fw fa-bolt text-secondary me-2"></i>Record Now
                                        </a>
                                    <?php } ?>
                                    <?php if ($row_state === 'active' && $row_next < date('Y-m-d')) { ?>
                                        <a class="dropdown-item confirm-link" href="<?= $row_post('recurring_expense_catch_up') ?>"
                                            data-confirm-title="Skip the missed runs?" data-confirm-text="The next date moves on to <?= escapeHtml(recurringExpenseCatchUp($row)) ?>. Nothing is recorded for the runs it missed." data-confirm-button="Move on">
                                            <i class="fas fa-fw fa-forward text-secondary me-2"></i>Move On to <?= escapeHtml(recurringExpenseCatchUp($row)) ?>
                                        </a>
                                    <?php } ?>
                                    <?php if ($row_state === 'active') { ?>
                                        <a class="dropdown-item" href="<?= $row_post('recurring_expense_set_status', '&amp;status=0') ?>"><i class="fas fa-fw fa-pause text-secondary me-2"></i>Pause</a>
                                    <?php } elseif ($row_state === 'paused') { ?>
                                        <a class="dropdown-item" href="<?= $row_post('recurring_expense_set_status', '&amp;status=1') ?>"><i class="fas fa-fw fa-play text-secondary me-2"></i>Resume</a>
                                    <?php } ?>
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/recurring_expense/recurring_expense_edit.php?id=<?= $id ?>"><i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit</a>
                                <?php } ?>
                                <?php if ($can_delete) { ?>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item text-danger text-bold confirm-link" href="<?= $row_post('delete_recurring_expense') ?>"
                                        data-confirm-text="<?= escapeHtml(recurringExpenseLabel($row)) ?> goes for good. The expenses it recorded stay.">
                                        <i class="fas fa-fw fa-trash me-2"></i>Delete
                                    </a>
                                <?php } ?>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
    <?php
    $empty_state = [
        'thing' => 'recurring expenses',
        'icon' => 'fa-redo-alt',
        'filtered' => $any_filter,
        'views' => ['status'],
        'view' => emptyStatePill($statuses, $status, 'active', ['late']),
        'text' => 'Rent, colocation, licensing and insurance - recorded for you each time they come round.',
        'actions' => $can_edit ? [['label' => 'New Recurring Expense', 'modal' => 'modals/recurring_expense/recurring_expense_add.php' . ($vendor_filter ? "?vendor_id=$vendor_filter" : ''), 'size' => 'lg']] : [],
    ];
    require_once "../includes/filter_footer.php";
    ?>
</div>

<?php if (!empty($_GET['open'])) { ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var open = document.createElement('a');
            open.className = 'ajax-modal';
            open.href = '#';
            open.dataset.modalSize = 'lg';
            open.dataset.modalUrl = 'modals/recurring_expense/recurring_expense.php?id=<?= intval($_GET['open']) ?>';
            document.body.appendChild(open);
            open.click();
            open.remove();
        });
    </script>
<?php } ?>

<?php require_once "../includes/footer.php";
