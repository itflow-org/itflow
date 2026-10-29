<?php

/*
 * Invoices - one client's, or every invoice the agent can see.
 *
 * Along the top, the money: what is owed, what of that is late, what is
 * sitting in drafts, and what came in over the last 30 days - each opens
 * the invoices behind it. The pills filter by where an invoice stands. Each
 * row has what is still owed on it and, while it is owed, how long until
 * it is due or how late it is. ?status= takes Draft, Unpaid, Overdue,
 * Paid and Cancelled - the dashboard, client pages and reports link with
 * it. ?recurring_invoice_id= shows the invoices a recurring invoice made.
 */

// Default Column Sortby/Order Filter
$sort = "invoice_number";
$order = "DESC";

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $client_query = "AND invoice_client_id = $client_id";
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
// Taking payments and sending statements are accounting's too (billingMoneyCan())
$can_pay = billingMoneyCan('take');
$can_statement = billingMoneyCan('statement');
$mail_ready = !empty($config_smtp_provider);

// Client Filter (all clients)
$client = '';
if (!$client_url && !empty($_GET['client'])) {
    $client = intval($_GET['client']);
    $client_query = "AND invoice_client_id = $client";
}

// From a recurring invoice
$recurring_filter = intval($_GET['recurring_invoice_id'] ?? 0);
$recurring_query = $recurring_filter ? "AND invoice_recurring_invoice_id = $recurring_filter" : '';
$recurring_filter_row = $recurring_filter ? billingRow('recurring', $recurring_filter) : null;

// Hardware sold on them that isn't on the client's assets yet (functions/products.php)
$hardware_filter = !empty($_GET['hardware']);
$hardware_query = $hardware_filter ? "AND " . productHardwareInvoiceSql() . " AND invoices.invoice_id IN (SELECT invoice_items.item_invoice_id FROM invoice_items
    JOIN products ON products.product_id = invoice_items.item_product_id WHERE " . productHardwareShortSql() . ")" : '';

// Category Filter
$category_filter = intval($_GET['category'] ?? 0);
$category_query = $category_filter ? "AND invoice_category_id = $category_filter" : '';

$open_sql = "invoice_status IN ('Sent', 'Viewed', 'Partial')";
$statuses = [
    '' => ['label' => 'All', 'where' => ''],
    'Draft' => ['label' => 'Draft', 'icon' => 'fa-pencil-ruler', 'where' => "invoice_status = 'Draft'", 'title' => 'Not sent yet - the client can\'t see them'],
    'Unpaid' => ['label' => 'Unpaid', 'icon' => 'fa-hand-holding-usd', 'where' => $open_sql, 'title' => 'Sent, and not paid in full'],
    'Overdue' => ['label' => 'Overdue', 'icon' => 'fa-exclamation-circle', 'where' => "$open_sql AND invoice_due < CURDATE()", 'title' => 'Past their due date and not paid in full'],
    'Paid' => ['label' => 'Paid', 'icon' => 'fa-check-circle', 'where' => "invoice_status = 'Paid'"],
    'Cancelled' => ['label' => 'Cancelled', 'icon' => 'fa-ban', 'where' => "invoice_status IN ('Cancelled', 'Non-Billable')", 'title' => 'Cancelled, or closed off as non-billable'],
];
$status = isset($statuses[$_GET['status'] ?? '']) ? ($_GET['status'] ?? '') : '';
$status_query = $statuses[$status]['where'] ? 'AND ' . $statuses[$status]['where'] : '';

// Only these sort - filter_header.php hands through any lowercase word
$sortable_columns = ['invoice_number' => 'invoice_number', 'invoice_scope' => 'invoice_scope', 'client_name' => 'client_name', 'invoice_date' => 'invoice_date',
    'invoice_due' => 'invoice_due', 'invoice_amount' => 'invoice_amount', 'balance' => 'balance', 'invoice_status' => 'invoice_status', 'category_name' => 'category_name'];
if (!isset($sortable_columns[$sort])) {
    $sort = 'invoice_number';
}

$paid_join = "LEFT JOIN (SELECT payment_invoice_id, SUM(payment_amount) AS paid FROM payments GROUP BY payment_invoice_id) AS invoice_payments ON payment_invoice_id = invoice_id";
$scope_query = clientScopeSql('invoice_client_id');
$search_query = "AND (CONCAT(invoice_prefix, invoice_number) LIKE '%$q%' OR invoice_scope LIKE '%$q%' OR client_name LIKE '%$q%' OR invoice_status LIKE '%$q%' OR invoice_amount LIKE '%$q%' OR category_name LIKE '%$q%')";
$date_query = "AND DATE(invoice_date) BETWEEN '$dtf' AND '$dtt'";
$filters = "$scope_query $client_query $recurring_query $hardware_query $category_query $date_query $search_query";

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS invoices.*, client_name, category_name, category_color,
        recurring_invoice_prefix, recurring_invoice_number,
        COALESCE(invoice_payments.paid, 0) AS paid, invoice_amount - COALESCE(invoice_payments.paid, 0) AS balance
    FROM invoices
    LEFT JOIN clients ON invoice_client_id = client_id
    LEFT JOIN categories ON invoice_category_id = category_id
    LEFT JOIN recurring_invoices ON invoice_recurring_invoice_id = recurring_invoice_id
    $paid_join
    WHERE 1 $filters $status_query
    ORDER BY {$sortable_columns[$sort]} $order, invoice_id DESC LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

$invoices = [];
while ($row = mysqli_fetch_assoc($sql)) {
    $invoices[] = $row;
}

// How many of each, for the pills - what the filters above leave, before the pill
$count_sql = [];
foreach ($statuses as $key => $s) {
    $count_sql[] = $key === '' ? 'COUNT(*)' : "COALESCE(SUM({$s['where']}), 0)";
}
$row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT " . implode(', ', $count_sql) . " FROM invoices
    LEFT JOIN clients ON invoice_client_id = client_id LEFT JOIN categories ON invoice_category_id = category_id WHERE 1 $filters"));
$counts = [];
foreach (array_keys($statuses) as $i => $key) {
    $counts[$key] = intval($row[$i]);
}

// The money along the top - everything in this scope, not narrowed by search, date or pill
$money_scope = "$scope_query $client_query $recurring_query";
$money = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT
        COALESCE(SUM(CASE WHEN $open_sql AND balance > 0 THEN balance END), 0) AS owed,
        COALESCE(SUM(CASE WHEN $open_sql AND balance > 0 THEN 1 END), 0) AS owed_count,
        COALESCE(SUM(CASE WHEN $open_sql AND balance > 0 AND invoice_due < CURDATE() THEN balance END), 0) AS overdue,
        COALESCE(SUM(CASE WHEN $open_sql AND balance > 0 AND invoice_due < CURDATE() THEN 1 END), 0) AS overdue_count,
        COALESCE(SUM(CASE WHEN invoice_status = 'Draft' THEN invoice_amount END), 0) AS draft,
        COALESCE(SUM(CASE WHEN invoice_status = 'Draft' THEN 1 END), 0) AS draft_count
    FROM (SELECT invoice_status, invoice_due, invoice_amount, invoice_amount - COALESCE(invoice_payments.paid, 0) AS balance
        FROM invoices $paid_join WHERE 1 $money_scope) AS scoped"));
$collected = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COALESCE(SUM(payment_amount), 0) AS amount, COUNT(*) AS payments FROM payments
    JOIN invoices ON payment_invoice_id = invoice_id
    WHERE payment_date >= CURDATE() - INTERVAL 30 DAY $money_scope"));

$filter_url = function ($changes) {
    $params = array_merge($_GET, $changes);
    unset($params['page']);
    return '?' . http_build_query(array_filter($params, function ($v) { return $v !== '' && $v !== null; }));
};

// Clients with a card on file, for Charge Saved Card - one query, not one a row
$saved_card_clients = [];
$sql_saved = mysqli_query($mysqli, "SELECT DISTINCT saved_payment_client_id FROM client_saved_payment_methods
    JOIN payment_providers ON saved_payment_provider_id = payment_provider_id WHERE payment_provider_active = 1");
while ($row = mysqli_fetch_row($sql_saved)) {
    $saved_card_clients[intval($row[0])] = true;
}

$stat_money = function ($amount) use ($session_company_currency) {
    return escapeHtml(billingMoney($amount, $session_company_currency));
};

?>

<!-- The money -->
<div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-3">
        <a class="itflow-billing-stat <?= $status === 'Unpaid' ? 'active' : '' ?>" href="<?= escapeHtml($filter_url(['status' => 'Unpaid'])) ?>">
            <div class="itflow-billing-stat-label"><i class="fas fa-hand-holding-usd me-1"></i>Owed to you</div>
            <div class="itflow-billing-stat-value"><?= $stat_money($money['owed']) ?></div>
            <div class="itflow-billing-stat-note"><?= intval($money['owed_count']) ?> unpaid invoice<?= intval($money['owed_count']) === 1 ? '' : 's' ?></div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-3">
        <a class="itflow-billing-stat <?= $status === 'Overdue' ? 'active' : '' ?>" href="<?= escapeHtml($filter_url(['status' => 'Overdue'])) ?>">
            <div class="itflow-billing-stat-label"><i class="fas fa-exclamation-circle me-1"></i>Overdue</div>
            <div class="itflow-billing-stat-value <?= $money['overdue'] > 0 ? 'text-danger-emphasis' : '' ?>"><?= $stat_money($money['overdue']) ?></div>
            <div class="itflow-billing-stat-note"><?= intval($money['overdue_count']) ?> past due<?= $money['owed'] > 0 ? ' &middot; ' . round($money['overdue'] / $money['owed'] * 100) . '% of what is owed' : '' ?></div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-3">
        <a class="itflow-billing-stat <?= $status === 'Draft' ? 'active' : '' ?>" href="<?= escapeHtml($filter_url(['status' => 'Draft'])) ?>">
            <div class="itflow-billing-stat-label"><i class="fas fa-pencil-ruler me-1"></i>In drafts</div>
            <div class="itflow-billing-stat-value"><?= $stat_money($money['draft']) ?></div>
            <div class="itflow-billing-stat-note"><?= intval($money['draft_count']) ?> not sent yet</div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-3">
        <a class="itflow-billing-stat <?= $status === 'Paid' ? 'active' : '' ?>" href="<?= escapeHtml($filter_url(['status' => 'Paid'])) ?>">
            <div class="itflow-billing-stat-label"><i class="fas fa-coins me-1"></i>Collected, 30 days</div>
            <div class="itflow-billing-stat-value text-success-emphasis"><?= $stat_money($collected['amount']) ?></div>
            <div class="itflow-billing-stat-note"><?= intval($collected['payments']) ?> payment<?= intval($collected['payments']) === 1 ? '' : 's' ?></div>
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fa fa-fw fa-file-invoice-dollar me-2"></i>Invoices</h3>
        <div class="card-tools">
            <div class="btn-group">
                <?php if ($can_edit) { ?>
                    <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/invoice/invoice_add.php?<?= $client_url ?>">
                        <i class="fas fa-plus me-2"></i>New Invoice
                    </button>
                <?php } ?>
                <button type="button" class="btn btn-primary dropdown-toggle <?= $can_edit ? 'dropdown-toggle-split' : '' ?>" data-bs-toggle="dropdown"><?= $can_edit ? '' : '<i class="fas fa-ellipsis-h"></i>' ?></button>
                <div class="dropdown-menu dropdown-menu-end">
                    <a class="dropdown-item ajax-modal" href="#"
                        data-modal-url="<?= buildExportModalUrl('modals/invoice/invoice_export.php', ['client_id', 'status', 'category', 'q'], ['dtf' => $dtf, 'dtt' => $dtt, 'client_id' => $client ?: '']) ?>">
                        <i class="fa fa-fw fa-download text-secondary me-2"></i>Export
                    </a>
                    <?php if ($client_url && $can_statement && $mail_ready) { ?>
                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/client/client_statement.php?client_id=<?= $client_id ?>">
                            <i class="fa fa-fw fa-file-alt text-secondary me-2"></i>Send Statement
                        </a>
                    <?php } ?>
                    <?php if ($client_url) { ?>
                        <a class="dropdown-item" href="post.php?export_client_statement_pdf=<?= $client_id ?>&amp;csrf_token=<?= $_SESSION['csrf_token'] ?>" target="_blank">
                            <i class="fa fa-fw fa-file-pdf text-secondary me-2"></i>Statement PDF
                        </a>
                    <?php } ?>
                    <?php if ($client_url && $can_pay && $money['owed'] > 0) { ?>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/payment/payment_bulk_add.php?client_id=<?= $client_id ?>">
                            <i class="fa fa-fw fa-layer-group text-secondary me-2"></i>Batch Payment
                        </a>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card-header py-3">
        <form autocomplete="off">
            <?php if ($client_url) { ?>
                <input type="hidden" name="client_id" value="<?= $client_id ?>">
            <?php } ?>
            <input type="hidden" name="status" value="<?= escapeHtml($status) ?>">
            <?php if ($recurring_filter) { ?>
                <input type="hidden" name="recurring_invoice_id" value="<?= $recurring_filter ?>">
            <?php } ?>
            <?php if ($hardware_filter) { ?>
                <input type="hidden" name="hardware" value="1">
            <?php } ?>
            <div class="row g-2 align-items-center">
                <div class="<?= $client_url ? 'col-lg-4' : 'col-lg-3' ?> col-md-6">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search number, scope, client, amount...">
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
                <div class="col-lg-2 col-md-6">
                    <input type="text" id="dateFilter" class="form-control" autocomplete="off" title="Invoice date" aria-label="Invoice date">
                    <input type="hidden" name="canned_date" id="canned_date" value="<?= escapeHtml($_GET['canned_date'] ?? '') ?>">
                    <input type="hidden" name="dtf" id="dtf" value="<?= escapeHtml($dtf ?? '') ?>">
                    <input type="hidden" name="dtt" id="dtt" value="<?= escapeHtml($dtt ?? '') ?>">
                </div>
                <div class="col-lg-3 col-md-6">
                    <select class="form-select select2" name="category" onchange="this.form.submit()">
                        <option value="">- Any category -</option>
                        <?= categoryFilterOptionsHtml('Income', $category_filter, "SELECT DISTINCT invoice_category_id FROM invoices") ?>
                    </select>
                </div>
                <?php if (!$client_url) { ?>
                    <div class="col-lg-2 col-md-6">
                        <select class="form-select select2" name="client" onchange="this.form.submit()">
                            <option value="">- All Clients -</option>
                            <?php
                            $sql_clients_filter = mysqli_query($mysqli, "SELECT DISTINCT client_id, client_name, client_abbreviation FROM clients
                                JOIN invoices ON invoice_client_id = client_id
                                WHERE 1 " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
                            while ($row = mysqli_fetch_assoc($sql_clients_filter)) {
                                $client_option_id = intval($row['client_id']); ?>
                                <option <?= $client === $client_option_id ? 'selected' : '' ?> value="<?= $client_option_id ?>"<?= selectOptionClient($row['client_id'], $row['client_name'], $row['client_abbreviation'] ?? '') ?>><?= escapeHtml($row['client_name']) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                <?php } ?>
                <div class="<?= $client_url ? 'col-lg-3' : 'col-lg-2' ?> col-md-6">
                    <div class="d-flex justify-content-end gap-2">
                        <?php if ($can_edit) { ?>
                            <div class="dropdown" id="bulkActionButton" hidden>
                                <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-fw fa-layer-group me-2"></i>Bulk Action (<span id="selectedCount">0</span>)
                                </button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/invoice/invoice_bulk_edit_category.php" data-bulk="true">
                                        <i class="fas fa-fw fa-tag me-2"></i>Set Category
                                    </a>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </form>
        <!-- Where they stand - counted over what the filters above leave -->
        <nav class="nav nav-pills itflow-asset-tabs mt-2 flex-nowrap overflow-auto">
            <?php foreach ($statuses as $key => $s) {
                $alarm = $key === 'Overdue' && $counts[$key]; ?>
                <a class="nav-link text-nowrap <?= $status === $key ? 'active' : '' ?>" href="<?= escapeHtml($filter_url(['status' => $key])) ?>"<?= !empty($s['title']) ? ' title="' . escapeHtml($s['title']) . '"' : '' ?>>
                    <?php if (!empty($s['icon'])) { ?><i class="fas fa-fw <?= $s['icon'] ?> me-1"></i><?php } ?><?= $s['label'] ?>
                    <span class="badge rounded-pill ms-1 <?= $alarm ? 'text-bg-danger' : '' ?>"><?= $counts[$key] ?></span>
                </a>
            <?php } ?>
        </nav>
        <?php if ($recurring_filter) { ?>
            <div class="small text-body-secondary mt-2">
                <i class="fas fa-filter me-1"></i>Made by <?= $recurring_filter_row ? '<a href="recurring_invoice.php?recurring_invoice_id=' . $recurring_filter . '">' . escapeHtml(billingNumber('recurring', $recurring_filter_row)) . '</a>' : 'a recurring invoice' ?>
                <a class="ms-2" href="<?= escapeHtml($filter_url(['recurring_invoice_id' => ''])) ?>">Clear</a>
            </div>
        <?php } ?>
        <?php if ($hardware_filter) { ?>
            <div class="small text-body-secondary mt-2">
                <i class="fas fa-filter me-1"></i>Hardware sold on them isn't on the client's assets yet - open one and use Add to Assets
                <a class="ms-2" href="<?= escapeHtml($filter_url(['hardware' => ''])) ?>">Clear</a>
            </div>
        <?php } ?>
    </div>

    <form id="bulkActions" action="post.php" method="post">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
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
                    <th><a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=invoice_number&order=<?= $disp ?>">Invoice <?php if ($sort == 'invoice_number') { echo $order_icon; } ?></a></th>
                    <?php if (!$client_url) { ?>
                        <th><a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=client_name&order=<?= $disp ?>">Client <?php if ($sort == 'client_name') { echo $order_icon; } ?></a></th>
                    <?php } ?>
                    <th><a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=invoice_date&order=<?= $disp ?>">Date <?php if ($sort == 'invoice_date') { echo $order_icon; } ?></a></th>
                    <th><a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=invoice_due&order=<?= $disp ?>">Due <?php if ($sort == 'invoice_due') { echo $order_icon; } ?></a></th>
                    <th class="text-end"><a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=invoice_amount&order=<?= $disp ?>">Total <?php if ($sort == 'invoice_amount') { echo $order_icon; } ?></a></th>
                    <th class="text-end"><a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=balance&order=<?= $disp ?>">Balance <?php if ($sort == 'balance') { echo $order_icon; } ?></a></th>
                    <th><a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=invoice_status&order=<?= $disp ?>">Status <?php if ($sort == 'invoice_status') { echo $order_icon; } ?></a></th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($invoices as $row) {
                    $invoice_id = intval($row['invoice_id']);
                    $row_client_id = intval($row['invoice_client_id']);
                    $row_number = billingNumber('invoice', $row);
                    $row_status = (string) $row['invoice_status'];
                    $row_state = invoiceState($row, $row['paid']);
                    $row_currency = (string) $row['invoice_currency_code'];
                    $row_url = "invoice.php?{$client_url}invoice_id=$invoice_id";
                    $row_can_pay = $can_pay && invoiceCanPay($row, $row_state);
                    ?>
                    <tr>
                        <?php if ($can_edit) { ?>
                            <td class="checkbox-column bg-light border-end">
                                <div class="form-check">
                                    <input class="form-check-input bulk-select" type="checkbox" name="invoice_ids[]" value="<?= $invoice_id ?>" aria-label="Select <?= escapeHtml($row_number) ?>">
                                </div>
                            </td>
                        <?php } ?>
                        <td class="itflow-billing-number">
                            <a class="fw-semibold" href="<?= $row_url ?>"><?= escapeHtml($row_number) ?></a>
                            <?php if (intval($row['invoice_recurring_invoice_id'])) { ?>
                                <a class="text-body-secondary small ms-1" href="recurring_invoice.php?recurring_invoice_id=<?= intval($row['invoice_recurring_invoice_id']) ?>" title="Made by <?= escapeHtml($row['recurring_invoice_prefix'] . $row['recurring_invoice_number']) ?>"><i class="fas fa-redo-alt"></i></a>
                            <?php } ?>
                            <div class="small text-body-secondary text-truncate" style="max-width: 22rem;">
                                <?= escapeHtml(implode(' · ', array_filter([$row['invoice_scope'], $row['category_name']]))) ?: '&nbsp;' ?>
                            </div>
                        </td>
                        <?php if (!$client_url) { ?>
                            <td><a class="text-reset" href="invoices.php?client_id=<?= $row_client_id ?>"><?= escapeHtml($row['client_name']) ?></a></td>
                        <?php } ?>
                        <td class="text-nowrap"><?= escapeHtml($row['invoice_date']) ?></td>
                        <td class="text-nowrap">
                            <?= escapeHtml($row['invoice_due']) ?>
                            <?php $due_pill = invoiceDuePill($row_state); if ($due_pill) { ?><div><?= $due_pill ?></div><?php } ?>
                        </td>
                        <td class="text-end itflow-money"><?= escapeHtml(billingMoney($row['invoice_amount'], $row_currency)) ?></td>
                        <td class="text-end itflow-money">
                            <?php if (in_array($row_state['state'], ['open', 'overdue'], true)) { ?>
                                <span class="fw-semibold <?= $row_state['state'] === 'overdue' ? 'text-danger-emphasis' : '' ?>"><?= escapeHtml(billingMoney($row_state['balance'], $row_currency)) ?></span>
                                <?php if ($row_state['paid'] > 0) { ?><div class="small text-body-secondary"><?= escapeHtml(billingMoney($row_state['paid'], $row_currency)) ?> paid</div><?php } ?>
                            <?php } else { ?>
                                <span class="text-body-secondary">-</span>
                            <?php } ?>
                        </td>
                        <td class="text-nowrap">
                            <?php if ($row['paid'] > 0) { ?>
                                <a href="#" class="ajax-modal text-decoration-none" title="Payments" data-modal-url="modals/invoice/invoice_payments.php?invoice_id=<?= $invoice_id ?>"><?= invoiceStatusBadge($row_status) ?></a>
                            <?php } else { ?>
                                <?= invoiceStatusBadge($row_status) ?>
                            <?php } ?>
                        </td>
                        <td class="text-end">
                            <div class="dropdown dropstart">
                                <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item" href="<?= $row_url ?>"><i class="fas fa-fw fa-eye text-secondary me-2"></i>Open</a>
                                    <a class="dropdown-item" href="post.php?export_invoice_pdf=<?= $invoice_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" target="_blank"><i class="fas fa-fw fa-file-pdf text-secondary me-2"></i>Download PDF</a>
                                    <?php if ($row_can_pay) { ?>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/payment/payment_add.php?id=<?= $invoice_id ?>">
                                            <i class="fas fa-fw fa-credit-card text-secondary me-2"></i>Add Payment
                                        </a>
                                        <?php if (isset($saved_card_clients[$row_client_id]) && in_array($row_status, ['Sent', 'Viewed'], true)) { ?>
                                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/payment/payment_saved_method_add.php?id=<?= $invoice_id ?>">
                                                <i class="fas fa-fw fa-wallet text-secondary me-2"></i>Charge Saved Card
                                            </a>
                                        <?php } ?>
                                    <?php } ?>
                                    <?php if ($can_edit) { ?>
                                        <div class="dropdown-divider"></div>
                                        <?php if ($mail_ready && !in_array($row_status, ['Cancelled', 'Non-Billable'], true)) { ?>
                                            <button type="submit" class="dropdown-item confirm-link" form="quickSendInvoice" name="invoice_id" value="<?= $invoice_id ?>"
                                                data-confirm-title="Send <?= escapeHtml($row_number) ?> now?" data-confirm-text="It goes to the client's primary and billing contacts." data-confirm-button="Send">
                                                <i class="fas fa-fw fa-bolt text-secondary me-2"></i>Quick Send
                                            </button>
                                            <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/invoice/invoice_email.php?invoice_id=<?= $invoice_id ?>">
                                                <i class="fas fa-fw fa-paper-plane text-secondary me-2"></i>Send Email<span class="text-body-secondary">...</span>
                                            </a>
                                        <?php } ?>
                                        <?php if ($row_status === 'Draft') { ?>
                                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/invoice/invoice_mark_sent.php?invoice_id=<?= $invoice_id ?>">
                                                <i class="fas fa-fw fa-check text-secondary me-2"></i>Mark Sent<span class="text-body-secondary">...</span>
                                            </a>
                                        <?php } ?>
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/invoice/invoice_edit.php?id=<?= $invoice_id ?>">
                                            <i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit
                                        </a>
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/invoice/invoice_copy.php?id=<?= $invoice_id ?>">
                                            <i class="fas fa-fw fa-copy text-secondary me-2"></i>Copy
                                        </a>
                                    <?php } ?>
                                    <?php if ($can_delete) { ?>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_invoice=<?= $invoice_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                            data-confirm-text="<?= escapeHtml($row_number) ?> goes for good, with its lines, payments and history.">
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
    </form>
    <?php
    $empty_state = [
        'thing' => 'invoices',
        'icon' => 'fa-file-invoice-dollar',
        'filtered' => $q || $category_filter || $recurring_filter || $hardware_filter || $dtf !== '1970-01-01',
        'views' => ['status'],
        'view' => emptyStatePill($statuses, $status, '', ['Unpaid', 'Overdue']),
        'text' => 'Bill a client by hand, or from an accepted quote or their unbilled time.',
        'actions' => $can_edit ? [['label' => 'New Invoice', 'modal' => "modals/invoice/invoice_add.php?$client_url"]] : [],
    ];
    require_once "../includes/filter_footer.php";
    ?>
</div>

<?php if ($can_edit && $mail_ready) { ?>
    <?php
    /*
     * One hidden form for the whole page, targeted by the Quick Send buttons via
     * their form="" attribute. It cannot be a form per button: the table is in
     * the bulkActions form, and a nested form is invalid HTML - the browser
     * drops the inner one and the click submits the bulk action instead. The
     * button carries the id as its own name/value.
     */
    ?>
    <form id="quickSendInvoice" action="post.php" method="post" class="d-none">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="email_invoice" value="1">
        <input type="hidden" name="quick_send" value="1">
    </form>
<?php } ?>

<script src="../js/bulk_actions.js"></script>

<?php require_once "../includes/footer.php";
