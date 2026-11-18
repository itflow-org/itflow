<?php

/*
 * A line on a draft purchase order - new (?po_id=), or one on it
 * (&item_id=). Picking a product fills its name, cost and part number.
 * Posts po_line_save.
 */

require_once '../../../includes/modal_header.php';

poRequireLevel(2);

$po = poRow(intval($_GET['po_id'] ?? 0));
if (!$po || !poAccessible($po['po_id'])) {
    exit(json_encode(['error' => 'That purchase order no longer exists.']));
}
if ($po['po_status'] !== 'draft') {
    exit(json_encode(['error' => 'Only a draft can be changed - send it back to draft first.']));
}
$line = null;
if (intval($_GET['item_id'] ?? 0)) {
    $line = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM po_items WHERE po_item_id = " . intval($_GET['item_id']) . " AND po_item_po_id = " . intval($po['po_id'])));
    if (!$line) {
        exit(json_encode(['error' => 'That line is no longer on the purchase order.']));
    }
}
$product_id = intval($line['po_item_product_id'] ?? 0);
$client_id = intval($line['po_item_client_id'] ?? 0);

$products = [];
$sql = mysqli_query($mysqli, "SELECT product_id, product_name, product_cost, product_vendor_part, product_vendor_id FROM products WHERE product_archived_at IS NULL"
    . ($product_id ? " OR product_id = $product_id" : '') . " ORDER BY product_vendor_id = " . intval($po['po_vendor_id']) . " DESC, product_name");
while ($row = mysqli_fetch_assoc($sql)) {
    $products[] = $row;
}
$clients = [];
$sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients WHERE (client_archived_at IS NULL" . ($client_id ? " OR client_id = $client_id" : '') . ")" . clientNotLeadSql($client_id) . clientScopeSql('client_id') . " ORDER BY client_name");
while ($row = mysqli_fetch_assoc($sql)) {
    $clients[] = $row;
}
$form_id = 'poLine' . ($line ? intval($line['po_item_id']) : 'New');

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-list me-2"></i><?= $line ? 'Edit Line' : 'Add Line' ?> <span class="fw-normal text-white-50 ms-1"><?= escapeHtml(poNumber($po)) ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off" id="<?= $form_id ?>">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="po_id" value="<?= intval($po['po_id']) ?>">
    <?php if ($line) { ?><input type="hidden" name="po_item_id" value="<?= intval($line['po_item_id']) ?>"><?php } ?>

    <div class="modal-body">
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label">Product</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-box-open"></i></span>
                    <select class="form-select select2" name="product" data-line-product>
                        <option value="0">- Not from the catalog -</option>
                        <?php foreach ($products as $row) { ?>
                            <option value="<?= intval($row['product_id']) ?>" <?= intval($row['product_id']) === $product_id ? 'selected' : '' ?>
                                data-name="<?= escapeHtml($row['product_name']) ?>" data-cost="<?= number_format(floatval($row['product_cost']), 2, '.', '') ?>" data-part="<?= escapeHtml((string) $row['product_vendor_part']) ?>"><?= escapeHtml($row['product_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="col-sm-8">
                <label class="form-label">Item <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-tag"></i></span>
                    <input type="text" class="form-control" name="name" maxlength="255" required value="<?= escapeHtml((string) ($line['po_item_name'] ?? '')) ?>" data-line-name>
                </div>
            </div>
            <div class="col-sm-4">
                <label class="form-label">Their part number</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-barcode"></i></span>
                    <input type="text" class="form-control" name="vendor_part" maxlength="200" value="<?= escapeHtml((string) ($line['po_item_vendor_part'] ?? '')) ?>" data-line-part>
                </div>
            </div>
            <div class="col-12">
                <label class="form-label">Description</label>
                <div class="input-group">
                    <span class="input-group-text align-items-start pt-2"><i class="fas fa-fw fa-align-left"></i></span>
                    <textarea class="form-control" name="description" rows="2"><?= escapeHtml((string) ($line['po_item_description'] ?? '')) ?></textarea>
                </div>
            </div>
            <div class="col-sm-4">
                <label class="form-label">How many <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-hashtag"></i></span>
                    <input type="text" class="form-control text-end" name="quantity" inputmode="decimal" required value="<?= escapeHtml($line ? billingQuantity($line['po_item_quantity']) : '1') ?>">
                </div>
            </div>
            <div class="col-sm-4">
                <label class="form-label">Unit cost <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-shopping-cart"></i></span>
                    <input type="text" class="form-control text-end" name="cost" inputmode="decimal" required value="<?= $line ? number_format(floatval($line['po_item_cost']), 2, '.', '') : '' ?>" placeholder="0.00" data-line-cost>
                </div>
            </div>
            <div class="col-sm-4">
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
        </div>
    </div>

    <div class="modal-footer">
        <button type="submit" name="po_line_save" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i><?= $line ? 'Save' : 'Add' ?></button>
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
    var product = form.querySelector('[data-line-product]');
    product.addEventListener('change', function () {
        var option = product.options[product.selectedIndex];
        if (!option || option.value === '0') {
            return;
        }
        form.querySelector('[data-line-name]').value = option.dataset.name || '';
        form.querySelector('[data-line-cost]').value = option.dataset.cost || '';
        form.querySelector('[data-line-part]').value = option.dataset.part || '';
    });
})();
</script>

<?php
require_once '../../../includes/modal_footer.php';
