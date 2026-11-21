<?php

/*
 * A new purchase order - from items ticked on To Order (?order_item_ids[]=),
 * or an empty one to add lines to. Who it is from: the vendor the items share,
 * or the one picked. It ships to the agent's office - change it on the PO.
 * Posts po_create.
 */

require_once '../../../includes/modal_header.php';

poRequireLevel(2);

$items = orderItemsByIds((array) ($_GET['order_item_ids'] ?? []));
if (!empty($_GET['order_item_ids']) && !$items) {
    exit(json_encode(['error' => 'Those items are no longer waiting - someone may have ordered them.']));
}
$item_vendors = array_count_values(array_filter(array_map('intval', array_column($items, 'order_item_vendor_id'))));
arsort($item_vendors);
$vendor_id = intval(array_key_first($item_vendors) ?? 0);
$mixed = count($item_vendors) > 1 || ($item_vendors && in_array(0, array_map('intval', array_column($items, 'order_item_vendor_id')), true));
$total = 0;
foreach ($items as $item) {
    $total += round(floatval($item['order_item_quantity']) * floatval($item['order_item_cost'] ?? 0), 2);
}
$office = officeForUser($session_user_id ?? 0);

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-file-signature me-2"></i>New Purchase Order</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php foreach ($items as $item) { ?>
        <input type="hidden" name="order_item_ids[]" value="<?= intval($item['order_item_id']) ?>">
    <?php } ?>

    <div class="modal-body">
        <?php if ($items) { ?>
            <div class="p-3 rounded bg-body-tertiary mb-3 small" data-po-new-items>
                <div class="fw-semibold mb-1"><?= count($items) ?> item<?= count($items) === 1 ? '' : 's' ?> from To Order<?= $total > 0 ? ' - about ' . escapeHtml(poMoney($total)) : '' ?></div>
                <?php foreach (array_slice($items, 0, 6) as $item) { ?>
                    <div class="text-truncate"><?= escapeHtml(billingQuantity($item['order_item_quantity'])) ?> x <?= escapeHtml($item['order_item_name']) ?></div>
                <?php } ?>
                <?php if (count($items) > 6) { ?><div class="text-body-secondary">and <?= count($items) - 6 ?> more</div><?php } ?>
            </div>
        <?php } ?>

        <label class="form-label">Vendor <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-building"></i></span>
            <select class="form-select select2" name="vendor" required>
                <option value="">- Select a vendor -</option>
                <?php foreach (poVendors() as $option_id => $option_name) { ?>
                    <option value="<?= $option_id ?>" <?= $option_id === $vendor_id ? 'selected' : '' ?>><?= escapeHtml($option_name) ?></option>
                <?php } ?>
            </select>
        </div>
        <div class="form-text">
            <?= $mixed ? 'Not all of them have this vendor - every item goes on this purchase order, from the vendor picked.' : 'Add a vendor on the Vendors page if they aren\'t here.' ?>
            It ships to <?= intval($office['office_id']) === 0 ? 'the main office' : escapeHtml($office['name']) ?><?= officesAll() && count(officesAll()) > 1 ? ' - change that on the purchase order' : '' ?>.
        </div>
    </div>

    <div class="modal-footer">
        <button type="submit" name="po_create" class="btn btn-primary fw-bold"><i class="fas fa-check me-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
