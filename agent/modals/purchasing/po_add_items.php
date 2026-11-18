<?php

/*
 * Waiting items onto a draft purchase order - either the items ticked on To
 * Order (?order_item_ids[]=) onto a draft picked here, or, from a draft
 * (?po_id=), the waiting items ticked here (its vendor's first). Posts po_add_items.
 */

require_once '../../../includes/modal_header.php';

poRequireLevel(2);

$po = intval($_GET['po_id'] ?? 0) ? poRow(intval($_GET['po_id'])) : null;
if (intval($_GET['po_id'] ?? 0) && (!$po || !poAccessible($po['po_id']) || $po['po_status'] !== 'draft')) {
    exit(json_encode(['error' => $po && $po['po_status'] !== 'draft' ? poNumber($po) . " isn't a draft any more." : 'That purchase order no longer exists.']));
}

if ($po) {
    // Every waiting item, its vendor's first
    $items = orderItemsWaiting();
    usort($items, function ($a, $b) use ($po) {
        return (intval($b['order_item_vendor_id']) === intval($po['po_vendor_id'])) <=> (intval($a['order_item_vendor_id']) === intval($po['po_vendor_id']));
    });
} else {
    $items = orderItemsByIds((array) ($_GET['order_item_ids'] ?? []));
    if (!$items) {
        exit(json_encode(['error' => 'Those items are no longer waiting - someone may have ordered them.']));
    }
    $drafts = [];
    $sql = mysqli_query($mysqli, "SELECT po_id, po_prefix, po_number, po_vendor_id, po_total, po_currency_code, vendor_name FROM purchase_orders
        LEFT JOIN vendors ON vendors.vendor_id = po_vendor_id WHERE po_status = 'draft'" . poScopeSql('po_id') . " ORDER BY po_id DESC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $drafts[] = $row;
    }
    if (!$drafts) {
        exit(json_encode(['error' => 'There is no draft purchase order to put them on - make one instead.']));
    }
    $item_vendors = array_filter(array_map('intval', array_column($items, 'order_item_vendor_id')));
    $vendor_id = count(array_unique($item_vendors)) === 1 ? reset($item_vendors) : 0;
}

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-cart-plus me-2"></i><?= $po ? 'From To Order' : 'Put on a Draft' ?><?= $po ? ' <span class="fw-normal text-white-50 ms-1">' . escapeHtml(poNumber($po)) . '</span>' : '' ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">
        <?php if ($po) { ?>
            <?php if (!$items) { ?>
                <p class="text-body-secondary mb-0">Nothing is waiting on To Order.</p>
            <?php } else { ?>
                <input type="hidden" name="po_id" value="<?= intval($po['po_id']) ?>">
                <p class="small text-body-secondary">Tick what goes on it - each from <?= escapeHtml((string) $po['vendor_name']) ?> once it is on.</p>
                <div class="list-group" data-po-add-items>
                    <?php foreach ($items as $item) {
                        $same = intval($item['order_item_vendor_id']) === intval($po['po_vendor_id']); ?>
                        <label class="list-group-item d-flex align-items-center gap-3">
                            <input class="form-check-input m-0" type="checkbox" name="order_item_ids[]" value="<?= intval($item['order_item_id']) ?>" <?= $same ? 'checked' : '' ?>>
                            <span class="flex-grow-1 min-w-0">
                                <span class="fw-semibold"><?= escapeHtml(billingQuantity($item['order_item_quantity'])) ?> x <?= escapeHtml($item['order_item_name']) ?></span>
                                <span class="d-block small text-body-secondary"><?= intval($item['order_item_client_id']) ? escapeHtml((string) $item['client_name']) : 'Our stock' ?>
                                    &middot; <?= $item['vendor_name'] ? escapeHtml($item['vendor_name']) : 'no vendor yet' ?></span>
                            </span>
                            <span class="small text-nowrap"><?= $item['order_item_cost'] !== null && floatval($item['order_item_cost']) > 0 ? escapeHtml(poMoney(round(floatval($item['order_item_quantity']) * floatval($item['order_item_cost']), 2))) : '' ?></span>
                        </label>
                    <?php } ?>
                </div>
            <?php } ?>
        <?php } else { ?>
            <?php foreach ($items as $item) { ?>
                <input type="hidden" name="order_item_ids[]" value="<?= intval($item['order_item_id']) ?>">
            <?php } ?>
            <p class="small text-body-secondary"><?= count($items) ?> item<?= count($items) === 1 ? '' : 's' ?> - each from the draft's vendor once it is on.</p>
            <label class="form-label">Draft <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-file-signature"></i></span>
                <select class="form-select select2" name="po_id" required>
                    <?php foreach ($drafts as $row) { ?>
                        <option value="<?= intval($row['po_id']) ?>" <?= $vendor_id && intval($row['po_vendor_id']) === $vendor_id ? 'selected' : '' ?>><?= escapeHtml(poNumber($row) . ' - ' . $row['vendor_name'] . ' - ' . poMoney($row['po_total'], $row['po_currency_code'])) ?></option>
                    <?php } ?>
                </select>
            </div>
        <?php } ?>
    </div>

    <div class="modal-footer">
        <?php if (!$po || $items) { ?>
            <button type="submit" name="po_add_items" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Put On</button>
        <?php } ?>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
