<?php

/*
 * A purchase order's details - who it is from (while it is a draft), where it
 * ships (one of our offices, or an address typed in - a client's site, say),
 * shipping, and the note printed for the vendor. Posts po_details_save.
 */

require_once '../../../includes/modal_header.php';

poRequireLevel(2);

$po = poRow(intval($_GET['po_id'] ?? 0));
if (!$po || !poAccessible($po['po_id'])) {
    exit(json_encode(['error' => 'That purchase order no longer exists.']));
}
if (in_array($po['po_status'], ['received', 'cancelled'], true)) {
    exit(json_encode(['error' => poNumber($po) . ' can no longer be changed.']));
}
$other = $po['po_ship_to_office_id'] === null;
$form_id = 'poDetails' . intval($po['po_id']);

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-edit me-2"></i>Details <span class="fw-normal text-white-50 ms-1"><?= escapeHtml(poNumber($po)) ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off" id="<?= $form_id ?>">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="po_id" value="<?= intval($po['po_id']) ?>">

    <div class="modal-body">
        <div class="row g-3">
            <?php if ($po['po_status'] === 'draft') { ?>
                <div class="col-12">
                    <label class="form-label">Vendor <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-fw fa-building"></i></span>
                        <select class="form-select select2" name="vendor" required>
                            <?php foreach (poVendors($po['po_vendor_id']) as $option_id => $option_name) { ?>
                                <option value="<?= $option_id ?>" <?= $option_id === intval($po['po_vendor_id']) ? 'selected' : '' ?>><?= escapeHtml($option_name) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
            <?php } ?>
            <div class="col-12">
                <label class="form-label">Ship to</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-map-marker-alt"></i></span>
                    <select class="form-select" name="ship_to" data-ship-to>
                        <?php foreach (officesAll() as $office_id => $office) { ?>
                            <option value="<?= intval($office_id) ?>" <?= !$other && intval($po['po_ship_to_office_id']) === intval($office_id) ? 'selected' : '' ?>><?= escapeHtml($office['name'] . (officeAddressLine($office) !== '' ? ' - ' . officeAddressLine($office, ', ') : '')) ?></option>
                        <?php } ?>
                        <option value="other" <?= $other ? 'selected' : '' ?>>Another address - a client's site, say</option>
                    </select>
                </div>
            </div>
            <div class="col-12" data-ship-to-other <?= $other ? '' : 'hidden' ?>>
                <div class="input-group">
                    <span class="input-group-text align-items-start pt-2"><i class="fas fa-fw fa-truck"></i></span>
                    <textarea class="form-control" name="ship_to_address" rows="4" placeholder="Name on the first line, then the address"><?= $other ? escapeHtml((string) $po['po_ship_to']) : '' ?></textarea>
                </div>
            </div>
            <div class="col-sm-6">
                <label class="form-label">Shipping</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-shipping-fast"></i></span>
                    <input type="text" class="form-control text-end" name="shipping" inputmode="decimal" value="<?= floatval($po['po_shipping']) > 0 ? number_format(floatval($po['po_shipping']), 2, '.', '') : '' ?>" placeholder="0.00">
                </div>
                <div class="form-text">Added to the total.</div>
            </div>
            <div class="col-12">
                <label class="form-label">Note for the vendor</label>
                <div class="input-group">
                    <span class="input-group-text align-items-start pt-2"><i class="fas fa-fw fa-sticky-note"></i></span>
                    <textarea class="form-control" name="note" rows="3" placeholder="Delivery hours, who to ask for - printed on the purchase order"><?= escapeHtml((string) $po['po_note']) ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-footer">
        <button type="submit" name="po_details_save" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Save</button>
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
    var ship = form.querySelector('[data-ship-to]');
    var other = form.querySelector('[data-ship-to-other]');
    ship.addEventListener('change', function () {
        other.hidden = ship.value !== 'other';
    });
})();
</script>

<?php
require_once '../../../includes/modal_footer.php';
