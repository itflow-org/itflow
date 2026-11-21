<?php

/*
 * Purchasing > Receiving - every line still to come in on the purchase orders
 * that are ordered, by PO, oldest order first - or one PO's (?po_id=). Type
 * how many came in (All fills in what is left) and receive them together.
 * Our own stock of a product that tracks it goes on the shelf; a client's
 * hardware can become their assets, a unit each, with the serials typed. The
 * work is poReceive() in functions/purchase_orders.php.
 */

require_once "includes/inc_all.php";

poRequireLevel(1);
$can_edit = poLevel() >= 2;
$can_assets = lookupUserPermission('module_support') >= 2;
$can_stock = lookupUserPermission('module_sales') >= 2;

$po_filter = intval($_GET['po_id'] ?? 0);
$vendor_filter = intval($_GET['vendor'] ?? 0);
$by_po = poAwaitingLines($po_filter);
if ($vendor_filter) {
    $by_po = array_filter($by_po, function ($lines) use ($mysqli, $vendor_filter) {
        return intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT po_vendor_id FROM purchase_orders WHERE po_id = " . intval($lines[0]['po_item_po_id'])))[0] ?? 0) === $vendor_filter;
    });
}
$tracking = poTrackingFor(array_keys($by_po));
$asset_types = array_keys(productAssetTypes());
$line_count = array_sum(array_map('count', $by_po));

?>

<?= poTabsHtml('receiving.php') ?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-truck-loading me-2"></i>Receiving</h3>
        <div class="card-tools">
            <a class="btn btn-outline-light" href="purchase_orders.php?show=ordered"><i class="fas fa-file-signature"></i><span class="d-none d-lg-inline ms-2">Ordered POs</span></a>
        </div>
    </div>

    <div class="card-header py-3">
        <form autocomplete="off">
            <div class="row g-2 align-items-center">
                <div class="col-lg-4 col-md-6">
                    <select class="form-select select2" name="vendor" onchange="this.form.submit()" aria-label="Vendor">
                        <option value="">- Any vendor -</option>
                        <?php
                        if (poReady()) {
                            $sql_vendors = mysqli_query($mysqli, "SELECT DISTINCT vendor_id, vendor_name FROM vendors JOIN purchase_orders ON po_vendor_id = vendor_id WHERE po_status IN ('ordered', 'partial') ORDER BY vendor_name");
                            while ($row = mysqli_fetch_assoc($sql_vendors)) {
                                $option_id = intval($row['vendor_id']); ?>
                                <option value="<?= $option_id ?>" <?= $vendor_filter === $option_id ? 'selected' : '' ?>><?= escapeHtml($row['vendor_name']) ?></option>
                            <?php }
                        } ?>
                    </select>
                </div>
                <div class="col-lg-8 col-md-6 small text-body-secondary">
                    <?php if ($po_filter) { ?>
                        <a href="receiving.php"><i class="fas fa-fw fa-times me-1"></i>Show every purchase order</a>
                    <?php } else { ?>
                        <?= $line_count ?> line<?= $line_count === 1 ? '' : 's' ?> on <?= count($by_po) ?> purchase order<?= count($by_po) === 1 ? '' : 's' ?> still to come in.
                    <?php } ?>
                </div>
            </div>
        </form>
    </div>

    <?php if (!$by_po) { ?>
        <?= emptyStateHtml(['thing' => 'deliveries', 'icon' => 'fa-truck-loading', 'filtered' => (bool) ($po_filter || $vendor_filter),
            'title' => 'Nothing to receive', 'text' => 'Lines show here once their purchase order is marked ordered, until all of them have come in.']) ?>
    <?php } else { ?>
        <form action="post.php" method="post" autocomplete="off" data-receiving>
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <?php if ($po_filter) { ?><input type="hidden" name="po_id" value="<?= $po_filter ?>"><?php } ?>
            <?php foreach ($by_po as $po_id => $lines) {
                $head = $lines[0]; ?>
                <div class="border-top" data-receiving-po="<?= intval($po_id) ?>">
                    <div class="d-flex flex-wrap align-items-center gap-2 px-3 py-2 bg-body-tertiary">
                        <a class="fw-semibold" href="purchase_order.php?po_id=<?= intval($po_id) ?>"><?= escapeHtml(poNumber($head)) ?></a>
                        <span class="text-body-secondary">&middot; <?= escapeHtml((string) $head['vendor_name']) ?></span>
                        <?php if ($head['po_order_date']) { ?><span class="small text-body-secondary">&middot; ordered <?= escapeHtml(date('M j', strtotime($head['po_order_date']))) ?></span><?php } ?>
                        <?php if ($head['po_vendor_reference']) { ?><span class="small text-body-secondary">&middot; their order <?= escapeHtml($head['po_vendor_reference']) ?></span><?php } ?>
                        <span class="ms-auto small">
                            <?php foreach (array_slice($tracking[intval($po_id)] ?? [], 0, 3) as $row) { ?>
                                <span class="ms-2"><?= poTrackingHtml($row) ?></span>
                            <?php } ?>
                        </span>
                    </div>
                    <div class="table-responsive">
                        <?php // Fixed widths, so the columns line up from one purchase order to the next ?>
                        <table class="table align-middle mb-0" style="table-layout: fixed; min-width: 52rem;">
                            <thead class="text-nowrap small">
                            <tr>
                                <th class="ps-3">Item</th>
                                <th class="text-end" style="width: 6.5rem;">Ordered</th>
                                <th class="text-end" style="width: 6.5rem;">In so far</th>
                                <th style="width: 11rem;">Came in</th>
                                <th style="width: 34%;">Serials</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($lines as $line) {
                                $item_id = intval($line['po_item_id']);
                                $left = poItemLeft($line);
                                $client_id = intval($line['po_item_client_id']);
                                $type = $client_id ? poAssetTypeFor($line) : '';
                                $stock = !$client_id && intval($line['po_item_product_id']) && intval($line['product_track_stock']); ?>
                                <tr data-receiving-line="<?= $item_id ?>" data-left="<?= $left ?>">
                                    <td class="ps-3">
                                        <div class="fw-semibold"><?= escapeHtml($line['po_item_name']) ?></div>
                                        <div class="small text-body-secondary">
                                            <?php if ($client_id) { ?>
                                                <i class="fas fa-fw fa-user-friends me-1"></i><?= escapeHtml((string) $line['client_name']) ?>
                                            <?php } elseif ($stock) { ?>
                                                <i class="fas fa-fw fa-warehouse me-1"></i>Our stock - goes on the shelf<?= $can_stock ? '' : ' (needs Sales edit access)' ?>
                                            <?php } else { ?>
                                                <i class="fas fa-fw fa-warehouse me-1"></i>Our stock
                                            <?php } ?>
                                            <?php if ($line['po_item_vendor_part']) { ?> &middot; <span class="font-monospace"><?= escapeHtml($line['po_item_vendor_part']) ?></span><?php } ?>
                                        </div>
                                    </td>
                                    <td class="text-end"><?= escapeHtml(billingQuantity($line['po_item_quantity'])) ?></td>
                                    <td class="text-end"><?= escapeHtml(billingQuantity($line['po_item_received'])) ?></td>
                                    <td>
                                        <?php if ($can_edit) { ?>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text"><i class="fas fa-fw fa-dolly"></i></span>
                                                <input type="text" class="form-control text-end" name="received[<?= $item_id ?>][qty]" inputmode="decimal" placeholder="of <?= escapeHtml(billingQuantity($left)) ?>" aria-label="How many came in" data-receive-qty>
                                                <button type="button" class="btn btn-outline-secondary" data-receive-all title="All <?= escapeHtml(billingQuantity($left)) ?>">All</button>
                                            </div>
                                        <?php } else { ?>
                                            <span class="text-body-secondary small"><?= escapeHtml(billingQuantity($left)) ?> to come</span>
                                        <?php } ?>
                                    </td>
                                    <td>
                                        <?php if ($can_edit) { ?>
                                            <textarea class="form-control form-control-sm font-monospace" name="received[<?= $item_id ?>][serials]" rows="1" placeholder="Serial numbers, one a line" aria-label="Serial numbers"></textarea>
                                            <?php if ($client_id && $can_assets) { ?>
                                                <div class="input-group input-group-sm mt-1">
                                                    <span class="input-group-text"><i class="fas fa-fw fa-laptop"></i></span>
                                                    <select class="form-select" name="received[<?= $item_id ?>][asset_type]" aria-label="Add to the client's assets as">
                                                        <option value="">Don't add to assets</option>
                                                        <?php foreach ($type !== '' && !in_array($type, $asset_types, true) ? array_keys(productAssetTypes($type)) : $asset_types as $asset_type) { ?>
                                                            <option value="<?= escapeHtml($asset_type) ?>" <?= $asset_type === $type ? 'selected' : '' ?>>Add to assets as <?= escapeHtml($asset_type) ?></option>
                                                        <?php } ?>
                                                    </select>
                                                </div>
                                            <?php } ?>
                                        <?php } ?>
                                    </td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php } ?>
            <?php if ($can_edit) { ?>
                <div class="card-footer d-flex flex-wrap align-items-center gap-2">
                    <span class="small text-body-secondary me-auto">Only the lines with a number are received - the rest wait for next time.</span>
                    <button type="submit" name="po_receive" class="btn btn-primary fw-bold"><i class="fas fa-check me-2"></i>Receive</button>
                </div>
            <?php } ?>
        </form>
    <?php } ?>
</div>

<script src="<?= staticFileUrl('/js/purchase_orders.js') ?>"></script>
<?php require_once "../includes/footer.php";
