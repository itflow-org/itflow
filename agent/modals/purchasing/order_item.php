<?php

/*
 * An item for To Order - new, from a product (?product_id=, the product
 * page's Order More), or one still waiting (?id=). Picking a product fills its
 * name, cost and vendor. Posts order_item_save.
 */

require_once '../../../includes/modal_header.php';

poRequireLevel(2);

$item = intval($_GET['id'] ?? 0) ? orderItemRow(intval($_GET['id'])) : null;
if (intval($_GET['id'] ?? 0) && (!$item || $item['order_item_status'] !== 'waiting')) {
    exit(json_encode(['error' => $item ? 'It is on a purchase order already - change it there.' : 'That item is no longer on the list.']));
}
$product_id = $item ? intval($item['order_item_product_id']) : intval($_GET['product_id'] ?? 0);
$product = $product_id ? productRow($product_id) : null;

// Defaults for a new item from a product - enough to bring it back over its reorder level
$quantity = $item ? billingQuantity($item['order_item_quantity']) : '1';
if (!$item && $product) {
    $level = floatval($product['product_reorder_level']);
    $quantity = billingQuantity(floatval($product['product_reorder_qty']) > 0 ? floatval($product['product_reorder_qty']) : max(1, ceil($level - floatval($product['product_stock']) + 1)));
}
$cost = $item ? ($item['order_item_cost'] !== null ? number_format(floatval($item['order_item_cost']), 2, '.', '') : '') : ($product && floatval($product['product_cost']) > 0 ? number_format(floatval($product['product_cost']), 2, '.', '') : '');
$vendor_id = $item ? intval($item['order_item_vendor_id']) : ($product ? intval($product['product_vendor_id']) : 0);
$client_id = $item ? intval($item['order_item_client_id']) : intval($_GET['client_id'] ?? 0);

$products = [];
$sql = mysqli_query($mysqli, "SELECT product_id, product_name, product_cost, product_vendor_id, product_type FROM products WHERE product_archived_at IS NULL"
    . ($product_id ? " OR product_id = $product_id" : '') . " ORDER BY product_name");
while ($row = mysqli_fetch_assoc($sql)) {
    $products[] = $row;
}
$clients = [];
$sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients WHERE (client_archived_at IS NULL" . ($client_id ? " OR client_id = $client_id" : '') . ")" . clientNotLeadSql($client_id) . clientScopeSql('client_id') . " ORDER BY client_name");
while ($row = mysqli_fetch_assoc($sql)) {
    $clients[] = $row;
}
$form_id = 'orderItem' . ($item ? intval($item['order_item_id']) : 'New');

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-cart-plus me-2"></i><?= $item ? 'Edit Item' : 'Add to To Order' ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off" id="<?= $form_id ?>">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php if ($item) { ?><input type="hidden" name="order_item_id" value="<?= intval($item['order_item_id']) ?>"><?php } ?>

    <div class="modal-body">
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label">Product</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-box-open"></i></span>
                    <select class="form-select select2" name="product" data-order-product>
                        <option value="0">- Not from the catalog -</option>
                        <?php foreach ($products as $row) { ?>
                            <option value="<?= intval($row['product_id']) ?>" <?= intval($row['product_id']) === $product_id ? 'selected' : '' ?>
                                data-name="<?= escapeHtml($row['product_name']) ?>" data-cost="<?= floatval($row['product_cost']) > 0 ? number_format(floatval($row['product_cost']), 2, '.', '') : '' ?>" data-vendor="<?= intval($row['product_vendor_id']) ?>"><?= escapeHtml($row['product_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="form-text">Picking one fills in its name, cost and vendor. Our stock of a product that tracks stock goes on the shelf when it is received.</div>
            </div>
            <div class="col-12">
                <label class="form-label">Item <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-tag"></i></span>
                    <input type="text" class="form-control" name="name" maxlength="255" required value="<?= escapeHtml($item['order_item_name'] ?? ($product['product_name'] ?? '')) ?>" placeholder="What to order - 24 port PoE switch" data-order-name>
                </div>
            </div>
            <div class="col-12">
                <label class="form-label">Description</label>
                <div class="input-group">
                    <span class="input-group-text align-items-start pt-2"><i class="fas fa-fw fa-align-left"></i></span>
                    <textarea class="form-control" name="description" rows="2" placeholder="Model, size, colour - goes on the purchase order"><?= escapeHtml((string) ($item['order_item_description'] ?? '')) ?></textarea>
                </div>
            </div>
            <div class="col-sm-5">
                <label class="form-label">How many <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-hashtag"></i></span>
                    <input type="text" class="form-control text-end" name="quantity" inputmode="decimal" required value="<?= escapeHtml($quantity) ?>">
                </div>
            </div>
            <div class="col-sm-7">
                <label class="form-label">Unit cost</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-shopping-cart"></i></span>
                    <input type="text" class="form-control text-end" name="cost" inputmode="decimal" value="<?= escapeHtml($cost) ?>" placeholder="What one costs us" data-order-cost>
                </div>
            </div>
            <div class="col-sm-6">
                <label class="form-label">Vendor</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-building"></i></span>
                    <select class="form-select select2" name="vendor" data-order-vendor>
                        <option value="0">- Not decided yet -</option>
                        <?php foreach (poVendors($vendor_id) as $option_id => $option_name) { ?>
                            <option value="<?= $option_id ?>" <?= $option_id === $vendor_id ? 'selected' : '' ?>><?= escapeHtml($option_name) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="col-sm-6">
                <label class="form-label">For</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-user-friends"></i></span>
                    <select class="form-select select2" name="client">
                        <option value="0">Our own stock</option>
                        <?php foreach ($clients as $row) { ?>
                            <option value="<?= intval($row['client_id']) ?>" <?= intval($row['client_id']) === $client_id ? 'selected' : '' ?><?= selectOptionClient($row['client_id'], $row['client_name'], $row['client_abbreviation'] ?? '') ?>><?= escapeHtml($row['client_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="col-12">
                <label class="form-label">Note</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-sticky-note"></i></span>
                    <input type="text" class="form-control" name="note" maxlength="255" value="<?= escapeHtml((string) ($item['order_item_note'] ?? '')) ?>" placeholder="For whoever orders it - not on the purchase order">
                </div>
            </div>
        </div>
    </div>

    <div class="modal-footer">
        <button type="submit" name="order_item_save" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i><?= $item ? 'Save' : 'Add' ?></button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<script>
(function () {
    var form = document.getElementById('<?= $form_id ?>');
    if (!form || form.dataset.wired) {
        return;
    }
    form.dataset.wired = '1';
    // A product picked fills in what is still empty, and its vendor
    var product = form.querySelector('[data-order-product]');
    function filled() {
        var option = product.options[product.selectedIndex];
        if (!option || option.value === '0') {
            return;
        }
        var name = form.querySelector('[data-order-name]');
        var cost = form.querySelector('[data-order-cost]');
        var vendor = form.querySelector('[data-order-vendor]');
        name.value = option.dataset.name || name.value;
        if (option.dataset.cost) {
            cost.value = option.dataset.cost;
        }
        if (option.dataset.vendor && option.dataset.vendor !== '0' && vendor.querySelector('option[value="' + option.dataset.vendor + '"]')) {
            vendor.value = option.dataset.vendor;
            vendor.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }
    product.addEventListener('change', filled);
})();
</script>

<?php
require_once '../../../includes/modal_footer.php';
