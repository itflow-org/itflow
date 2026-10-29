<?php

/*
 * A client's tax - its own rate for new lines, and a tax exemption with the
 * certificate it rests on - on the Billing tab of client_add.php and
 * client_edit.php, which set $client_tax (the client's row, [] when new) and
 * $client_tax_key (keeps ids apart). Saved by agent/post/client.php through
 * taxClientSetSql().
 */

isset($mysqli) || die("Direct file access is not allowed");

$ct = $client_tax ?? [];
$ct_key = $client_tax_key ?? 'New';
$ct_default = intval($ct['client_default_tax_id'] ?? 0);
$ct_company_default = taxRow(taxSettings()['default_id']);
$ct_expires = (string) ($ct['client_tax_exempt_expires'] ?? '');
?>

<input type="hidden" name="tax_fields" value="1">

<div class="mb-3">
    <label>Tax Rate</label>
    <div class="input-group">
        <span class="input-group-text"><i class="fa fa-fw fa-percent"></i></span>
        <select class="form-select select2" name="default_tax_id">
            <option value="0"><?= $ct_company_default ? 'The default - ' . escapeHtml(taxLabel($ct_company_default)) : 'The default - none set' ?></option>
            <?php
            $ct_taxes = billingTaxes();
            if ($ct_default && !isset($ct_taxes[$ct_default]) && ($ct_row = taxRow($ct_default))) {
                $ct_taxes[$ct_default] = $ct_row;
            }
            foreach ($ct_taxes as $ct_id => $ct_tax) { ?>
                <option value="<?= intval($ct_id) ?>"<?= intval($ct_id) === $ct_default ? ' selected' : '' ?>><?= escapeHtml(taxLabel($ct_tax)) ?></option>
            <?php } ?>
        </select>
    </div>
    <small class="text-secondary">For new lines on its invoices and quotes - where it is, for sales tax</small>
</div>

<div class="mb-2">
    <div class="form-check form-switch">
        <input type="checkbox" class="form-check-input" name="tax_exempt" value="1" id="clientTaxExempt<?= $ct_key ?>"<?= !empty($ct['client_tax_exempt']) ? ' checked' : '' ?>>
        <label class="form-check-label" for="clientTaxExempt<?= $ct_key ?>">Tax exempt</label>
    </div>
    <small class="text-secondary">Its new lines get no tax, and its invoices and quotes say it is exempt</small>
</div>

<div class="row">
    <div class="col-md-7 mb-3">
        <label>Exemption Certificate</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-file-contract"></i></span>
            <input type="text" class="form-control" name="tax_exempt_number" maxlength="200" placeholder="Certificate number" value="<?= escapeHtml($ct['client_tax_exempt_number'] ?? '') ?>">
        </div>
    </div>
    <div class="col-md-5 mb-3">
        <label>Expires</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-calendar-times"></i></span>
            <input type="date" class="form-control" name="tax_exempt_expires" max="2999-12-31" value="<?= escapeHtml($ct_expires) ?>">
        </div>
    </div>
</div>
