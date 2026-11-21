<?php

/*
 * Purchasing > To Order - what is waiting to go on a purchase order: items
 * added by hand, or from products that are low on stock. Each is for a client
 * or for our own stock, with who it comes from and what it costs. Tick some
 * to make a purchase order to one vendor, put them on a draft one, or take
 * them off. The work is in functions/purchase_orders.php; the handlers are
 * agent/post/purchase_order.php.
 */

// Default Column Sortby/Order Filter
$sort = "vendor_name";
$order = "ASC";

require_once "includes/inc_all.php";

poRequireLevel(1);
$can_edit = poLevel() >= 2;

$vendor_filter = intval($_GET['vendor'] ?? 0);
$client_filter = intval($_GET['client'] ?? 0);

$sortable_columns = [
    'order_item_name' => "order_item_name $order",
    'client_name' => "order_item_client_id = 0 $order, client_name $order, order_item_name ASC",
    'vendor_name' => "vendor_name IS NULL $order, vendor_name $order, order_item_name ASC",
    'order_item_quantity' => "order_item_quantity $order",
    'order_item_cost' => "order_item_cost IS NULL, order_item_cost $order",
    'order_item_created_at' => "order_item_created_at $order",
];
if (!isset($sortable_columns[$sort])) {
    $sort = 'vendor_name';
}

$where = "order_item_status = 'waiting'" . clientScopeSql('order_item_client_id')
    . ($vendor_filter ? " AND order_item_vendor_id = " . max(0, $vendor_filter) : '')
    . ($client_filter ? " AND order_item_client_id = " . max(0, $client_filter) : '')
    . ($q !== '' ? " AND (order_item_name LIKE '%$q%' OR order_item_description LIKE '%$q%' OR order_item_note LIKE '%$q%' OR vendor_name LIKE '%$q%' OR client_name LIKE '%$q%' OR product_name LIKE '%$q%')" : '');
$joins = "LEFT JOIN clients ON client_id = order_item_client_id AND order_item_client_id > 0
    LEFT JOIN vendors ON vendor_id = order_item_vendor_id AND order_item_vendor_id > 0
    LEFT JOIN products ON product_id = order_item_product_id AND order_item_product_id > 0
    LEFT JOIN users ON user_id = order_item_created_by AND order_item_created_by > 0";

$items = [];
$num_rows = [0];
$totals = ['count' => 0, 'amount' => 0, 'no_vendor' => 0, 'no_cost' => 0];
$low_stock = [];
if (poReady()) {
    $sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS order_items.*, client_name, vendor_name, product_name, user_name AS created_by_name
        FROM order_items $joins
        WHERE $where
        ORDER BY {$sortable_columns[$sort]}, order_item_id ASC LIMIT $record_from, $record_to");
    $num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));
    while ($row = mysqli_fetch_assoc($sql)) {
        $items[] = $row;
    }
    // Across everything waiting the agent can see, whatever the search
    $totals = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS count, COALESCE(SUM(ROUND(order_item_quantity * COALESCE(order_item_cost, 0), 2)), 0) AS amount,
            COALESCE(SUM(order_item_vendor_id = 0), 0) AS no_vendor, COALESCE(SUM(order_item_cost IS NULL OR order_item_cost <= 0), 0) AS no_cost
        FROM order_items WHERE order_item_status = 'waiting'" . clientScopeSql('order_item_client_id')));
    $low_stock = $can_edit ? orderItemsLowStock() : [];
}

$filter_url = function ($changes) {
    $params = array_merge($_GET, $changes);
    unset($params['page']);
    return '?' . http_build_query(array_filter($params, function ($v) { return $v !== '' && $v !== null && $v !== 0; }));
};
$sort_link = function ($column, $label) use ($url_query_strings_sort, $sort, $disp, $order_icon) {
    return '<a class="text-reset text-decoration-none" href="?' . escapeHtml($url_query_strings_sort) . '&amp;sort=' . $column . '&amp;order=' . $disp . '">'
        . $label . ($sort === $column ? " $order_icon" : '') . '</a>';
};
$csrf = $_SESSION['csrf_token'];
$drafts = poReady() ? intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM purchase_orders WHERE po_status = 'draft'" . poScopeSql('po_id')))[0]) : 0;

?>

<?= poTabsHtml('to_order.php') ?>

<?php if ($low_stock) { ?>
    <div class="alert alert-warning d-flex flex-wrap align-items-center gap-2" data-low-stock="<?= count($low_stock) ?>">
        <i class="fas fa-fw fa-boxes"></i>
        <span class="me-auto"><strong><?= count($low_stock) ?> product<?= count($low_stock) === 1 ? ' is' : 's are' ?> low on stock</strong> and not on order -
            <?= escapeHtml(implode(', ', array_slice(array_column($low_stock, 'product_name'), 0, 4)) . (count($low_stock) > 4 ? ' and ' . (count($low_stock) - 4) . ' more' : '')) ?>.</span>
        <form action="post.php" method="post" class="m-0">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <button type="submit" name="order_items_low_stock" class="btn btn-sm btn-warning"><i class="fas fa-fw fa-cart-plus me-1"></i>Add them to the list</button>
        </form>
    </div>
<?php } ?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fa fa-fw fa-cart-plus me-2"></i>To Order</h3>
        <div class="card-tools">
            <?php if ($can_edit) { ?>
                <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/purchasing/order_item.php"><i class="fas fa-plus"></i><span class="d-none d-lg-inline ms-2">Add Item</span></button>
            <?php } ?>
        </div>
    </div>

    <div class="card-header py-3">
        <form autocomplete="off">
            <input type="hidden" name="sort" value="<?= escapeHtml($sort) ?>">
            <input type="hidden" name="order" value="<?= escapeHtml($order) ?>">
            <div class="row g-2 align-items-center">
                <div class="col-lg-5 col-md-6">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?= stripslashes(escapeHtml($q)) ?>" placeholder="Search what's waiting..." title="Item, description, note, vendor, client or product">
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <select class="form-select select2" name="vendor" onchange="this.form.submit()" aria-label="Vendor">
                        <option value="">- Any vendor -</option>
                        <option value="-1" <?= $vendor_filter === -1 ? 'selected' : '' ?>>No vendor yet</option>
                        <?php foreach (poVendors() as $vendor_id => $vendor_name) { ?>
                            <option value="<?= $vendor_id ?>" <?= $vendor_filter === $vendor_id ? 'selected' : '' ?>><?= escapeHtml($vendor_name) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <select class="form-select select2" name="client" onchange="this.form.submit()" aria-label="For">
                        <option value="">- Anyone -</option>
                        <option value="-1" <?= $client_filter === -1 ? 'selected' : '' ?>>Our own stock</option>
                        <?php
                        if (poReady()) {
                            $sql_clients = mysqli_query($mysqli, "SELECT DISTINCT client_id, client_name, client_abbreviation FROM clients JOIN order_items ON order_item_client_id = client_id
                                WHERE order_item_status = 'waiting'" . clientScopeSql('client_id') . " ORDER BY client_name");
                            while ($row = mysqli_fetch_assoc($sql_clients)) {
                                $option_id = intval($row['client_id']); ?>
                                <option value="<?= $option_id ?>" <?= $client_filter === $option_id ? 'selected' : '' ?><?= selectOptionClient($option_id, $row['client_name'], $row['client_abbreviation'] ?? '') ?>><?= escapeHtml($row['client_name']) ?></option>
                            <?php }
                        } ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <div class="d-flex justify-content-end gap-2">
                        <?php if ($can_edit) { ?>
                            <div class="dropdown" id="bulkActionButton" hidden>
                                <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-fw fa-layer-group me-2"></i>Bulk Action (<span id="selectedCount">0</span>)
                                </button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/purchasing/po_new.php" data-bulk="true"><i class="fas fa-fw fa-file-signature me-2"></i>Make a Purchase Order</a>
                                    <a class="dropdown-item ajax-modal <?= $drafts ? '' : 'disabled' ?>" href="#" data-modal-url="modals/purchasing/po_add_items.php" data-bulk="true"><i class="fas fa-fw fa-plus-square me-2"></i>Put on a Draft</a>
                                    <div class="dropdown-divider"></div>
                                    <button type="submit" form="bulkActions" name="order_items_remove" class="dropdown-item text-danger confirm-link" data-confirm-title="Take them off the list?" data-confirm-text="They're taken off To Order - nothing is ordered." data-confirm-button="Take Off"><i class="fas fa-fw fa-times me-2"></i>Take Off the List</button>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </form>
        <?php if (intval($totals['count'])) { ?>
            <div class="small text-body-secondary mt-2" data-to-order-totals>
                <?= intval($totals['count']) ?> waiting, about <strong><?= escapeHtml(poMoney($totals['amount'])) ?></strong>
                <?php if (intval($totals['no_vendor'])) { ?> &middot; <a class="text-warning-emphasis" href="<?= escapeHtml($filter_url(['vendor' => -1])) ?>"><?= intval($totals['no_vendor']) ?> with no vendor yet</a><?php } ?>
                <?php if (intval($totals['no_cost'])) { ?> &middot; <?= intval($totals['no_cost']) ?> without a cost<?php } ?>
            </div>
        <?php } ?>
    </div>

    <form id="bulkActions" action="post.php" method="post">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="<?= $num_rows[0] ? '' : 'd-none' ?> text-nowrap table-light">
                <tr>
                    <?php if ($can_edit) { ?>
                        <th class="ps-3" style="width: 1%;">
                            <div class="form-check">
                                <input class="form-check-input" id="selectAllCheckbox" type="checkbox" onclick="checkAll(this)" title="Select all on this page">
                            </div>
                        </th>
                    <?php } ?>
                    <th class="<?= $can_edit ? '' : 'ps-3' ?>"><?= $sort_link('order_item_name', 'Item') ?></th>
                    <th><?= $sort_link('client_name', 'For') ?></th>
                    <th><?= $sort_link('vendor_name', 'Vendor') ?></th>
                    <th class="text-end"><?= $sort_link('order_item_quantity', 'Qty') ?></th>
                    <th class="text-end d-none d-md-table-cell"><?= $sort_link('order_item_cost', 'Unit cost') ?></th>
                    <th class="text-end d-none d-md-table-cell">Amount</th>
                    <th class="d-none d-xl-table-cell"><?= $sort_link('order_item_created_at', 'Added') ?></th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $row) {
                    $item_id = intval($row['order_item_id']);
                    $has_cost = $row['order_item_cost'] !== null && floatval($row['order_item_cost']) > 0; ?>
                    <tr data-order-item-id="<?= $item_id ?>">
                        <?php if ($can_edit) { ?>
                            <td class="ps-3">
                                <div class="form-check">
                                    <input class="form-check-input bulk-select" type="checkbox" name="order_item_ids[]" value="<?= $item_id ?>" aria-label="Select <?= escapeHtml($row['order_item_name']) ?>">
                                </div>
                            </td>
                        <?php } ?>
                        <td class="<?= $can_edit ? '' : 'ps-3' ?>">
                            <?php $product_link = $row['product_name'] && lookupUserPermission('module_sales') >= 1 ? 'product.php?product_id=' . intval($row['order_item_product_id']) : ''; ?>
                            <div class="fw-semibold"><?= $product_link !== '' && $row['product_name'] === $row['order_item_name'] ? '<a class="text-reset" href="' . $product_link . '" title="Open the product">' . escapeHtml($row['order_item_name']) . '</a>' : escapeHtml($row['order_item_name']) ?>
                                <?php if ($row['order_item_source'] === 'reorder') { ?><span class="badge rounded-pill text-bg-light border fw-normal ms-1" title="Added because it was low on stock"><i class="fas fa-fw fa-boxes me-1"></i>Restock</span><?php } ?>
                            </div>
                            <?php if ($product_link !== '' && $row['product_name'] !== $row['order_item_name']) { ?>
                                <div class="small"><a class="text-body-secondary" href="<?= $product_link ?>" title="The product"><i class="fas fa-fw fa-box-open me-1"></i><?= escapeHtml($row['product_name']) ?></a></div>
                            <?php } ?>
                            <?php if (trim((string) $row['order_item_description']) !== '') { ?>
                                <div class="small text-body-secondary text-truncate" style="max-width: 28rem;"><?= escapeHtml($row['order_item_description']) ?></div>
                            <?php } ?>
                            <?php if ($row['order_item_note']) { ?>
                                <div class="small text-body-secondary"><i class="fas fa-fw fa-sticky-note me-1"></i><?= escapeHtml($row['order_item_note']) ?></div>
                            <?php } ?>
                        </td>
                        <td>
                            <?php if (intval($row['order_item_client_id'])) { ?>
                                <a class="text-reset" href="client_overview.php?client_id=<?= intval($row['order_item_client_id']) ?>"><?= escapeHtml($row['client_name']) ?></a>
                            <?php } else { ?>
                                <span class="text-body-secondary"><i class="fas fa-fw fa-warehouse me-1"></i>Our stock</span>
                            <?php } ?>
                        </td>
                        <td>
                            <?php if ($row['vendor_name']) { ?>
                                <?= escapeHtml($row['vendor_name']) ?>
                            <?php } else { ?>
                                <span class="text-warning-emphasis"><i class="fas fa-fw fa-exclamation-circle me-1"></i>Not set</span>
                            <?php } ?>
                        </td>
                        <td class="text-end"><?= escapeHtml(billingQuantity($row['order_item_quantity'])) ?></td>
                        <td class="text-end d-none d-md-table-cell"><?= $has_cost ? escapeHtml(poMoney($row['order_item_cost'])) : '<span class="text-body-tertiary">No cost</span>' ?></td>
                        <td class="text-end d-none d-md-table-cell"><?= $has_cost ? escapeHtml(poMoney(round(floatval($row['order_item_quantity']) * floatval($row['order_item_cost']), 2))) : '' ?></td>
                        <td class="d-none d-xl-table-cell small text-body-secondary"><span title="<?= formatDateTime($row['order_item_created_at']) ?>"><?= escapeHtml(timeAgo($row['order_item_created_at'])) ?></span><?= $row['created_by_name'] ? ' by ' . escapeHtml($row['created_by_name']) : '' ?></td>
                        <td class="text-end">
                            <?php if ($can_edit) { ?>
                                <div class="dropdown dropstart">
                                    <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/purchasing/order_item.php?id=<?= $item_id ?>"><i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit</a>
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/purchasing/po_new.php?order_item_ids[]=<?= $item_id ?>"><i class="fas fa-fw fa-file-signature text-secondary me-2"></i>Make a Purchase Order</a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger confirm-link" href="post.php?order_item_remove=<?= $item_id ?>&csrf_token=<?= $csrf ?>"
                                            data-confirm-title="Take <?= escapeHtml($row['order_item_name']) ?> off the list?" data-confirm-text="It's taken off To Order - nothing is ordered." data-confirm-button="Take Off">
                                            <i class="fas fa-fw fa-times me-2"></i>Take Off the List
                                        </a>
                                    </div>
                                </div>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </form>
    <?php
    $empty_state = [
        'thing' => 'items to order',
        'icon' => 'fa-cart-plus',
        'filtered' => $q !== '' || $vendor_filter || $client_filter,
        'text' => 'What needs buying - for a client, or for your own stock. Tick items to make a purchase order to their vendor.',
        'actions' => $can_edit ? [['label' => 'Add Item', 'modal' => 'modals/purchasing/order_item.php']] : [],
    ];
    require_once "../includes/filter_footer.php";
    ?>
</div>

<script src="<?= staticFileUrl('/js/bulk_actions.js') ?>"></script>
<?php require_once "../includes/footer.php";
