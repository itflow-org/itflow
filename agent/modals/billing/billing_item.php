<?php

/*
 * Edit a line on an invoice, quote or recurring invoice - its name and
 * description, quantity, price and tax, with what it comes to as you type.
 * ?type=invoice|quote|recurring&doc_id=&item_id=
 *
 * A line from a product keeps its link to the product; on an invoice,
 * changing the quantity of a stocked product moves the stock with it.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_sales', 2);

$type = (string) ($_GET['type'] ?? '');
$doc_id = intval($_GET['doc_id'] ?? 0);
$item_id = intval($_GET['item_id'] ?? 0);

$doc = billingType($type) ? billingRow($type, $doc_id) : null;
if (!$doc) {
    exit(json_encode(['error' => 'That document no longer exists.']));
}
$client_id = intval($doc['client_id']);
enforceClientAccess();

if (!billingCanEditItems($type, $doc)) {
    exit(json_encode(['error' => billingNumber($type, $doc) . " can't be changed now - " . ($type === 'quote' ? 'it has been answered or invoiced.' : 'it is paid, cancelled or non-billable.')]));
}

$item = null;
foreach (billingItems($type, $doc_id) as $line) {
    if (intval($line['item_id']) === $item_id) {
        $item = $line;
    }
}
if (!$item) {
    exit(json_encode(['error' => 'That line no longer exists.']));
}

$t = billingType($type);
$currency = (string) $doc["{$t['col']}_currency_code"];
$taxes = billingTaxes($item['item_created_at']);
$product_name = intval($item['item_product_id']) ? getFieldById('products', intval($item['item_product_id']), 'product_name') : '';
$product_type = intval($item['item_product_id']) ? getFieldById('products', intval($item['item_product_id']), 'product_type') : '';
$quantity = billingQuantity($item['item_quantity']);
$price = number_format(floatval($item['item_price']), 2, '.', '');

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-edit me-2"></i>Edit Line <span class="fw-normal text-white-50 ms-1"><?= escapeHtml(billingNumber($type, $doc)) ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off" data-billing-line-form>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="billing_item_save" value="1">
    <input type="hidden" name="type" value="<?= escapeHtml($type) ?>">
    <input type="hidden" name="doc_id" value="<?= $doc_id ?>">
    <input type="hidden" name="item_id" value="<?= $item_id ?>">

    <div class="modal-body">
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label">Item <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-cube"></i></span>
                    <input type="text" class="form-control" name="name" maxlength="200" value="<?= escapeHtml($item['item_name']) ?>" required data-line-name>
                </div>
                <?php if ($product_name !== '' && $product_name !== null) { ?>
                    <div class="form-text"><i class="fas fa-link me-1"></i>From the <?= $product_type === 'product' ? 'product' : 'service' ?> <strong><?= escapeHtml($product_name) ?></strong><?= $type === 'invoice' && $product_type === 'product' ? ' - changing the quantity moves its stock' : '' ?>.</div>
                <?php } ?>
            </div>
            <div class="col-12">
                <label class="form-label">Description</label>
                <div class="input-group">
                    <span class="input-group-text align-items-start pt-2"><i class="fa fa-fw fa-align-left"></i></span>
                    <textarea class="form-control" name="description" rows="3" placeholder="What it is, the period it covers, serial numbers - shown under the item" data-line-description><?= escapeHtml($item['item_description']) ?></textarea>
                </div>
            </div>
            <div class="col-sm-4">
                <label class="form-label">Quantity <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-hashtag"></i></span>
                    <input type="text" class="form-control text-end" name="qty" inputmode="decimal" value="<?= $quantity ?>" required data-line-qty>
                </div>
            </div>
            <div class="col-sm-4">
                <label class="form-label">Price <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                    <input type="text" class="form-control text-end" name="price" inputmode="decimal" value="<?= $price ?>" required data-line-price>
                </div>
                <div class="form-text">Each, in <?= escapeHtml($currency) ?>. A credit can be negative.</div>
            </div>
            <div class="col-sm-4">
                <label class="form-label">Tax</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-percent"></i></span>
                    <select class="form-select" name="tax_id" data-line-tax>
                        <option value="0" data-percent="0">No tax</option>
                        <?php foreach ($taxes as $tax_id => $tax) { ?>
                            <option value="<?= intval($tax_id) ?>" data-percent="<?= floatval($tax['tax_percent']) ?>" <?= intval($item['item_tax_id']) === intval($tax_id) ? 'selected' : '' ?>><?= escapeHtml($tax['tax_name'] . ' ' . billingQuantity($tax['tax_percent']) . '%') ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
        </div>
        <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
            <span class="text-body-secondary">The line comes to</span>
            <span class="fs-5 fw-semibold itflow-money" data-line-preview><?= escapeHtml(billingMoney($item['item_total'], $currency)) ?></span>
        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<script>
(function () {
    var form = document.querySelector('form[data-billing-line-form]:not([data-wired])');
    if (!form) {
        return;
    }
    form.setAttribute('data-wired', '1');
    if (typeof itflowBillingLineForm === 'function') {
        itflowBillingLineForm(form, { currency: <?= json_encode($currency) ?>, locale: <?= json_encode(str_replace('_', '-', (string) $session_company_locale)) ?> });
    }
})();
</script>

<?php
require_once '../../../includes/modal_footer.php';
