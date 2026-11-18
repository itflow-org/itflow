<?php

/*
 * Purchasing > Purchase Orders - every purchase order the agent can see (one
 * for a client they can't see is left out). Along the top, what is waiting to
 * be ordered, waiting for approval, on order and in this month. Tabs: Open
 * (the default - not received or cancelled), Drafts, Awaiting approval,
 * Approved, Ordered (partly received too), Received, Cancelled and All. The
 * work is in functions/purchase_orders.php; the handlers are
 * agent/post/purchase_order.php.
 */

// Default Column Sortby/Order Filter
$sort = "po_number";
$order = "DESC";

require_once "includes/inc_all.php";

poRequireLevel(1);
$can_edit = poLevel() >= 2;

$views = [
    'open' => ['label' => 'Open', 'icon' => '', 'where' => "po_status IN ('draft', 'pending', 'approved', 'ordered', 'partial')"],
    'draft' => ['label' => 'Drafts', 'icon' => 'fa-pencil-alt', 'where' => "po_status = 'draft'"],
    'pending' => ['label' => 'Awaiting approval', 'icon' => 'fa-hourglass-half', 'where' => "po_status = 'pending'"],
    'approved' => ['label' => 'Approved', 'icon' => 'fa-thumbs-up', 'where' => "po_status = 'approved'"],
    'ordered' => ['label' => 'Ordered', 'icon' => 'fa-paper-plane', 'where' => "po_status IN ('ordered', 'partial')", 'title' => 'Ordered, and partly received'],
    'received' => ['label' => 'Received', 'icon' => 'fa-check-double', 'where' => "po_status = 'received'"],
    'cancelled' => ['label' => 'Cancelled', 'icon' => 'fa-ban', 'where' => "po_status = 'cancelled'"],
    'all' => ['label' => 'All', 'icon' => '', 'where' => '1 = 1'],
];
$view = isset($views[$_GET['show'] ?? '']) ? $_GET['show'] : 'open';
$vendor_filter = intval($_GET['vendor'] ?? 0);

$sortable_columns = [
    'po_number' => "po_number $order",
    'vendor_name' => "vendor_name $order, po_number DESC",
    'po_total' => "po_total $order",
    'po_order_date' => "po_order_date IS NULL, po_order_date $order, po_number DESC",
    'po_status' => "FIELD(po_status, 'pending', 'draft', 'approved', 'ordered', 'partial', 'received', 'cancelled') $order, po_number DESC",
];
if (!isset($sortable_columns[$sort])) {
    $sort = 'po_number';
}

$scope = poScopeSql('po_id');
$search = $q !== '' ? " AND (CONCAT(po_prefix, po_number) LIKE '%$q%' OR vendor_name LIKE '%$q%' OR po_vendor_reference LIKE '%$q%' OR po_note LIKE '%$q%'
    OR po_id IN (SELECT po_item_po_id FROM po_items LEFT JOIN clients ON client_id = po_item_client_id WHERE po_item_name LIKE '%$q%' OR po_item_vendor_part LIKE '%$q%' OR client_name LIKE '%$q%'))" : '';
$filters = $scope . $search . ($vendor_filter ? " AND po_vendor_id = $vendor_filter" : '');
$joins = "LEFT JOIN vendors ON vendors.vendor_id = po_vendor_id LEFT JOIN users ON user_id = po_created_by";

$pos = [];
$num_rows = [0];
$counts = array_fill_keys(array_keys($views), 0);
$stats = ['to_order' => 0, 'pending' => 0, 'on_order' => 0, 'on_order_total' => 0, 'received_month' => 0];
$lines = [];
if (poReady()) {
    $sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS purchase_orders.*, vendor_name, user_name AS created_by_name
        FROM purchase_orders $joins
        WHERE {$views[$view]['where']} $filters
        ORDER BY {$sortable_columns[$sort]}, po_id DESC LIMIT $record_from, $record_to");
    $num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));
    while ($row = mysqli_fetch_assoc($sql)) {
        $pos[] = $row;
    }

    // Each one's lines - how many, who they are for, and how much has come in
    if ($pos) {
        $ids = implode(',', array_map('intval', array_column($pos, 'po_id')));
        $sql = mysqli_query($mysqli, "SELECT po_item_po_id, COUNT(*) AS lines_count, SUM(po_item_quantity) AS qty, SUM(LEAST(po_item_received, po_item_quantity)) AS received,
                GROUP_CONCAT(DISTINCT IF(po_item_client_id > 0, client_name, NULL) ORDER BY client_name SEPARATOR '|') AS clients, SUM(po_item_client_id = 0) AS stock_lines
            FROM po_items LEFT JOIN clients ON client_id = po_item_client_id AND po_item_client_id > 0
            WHERE po_item_po_id IN ($ids) GROUP BY po_item_po_id");
        while ($row = mysqli_fetch_assoc($sql)) {
            $lines[intval($row['po_item_po_id'])] = $row;
        }
    }

    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT " . implode(', ', array_map(function ($v) {
        return "COALESCE(SUM({$v['where']}), 0)";
    }, $views)) . " FROM purchase_orders $joins WHERE 1 $filters"));
    $counts = array_combine(array_keys($views), array_map('intval', $row));

    // The figures along the top - everything the agent can see, whatever the search
    $stats = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT
            COALESCE(SUM(po_status = 'pending'), 0) AS pending,
            COALESCE(SUM(po_status IN ('ordered', 'partial')), 0) AS on_order,
            COALESCE(SUM(IF(po_status IN ('ordered', 'partial'), po_total, 0)), 0) AS on_order_total,
            COALESCE(SUM(po_status = 'received' AND po_received_at >= '" . date('Y-m-01') . "'), 0) AS received_month
        FROM purchase_orders WHERE 1 $scope"));
    $stats['to_order'] = poNavCounts()['to_order'];
}

$filter_url = function ($changes) {
    $params = array_merge($_GET, $changes);
    unset($params['page']);
    return '?' . http_build_query(array_filter($params, function ($v) { return $v !== '' && $v !== null; }));
};
$sort_link = function ($column, $label) use ($url_query_strings_sort, $sort, $disp, $order_icon) {
    return '<a class="text-reset text-decoration-none" href="?' . escapeHtml($url_query_strings_sort) . '&amp;sort=' . $column . '&amp;order=' . $disp . '">'
        . $label . ($sort === $column ? " $order_icon" : '') . '</a>';
};
$csrf = $_SESSION['csrf_token'];

?>

<div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-3">
        <a class="itflow-billing-stat" href="to_order.php">
            <div class="itflow-billing-stat-label"><i class="fas fa-cart-plus me-1"></i>To order</div>
            <div class="itflow-billing-stat-value"><?= intval($stats['to_order']) ?></div>
            <div class="itflow-billing-stat-note">Waiting to go on a purchase order</div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-3">
        <a class="itflow-billing-stat <?= $view === 'pending' ? 'active' : '' ?>" href="<?= escapeHtml($filter_url(['show' => 'pending'])) ?>">
            <div class="itflow-billing-stat-label"><i class="fas fa-hourglass-half me-1"></i>Awaiting approval</div>
            <div class="itflow-billing-stat-value <?= intval($stats['pending']) ? 'text-warning-emphasis' : '' ?>"><?= intval($stats['pending']) ?></div>
            <div class="itflow-billing-stat-note"><?= poSettings()['approval'] ? 'Can\'t be ordered until approved' : 'Approval is off' ?></div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-3">
        <a class="itflow-billing-stat <?= $view === 'ordered' ? 'active' : '' ?>" href="<?= escapeHtml($filter_url(['show' => 'ordered'])) ?>">
            <div class="itflow-billing-stat-label"><i class="fas fa-paper-plane me-1"></i>On order</div>
            <div class="itflow-billing-stat-value"><?= intval($stats['on_order']) ?></div>
            <div class="itflow-billing-stat-note"><?= escapeHtml(poMoney($stats['on_order_total'])) ?> still coming</div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-3">
        <a class="itflow-billing-stat <?= $view === 'received' ? 'active' : '' ?>" href="<?= escapeHtml($filter_url(['show' => 'received'])) ?>">
            <div class="itflow-billing-stat-label"><i class="fas fa-check-double me-1"></i>Received</div>
            <div class="itflow-billing-stat-value"><?= intval($stats['received_month']) ?></div>
            <div class="itflow-billing-stat-note">All in this month</div>
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fa fa-fw fa-file-signature me-2"></i>Purchase Orders</h3>
        <div class="card-tools">
            <?php if ($can_edit) { ?>
                <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/purchasing/po_new.php"><i class="fas fa-plus"></i><span class="d-none d-lg-inline ms-2">New Purchase Order</span></button>
            <?php } ?>
        </div>
    </div>

    <div class="card-header py-3">
        <form autocomplete="off">
            <input type="hidden" name="show" value="<?= escapeHtml($view) ?>">
            <div class="row g-2 align-items-center">
                <div class="col-lg-8 col-md-6">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?= stripslashes(escapeHtml($q)) ?>" placeholder="Search purchase orders..." title="Number, vendor, their order number, note, item, part or client">
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <select class="form-select select2" name="vendor" onchange="this.form.submit()" aria-label="Vendor">
                        <option value="">- Any vendor -</option>
                        <?php
                        if (poReady()) {
                            $sql_vendors = mysqli_query($mysqli, "SELECT DISTINCT vendor_id, vendor_name FROM vendors JOIN purchase_orders ON po_vendor_id = vendor_id ORDER BY vendor_name");
                            while ($row = mysqli_fetch_assoc($sql_vendors)) {
                                $option_id = intval($row['vendor_id']); ?>
                                <option value="<?= $option_id ?>" <?= $vendor_filter === $option_id ? 'selected' : '' ?>><?= escapeHtml($row['vendor_name']) ?></option>
                            <?php }
                        } ?>
                    </select>
                </div>
            </div>
        </form>
        <nav class="nav nav-pills itflow-asset-tabs mt-2 flex-nowrap overflow-auto">
            <?php foreach ($views as $key => $v) {
                $alarm = $key === 'pending' && $counts[$key]; ?>
                <a class="nav-link text-nowrap <?= $view === $key ? 'active' : '' ?>" href="<?= escapeHtml($filter_url(['show' => $key])) ?>"<?= !empty($v['title']) ? ' title="' . escapeHtml($v['title']) . '"' : '' ?>>
                    <?php if ($v['icon'] !== '') { ?><i class="fas fa-fw <?= $v['icon'] ?> me-1"></i><?php } ?><?= $v['label'] ?>
                    <span class="badge rounded-pill ms-1 <?= $alarm ? 'text-bg-warning' : '' ?>"><?= $counts[$key] ?></span>
                </a>
            <?php } ?>
        </nav>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="<?= $num_rows[0] ? '' : 'd-none' ?> text-nowrap table-light">
            <tr>
                <th class="ps-3"><?= $sort_link('po_number', 'Purchase order') ?></th>
                <th><?= $sort_link('vendor_name', 'Vendor') ?></th>
                <th class="d-none d-md-table-cell">For</th>
                <th><?= $sort_link('po_status', 'Status') ?></th>
                <th class="d-none d-lg-table-cell"><?= $sort_link('po_order_date', 'Ordered') ?></th>
                <th class="text-end"><?= $sort_link('po_total', 'Total') ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($pos as $row) {
                $po_id = intval($row['po_id']);
                $line = $lines[$po_id] ?? ['lines_count' => 0, 'qty' => 0, 'received' => 0, 'clients' => '', 'stock_lines' => 0];
                $clients = array_filter(explode('|', (string) $line['clients']));
                $for = array_merge($clients, intval($line['stock_lines']) ? ['Our stock'] : []);
                $arriving = in_array($row['po_status'], ['ordered', 'partial'], true); ?>
                <tr data-po-id="<?= $po_id ?>">
                    <td class="ps-3">
                        <div class="d-flex align-items-center gap-3">
                            <span class="itflow-asset-icon itflow-asset-tone-other d-none d-sm-inline-flex" aria-hidden="true"><i class="fas fa-fw fa-file-signature"></i></span>
                            <div class="min-w-0">
                                <a class="fw-semibold" href="purchase_order.php?po_id=<?= $po_id ?>"><?= escapeHtml(poNumber($row)) ?></a>
                                <div class="small text-body-secondary"><?= intval($line['lines_count']) ?> line<?= intval($line['lines_count']) === 1 ? '' : 's' ?><?= $row['po_vendor_reference'] ? ' &middot; their order ' . escapeHtml($row['po_vendor_reference']) : '' ?></div>
                            </div>
                        </div>
                    </td>
                    <td><?= escapeHtml((string) $row['vendor_name']) ?></td>
                    <td class="d-none d-md-table-cell small"><?= $for ? escapeHtml(implode(', ', array_slice($for, 0, 2)) . (count($for) > 2 ? ' +' . (count($for) - 2) : '')) : '<span class="text-body-tertiary">Nothing yet</span>' ?></td>
                    <td class="text-nowrap">
                        <?= poStatusBadge($row['po_status']) ?>
                        <?php if ($arriving && floatval($line['qty']) > 0) { ?>
                            <div class="small text-body-secondary mt-1"><?= escapeHtml(billingQuantity($line['received'])) ?> of <?= escapeHtml(billingQuantity($line['qty'])) ?> in</div>
                        <?php } elseif ($row['po_status'] === 'draft' || $row['po_status'] === 'pending') { ?>
                            <div class="small text-body-secondary mt-1">Made <?= escapeHtml(timeAgo($row['po_created_at'])) ?><?= $row['created_by_name'] ? ' by ' . escapeHtml($row['created_by_name']) : '' ?></div>
                        <?php } ?>
                    </td>
                    <td class="d-none d-lg-table-cell small"><?= $row['po_order_date'] ? escapeHtml(date('M j, Y', strtotime($row['po_order_date']))) : '<span class="text-body-tertiary">Not yet</span>' ?></td>
                    <td class="text-end fw-semibold"><?= escapeHtml(poMoney($row['po_total'], $row['po_currency_code'])) ?></td>
                    <td class="text-end">
                        <div class="dropdown dropstart">
                            <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item" href="purchase_order.php?po_id=<?= $po_id ?>"><i class="fas fa-fw fa-eye text-secondary me-2"></i>Open</a>
                                <a class="dropdown-item" href="post.php?po_pdf=<?= $po_id ?>&csrf_token=<?= $csrf ?>" target="_blank"><i class="fas fa-fw fa-file-pdf text-secondary me-2"></i>PDF</a>
                                <?php if ($arriving && $can_edit) { ?>
                                    <a class="dropdown-item" href="receiving.php?po_id=<?= $po_id ?>"><i class="fas fa-fw fa-truck-loading text-secondary me-2"></i>Receive</a>
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
        'thing' => 'purchase orders',
        'icon' => 'fa-file-signature',
        'filtered' => $q !== '' || $vendor_filter,
        'views' => ['show'],
        'view' => emptyStatePill($views, $view, 'open'),
        'text' => 'What you buy from your vendors - made from To Order, approved when it needs to be, emailed to the vendor and received as it arrives.',
        'actions' => $can_edit ? [['label' => 'New Purchase Order', 'modal' => 'modals/purchasing/po_new.php']] : [],
    ];
    require_once "../includes/filter_footer.php";
    ?>
</div>

<?php require_once "../includes/footer.php";
