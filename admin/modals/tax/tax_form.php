<?php

/*
 * A tax's fields - for tax_add.php and tax_edit.php, which set $tax (a
 * taxRow(), or null when new). One rate, or parts each charged on the line
 * or on top of the parts above it (js/tax_form.js). Posts to admin/post.php
 * (admin/post/tax.php).
 */

isset($mysqli) || die("Direct file access is not allowed");

$t = $tax ?? null;
$t_parts = $t && $t['grouped'] ? $t['components'] : [];

// One part's row - $key keeps a part's name, percent and "on top" together when rows are added and removed
$tax_part_row = function ($key, array $part = []) {
    ob_start(); ?>
    <div class="row g-2 align-items-center mb-2" data-tax-part>
        <div class="col-sm-4">
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                <input type="text" class="form-control" name="part_name[<?= $key ?>]" maxlength="200" placeholder="State, county, GST..." value="<?= escapeHtml($part['name'] ?? '') ?>" data-tax-part-name>
            </div>
        </div>
        <div class="col-sm-3">
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-id-card"></i></span>
                <input type="text" class="form-control" name="part_registration[<?= $key ?>]" maxlength="100" placeholder="Its own reg. no." title="This part's registration number, when it isn't the tax's" value="<?= escapeHtml($part['registration'] ?? '') ?>">
            </div>
        </div>
        <div class="col-7 col-sm-2">
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-percent"></i></span>
                <input type="text" class="form-control" inputmode="decimal" pattern="[0-9]*\.?[0-9]{0,4}" name="part_percent[<?= $key ?>]" placeholder="0" value="<?= isset($part['percent']) ? taxPercentText($part['percent']) : '' ?>" data-tax-part-percent>
            </div>
        </div>
        <div class="col-3 col-sm-2">
            <div class="form-check mb-0" title="Charged on the line plus the parts above it, not just the line">
                <input type="checkbox" class="form-check-input" name="part_compound[<?= $key ?>]" value="1" id="taxPartCompound<?= $key ?>"<?= !empty($part['compound']) ? ' checked' : '' ?> data-tax-part-compound>
                <label class="form-check-label small" for="taxPartCompound<?= $key ?>">On top</label>
            </div>
        </div>
        <div class="col-2 col-sm-1 text-end">
            <button type="button" class="btn btn-sm btn-outline-secondary" title="Remove this part" data-tax-remove-part><i class="fas fa-times"></i></button>
        </div>
    </div>
    <?php return ob_get_clean();
};
?>

<div data-tax-form>
    <div class="mb-3">
        <label class="form-label">Name <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-balance-scale"></i></span>
            <input type="text" class="form-control" name="name" maxlength="200" placeholder="PA Sales Tax, GST, VAT" value="<?= escapeHtml($t['tax_name'] ?? '') ?>" required autofocus>
        </div>
    </div>

    <div class="row">
        <div class="col-md-7 mb-3">
            <label class="form-label">Description</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-align-left"></i></span>
                <input type="text" class="form-control" name="description" maxlength="255" placeholder="Where and what it is charged on" value="<?= escapeHtml($t['tax_description'] ?? '') ?>">
            </div>
        </div>
        <div class="col-md-5 mb-3">
            <label class="form-label">Registration number</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-id-card"></i></span>
                <input type="text" class="form-control" name="registration" maxlength="100" placeholder="Permit, GST/HST or VAT number" value="<?= escapeHtml($t['tax_registration_number'] ?? '') ?>">
            </div>
            <small class="text-secondary">Printed with this tax on invoices</small>
        </div>
    </div>

    <div class="mb-1<?= $t_parts ? ' d-none' : '' ?>" data-tax-single>
        <label class="form-label">Rate <strong class="text-danger">*</strong></label>
        <div class="row g-2 align-items-center">
            <div class="col-sm-4">
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-percent"></i></span>
                    <input type="text" class="form-control" inputmode="decimal" pattern="[0-9]*\.?[0-9]{0,4}" name="percent" placeholder="0" value="<?= $t && !$t_parts ? taxPercentText($t['tax_percent']) : '' ?>"<?= $t_parts ? ' disabled' : ' required' ?> data-tax-percent>
                </div>
            </div>
            <div class="col-sm-8">
                <button type="button" class="btn btn-link btn-sm px-0 text-decoration-none" data-tax-split><i class="fas fa-layer-group me-1"></i>Split into parts - a state's and a county's, GST and PST</button>
            </div>
        </div>
    </div>

    <div class="mb-1<?= $t_parts ? '' : ' d-none' ?>" data-tax-parts>
        <label class="form-label">Parts <strong class="text-danger">*</strong></label>
        <div data-tax-part-rows>
            <?php foreach ($t_parts as $i => $part) {
                echo $tax_part_row($i, $part);
            } ?>
        </div>
        <template data-tax-part-template><?= $tax_part_row('__KEY__') ?></template>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" data-tax-add-part><i class="fas fa-plus me-1"></i>Add a part</button>
            <span class="ms-auto">Comes to <strong data-tax-total><?= $t ? taxPercentText($t['tax_percent']) : '0' ?>%</strong></span>
        </div>
        <small class="text-secondary d-block mt-1">Each part is shown on its own on invoices, with its registration number - or the tax's. <em>On top</em> charges a part on the line plus the parts above it. Name a part the same as another tax's (PA Sales Tax at 6%) and Tax Collected adds them up together.</small>
    </div>
</div>

<script src="/js/tax_form.js"></script>
