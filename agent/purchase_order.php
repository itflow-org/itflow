<?php

/*
 * A purchase order. ?po_id=
 *
 * What it is and where it stands, with the next step: send it for approval,
 * approve it or send it back, mark it ordered or email it to the vendor,
 * receive what comes in. Its lines - changed on a draft, with what has come in
 * once ordered - shipping and the total. Beside them who it is from and where
 * it ships, the tracking numbers, the vendor's bill and what has been received
 * (each can be undone), then its history. The work is in
 * functions/purchase_orders.php; the handlers are agent/post/purchase_order.php.
 */

require_once "includes/inc_all.php";

poRequireLevel(1);

$po = poRow(intval($_GET['po_id'] ?? 0));

if (!$po || !poAccessible($po['po_id'])) {

    echo '<div class="itflow-asset-empty mt-5"><i class="fas fa-file-signature fa-3x mb-3 opacity-50"></i><h1 class="h4">That purchase order isn\'t here</h1>'
        . '<p>It may be for a client you can\'t see.</p><a class="btn btn-outline-secondary" href="purchase_orders.php"><i class="fas fa-arrow-left me-2"></i>Back to Purchase Orders</a></div>';

} else {

    $po_id = intval($po['po_id']);
    $status = $po['po_status'];
    $number = poNumber($po);
    $currency = (string) $po['po_currency_code'];
    $money = function ($amount) use ($currency) {
        return escapeHtml(poMoney($amount, $currency));
    };
    $level = poLevel();
    $can_edit = $level >= 2;
    $draft = $status === 'draft';
    $arriving = in_array($status, ['ordered', 'partial'], true);
    $ordered_or_after = in_array($status, ['ordered', 'partial', 'received'], true);
    $items = poItems($po_id);
    $tracking = poTracking($po_id);
    $receipts = poReceipts($po_id);
    $expense = poExpense($po);
    $history = auditLogHistory('Purchase Order', $po_id);
    $needs_approval = $draft && poNeedsApproval($po);
    $can_order = $can_edit && poCanOrder($po);
    $may_approve = poMayApprove($po);
    $can_cancel = ($draft && $can_edit) || ($level >= 3 && in_array($status, ['pending', 'approved', 'ordered'], true) && !array_filter($items, function ($item) {
        return floatval($item['po_item_received']) > 0;
    }));
    $can_bill = $can_edit && lookupUserPermission('module_financial') >= 2 && $ordered_or_after && !$expense;
    $subtotal = 0;
    foreach ($items as $item) {
        $subtotal += round(floatval($item['po_item_quantity']) * floatval($item['po_item_cost']), 2);
    }
    $qty = array_sum(array_map('floatval', array_column($items, 'po_item_quantity')));
    $received = array_sum(array_map(function ($item) {
        return min(floatval($item['po_item_received']), floatval($item['po_item_quantity']));
    }, $items));
    $waiting_count = $draft && poReady() ? intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM order_items WHERE order_item_status = 'waiting'" . clientScopeSql('order_item_client_id')))[0]) : 0;
    $csrf = $_SESSION['csrf_token'];
    $modal = function ($file, $params = '') use ($po_id) {
        return "modals/purchasing/$file.php?po_id=$po_id" . ($params !== '' ? "&$params" : '');
    };
    $vendor_email = (string) ($po['vendor_contact_email'] ?: $po['vendor_email']);

    // Override Tab Title // No Sanitizing needed as this var will only be used in the tab title
    $page_title = $number;

    ?>

    <ol class="breadcrumb d-print-none">
        <li class="breadcrumb-item"><a href="purchase_orders.php">Purchase Orders</a></li>
        <li class="breadcrumb-item active"><?= escapeHtml($number) ?></li>
    </ol>

    <!-- What it is, and the next step -->
    <div class="card mb-3">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <span class="itflow-asset-icon itflow-asset-icon-lg itflow-asset-tone-other" aria-hidden="true"><i class="fas fa-fw fa-file-signature"></i></span>

            <div class="flex-grow-1 min-w-0">
                <h1 class="h4 fw-semibold mb-1 d-flex flex-wrap align-items-center gap-2">
                    <span><?= escapeHtml($number) ?></span>
                    <span class="fs-6"><?= poStatusBadge($status) ?></span>
                </h1>
                <div class="text-body-secondary small d-flex flex-wrap column-gap-3 row-gap-1">
                    <span><i class="fas fa-fw fa-building me-1"></i><a class="text-reset" href="vendor.php?vendor_id=<?= intval($po['po_vendor_id']) ?>"><?= escapeHtml((string) $po['vendor_name']) ?></a></span>
                    <span title="<?= escapeHtml($po['po_created_at']) ?>"><i class="fas fa-fw fa-pencil-alt me-1"></i>Made <?= escapeHtml(date('M j, Y', strtotime($po['po_created_at']))) ?><?= $po['created_by_name'] ? ' by ' . escapeHtml($po['created_by_name']) : '' ?></span>
                    <?php if ($po['po_approved_at'] && $po['approved_by_name'] && in_array($status, ['approved', 'ordered', 'partial', 'received'], true)) { ?>
                        <span title="<?= escapeHtml((string) $po['po_approval_note']) ?>"><i class="fas fa-fw fa-thumbs-up me-1"></i>Approved by <?= escapeHtml($po['approved_by_name']) ?></span>
                    <?php } ?>
                    <?php if ($po['po_order_date']) { ?><span><i class="fas fa-fw fa-paper-plane me-1"></i>Ordered <?= escapeHtml(date('M j, Y', strtotime($po['po_order_date']))) ?></span><?php } ?>
                    <?php if ($po['po_vendor_reference']) { ?><span title="Their order number"><i class="fas fa-fw fa-hashtag me-1"></i><?= escapeHtml($po['po_vendor_reference']) ?></span><?php } ?>
                    <?php if ($ordered_or_after && $qty > 0) { ?><span><i class="fas fa-fw fa-truck-loading me-1"></i><?= escapeHtml(billingQuantity($received)) ?> of <?= escapeHtml(billingQuantity($qty)) ?> in</span><?php } ?>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 d-print-none" data-po-actions>
                <?php if ($may_approve) { ?>
                    <button type="button" class="btn btn-success ajax-modal" data-modal-url="<?= $modal('po_decide', 'approve=1') ?>"><i class="fas fa-thumbs-up me-2"></i>Approve</button>
                    <button type="button" class="btn btn-outline-secondary ajax-modal" data-modal-url="<?= $modal('po_decide', 'approve=0') ?>"><i class="fas fa-undo me-2"></i>Send Back</button>
                <?php } elseif ($can_edit && $draft && $needs_approval && $items) { ?>
                    <form action="post.php" method="post" class="m-0">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <input type="hidden" name="po_id" value="<?= $po_id ?>">
                        <button type="submit" name="po_send_approval" class="btn btn-primary"><i class="fas fa-hourglass-half me-2"></i>Send for Approval</button>
                    </form>
                <?php } elseif ($can_order) { ?>
                    <button type="button" class="btn btn-primary ajax-modal" data-modal-url="<?= $modal('po_email') ?>"><i class="fas fa-paper-plane me-2"></i>Email to Vendor</button>
                    <button type="button" class="btn btn-outline-primary ajax-modal" data-modal-url="<?= $modal('po_ordered') ?>"><i class="fas fa-check me-2"></i>Mark Ordered</button>
                <?php } elseif ($can_edit && $arriving) { ?>
                    <a class="btn btn-primary" href="receiving.php?po_id=<?= $po_id ?>"><i class="fas fa-truck-loading me-2"></i>Receive</a>
                <?php } ?>
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-h"></i><span class="visually-hidden">More</span></button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <a class="dropdown-item" href="post.php?po_pdf=<?= $po_id ?>&csrf_token=<?= $csrf ?>" target="_blank"><i class="fas fa-fw fa-file-pdf text-secondary me-2"></i>PDF</a>
                        <?php if ($can_edit && $ordered_or_after) { ?>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= $modal('po_email') ?>"><i class="fas fa-fw fa-envelope text-secondary me-2"></i>Email to Vendor</a>
                        <?php } ?>
                        <?php if ($can_edit && !in_array($status, ['received', 'cancelled'], true)) { ?>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= $modal('po_details') ?>"><i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit Details</a>
                        <?php } ?>
                        <?php if ($can_edit && in_array($status, ['pending', 'approved'], true)) { ?>
                            <a class="dropdown-item confirm-link" href="post.php?po_back_to_draft=<?= $po_id ?>&csrf_token=<?= $csrf ?>" data-confirm-title="Back to draft?" data-confirm-text="To change it. <?= $status === 'approved' ? 'It will need approving again if it is over the approval amount.' : 'It stops waiting for approval.' ?>" data-confirm-button="Back to Draft"><i class="fas fa-fw fa-pencil-alt text-secondary me-2"></i>Back to Draft</a>
                        <?php } ?>
                        <?php if ($can_bill) { ?>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= $modal('po_bill') ?>"><i class="fas fa-fw fa-receipt text-secondary me-2"></i>Record Vendor Bill</a>
                        <?php } ?>
                        <?php if ($can_cancel) { ?>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-danger confirm-link" href="post.php?po_cancel=<?= $po_id ?>&csrf_token=<?= $csrf ?>" data-confirm-title="Cancel <?= escapeHtml($number) ?>?" data-confirm-text="Its items go back on To Order.<?= $ordered_or_after ? ' Tell the vendor too - cancelling it here sends them nothing.' : '' ?>" data-confirm-button="Cancel It"><i class="fas fa-fw fa-ban me-2"></i>Cancel</a>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($status === 'pending') {
        $approvers = array_diff_key(poApprovers($po), [intval($po['po_created_by']) => true]);
        $names = array_column($approvers, 'user_name'); ?>
        <div class="alert alert-warning d-flex align-items-center gap-2" data-po-note="pending">
            <i class="fas fa-fw fa-hourglass-half"></i>
            <span>Sent for approval <?= escapeHtml(timeAgo($po['po_submitted_at'])) ?> - <?= $may_approve ? 'you can approve it.' : ($names ? 'waiting for ' . escapeHtml(implode(', ', array_slice($names, 0, 4))) . (count($names) > 4 ? ' or another approver' : '') . '.' : 'waiting for an admin.') ?></span>
        </div>
    <?php } elseif ($draft && $po['po_approval_note']) { ?>
        <div class="alert alert-info d-flex align-items-center gap-2" data-po-note="sent-back">
            <i class="fas fa-fw fa-undo"></i><span>Sent back: <?= escapeHtml($po['po_approval_note']) ?></span>
        </div>
    <?php } elseif ($draft && $needs_approval && $items) { ?>
        <div class="alert alert-secondary d-flex align-items-center gap-2" data-po-note="needs-approval">
            <i class="fas fa-fw fa-info-circle"></i>
            <span>It needs approving before it is ordered - <?= poSettings()['approval_over'] > 0 && floatval($po['po_total']) <= poSettings()['approval_over'] ? 'a line has no cost.' : (poSettings()['approval_over'] > 0 ? 'it is over ' . escapeHtml(poMoney(poSettings()['approval_over'])) . '.' : 'every purchase order does.') ?></span>
        </div>
    <?php } ?>

    <div class="row g-3">
        <div class="col-lg-8">
            <!-- The lines -->
            <div class="card mb-3">
                <div class="card-header d-flex flex-wrap align-items-center gap-2">
                    <h3 class="card-title mb-0 me-auto"><i class="fas fa-fw fa-list me-2 text-secondary"></i>Lines <span class="text-body-secondary fw-normal">(<?= count($items) ?>)</span></h3>
                    <?php if ($can_edit && $draft) { ?>
                        <?php if ($waiting_count) { ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary ajax-modal" data-modal-size="lg" data-modal-url="<?= $modal('po_add_items') ?>"><i class="fas fa-cart-plus me-1"></i>From To Order</button>
                        <?php } ?>
                        <button type="button" class="btn btn-sm btn-primary ajax-modal" data-modal-url="<?= $modal('po_line') ?>"><i class="fas fa-plus me-1"></i>Add Line</button>
                    <?php } ?>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0" data-po-lines>
                        <thead class="table-light text-nowrap">
                        <tr>
                            <th class="ps-3">Item</th>
                            <th class="text-end">Qty</th>
                            <?php if ($ordered_or_after) { ?><th class="text-end">In</th><?php } ?>
                            <th class="text-end">Unit cost</th>
                            <th class="text-end">Amount</th>
                            <?php if ($can_edit && $draft) { ?><th></th><?php } ?>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($items as $item) {
                            $item_id = intval($item['po_item_id']);
                            $left = poItemLeft($item); ?>
                            <tr data-po-item-id="<?= $item_id ?>">
                                <td class="ps-3">
                                    <?php $product_link = $item['product_name'] && lookupUserPermission('module_sales') >= 1 ? 'product.php?product_id=' . intval($item['po_item_product_id']) : ''; ?>
                                    <div class="fw-semibold"><?= $product_link !== '' && $item['product_name'] === $item['po_item_name'] ? '<a class="text-reset" href="' . $product_link . '" title="Open the product">' . escapeHtml($item['po_item_name']) . '</a>' : escapeHtml($item['po_item_name']) ?></div>
                                    <div class="small text-body-secondary d-flex flex-wrap column-gap-3">
                                        <?php if ($item['po_item_vendor_part']) { ?><span title="Their part number"><i class="fas fa-fw fa-barcode me-1"></i><span class="font-monospace"><?= escapeHtml($item['po_item_vendor_part']) ?></span></span><?php } ?>
                                        <?php if (intval($item['po_item_client_id'])) { ?>
                                            <span><i class="fas fa-fw fa-user-friends me-1"></i><a class="text-reset" href="client_overview.php?client_id=<?= intval($item['po_item_client_id']) ?>"><?= escapeHtml((string) $item['client_name']) ?></a></span>
                                        <?php } else { ?>
                                            <span><i class="fas fa-fw fa-warehouse me-1"></i>Our stock<?= intval($item['product_track_stock']) ? ' - goes on the shelf' : '' ?></span>
                                        <?php } ?>
                                        <?php if ($product_link !== '' && $item['product_name'] !== $item['po_item_name']) { ?>
                                            <span><i class="fas fa-fw fa-box-open me-1"></i><a class="text-reset" href="<?= $product_link ?>"><?= escapeHtml($item['product_name']) ?></a></span>
                                        <?php } ?>
                                    </div>
                                    <?php if (trim((string) $item['po_item_description']) !== '') { ?>
                                        <div class="small text-body-secondary"><?= nl2br(escapeHtml($item['po_item_description'])) ?></div>
                                    <?php } ?>
                                </td>
                                <td class="text-end"><?= escapeHtml(billingQuantity($item['po_item_quantity'])) ?></td>
                                <?php if ($ordered_or_after) { ?>
                                    <td class="text-end text-nowrap <?= $left > 0 ? '' : 'text-success-emphasis' ?>"><?= escapeHtml(billingQuantity($item['po_item_received'])) ?><?= $left > 0 ? '' : ' <i class="fas fa-check"></i>' ?></td>
                                <?php } ?>
                                <td class="text-end <?= floatval($item['po_item_cost']) > 0 ? '' : 'text-warning-emphasis' ?>"><?= floatval($item['po_item_cost']) > 0 ? $money($item['po_item_cost']) : 'No cost' ?></td>
                                <td class="text-end fw-semibold"><?= $money(round(floatval($item['po_item_quantity']) * floatval($item['po_item_cost']), 2)) ?></td>
                                <?php if ($can_edit && $draft) { ?>
                                    <td class="text-end text-nowrap">
                                        <button type="button" class="btn btn-sm btn-light ajax-modal" title="Edit" data-modal-url="<?= $modal('po_line', "item_id=$item_id") ?>"><i class="fas fa-fw fa-edit"></i></button>
                                        <a class="btn btn-sm btn-light text-danger confirm-link" title="Take off" href="post.php?po_line_remove=<?= $item_id ?>&po_id=<?= $po_id ?>&csrf_token=<?= $csrf ?>"
                                            data-confirm-title="Take <?= escapeHtml($item['po_item_name']) ?> off?" data-confirm-text="<?= intval($item['po_item_order_item_id']) ? 'It goes back on To Order.' : 'It is taken off the purchase order.' ?>" data-confirm-button="Take Off"><i class="fas fa-fw fa-times"></i></a>
                                    </td>
                                <?php } ?>
                            </tr>
                        <?php } ?>
                        <?php if (!$items) { ?>
                            <tr><td colspan="6" class="text-center text-body-secondary py-4" data-po-empty>Nothing on it yet<?= $can_edit && $draft ? ' - add a line, or items from To Order.' : '.' ?></td></tr>
                        <?php } ?>
                        </tbody>
                        <tfoot>
                        <?php $span = 3 + ($ordered_or_after ? 1 : 0); ?>
                        <tr>
                            <td colspan="<?= $span ?>" class="text-end text-body-secondary border-0 pb-0">Subtotal</td>
                            <td class="text-end border-0 pb-0"><?= $money($subtotal) ?></td>
                            <?php if ($can_edit && $draft) { ?><td class="border-0"></td><?php } ?>
                        </tr>
                        <tr>
                            <td colspan="<?= $span ?>" class="text-end text-body-secondary border-0 pb-0">Shipping</td>
                            <td class="text-end border-0 pb-0"><?= floatval($po['po_shipping']) > 0 ? $money($po['po_shipping']) : '<span class="text-body-tertiary">-</span>' ?></td>
                            <?php if ($can_edit && $draft) { ?><td class="border-0"></td><?php } ?>
                        </tr>
                        <tr>
                            <td colspan="<?= $span ?>" class="text-end fw-bold border-0">Total</td>
                            <td class="text-end fw-bold fs-5 border-0" data-po-total><?= $money($po['po_total']) ?></td>
                            <?php if ($can_edit && $draft) { ?><td class="border-0"></td><?php } ?>
                        </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <?php if ($receipts) { ?>
                <!-- What has come in -->
                <div class="card mb-3">
                    <div class="card-header"><h3 class="card-title mb-0"><i class="fas fa-fw fa-truck-loading me-2 text-secondary"></i>Received <span class="text-body-secondary fw-normal">(<?= count($receipts) ?>)</span></h3></div>
                    <ul class="list-group list-group-flush" data-po-receipts>
                        <?php foreach ($receipts as $receipt) {
                            $asset_count = count(array_filter(explode(',', (string) $receipt['po_receipt_asset_ids']))); ?>
                            <li class="list-group-item d-flex flex-wrap align-items-center gap-2" data-po-receipt-id="<?= intval($receipt['po_receipt_id']) ?>">
                                <span class="itflow-asset-icon itflow-asset-icon-sm bg-success-subtle text-success-emphasis"><i class="fas fa-fw fa-dolly"></i></span>
                                <div class="flex-grow-1 min-w-0">
                                    <div><strong><?= escapeHtml(billingQuantity($receipt['po_receipt_quantity'])) ?></strong> x <?= escapeHtml($receipt['po_item_name']) ?></div>
                                    <div class="small text-body-secondary">
                                        <span title="<?= escapeHtml($receipt['po_receipt_at']) ?>"><?= escapeHtml(timeAgo($receipt['po_receipt_at'])) ?></span><?= $receipt['user_name'] ? ' by ' . escapeHtml($receipt['user_name']) : '' ?>
                                        <?= intval($receipt['po_receipt_stock_id']) ? ' &middot; on the shelf' : '' ?>
                                        <?= $asset_count ? " &middot; $asset_count asset" . ($asset_count === 1 ? '' : 's') . ' made' : '' ?>
                                        <?php if ($receipt['po_receipt_serials']) { ?> &middot; <span class="font-monospace"><?= escapeHtml(str_replace("\n", ', ', $receipt['po_receipt_serials'])) ?></span><?php } ?>
                                    </div>
                                </div>
                                <?php if ($level >= 3 && $ordered_or_after) { ?>
                                    <a class="btn btn-sm btn-light confirm-link" href="post.php?po_receipt_undo=<?= intval($receipt['po_receipt_id']) ?>&csrf_token=<?= $csrf ?>"
                                        data-confirm-title="Undo this receipt?" data-confirm-text="It is waiting to come in again<?= intval($receipt['po_receipt_stock_id']) ? ', what it put on the shelf comes off' : '' ?><?= $asset_count ? " and the $asset_count asset" . ($asset_count === 1 ? '' : 's') . ' it made ' . ($asset_count === 1 ? 'is' : 'are') . ' deleted' : '' ?>." data-confirm-button="Undo"><i class="fas fa-undo me-1"></i>Undo</a>
                                <?php } ?>
                            </li>
                        <?php } ?>
                    </ul>
                </div>
            <?php } ?>
        </div>

        <div class="col-lg-4">
            <!-- Who it is from, and where it goes -->
            <div class="card mb-3">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title mb-0 me-auto"><i class="fas fa-fw fa-info-circle me-2 text-secondary"></i>Details</h3>
                    <?php if ($can_edit && !in_array($status, ['received', 'cancelled'], true)) { ?>
                        <button type="button" class="btn btn-sm btn-light ajax-modal" title="Edit details" data-modal-url="<?= $modal('po_details') ?>"><i class="fas fa-fw fa-edit"></i></button>
                    <?php } ?>
                </div>
                <div class="card-body small" data-po-details>
                    <div class="text-uppercase text-body-secondary fw-semibold small mb-1">Vendor</div>
                    <div class="fw-semibold"><?= escapeHtml((string) $po['vendor_name']) ?></div>
                    <?php if ($po['vendor_contact_name']) { ?><div><?= escapeHtml($po['vendor_contact_name']) ?></div><?php } ?>
                    <?php if ($vendor_email !== '') { ?><div><a href="mailto:<?= escapeHtml($vendor_email) ?>"><?= escapeHtml($vendor_email) ?></a></div><?php } ?>
                    <?php if ($po['vendor_phone']) { ?><div><?= escapeHtml(formatPhoneNumber($po['vendor_phone'], $po['vendor_phone_country_code'])) ?></div><?php } ?>
                    <?php if ($po['vendor_account_number']) { ?><div class="text-body-secondary">Our account <?= escapeHtml($po['vendor_account_number']) ?></div><?php } ?>

                    <div class="text-uppercase text-body-secondary fw-semibold small mt-3 mb-1">Ship to</div>
                    <div><?= nl2br(escapeHtml(trim((string) $po['po_ship_to']))) ?></div>

                    <?php if (trim((string) $po['po_note']) !== '') { ?>
                        <div class="text-uppercase text-body-secondary fw-semibold small mt-3 mb-1">Note for the vendor</div>
                        <div><?= nl2br(escapeHtml($po['po_note'])) ?></div>
                    <?php } ?>
                </div>
            </div>

            <?php if ($ordered_or_after || $tracking) { ?>
                <!-- Tracking -->
                <div class="card mb-3">
                    <div class="card-header d-flex align-items-center">
                        <h3 class="card-title mb-0 me-auto"><i class="fas fa-fw fa-shipping-fast me-2 text-secondary"></i>Tracking</h3>
                        <?php if ($can_edit && $ordered_or_after) { ?>
                            <button type="button" class="btn btn-sm btn-light ajax-modal" title="Add tracking" data-modal-url="<?= $modal('po_tracking') ?>"><i class="fas fa-fw fa-plus"></i></button>
                        <?php } ?>
                    </div>
                    <ul class="list-group list-group-flush small" data-po-tracking>
                        <?php foreach ($tracking as $row) { ?>
                            <li class="list-group-item d-flex align-items-center gap-2">
                                <span class="flex-grow-1"><?= poTrackingHtml($row) ?></span>
                                <?php if ($can_edit) { ?>
                                    <a class="text-body-tertiary confirm-link" title="Take it off" href="post.php?po_tracking_remove=<?= intval($row['po_tracking_id']) ?>&po_id=<?= $po_id ?>&csrf_token=<?= $csrf ?>" data-confirm-title="Take this tracking number off?" data-confirm-button="Take Off"><i class="fas fa-times"></i></a>
                                <?php } ?>
                            </li>
                        <?php } ?>
                        <?php if (!$tracking) { ?>
                            <li class="list-group-item text-body-secondary">None yet - add the numbers the vendor sends.</li>
                        <?php } ?>
                    </ul>
                </div>

                <!-- The vendor's bill -->
                <div class="card mb-3">
                    <div class="card-header"><h3 class="card-title mb-0"><i class="fas fa-fw fa-receipt me-2 text-secondary"></i>Vendor bill</h3></div>
                    <div class="card-body small" data-po-bill>
                        <?php if ($expense) { ?>
                            <div class="d-flex align-items-center gap-2">
                                <i class="fas fa-fw fa-check-circle text-success"></i>
                                <span class="flex-grow-1">Recorded as an expense - <strong><?= escapeHtml(poMoney($expense['expense_amount'], $expense['expense_currency_code'])) ?></strong> on <?= escapeHtml(date('M j, Y', strtotime($expense['expense_date']))) ?><?= $expense['expense_reference'] ? ', their invoice ' . escapeHtml($expense['expense_reference']) : '' ?>.</span>
                            </div>
                            <?php if (lookupUserPermission('module_financial') >= 1) { ?>
                                <a class="d-inline-block mt-2" href="expenses.php?open=<?= intval($expense['expense_id']) ?>"><i class="fas fa-fw fa-external-link-alt me-1"></i>Open the expense</a>
                            <?php } ?>
                        <?php } elseif ($can_bill) { ?>
                            <p class="text-body-secondary mb-2">When the vendor's invoice comes, record it as an expense here - it stays linked to <?= escapeHtml($number) ?>.</p>
                            <button type="button" class="btn btn-sm btn-outline-primary ajax-modal" data-modal-url="<?= $modal('po_bill') ?>"><i class="fas fa-receipt me-1"></i>Record Vendor Bill</button>
                        <?php } else { ?>
                            <span class="text-body-secondary">Not recorded yet<?= $can_edit && lookupUserPermission('module_financial') < 2 ? ' - recording it needs Financial edit access' : '' ?>.</span>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>

            <?= auditLogHistoryCard($history, ['empty' => 'Nothing logged yet.']) ?>
        </div>
    </div>

<?php }

require_once "../includes/footer.php";
