<?php

/*
 * Products - the catalog of services and products that lines on invoices,
 * quotes and recurring invoices are picked from.
 *
 * At the top: what the catalog bills each month on recurring invoices, what
 * it sold over the last 90 days, and what the stock on the shelf is worth.
 * Tabs for services, products, what is billed each month, quarter or year,
 * and what has its stock tracked (?type=, which old links to ?type=service
 * and ?type=product still use). Each row: what it is and its code, how it is
 * billed by default, the stock on the shelf, who we buy it from, and the
 * price with its margin. The name opens the product's page.
 */

// Default Column Sortby/Order Filter
$sort = "product_name";
$order = "ASC";

require_once "includes/inc_all.php";

// Perms
enforceUserPermission('module_sales');
$can_edit = lookupUserPermission('module_sales') >= 2;
$can_delete = lookupUserPermission('module_sales') >= 3 && !empty($config_destructive_deletes_enable);

// Tabs
$views = [
    'all' => ['label' => 'All', 'icon' => 'fa-th-list', 'sql' => ''],
    'service' => ['label' => 'Services', 'icon' => 'fa-wrench', 'sql' => "AND product_type = 'service'"],
    'product' => ['label' => 'Products', 'icon' => 'fa-box-open', 'sql' => "AND product_type = 'product'"],
    'recurring' => ['label' => 'Recurring', 'icon' => 'fa-redo-alt', 'sql' => "AND product_frequency IS NOT NULL", 'title' => 'Billed each month, quarter or year by default'],
    'stocked' => ['label' => 'Stocked', 'icon' => 'fa-warehouse', 'sql' => "AND product_track_stock = 1", 'title' => 'Stock tracked'],
];
$view = (string) ($_GET['type'] ?? 'all');
if (!isset($views[$view])) {
    $view = 'all';
}

// Category Filter
$category_filter = intval($_GET['category'] ?? 0);
$category_query = $category_filter ? "AND product_category_id = $category_filter" : '';

// Vendor Filter
$vendor_filter = intval($_GET['vendor'] ?? 0);
$vendor_query = $vendor_filter ? "AND product_vendor_id = $vendor_filter" : '';

$search_query = "AND (product_name LIKE '%$q%' OR product_description LIKE '%$q%' OR product_code LIKE '%$q%' OR product_vendor_part LIKE '%$q%' OR product_location LIKE '%$q%'
    OR category_name LIKE '%$q%' OR vendor_name LIKE '%$q%' OR product_price LIKE '%$q%')";
$from = "FROM products
    LEFT JOIN categories ON product_category_id = category_id
    LEFT JOIN taxes ON product_tax_id = tax_id
    LEFT JOIN vendors ON product_vendor_id = vendor_id";
$where = "WHERE product_$archive_query $search_query $category_query $vendor_query";

// Only these sort - an unknown column takes the page down
$sortable_columns = ['product_name', 'product_code', 'category_name', 'vendor_name', 'product_price', 'product_cost', 'margin', 'product_stock', 'product_frequency', 'product_created_at'];
if (!in_array($sort, $sortable_columns, true)) {
    $sort = 'product_name';
}
$sort_sql = $sort === 'margin' ? "IF(product_cost > 0 AND product_price > 0, (product_price - product_cost) / product_price, NULL)" : $sort;

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS products.*, category_name, category_color, tax_name, tax_percent, vendor_name, " . productStockSql() . " AS product_stock
    $from
    $where
    {$views[$view]['sql']}
    ORDER BY $sort_sql $order, product_name ASC LIMIT $record_from, $record_to"
);
$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));
$products = [];
while ($row = mysqli_fetch_assoc($sql)) {
    $products[] = $row;
}

// Each tab's count, with the same search and filters
$counts = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS `all`,
        COALESCE(SUM(product_type = 'service'), 0) AS service, COALESCE(SUM(product_type = 'product'), 0) AS product,
        COALESCE(SUM(product_frequency IS NOT NULL), 0) AS recurring, COALESCE(SUM(product_track_stock = 1), 0) AS stocked
    $from $where"));

// The catalog at work - recurring, sold, on the shelf
$scope_invoice = clientScopeSql('invoice_client_id');
$scope_recurring = clientScopeSql('recurring_invoice_client_id');
$recurring_stat = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COALESCE(SUM(" . recurringInvoiceMonthlySql('item_subtotal', 'recurring_invoice_frequency') . "), 0) AS monthly,
        COUNT(DISTINCT item_product_id) AS products, COUNT(DISTINCT recurring_invoice_client_id) AS clients
    FROM recurring_invoice_items
    JOIN recurring_invoices ON recurring_invoice_id = item_recurring_invoice_id
    WHERE item_product_id > 0 AND recurring_invoice_status = 1 AND recurring_invoice_archived_at IS NULL $scope_recurring"));
$sold_stat = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COALESCE(SUM(item_subtotal), 0) AS amount, COUNT(DISTINCT item_invoice_id) AS invoices
    FROM invoice_items
    JOIN invoices ON invoice_id = item_invoice_id
    WHERE item_product_id > 0 AND invoice_status NOT IN ('Draft', 'Cancelled', 'Non-Billable') AND invoice_date >= CURDATE() - INTERVAL 90 DAY $scope_invoice"));
$top_seller = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT product_id, product_name, SUM(item_subtotal) AS amount
    FROM invoice_items
    JOIN invoices ON invoice_id = item_invoice_id
    JOIN products ON product_id = item_product_id
    WHERE invoice_status NOT IN ('Draft', 'Cancelled', 'Non-Billable') AND invoice_date >= CURDATE() - INTERVAL 90 DAY $scope_invoice
    GROUP BY product_id ORDER BY amount DESC LIMIT 1"));
$stock_stat = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COALESCE(SUM(GREATEST(s.on_hand, 0) * product_cost), 0) AS value, COALESCE(SUM(GREATEST(s.on_hand, 0)), 0) AS units,
        COUNT(*) AS tracked, COALESCE(SUM(s.on_hand <= 0), 0) AS out_of_stock, COALESCE(SUM(product_cost <= 0 AND s.on_hand > 0), 0) AS no_cost
    FROM products
    JOIN (SELECT stock_product_id, SUM(stock_qty) AS on_hand FROM product_stock GROUP BY stock_product_id) s ON s.stock_product_id = product_id
    WHERE product_track_stock = 1 AND product_archived_at IS NULL"));
$tracked_count = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM products WHERE product_track_stock = 1 AND product_archived_at IS NULL"))[0]);
$catalog_count = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM products"))[0]);

$filter_url = function ($changes) {
    $params = array_merge($_GET, $changes);
    unset($params['page']);
    return '?' . http_build_query(array_filter($params, function ($v) { return $v !== '' && $v !== null && $v !== 0; }));
};
$money = function ($amount, $currency = null) use ($session_company_currency) {
    return escapeHtml(billingMoney($amount, $currency ?: $session_company_currency));
};
$add_type = $view === 'product' || $view === 'stocked' ? 'product' : 'service';

?>

<!-- The catalog at work -->
<div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-3">
        <a class="itflow-billing-stat <?= $view === 'all' ? '' : '' ?>" href="<?= escapeHtml($filter_url(['type' => 'all'])) ?>">
            <div class="itflow-billing-stat-label"><i class="fas fa-th-list me-1"></i>Catalog</div>
            <div class="itflow-billing-stat-value"><?= intval($counts['service']) ?> <span class="fs-6 fw-normal text-body-secondary">services</span> &middot; <?= intval($counts['product']) ?> <span class="fs-6 fw-normal text-body-secondary">products</span></div>
            <div class="itflow-billing-stat-note"><?= intval($counts['recurring']) ?> billed each month, quarter or year</div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-3">
        <a class="itflow-billing-stat" href="recurring_invoices.php" title="What lines from the catalog bring in a month on active recurring invoices - a quarterly one counts a third, a yearly one a twelfth">
            <div class="itflow-billing-stat-label"><i class="fas fa-redo-alt me-1"></i>On recurring</div>
            <div class="itflow-billing-stat-value text-success-emphasis"><?= $money($recurring_stat['monthly']) ?><span class="fs-6 fw-normal text-body-secondary ms-1">/mo</span></div>
            <div class="itflow-billing-stat-note"><?= intval($recurring_stat['products']) ?> item<?= intval($recurring_stat['products']) === 1 ? '' : 's' ?> across <?= intval($recurring_stat['clients']) ?> client<?= intval($recurring_stat['clients']) === 1 ? '' : 's' ?></div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="itflow-billing-stat" title="Lines from the catalog on invoices dated in the last 90 days - not drafts, cancelled or non-billable - before tax">
            <div class="itflow-billing-stat-label"><i class="fas fa-chart-line me-1"></i>Sold, 90 days</div>
            <div class="itflow-billing-stat-value"><?= $money($sold_stat['amount']) ?></div>
            <div class="itflow-billing-stat-note text-truncate">
                <?php if ($top_seller) { ?>
                    Top: <a class="text-reset" href="product.php?product_id=<?= intval($top_seller['product_id']) ?>"><?= escapeHtml($top_seller['product_name']) ?></a>
                <?php } else { ?>
                    On <?= intval($sold_stat['invoices']) ?> invoices
                <?php } ?>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <a class="itflow-billing-stat <?= $view === 'stocked' ? 'active' : '' ?>" href="<?= escapeHtml($filter_url(['type' => 'stocked'])) ?>" title="What is on the shelf, at each product's cost">
            <div class="itflow-billing-stat-label"><i class="fas fa-warehouse me-1"></i>On the shelf</div>
            <?php if ($tracked_count && floatval($stock_stat['value']) <= 0) { ?>
                <div class="itflow-billing-stat-value"><?= intval($stock_stat['units']) ?> <span class="fs-6 fw-normal text-body-secondary">unit<?= intval($stock_stat['units']) === 1 ? '' : 's' ?></span></div>
                <div class="itflow-billing-stat-note">
                    On <?= $tracked_count ?> product<?= $tracked_count === 1 ? '' : 's' ?> - add a cost to value them
                    <?php $out = $tracked_count - intval($stock_stat['tracked']) + intval($stock_stat['out_of_stock']); if ($out) { ?>
                        &middot; <span class="text-danger-emphasis"><?= $out ?> out</span>
                    <?php } ?>
                </div>
            <?php } elseif ($tracked_count) { ?>
                <div class="itflow-billing-stat-value"><?= $money($stock_stat['value']) ?></div>
                <div class="itflow-billing-stat-note">
                    <?= intval($stock_stat['units']) ?> unit<?= intval($stock_stat['units']) === 1 ? '' : 's' ?> on <?= $tracked_count ?> product<?= $tracked_count === 1 ? '' : 's' ?>
                    <?php $out = $tracked_count - intval($stock_stat['tracked']) + intval($stock_stat['out_of_stock']); if ($out) { ?>
                        &middot; <span class="text-danger-emphasis"><?= $out ?> out</span>
                    <?php } ?>
                </div>
            <?php } else { ?>
                <div class="itflow-billing-stat-value text-body-secondary">-</div>
                <div class="itflow-billing-stat-note">No product tracks its stock</div>
            <?php } ?>
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-box-open me-2"></i>Products and Services</h3>
        <div class="card-tools">
            <div class="btn-group">
                <?php if ($can_edit) { ?>
                    <button type="button" class="btn btn-primary ajax-modal" data-modal-size="lg" data-modal-url="modals/product/product_add.php?type=<?= $add_type ?>">
                        <i class="fas fa-plus me-2"></i>New
                    </button>
                <?php } ?>
                <button type="button" class="btn btn-primary dropdown-toggle <?= $can_edit ? 'dropdown-toggle-split' : '' ?>" data-bs-toggle="dropdown"><?= $can_edit ? '' : '<i class="fas fa-ellipsis-h"></i>' ?></button>
                <div class="dropdown-menu dropdown-menu-end">
                    <?php if ($can_edit) { ?>
                        <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/product/product_add.php?type=service">
                            <i class="fas fa-fw fa-wrench text-secondary me-2"></i>New Service
                        </a>
                        <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/product/product_add.php?type=product">
                            <i class="fas fa-fw fa-box-open text-secondary me-2"></i>New Product
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/product/product_import.php">
                            <i class="fas fa-fw fa-upload text-secondary me-2"></i>Import
                        </a>
                    <?php } ?>
                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= buildExportModalUrl('modals/product/product_export.php', ['category', 'vendor', 'archived', 'q'], ['type' => $view === 'all' ? '' : $view]) ?>">
                        <i class="fas fa-fw fa-download text-secondary me-2"></i>Export
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="card-header py-3">
        <form autocomplete="off">
            <input type="hidden" name="archived" value="<?= $archived ?>">
            <input type="hidden" name="type" value="<?= escapeHtml($view) ?>">
            <div class="row g-2 align-items-center">
                <div class="col-lg-4 col-md-6">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search name, code, part number, vendor...">
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <select class="form-select select2" name="category" onchange="this.form.submit()" aria-label="Category">
                        <option value="">- All Categories -</option>
                        <?php
                        $sql_categories_filter = mysqli_query($mysqli, "SELECT category_id, category_name, category_color, category_icon FROM categories
                            WHERE category_type = 'Income' AND EXISTS (SELECT 1 FROM products WHERE product_category_id = category_id AND product_$archive_query) ORDER BY category_name ASC");
                        while ($row = mysqli_fetch_assoc($sql_categories_filter)) {
                            $category_option_id = intval($row['category_id']); ?>
                            <option <?= $category_filter === $category_option_id ? 'selected' : '' ?> value="<?= $category_option_id ?>"<?= selectOptionColor($row['category_color'] ?? '', $row['category_icon'] ?? '') ?>><?= escapeHtml($row['category_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-lg-3 col-md-6">
                    <select class="form-select select2" name="vendor" onchange="this.form.submit()" aria-label="Vendor">
                        <option value="">- All Vendors -</option>
                        <?php
                        $sql_vendors_filter = mysqli_query($mysqli, "SELECT vendor_id, vendor_name FROM vendors
                            WHERE EXISTS (SELECT 1 FROM products WHERE product_vendor_id = vendor_id AND product_$archive_query) ORDER BY vendor_name ASC");
                        while ($row = mysqli_fetch_assoc($sql_vendors_filter)) {
                            $vendor_option_id = intval($row['vendor_id']); ?>
                            <option <?= $vendor_filter === $vendor_option_id ? 'selected' : '' ?> value="<?= $vendor_option_id ?>"><?= escapeHtml($row['vendor_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <div class="d-flex justify-content-lg-end gap-2">
                        <a href="<?= escapeHtml($filter_url(['archived' => $archived ? '' : 1])) ?>" class="btn btn-<?= $archived ? 'primary' : 'outline-secondary' ?>" title="<?= $archived ? 'Showing archived - show current' : 'Show archived' ?>">
                            <i class="fa fa-fw fa-archive"></i><span class="d-lg-none d-xl-inline ms-2">Archived</span>
                        </a>
                        <?php if ($can_edit) { ?>
                            <div class="dropdown" id="bulkActionButton" hidden>
                                <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-fw fa-layer-group me-1"></i>(<span id="selectedCount">0</span>)
                                </button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/product/product_bulk_edit_category.php" data-bulk="true">
                                        <i class="fas fa-fw fa-list me-2"></i>Set Category
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <?php if ($archived) { ?>
                                        <button class="dropdown-item" type="submit" form="bulkActions" name="bulk_restore_products">
                                            <i class="fas fa-fw fa-redo me-2"></i>Restore
                                        </button>
                                        <?php if ($can_delete) { ?>
                                            <button class="dropdown-item text-danger text-bold confirm-link" type="submit" form="bulkActions" name="bulk_delete_products">
                                                <i class="fas fa-fw fa-trash me-2"></i>Delete
                                            </button>
                                        <?php } ?>
                                    <?php } else { ?>
                                        <button class="dropdown-item text-danger confirm-link" type="submit" form="bulkActions" name="bulk_archive_products">
                                            <i class="fas fa-fw fa-archive me-2"></i>Archive
                                        </button>
                                    <?php } ?>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </form>
        <nav class="nav nav-pills itflow-asset-tabs mt-2 flex-nowrap overflow-auto">
            <?php foreach ($views as $key => $v) { ?>
                <a class="nav-link text-nowrap <?= $view === $key ? 'active' : '' ?>" href="<?= escapeHtml($filter_url(['type' => $key === 'all' ? '' : $key])) ?>"<?= !empty($v['title']) ? ' title="' . escapeHtml($v['title']) . '"' : '' ?>>
                    <i class="fas fa-fw <?= $v['icon'] ?> me-1"></i><?= $v['label'] ?>
                    <span class="badge rounded-pill ms-1"><?= intval($counts[$key]) ?></span>
                </a>
            <?php } ?>
        </nav>
    </div>

    <form id="bulkActions" action="post.php" method="post">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 itflow-product-table">
                <?php if ($products) { ?>
                <thead class="text-nowrap">
                <tr>
                    <?php if ($can_edit) { ?>
                        <th class="checkbox-column border-end">
                            <div class="form-check">
                                <input class="form-check-input" id="selectAllCheckbox" type="checkbox" onclick="checkAll(this)" title="Select all on this page">
                            </div>
                        </th>
                    <?php } ?>
                    <th><a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=product_name&order=<?= $disp ?>">Item <?php if ($sort == 'product_name') { echo $order_icon; } ?></a></th>
                    <th class="d-none d-md-table-cell"><a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=product_frequency&order=<?= $disp ?>">Billed <?php if ($sort == 'product_frequency') { echo $order_icon; } ?></a></th>
                    <th class="d-none d-lg-table-cell text-end"><a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=product_stock&order=<?= $disp ?>">Stock <?php if ($sort == 'product_stock') { echo $order_icon; } ?></a></th>
                    <th class="d-none d-xl-table-cell"><a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=vendor_name&order=<?= $disp ?>">Vendor <?php if ($sort == 'vendor_name') { echo $order_icon; } ?></a></th>
                    <th class="text-end"><a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=product_price&order=<?= $disp ?>">Price <?php if ($sort == 'product_price') { echo $order_icon; } ?></a></th>
                    <th class="d-none d-sm-table-cell text-end"><a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=margin&order=<?= $disp ?>" title="Price less cost, as a share of the price">Margin <?php if ($sort == 'margin') { echo $order_icon; } ?></a></th>
                    <th></th>
                </tr>
                </thead>
                <?php } ?>
                <tbody>
                <?php foreach ($products as $row) {
                    $product_id = intval($row['product_id']);
                    $row_type = $row['product_type'];
                    $row_tracked = intval($row['product_track_stock']) === 1;
                    $row_stock = intval($row['product_stock']);
                    $row_margin = productMargin($row['product_price'], $row['product_cost']);
                    $row_currency = (string) $row['product_currency_code'];
                    $row_url = "product.php?product_id=$product_id";
                    $row_meta = array_filter([$row['product_code'] ? '<span class="font-monospace">' . escapeHtml($row['product_code']) . '</span>' : '', escapeHtml((string) $row['category_name'])]);
                    ?>
                    <tr>
                        <?php if ($can_edit) { ?>
                            <td class="checkbox-column bg-light border-end">
                                <div class="form-check">
                                    <input class="form-check-input bulk-select" type="checkbox" name="product_ids[]" value="<?= $product_id ?>" aria-label="Select <?= escapeHtml($row['product_name']) ?>">
                                </div>
                            </td>
                        <?php } ?>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <?= productTile($row_type, 'sm') ?>
                                <div class="min-w-0">
                                    <a class="fw-semibold" href="<?= $row_url ?>"><?= escapeHtml($row['product_name']) ?></a>
                                    <?php if ($row['product_archived_at']) { ?><span class="badge rounded-pill text-bg-secondary fw-normal ms-1">Archived</span><?php } ?>
                                    <div class="small text-body-secondary text-truncate" style="max-width: 28rem;"><?= implode(' &middot; ', $row_meta) ?: '&nbsp;' ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="d-none d-md-table-cell text-nowrap">
                            <?php if ($row['product_frequency']) { ?>
                                <i class="fas fa-fw fa-redo-alt text-info-emphasis me-1 small"></i><?= escapeHtml(productBilledText($row['product_frequency'], $row['product_seat_source'])) ?>
                            <?php } else { ?>
                                <span class="text-body-secondary">One time</span>
                            <?php } ?>
                        </td>
                        <td class="d-none d-lg-table-cell text-end text-nowrap">
                            <?php if ($row_tracked) { ?>
                                <span class="badge rounded-pill fw-normal <?= $row_stock > 0 ? 'bg-success-subtle text-success-emphasis' : 'bg-danger-subtle text-danger-emphasis' ?>"><?= $row_stock ?> in stock</span>
                                <?php if ($row['product_location']) { ?><div class="small text-body-secondary"><?= escapeHtml($row['product_location']) ?></div><?php } ?>
                            <?php } elseif ($row_type === 'product') { ?>
                                <span class="small text-body-secondary" title="Stock isn't tracked - it is ordered for each job">As needed</span>
                            <?php } else { ?>
                                <span class="text-body-secondary">-</span>
                            <?php } ?>
                        </td>
                        <td class="d-none d-xl-table-cell">
                            <?php if ($row['vendor_name']) { ?>
                                <a class="text-reset" href="vendor.php?vendor_id=<?= intval($row['product_vendor_id']) ?>"><?= escapeHtml($row['vendor_name']) ?></a>
                                <?php if ($row['product_vendor_part']) { ?><div class="small text-body-secondary font-monospace"><?= escapeHtml($row['product_vendor_part']) ?></div><?php } ?>
                            <?php } else { ?>
                                <span class="text-body-secondary">-</span>
                            <?php } ?>
                        </td>
                        <td class="text-end itflow-money">
                            <span class="fw-semibold"><?= $money($row['product_price'], $row_currency) ?></span>
                            <?php if (floatval($row['product_cost']) > 0) { ?><div class="small text-body-secondary">cost <?= $money($row['product_cost'], $row_currency) ?></div><?php } ?>
                        </td>
                        <td class="d-none d-sm-table-cell text-end"><?= productMarginPill($row_margin) ?: '<span class="text-body-secondary">-</span>' ?></td>
                        <td class="text-end">
                            <div class="dropdown dropstart">
                                <button class="btn btn-sm btn-link text-body-secondary" title="More actions" type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-fw fa-ellipsis-v"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item" href="<?= $row_url ?>"><i class="fas fa-fw fa-eye text-secondary me-2"></i>Open</a>
                                    <?php if ($can_edit) { ?>
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/product/product_edit.php?id=<?= $product_id ?>">
                                            <i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit
                                        </a>
                                        <?php if ($row_tracked && !$row['product_archived_at']) { ?>
                                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/product/product_stock_add.php?id=<?= $product_id ?>">
                                                <i class="fas fa-fw fa-dolly text-secondary me-2"></i>Add Stock
                                            </a>
                                        <?php } ?>
                                        <a class="dropdown-item" href="post.php?copy_product=<?= $product_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                            <i class="fas fa-fw fa-copy text-secondary me-2"></i>Copy
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <?php if ($row['product_archived_at']) { ?>
                                            <a class="dropdown-item" href="post.php?restore_product=<?= $product_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                <i class="fas fa-fw fa-redo text-secondary me-2"></i>Restore
                                            </a>
                                            <?php if ($can_delete) { ?>
                                                <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_product=<?= $product_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                                    data-confirm-text="<?= escapeHtml($row['product_name']) ?> goes for good, with its stock. Lines it is on keep their text and amounts.">
                                                    <i class="fas fa-fw fa-trash me-2"></i>Delete
                                                </a>
                                            <?php } ?>
                                        <?php } else { ?>
                                            <a class="dropdown-item text-danger confirm-link" href="post.php?archive_product=<?= $product_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                                data-confirm-text="<?= escapeHtml($row['product_name']) ?> stops being offered on new lines. Lines it is already on stay.">
                                                <i class="fas fa-fw fa-archive me-2"></i>Archive
                                            </a>
                                        <?php } ?>
                                    <?php } ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
                <?php if (!$products) { ?>
                    <tr>
                        <td colspan="8" class="text-center text-body-secondary py-5">
                            <?php if (!$catalog_count) { ?>
                                <i class="fas fa-box-open fa-2x mb-3 opacity-50"></i>
                                <div class="fw-semibold">Nothing in the catalog yet</div>
                                <div class="small mb-3">Add the services and products you sell, and pick them as lines on invoices, quotes and recurring invoices.</div>
                                <?php if ($can_edit) { ?>
                                    <button type="button" class="btn btn-primary ajax-modal" data-modal-size="lg" data-modal-url="modals/product/product_add.php?type=service"><i class="fas fa-plus me-2"></i>New Service</button>
                                    <button type="button" class="btn btn-outline-secondary ajax-modal" data-modal-url="modals/product/product_import.php"><i class="fas fa-upload me-2"></i>Import</button>
                                    <?php if ($session_is_admin ?? false) { ?>
                                        <div class="small mt-3">Or load a starter catalog from <a href="../admin/starter_content.php">Starter Content</a>.</div>
                                    <?php } ?>
                                <?php } ?>
                            <?php } else { ?>
                                Nothing here<?= $q !== '' || $category_filter || $vendor_filter ? ' that matches' : '' ?>.
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </form>
    <?php require_once "../includes/filter_footer.php"; ?>
</div>

<script src="/js/bulk_actions.js"></script>

<?php
require_once "../includes/footer.php";
