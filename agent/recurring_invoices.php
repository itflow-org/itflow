<?php

/*
 * Recurring Invoices - one client's agreements, or every one the agent can
 * see.
 *
 * Along the top, the recurring revenue: what the running ones bring in a
 * month (quarterly ones counted as a third, yearly as a twelfth), what bills over the next 30
 * days, how many are running, and how many need looking at - running, but
 * with a next date that has passed, so they won't bill until it moves on.
 * Pills: Active (the default - ?status=active), Next 7 days, Late, Paused
 * (?status=inactive, as before) and All.
 */

// Default Column Sortby/Order Filter
$sort = "recurring_invoice_next_date";
$order = "ASC";

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $client_query = "AND recurring_invoice_client_id = $client_id";
    $client_url = "client_id=$client_id&";
} else {
    require_once "includes/inc_all.php";
    $client_query = '';
    $client_url = '';
}

// Perms
enforceUserPermission('module_sales');

$can_edit = lookupUserPermission('module_sales') >= 2;
$can_delete = lookupUserPermission('module_sales') >= 3;

// Client Filter (all clients)
$client = '';
if (!$client_url && !empty($_GET['client'])) {
    $client = intval($_GET['client']);
    $client_query = "AND recurring_invoice_client_id = $client";
}

$statuses = [
    'active' => ['label' => 'Active', 'icon' => 'fa-play', 'where' => 'recurring_invoice_status = 1'],
    'soon' => ['label' => 'Next 7 days', 'icon' => 'fa-calendar-day', 'where' => 'recurring_invoice_status = 1 AND recurring_invoice_next_date BETWEEN CURDATE() AND CURDATE() + INTERVAL 7 DAY',
        'title' => 'Running, and billing in the next week'],
    'late' => ['label' => 'Late', 'icon' => 'fa-exclamation-triangle', 'where' => 'recurring_invoice_status = 1 AND recurring_invoice_next_date < CURDATE()',
        'title' => "Running, but the next date has passed - they won't bill until it moves on"],
    'inactive' => ['label' => 'Paused', 'icon' => 'fa-pause', 'where' => 'recurring_invoice_status = 0'],
    'all' => ['label' => 'All', 'where' => ''],
];
$status = isset($statuses[$_GET['status'] ?? '']) ? ($_GET['status'] ?? '') : 'active';
$status_query = $statuses[$status]['where'] ? 'AND ' . $statuses[$status]['where'] : '';

$sortable_columns = ['recurring_invoice_number' => 'recurring_invoice_number', 'recurring_invoice_scope' => 'recurring_invoice_scope', 'client_name' => 'client_name',
    'recurring_invoice_frequency' => 'recurring_invoice_frequency', 'recurring_invoice_amount' => 'recurring_invoice_amount', 'monthly' => 'monthly',
    'recurring_invoice_next_date' => 'recurring_invoice_next_date', 'recurring_invoice_last_sent' => 'recurring_invoice_last_sent', 'category_name' => 'category_name'];
if (!isset($sortable_columns[$sort])) {
    $sort = 'recurring_invoice_next_date';
}

$monthly_sql = recurringInvoiceMonthlySql();
$scope_query = clientScopeSql('recurring_invoice_client_id');
$search_query = "AND (CONCAT(recurring_invoice_prefix, recurring_invoice_number) LIKE '%$q%' OR recurring_invoice_frequency LIKE '%$q%' OR recurring_invoice_scope LIKE '%$q%' OR client_name LIKE '%$q%' OR category_name LIKE '%$q%')";
$filters = "AND recurring_invoice_archived_at IS NULL $scope_query $client_query $search_query";
$joins = "LEFT JOIN clients ON recurring_invoice_client_id = client_id LEFT JOIN categories ON recurring_invoice_category_id = category_id";

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS recurring_invoices.*, client_name, category_name, recurring_payment_id, $monthly_sql AS monthly
    FROM recurring_invoices $joins
    LEFT JOIN recurring_payments ON recurring_payment_recurring_invoice_id = recurring_invoice_id
    WHERE 1 $filters $status_query
    ORDER BY {$sortable_columns[$sort]} $order, recurring_invoice_id ASC LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

$recurring_invoices = [];
while ($row = mysqli_fetch_assoc($sql)) {
    $recurring_invoices[] = $row;
}

$count_sql = [];
foreach ($statuses as $key => $s) {
    $count_sql[] = $s['where'] === '' ? 'COUNT(*)' : "COALESCE(SUM({$s['where']}), 0)";
}
$row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT " . implode(', ', $count_sql) . " FROM recurring_invoices $joins WHERE 1 $filters"));
$counts = [];
foreach (array_keys($statuses) as $i => $key) {
    $counts[$key] = intval($row[$i]);
}

// The recurring revenue along the top - everything in this scope
$revenue = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT
        COALESCE(SUM(CASE WHEN recurring_invoice_status = 1 THEN $monthly_sql END), 0) AS mrr,
        COALESCE(SUM(CASE WHEN recurring_invoice_status = 1 AND recurring_invoice_next_date BETWEEN CURDATE() AND CURDATE() + INTERVAL 30 DAY THEN recurring_invoice_amount END), 0) AS next30,
        COALESCE(SUM(CASE WHEN recurring_invoice_status = 1 AND recurring_invoice_next_date BETWEEN CURDATE() AND CURDATE() + INTERVAL 30 DAY THEN 1 END), 0) AS next30_count,
        COALESCE(SUM(recurring_invoice_status = 1), 0) AS active_count,
        COALESCE(SUM(recurring_invoice_status = 0), 0) AS paused_count,
        COALESCE(SUM(recurring_invoice_status = 1 AND recurring_invoice_next_date < CURDATE()), 0) AS late_count
    FROM recurring_invoices WHERE recurring_invoice_archived_at IS NULL $scope_query $client_query"));

$filter_url = function ($changes) {
    $params = array_merge($_GET, $changes);
    unset($params['page']);
    return '?' . http_build_query(array_filter($params, function ($v) { return $v !== '' && $v !== null; }));
};

$stat_money = function ($amount) use ($session_company_currency) {
    return escapeHtml(billingMoney($amount, $session_company_currency));
};

?>

<!-- The recurring revenue -->
<div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-3">
        <a class="itflow-billing-stat" href="<?= escapeHtml($filter_url(['status' => 'active'])) ?>">
            <div class="itflow-billing-stat-label"><i class="fas fa-sync-alt me-1"></i>Monthly recurring</div>
            <div class="itflow-billing-stat-value text-success-emphasis"><?= $stat_money($revenue['mrr']) ?></div>
            <div class="itflow-billing-stat-note"><?= $stat_money($revenue['mrr'] * 12) ?> a year</div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="itflow-billing-stat">
            <div class="itflow-billing-stat-label"><i class="fas fa-calendar-alt me-1"></i>Bills in 30 days</div>
            <div class="itflow-billing-stat-value"><?= $stat_money($revenue['next30']) ?></div>
            <div class="itflow-billing-stat-note"><?= intval($revenue['next30_count']) ?> invoice<?= intval($revenue['next30_count']) === 1 ? '' : 's' ?> to be made</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <a class="itflow-billing-stat <?= $status === 'active' ? 'active' : '' ?>" href="<?= escapeHtml($filter_url(['status' => 'active'])) ?>">
            <div class="itflow-billing-stat-label"><i class="fas fa-play me-1"></i>Running</div>
            <div class="itflow-billing-stat-value"><?= intval($revenue['active_count']) ?></div>
            <div class="itflow-billing-stat-note"><?= intval($revenue['paused_count']) ?> paused</div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-3">
        <a class="itflow-billing-stat <?= $status === 'late' ? 'active' : '' ?>" href="<?= escapeHtml($filter_url(['status' => 'late'])) ?>">
            <div class="itflow-billing-stat-label"><i class="fas fa-exclamation-triangle me-1"></i>Need a look</div>
            <div class="itflow-billing-stat-value <?= $revenue['late_count'] ? 'text-danger-emphasis' : '' ?>"><?= intval($revenue['late_count']) ?></div>
            <div class="itflow-billing-stat-note"><?= $revenue['late_count'] ? "Next date passed - won't bill" : 'All on schedule' ?></div>
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fa fa-fw fa-redo-alt me-2"></i>Recurring Invoices</h3>
        <div class="card-tools">
            <div class="btn-group">
                <?php if ($can_edit) { ?>
                    <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/recurring_invoice/recurring_invoice_add.php?<?= $client_url ?>"><i class="fas fa-plus me-2"></i>New Recurring Invoice</button>
                <?php } ?>
                <?php if ($num_rows[0] > 0) { ?>
                    <button type="button" class="btn btn-primary dropdown-toggle <?= $can_edit ? 'dropdown-toggle-split' : '' ?>" data-bs-toggle="dropdown"><?= $can_edit ? '' : '<i class="fas fa-ellipsis-h"></i>' ?></button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= buildExportModalUrl('modals/recurring_invoice/recurring_invoice_export.php', ['client_id', 'q'], ['status' => in_array($status, ['active', 'inactive'], true) ? $status : '', 'client_id' => $client ?: '']) ?>">
                            <i class="fa fa-fw fa-download text-secondary me-2"></i>Export
                        </a>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>

    <div class="card-header py-3">
        <form autocomplete="off">
            <?php if ($client_url) { ?>
                <input type="hidden" name="client_id" value="<?= $client_id ?>">
            <?php } ?>
            <input type="hidden" name="status" value="<?= escapeHtml($status) ?>">
            <div class="row g-2 align-items-center">
                <div class="<?= $client_url ? 'col-lg-6' : 'col-lg-5' ?> col-md-6">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search number, scope, client, category...">
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
                <?php if (!$client_url) { ?>
                    <div class="col-lg-3 col-md-6">
                        <select class="form-select select2" name="client" onchange="this.form.submit()">
                            <option value="">- All Clients -</option>
                            <?php
                            $sql_clients_filter = mysqli_query($mysqli, "SELECT DISTINCT client_id, client_name, client_abbreviation FROM clients
                                JOIN recurring_invoices ON recurring_invoice_client_id = client_id WHERE 1 " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
                            while ($row = mysqli_fetch_assoc($sql_clients_filter)) {
                                $client_option_id = intval($row['client_id']); ?>
                                <option <?= $client === $client_option_id ? 'selected' : '' ?> value="<?= $client_option_id ?>"<?= selectOptionClient($row['client_id'], $row['client_name'], $row['client_abbreviation'] ?? '') ?>><?= escapeHtml($row['client_name']) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                <?php } ?>
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
                <th class="ps-3"><a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=recurring_invoice_number&order=<?= $disp ?>">Recurring invoice <?php if ($sort == 'recurring_invoice_number') { echo $order_icon; } ?></a></th>
                <?php if (!$client_url) { ?>
                    <th><a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=client_name&order=<?= $disp ?>">Client <?php if ($sort == 'client_name') { echo $order_icon; } ?></a></th>
                <?php } ?>
                <th class="text-end"><a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=recurring_invoice_amount&order=<?= $disp ?>">Amount <?php if ($sort == 'recurring_invoice_amount') { echo $order_icon; } ?></a></th>
                <th class="text-end"><a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=monthly&order=<?= $disp ?>">A month <?php if ($sort == 'monthly') { echo $order_icon; } ?></a></th>
                <th><a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=recurring_invoice_next_date&order=<?= $disp ?>">Next <?php if ($sort == 'recurring_invoice_next_date') { echo $order_icon; } ?></a></th>
                <th><a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=recurring_invoice_last_sent&order=<?= $disp ?>">Last <?php if ($sort == 'recurring_invoice_last_sent') { echo $order_icon; } ?></a></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($recurring_invoices as $row) {
                $recurring_invoice_id = intval($row['recurring_invoice_id']);
                $row_client_id = intval($row['recurring_invoice_client_id']);
                $row_number = billingNumber('recurring', $row);
                $row_active = intval($row['recurring_invoice_status']) === 1;
                $row_currency = (string) $row['recurring_invoice_currency_code'];
                $row_url = "recurring_invoice.php?{$client_url}recurring_invoice_id=$recurring_invoice_id";
                $row_frequency = recurringInvoiceFrequencies()[$row['recurring_invoice_frequency']] ?? ucfirst((string) $row['recurring_invoice_frequency']);
                ?>
                <tr class="<?= $row_active ? '' : 'text-body-secondary' ?>">
                    <td class="ps-3 itflow-billing-number">
                        <a class="fw-semibold" href="<?= $row_url ?>"><?= escapeHtml($row_number) ?></a>
                        <?php if (intval($row['recurring_payment_id'])) { ?><i class="fas fa-wallet text-success small ms-1" title="Autopay - charged to a card on file"></i><?php } ?>
                        <?php $row_seats = recurringSeatLines($recurring_invoice_id, $row_client_id);
                        if ($row_seats) {
                            $row_drift = count(array_filter($row_seats, function ($line) { return $line['seat_drift']; })); ?>
                            <i class="fas fa-users small ms-1 <?= $row_drift ? 'text-warning' : 'text-primary' ?>" title="<?= $row_drift ? 'Seats changed - the next invoice bills the new counts' : 'Bills per user or per device - counted for each invoice' ?>"></i>
                        <?php } ?>
                        <div class="small text-body-secondary text-truncate" style="max-width: 24rem;"><?= escapeHtml(implode(' · ', array_filter([$row['recurring_invoice_scope'], $row['category_name']]))) ?: '&nbsp;' ?></div>
                    </td>
                    <?php if (!$client_url) { ?>
                        <td><a class="text-reset" href="recurring_invoices.php?client_id=<?= $row_client_id ?>"><?= escapeHtml($row['client_name']) ?></a></td>
                    <?php } ?>
                    <td class="text-end itflow-money">
                        <span class="fw-semibold"><?= escapeHtml(billingMoney($row['recurring_invoice_amount'], $row_currency)) ?></span>
                        <div class="small text-body-secondary"><?= escapeHtml($row_frequency) ?></div>
                    </td>
                    <td class="text-end itflow-money"><?= escapeHtml(billingMoney($row['monthly'], $row_currency)) ?></td>
                    <td class="text-nowrap">
                        <?= $row_active ? escapeHtml($row['recurring_invoice_next_date']) : '' ?>
                        <div><?= recurringInvoiceNextPill($row) ?></div>
                    </td>
                    <td class="text-nowrap small"><?= $row['recurring_invoice_last_sent'] ? escapeHtml($row['recurring_invoice_last_sent']) : '<span class="text-body-secondary">Not yet</span>' ?></td>
                    <td class="text-end">
                        <div class="dropdown dropstart">
                            <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item" href="<?= $row_url ?>"><i class="fas fa-fw fa-eye text-secondary me-2"></i>Open</a>
                                <a class="dropdown-item" href="invoices.php?client_id=<?= $row_client_id ?>&recurring_invoice_id=<?= $recurring_invoice_id ?>"><i class="fas fa-fw fa-file-invoice-dollar text-secondary me-2"></i>Invoices Made</a>
                                <?php if ($can_edit) { ?>
                                    <div class="dropdown-divider"></div>
                                    <?php if ($row_active && $row['recurring_invoice_next_date'] < date('Y-m-d')) { ?>
                                        <a class="dropdown-item confirm-link" href="post.php?recurring_invoice_catch_up=<?= $recurring_invoice_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                            data-confirm-title="Skip the missed runs?" data-confirm-text="The next date moves on to <?= escapeHtml(recurringInvoiceCatchUp($row)) ?>. Nothing is billed for the runs it missed." data-confirm-button="Move on">
                                            <i class="fas fa-fw fa-forward text-secondary me-2"></i>Move On to <?= escapeHtml(recurringInvoiceCatchUp($row)) ?>
                                        </a>
                                    <?php } ?>
                                    <?php if ($row_active) { ?>
                                        <a class="dropdown-item confirm-link" href="post.php?recurring_invoice_set_status=<?= $recurring_invoice_id ?>&status=0&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                            data-confirm-title="Pause <?= escapeHtml($row_number) ?>?" data-confirm-text="No invoices are made from it until it is resumed." data-confirm-button="Pause">
                                            <i class="fas fa-fw fa-pause text-secondary me-2"></i>Pause
                                        </a>
                                    <?php } else { ?>
                                        <a class="dropdown-item" href="post.php?recurring_invoice_set_status=<?= $recurring_invoice_id ?>&status=1&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                            <i class="fas fa-fw fa-play text-secondary me-2"></i>Resume
                                        </a>
                                    <?php } ?>
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/recurring_invoice/recurring_invoice_edit.php?id=<?= $recurring_invoice_id ?>">
                                        <i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit
                                    </a>
                                <?php } ?>
                                <?php if ($can_delete) { ?>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_recurring_invoice=<?= $recurring_invoice_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                        data-confirm-text="<?= escapeHtml($row_number) ?> goes for good, with its lines, autopay and history. The invoices it made stay.">
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
    <?php if (!$num_rows[0]) { ?>
        <div class="itflow-asset-empty">
            <i class="fas fa-redo-alt fa-2x mb-2 opacity-50"></i>
            <div><?= $q || $status !== 'active' ? 'No recurring invoices match.' : 'No recurring invoices running.' ?></div>
        </div>
    <?php } ?>
    <?php require_once "../includes/filter_footer.php"; ?>
</div>

<?php require_once "../includes/footer.php";
