<?php

/*
 * The fields of a product or service - shared by the New and Edit windows.
 * Set before including:
 *
 *   $form_product  productRow() when editing, null for a new one
 *   $form_type     service | product - what a new one starts as
 *
 * Posts what productSave() reads: type, name, category, tax, price, cost,
 * frequency, seat_source, code, vendor, vendor_part, track_stock,
 * reorder_level, reorder_qty, location, asset_type, make, model,
 * warranty_months, description and notes.
 *
 * Should not be accessed directly.
 */

isset($mysqli) || die("Direct file access is not allowed");

$form_edit = !empty($form_product);
$form_value = function ($column, $default = '') use ($form_product) {
    return $form_product ? (string) ($form_product[$column] ?? $default) : $default;
};
$form_type = $form_edit ? $form_product['product_type'] : (isset(productTypes()[$form_type ?? '']) ? $form_type : 'service');
$form_id = 'productForm' . ($form_edit ? intval($form_product['product_id']) : 'New');
$form_category_id = intval($form_value('product_category_id', '0'));
$form_tax_id = intval($form_value('product_tax_id', '0'));
$form_vendor_id = intval($form_value('product_vendor_id', '0'));
$form_frequency = quoteFrequency($form_value('product_frequency'));
$form_seat = $form_value('product_seat_source');
// A new product tracks its stock unless told otherwise
$form_track = $form_edit ? intval($form_product['product_track_stock']) === 1 : true;
$form_stock = $form_edit ? floatval($form_product['product_stock']) : 0;
$form_reorder_level = $form_edit && floatval($form_product['product_reorder_level']) > 0 ? billingQuantity($form_product['product_reorder_level']) : '';
$form_reorder_qty = $form_edit && floatval($form_product['product_reorder_qty']) > 0 ? billingQuantity($form_product['product_reorder_qty']) : '';
$form_price = $form_edit ? number_format(floatval($form_product['product_price']), 2, '.', '') : '';
$form_cost = $form_edit && floatval($form_product['product_cost']) > 0 ? number_format(floatval($form_product['product_cost']), 2, '.', '') : '';
$form_currency = $form_edit ? (string) $form_product['product_currency_code'] : (string) $session_company_currency;
$form_asset_type = $form_value('product_asset_type');
$form_warranty = $form_edit && intval($form_product['product_warranty_months']) > 0 ? intval($form_product['product_warranty_months']) : '';

$form_categories = [];
$sql = mysqli_query($mysqli, "SELECT category_id, category_name, category_color, category_icon FROM categories
    WHERE category_type = 'Income' AND (category_archived_at IS NULL OR category_id = $form_category_id) ORDER BY category_name ASC");
while ($row = mysqli_fetch_assoc($sql)) {
    $form_categories[] = $row;
}
$form_taxes = billingTaxes();
$form_vendors = productVendors();
if ($form_vendor_id && !isset($form_vendors[$form_vendor_id]) && $form_value('vendor_name') !== '') {
    $form_vendors[$form_vendor_id] = $form_value('vendor_name') . ' (archived)';
}

?>
<div class="row g-3" id="<?= $form_id ?>" data-product-form data-currency="<?= escapeHtml($form_currency) ?>" data-locale="<?= escapeHtml(str_replace('_', '-', (string) $session_company_locale)) ?>">

    <!-- What it is -->
    <div class="col-12">
        <div class="btn-group w-100 itflow-product-type" role="group" aria-label="Type">
            <?php foreach (productTypes() as $type_key => $type) { ?>
                <input type="radio" class="btn-check" name="type" value="<?= $type_key ?>" id="<?= $form_id ?>Type<?= ucfirst($type_key) ?>" autocomplete="off" <?= $form_type === $type_key ? 'checked' : '' ?> data-product-type>
                <label class="btn btn-outline-secondary text-start d-flex align-items-center gap-2" for="<?= $form_id ?>Type<?= ucfirst($type_key) ?>">
                    <i class="fas fa-fw <?= $type['icon'] ?> fa-lg"></i>
                    <span>
                        <span class="fw-semibold d-block"><?= $type['label'] ?></span>
                        <span class="small opacity-75 d-none d-sm-block"><?= $type_key === 'service' ? 'Labor, managed services, hosting, licenses' : 'Hardware and goods - can track stock' ?></span>
                    </span>
                </label>
            <?php } ?>
        </div>
    </div>

    <div class="col-12">
        <label class="form-label">Name <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-tag"></i></span>
            <input type="text" class="form-control" name="name" maxlength="200" value="<?= escapeHtml($form_value('product_name')) ?>" placeholder="Managed Workstation, 24 port PoE switch, Microsoft 365 Business Premium" required <?= $form_edit ? '' : 'autofocus' ?>>
        </div>
    </div>

    <div class="col-sm-7">
        <label class="form-label">Category <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-list"></i></span>
            <select class="form-select select2" name="category" required>
                <option value="">- Category -</option>
                <?php foreach ($form_categories as $category) { ?>
                    <option value="<?= intval($category['category_id']) ?>" <?= $form_category_id === intval($category['category_id']) ? 'selected' : '' ?><?= selectOptionColor($category['category_color'] ?? '', $category['category_icon'] ?? '') ?>><?= escapeHtml($category['category_name']) ?></option>
                <?php } ?>
            </select>
            <button class="btn btn-secondary ajax-modal" title="New category" type="button" data-modal-size="sm" data-modal-url="../admin/modals/category/category_add.php?category=Income">
                <i class="fas fa-fw fa-plus"></i>
            </button>
        </div>
    </div>
    <div class="col-sm-5">
        <label class="form-label">Tax</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-balance-scale"></i></span>
            <select class="form-select" name="tax">
                <?= taxOptionsHtml($form_taxes, $form_tax_id) ?>
            </select>
        </div>
        <small class="text-secondary">Taxed, its lines take the client's own rate when it has one</small>
    </div>

    <!-- What it sells for, what it costs us -->
    <div class="col-sm-6">
        <label class="form-label">Price <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-dollar-sign"></i></span>
            <input type="text" class="form-control text-end" name="price" inputmode="decimal" value="<?= $form_price ?>" placeholder="0.00" required data-product-price>
        </div>
    </div>
    <div class="col-sm-6">
        <label class="form-label">Cost <span class="text-body-secondary fw-normal small">what it costs you</span></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-shopping-cart"></i></span>
            <input type="text" class="form-control text-end" name="cost" inputmode="decimal" value="<?= $form_cost ?>" placeholder="0.00" data-product-cost>
        </div>
    </div>
    <div class="col-12 mt-1">
        <div class="form-text" data-product-margin>Add a cost to see your margin - only agents see it.</div>
    </div>

    <!-- How it is billed by default -->
    <div class="col-sm-6">
        <label class="form-label">Billed</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-redo-alt"></i></span>
            <select class="form-select" name="frequency" data-product-frequency>
                <?php foreach (quoteFrequencies() as $frequency_value => $frequency_label) { ?>
                    <option value="<?= $frequency_value ?>" <?= $form_frequency === $frequency_value ? 'selected' : '' ?>><?= $frequency_label ?></option>
                <?php } ?>
            </select>
        </div>
    </div>
    <div class="col-sm-6" data-product-counted>
        <label class="form-label">Counted</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-users"></i></span>
            <select class="form-select" name="seat_source">
                <?php foreach (productSeatSources() as $seat_value => $seat_label) { ?>
                    <option value="<?= $seat_value ?>" <?= $form_seat === $seat_value ? 'selected' : '' ?>><?= $seat_label ?></option>
                <?php } ?>
            </select>
        </div>
    </div>
    <div class="col-12 mt-1">
        <div class="form-text" data-product-billed-help>Picked on a quote, it fills in Billed. On a recurring invoice, per user or per device takes the quantity from the client's count.</div>
    </div>

    <!-- Where it comes from -->
    <div class="col-sm-6">
        <label class="form-label">Code</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-barcode"></i></span>
            <input type="text" class="form-control" name="code" maxlength="200" value="<?= escapeHtml($form_value('product_code')) ?>" placeholder="Your SKU - MS-WKS, HW-SW24">
        </div>
    </div>
    <div class="col-sm-6">
        <label class="form-label">Vendor</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-building"></i></span>
            <select class="form-select select2" name="vendor">
                <option value="0">- Who you buy it from -</option>
                <?php foreach ($form_vendors as $vendor_id => $vendor_name) { ?>
                    <option value="<?= intval($vendor_id) ?>" <?= $form_vendor_id === intval($vendor_id) ? 'selected' : '' ?>><?= escapeHtml($vendor_name) ?></option>
                <?php } ?>
            </select>
        </div>
    </div>
    <div class="col-12">
        <label class="form-label">Vendor part number</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-hashtag"></i></span>
            <input type="text" class="form-control" name="vendor_part" maxlength="200" value="<?= escapeHtml($form_value('product_vendor_part')) ?>" placeholder="What to order it as - the vendor's SKU or the manufacturer part number">
        </div>
    </div>

    <!-- Stock - products only -->
    <div class="col-12" data-product-only>
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" name="track_stock" value="1" id="<?= $form_id ?>Track" <?= $form_track ? 'checked' : '' ?> data-product-track>
            <label class="form-check-label" for="<?= $form_id ?>Track">Track stock</label>
        </div>
        <div class="form-text" data-product-track-on>Invoicing it takes it off the shelf, and it can't be sold past what is there. Add stock as it comes in.</div>
        <div class="form-text" data-product-track-off>Ordered as needed - invoicing it doesn't touch stock.<?= $form_edit && $form_track && $form_stock ? ' The ' . billingQuantity($form_stock) . ' on the shelf stay on record but stop moving.' : '' ?></div>
    </div>
    <div class="col-sm-6" data-product-only data-product-tracked>
        <label class="form-label">Reorder at</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-bell"></i></span>
            <input type="text" class="form-control text-end" name="reorder_level" inputmode="decimal" value="<?= $form_reorder_level ?>" placeholder="When it is out">
        </div>
    </div>
    <div class="col-sm-6" data-product-only data-product-tracked>
        <label class="form-label">Order</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-shopping-basket"></i></span>
            <input type="text" class="form-control text-end" name="reorder_qty" inputmode="decimal" value="<?= $form_reorder_qty ?>" placeholder="How many to buy">
        </div>
    </div>
    <div class="col-12 mt-1" data-product-only data-product-tracked>
        <div class="form-text">Agents are told when it gets down to this, and it is listed under Reorder with the vendor and part number.</div>
    </div>
    <div class="col-12" data-product-only data-product-tracked data-product-location>
        <label class="form-label">Location</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-map-marker-alt"></i></span>
            <input type="text" class="form-control" name="location" maxlength="250" value="<?= escapeHtml($form_value('product_location')) ?>" placeholder="Where it is kept - shelf B2, the van, the back office">
        </div>
    </div>

    <!-- Hardware - becomes a client asset when it is sold -->
    <div class="col-sm-6" data-product-only>
        <label class="form-label">Client asset</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-laptop"></i></span>
            <select class="form-select select2" name="asset_type" data-product-asset-type>
                <option value="">- Not an asset -</option>
                <?php foreach (productAssetTypes() as $asset_type_option => $asset_type_icon) { ?>
                    <option value="<?= escapeHtml($asset_type_option) ?>" <?= $form_asset_type === $asset_type_option ? 'selected' : '' ?><?= selectOptionData(['icon' => "fas $asset_type_icon"]) ?>><?= escapeHtml($asset_type_option) ?></option>
                <?php } ?>
                <?php if ($form_asset_type !== '' && !isset(productAssetTypes()[$form_asset_type])) { ?>
                    <option value="<?= escapeHtml($form_asset_type) ?>" selected><?= escapeHtml($form_asset_type) ?></option>
                <?php } ?>
            </select>
        </div>
    </div>
    <div class="col-sm-6" data-product-only data-product-asset>
        <label class="form-label">Warranty</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-shield-alt"></i></span>
            <input type="text" class="form-control text-end" name="warranty_months" inputmode="numeric" maxlength="3" value="<?= $form_warranty ?>" placeholder="None">
            <span class="input-group-text">months</span>
        </div>
    </div>
    <div class="col-sm-6" data-product-only data-product-asset>
        <label class="form-label">Make</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-industry"></i></span>
            <input type="text" class="form-control" name="make" maxlength="200" value="<?= escapeHtml($form_value('product_make')) ?>" placeholder="Who makes it - Dell, Ubiquiti, Synology">
        </div>
    </div>
    <div class="col-sm-6" data-product-only data-product-asset>
        <label class="form-label">Model</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-cube"></i></span>
            <input type="text" class="form-control" name="model" maxlength="200" value="<?= escapeHtml($form_value('product_model')) ?>" placeholder="OptiPlex 7020, U6-Pro, RS1221+">
        </div>
    </div>
    <div class="col-12 mt-1" data-product-only>
        <div class="form-text" data-product-asset-on>When it is sold, the invoice offers to add each one to the client's assets with its serial number, this make and model, and the warranty counted from the invoice date.</div>
        <div class="form-text" data-product-asset-off>Pick an asset type for hardware the client keeps - a laptop, a switch, a firewall - and selling it offers to add it to their assets.</div>
    </div>

    <div class="col-12">
        <label class="form-label">Description <span class="text-body-secondary fw-normal small">on invoices and quotes</span></label>
        <div class="input-group">
            <span class="input-group-text align-items-start pt-2"><i class="fas fa-fw fa-align-left"></i></span>
            <textarea class="form-control" name="description" rows="3" placeholder="What the client sees under the line - what it covers, per user per month, what is included"><?= escapeHtml($form_value('product_description')) ?></textarea>
        </div>
    </div>
    <div class="col-12">
        <label class="form-label">Notes <span class="text-body-secondary fw-normal small">for agents only</span></label>
        <div class="input-group">
            <span class="input-group-text align-items-start pt-2"><i class="fas fa-fw fa-sticky-note"></i></span>
            <textarea class="form-control" name="notes" rows="2" placeholder="Lead time, where to order it, what to check before quoting"><?= escapeHtml($form_value('product_notes')) ?></textarea>
        </div>
    </div>
</div>

<script>
(function () {
    var form = document.getElementById(<?= json_encode($form_id) ?>);
    if (!form || form.dataset.wired) {
        return;
    }
    form.dataset.wired = '1';
    var currency = form.dataset.currency;
    var locale = form.dataset.locale;
    var price = form.querySelector('[data-product-price]');
    var cost = form.querySelector('[data-product-cost]');
    var margin = form.querySelector('[data-product-margin]');
    var frequency = form.querySelector('[data-product-frequency]');
    var counted = form.querySelector('[data-product-counted]');
    var track = form.querySelector('[data-product-track]');
    var assetType = form.querySelector('[data-product-asset-type]');

    function number(value) {
        var text = String(value || '').replace(/[\s,$€£]/g, '');
        return text === '' ? NaN : Number(text);
    }
    function money(amount) {
        try {
            return new Intl.NumberFormat(locale || undefined, { style: 'currency', currency: currency || 'USD' }).format(amount);
        } catch (e) {
            return amount.toFixed(2);
        }
    }
    function update() {
        var type = (form.querySelector('[data-product-type]:checked') || {}).value;
        form.querySelectorAll('[data-product-only]').forEach(function (el) {
            el.hidden = type !== 'product';
        });
        if (type === 'product') {
            form.querySelectorAll('[data-product-tracked]').forEach(function (el) {
                el.hidden = !track.checked;
            });
            form.querySelector('[data-product-track-on]').hidden = !track.checked;
            form.querySelector('[data-product-track-off]').hidden = track.checked;
            form.querySelectorAll('[data-product-asset]').forEach(function (el) {
                el.hidden = assetType.value === '';
            });
            form.querySelector('[data-product-asset-on]').hidden = assetType.value === '';
            form.querySelector('[data-product-asset-off]').hidden = assetType.value !== '';
        }
        counted.hidden = frequency.value === '';

        var p = number(price.value), c = number(cost.value);
        margin.classList.remove('text-success-emphasis', 'text-warning-emphasis', 'text-danger-emphasis');
        if (!isNaN(p) && !isNaN(c) && c > 0 && p > 0) {
            var percent = Math.round((p - c) / p * 1000) / 10;
            margin.textContent = 'Margin ' + money(p - c) + ' (' + percent + '%)' + (frequency.value ? ' ' + { month: 'a month', quarter: 'a quarter', year: 'a year' }[frequency.value] : '') + ' - only agents see it.';
            margin.classList.add(percent >= 30 ? 'text-success-emphasis' : (percent > 0 ? 'text-warning-emphasis' : 'text-danger-emphasis'));
        } else {
            margin.textContent = 'Add a cost to see your margin - only agents see it.';
        }
    }
    form.addEventListener('input', update);
    form.addEventListener('change', update);
    update();
})();
</script>
