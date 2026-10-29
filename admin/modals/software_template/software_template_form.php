<?php
/*
 * A license template's fields - shared by its New and Edit windows
 * (software_template_add.php, software_template_edit.php), which set
 * $template (softwareTemplateRow(), or [] for a new one) before including it.
 * Posts what softwareTemplateFields() reads (functions/software_template.php):
 * with the seats a license starts with, how often it renews, the vendor
 * template its vendor comes from and the product it is sold as.
 *
 * Should not be accessed directly.
 */

isset($mysqli) || die("Direct file access is not allowed");

$t = $template;
$type = (string) ($t['software_template_type'] ?? '');
$license_type = (string) ($t['software_template_license_type'] ?? '');

// The license types the license form offers (functions/software.php), keeping one this template already has
$license_types = array_keys(SOFTWARE_LICENSE_TYPES);
if ($license_type !== '' && !in_array($license_type, $license_types, true)) {
    $license_types[] = $license_type;
}

$seats = $t['software_template_seats'] ?? null;
$term = (string) ($t['software_template_term'] ?? '');
$vendor_template_id = intval($t['software_template_vendor_template_id'] ?? 0);
$product_id = intval($t['software_template_product_id'] ?? 0);

// Current vendor templates and products - and the one it has, even when archived since
$vendor_templates = [];
$sql = mysqli_query($mysqli, "SELECT vendor_template_id, vendor_template_name, vendor_template_description, vendor_template_archived_at FROM vendor_templates
    WHERE vendor_template_archived_at IS NULL OR vendor_template_id = $vendor_template_id ORDER BY vendor_template_name ASC");
while ($row = mysqli_fetch_assoc($sql)) {
    $vendor_templates[] = $row;
}
$products = [];
$sql = mysqli_query($mysqli, "SELECT product_id, product_name, product_type, product_price, product_currency_code, product_frequency, product_seat_source, product_archived_at FROM products
    WHERE product_archived_at IS NULL OR product_id = $product_id ORDER BY product_name ASC");
while ($row = mysqli_fetch_assoc($sql)) {
    $products[] = $row;
}

?>

<div class="row g-3">
    <div class="col-md-8">
        <label class="form-label">Name <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-cube"></i></span>
            <input type="text" class="form-control" name="name" placeholder="Microsoft 365 Business Premium" maxlength="200" value="<?= escapeHtml($t['software_template_name'] ?? '') ?>" required autofocus>
        </div>
    </div>
    <div class="col-md-4">
        <label class="form-label">Version</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-code-branch"></i></span>
            <input type="text" class="form-control" name="version" placeholder="2024" maxlength="200" value="<?= escapeHtml($t['software_template_version'] ?? '') ?>">
        </div>
    </div>
    <div class="col-12">
        <label class="form-label">Summary</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-align-left"></i></span>
            <input type="text" class="form-control" name="description" placeholder="One line on what it is - shown when an agent picks a template" maxlength="500" value="<?= escapeHtml($t['software_template_description'] ?? '') ?>">
        </div>
    </div>
    <div class="col-md-6">
        <label class="form-label">Type <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
            <select class="form-select select2" name="type" required>
                <option value="">- Pick a type -</option>
                <?= categoryOptionsHtml('software_type', $type) ?>
            </select>
            <?= categoryAddButtonHtml('software_type', 'btn btn-outline-secondary') ?>
        </div>
    </div>
    <div class="col-md-6">
        <label class="form-label">License type</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-certificate"></i></span>
            <select class="form-select select2" name="license_type">
                <option value="">- None -</option>
                <?php foreach ($license_types as $option) { ?>
                    <option value="<?= escapeHtml($option) ?>" <?= $option === $license_type ? 'selected' : '' ?><?= selectOptionData(['icon' => 'fas ' . softwareLicenseTypeIcon($option)]) ?>><?= escapeHtml($option) ?></option>
                <?php } ?>
            </select>
        </div>
    </div>
    <div class="col-md-6">
        <label class="form-label">Seats</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-users"></i></span>
            <input type="number" class="form-control" name="seats" min="0" max="1000000" step="1" placeholder="No limit" value="<?= $seats === null ? '' : intval($seats) ?>">
        </div>
        <div class="form-text">What a license starts with - change it for each client.</div>
    </div>
    <div class="col-md-6">
        <label class="form-label">Renews every</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-redo-alt"></i></span>
            <select class="form-select" name="term">
                <option value="">- Doesn't renew -</option>
                <?php foreach (SOFTWARE_RENEWAL_TERMS as $value => $label) { ?>
                    <option value="<?= escapeHtml($value) ?>" <?= $value === $term ? 'selected' : '' ?>><?= escapeHtml($label) ?></option>
                <?php } ?>
            </select>
        </div>
        <div class="form-text">Its renewal date is worked out from when it was bought.</div>
    </div>
    <div class="col-md-6">
        <label class="form-label">Vendor</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
            <select class="form-select select2" name="vendor_template_id">
                <option value="0">- None -</option>
                <?php foreach ($vendor_templates as $row) { ?>
                    <option value="<?= intval($row['vendor_template_id']) ?>" <?= intval($row['vendor_template_id']) === $vendor_template_id ? 'selected' : '' ?><?= selectOptionVendor($row['vendor_template_name'], $row['vendor_template_archived_at'] ? 'Archived' : (string) $row['vendor_template_description']) ?>><?= escapeHtml($row['vendor_template_name']) ?></option>
                <?php } ?>
            </select>
        </div>
        <div class="form-text">From the vendor templates - the client's own vendor made from it, or one made for them.</div>
    </div>
    <div class="col-md-6">
        <label class="form-label">Sold as</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-box-open"></i></span>
            <select class="form-select select2" name="product_id">
                <option value="0">- None -</option>
                <?php foreach ($products as $row) {
                    $type_icon = productTypes()[$row['product_type']]['icon'] ?? 'fa-wrench';
                    $line = $row['product_archived_at'] ? 'Archived' : billingMoney($row['product_price'], $row['product_currency_code']) . ' · ' . productBilledText($row['product_frequency'], $row['product_seat_source']); ?>
                    <option value="<?= intval($row['product_id']) ?>" <?= intval($row['product_id']) === $product_id ? 'selected' : '' ?><?= selectOptionData(['icon' => "fas $type_icon", 'description' => $line]) ?>><?= escapeHtml($row['product_name']) ?></option>
                <?php } ?>
            </select>
        </div>
        <div class="form-text">From Products &amp; Services - what you charge for it.</div>
    </div>
    <div class="col-12">
        <label class="form-label">Notes</label>
        <div class="input-group">
            <span class="input-group-text align-items-start pt-2"><i class="fa fa-fw fa-sticky-note"></i></span>
            <textarea class="form-control" name="notes" rows="6" placeholder="What every license from it should say - where to download it, how it is installed, who to call"><?= escapeHtml($t['software_template_notes'] ?? '') ?></textarea>
        </div>
        <div class="form-text">A license made from it starts with all of this - change anything there after.</div>
    </div>
</div>
