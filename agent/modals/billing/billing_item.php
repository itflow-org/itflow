<?php

/*
 * Edit a line on an invoice, quote or recurring invoice - its name and
 * description, quantity, price and tax, with what it comes to as you type.
 * ?type=invoice|quote|recurring&doc_id=&item_id=
 *
 * A line from a product keeps its link to the product; on an invoice,
 * changing the quantity of a stocked product moves the stock with it. On a
 * recurring invoice, the quantity can come from the client instead - their
 * users, devices or a license's seats, counted again for each invoice.
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
                    <textarea class="form-control" name="description" rows="3" placeholder="<?= $type === 'recurring' ? 'What it covers - {month}, {quarter}, {year} or {period} become the period each invoice covers' : 'What it is, the period it covers, serial numbers - shown under the item' ?>" data-line-description><?= escapeHtml($item['item_description']) ?></textarea>
                </div>
            </div>
            <?php if ($type === 'recurring') {
                $seat_options = recurringSeatOptions($client_id);
                $seat_source = (string) ($item['item_seat_source'] ?? '');
                $seat_filter = recurringSeatFilter($seat_source, $item['item_seat_filter'] ?? '');
                $seat_minimum = floatval($item['item_seat_minimum'] ?? 0); ?>
                <!-- Where the quantity comes from - counted again for each invoice -->
                <div class="col-12">
                    <label class="form-label">Quantity from</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-users"></i></span>
                        <select class="form-select" name="seat_source" data-seat-source>
                            <option value="">Typed in - the same every time</option>
                            <option value="contacts" <?= $seat_source === 'contacts' ? 'selected' : '' ?>>Users - the client's contacts (<?= intval($seat_options['contacts']) ?> now)</option>
                            <option value="assets" <?= $seat_source === 'assets' ? 'selected' : '' ?>>Devices - the client's devices in use (<?= intval($seat_options['assets']) ?> now)</option>
                            <option value="license" <?= $seat_source === 'license' ? 'selected' : '' ?> <?= $seat_options['licenses'] || $seat_source === 'license' ? '' : 'disabled' ?>>License seats - one of the client's licenses<?= $seat_options['licenses'] ? '' : ' (they have none)' ?></option>
                        </select>
                    </div>
                    <div class="form-text">Counted again each time an invoice is made, so a per-user or per-device line bills what the client has that month. Devices that are lost, stolen or retired aren't counted.</div>
                </div>
                <div class="col-12" data-seat-for="contacts" hidden>
                    <label class="form-label">Which contacts</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                        <select class="form-select" name="seat_tag" data-seat-input>
                            <option value="0">All of them</option>
                            <?php foreach ($seat_options['contact_tags'] as $tag_id => $tag) { ?>
                                <option value="<?= $tag_id ?>" <?= $seat_source === 'contacts' && intval($seat_filter['tag'] ?? 0) === $tag_id ? 'selected' : '' ?>>Tagged <?= escapeHtml($tag['tag_name']) ?> (<?= intval($tag['n']) ?>)</option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="form-text">Tag the contacts you bill for - "Licensed user", "Staff" - to leave out the rest.</div>
                </div>
                <div class="col-12" data-seat-for="assets" hidden>
                    <label class="form-label">Which devices</label>
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <?php foreach ($seat_options['asset_types'] as $asset_type => $asset_count) {
                            $type_id = 'seatType' . substr(md5($asset_type), 0, 8); ?>
                            <input type="checkbox" class="btn-check" name="seat_types[]" value="<?= escapeHtml($asset_type) ?>" id="<?= $type_id ?>" autocomplete="off" data-seat-input
                                <?= $seat_source === 'assets' && in_array($asset_type, $seat_filter['types'] ?? [], true) ? 'checked' : '' ?>>
                            <label class="btn btn-sm btn-outline-secondary" for="<?= $type_id ?>"><i class="fas fa-fw fa-<?= getAssetIcon($asset_type) ?> me-1"></i><?= escapeHtml($asset_type) ?> <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis ms-1"><?= $asset_count ?></span></label>
                        <?php } ?>
                        <?php if (!$seat_options['asset_types']) { ?>
                            <span class="small text-body-secondary">The client has no devices yet.</span>
                        <?php } ?>
                    </div>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                        <select class="form-select" name="seat_tag" data-seat-input>
                            <option value="0">Any tag</option>
                            <?php foreach ($seat_options['asset_tags'] as $tag_id => $tag) { ?>
                                <option value="<?= $tag_id ?>" <?= $seat_source === 'assets' && intval($seat_filter['tag'] ?? 0) === $tag_id ? 'selected' : '' ?>>Tagged <?= escapeHtml($tag['tag_name']) ?> (<?= intval($tag['n']) ?>)</option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="form-text">No types ticked counts every device - tick Desktop and Laptop for a per-workstation line, Server and Virtual Machine for per-server.</div>
                </div>
                <div class="col-12" data-seat-for="license" hidden>
                    <div class="row g-3">
                        <div class="col-sm-8">
                            <label class="form-label">License</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-fw fa-cube"></i></span>
                                <select class="form-select" name="seat_software_id" data-seat-input>
                                    <?php if ($seat_source === 'license' && !isset($seat_options['licenses'][intval($seat_filter['software_id'] ?? 0)])) { ?>
                                        <option value="0" selected disabled>The license it counted is gone - pick another</option>
                                    <?php } ?>
                                    <?php foreach ($seat_options['licenses'] as $software_id => $license) { ?>
                                        <option value="<?= $software_id ?>" <?= intval($seat_filter['software_id'] ?? 0) === $software_id ? 'selected' : '' ?>><?= escapeHtml($license['software_name']) ?> (<?= intval($license['used']) ?> in use<?= intval($license['software_seats']) ? ' of ' . intval($license['software_seats']) : '' ?>)</option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label">Count</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-fw fa-calculator"></i></span>
                                <select class="form-select" name="seat_count" data-seat-input>
                                    <option value="used">Seats in use</option>
                                    <option value="total" <?= ($seat_filter['count'] ?? '') === 'total' ? 'selected' : '' ?>>Seats bought</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-4" data-seat-for="any" hidden>
                    <label class="form-label">At least</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-arrow-down"></i></span>
                        <input type="text" class="form-control text-end" name="seat_minimum" inputmode="decimal" value="<?= $seat_minimum ? billingQuantity($seat_minimum) : '' ?>" placeholder="0" data-seat-input>
                    </div>
                    <div class="form-text">A contract's minimum.</div>
                </div>
                <div class="col-sm-8 d-flex align-items-center" data-seat-for="any" hidden>
                    <div class="small" data-seat-preview></div>
                </div>
            <?php } ?>
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
    if (typeof itflowBillingSeatForm === 'function') {
        itflowBillingSeatForm(form, <?= intval($doc_id) ?>);
    }
})();
</script>

<?php
require_once '../../../includes/modal_footer.php';
