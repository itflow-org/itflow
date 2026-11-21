<?php

/*
 * A tracking number the vendor sent for a purchase order - the carrier links
 * it to their tracking page. Posts po_tracking_add.
 */

require_once '../../../includes/modal_header.php';

poRequireLevel(2);

$po = poRow(intval($_GET['po_id'] ?? 0));
if (!$po || !poAccessible($po['po_id'])) {
    exit(json_encode(['error' => 'That purchase order no longer exists.']));
}
if (in_array($po['po_status'], ['draft', 'pending', 'approved', 'cancelled'], true)) {
    exit(json_encode(['error' => 'Tracking goes on a purchase order once it is ordered.']));
}

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-shipping-fast me-2"></i>Add Tracking <span class="fw-normal text-white-50 ms-1"><?= escapeHtml(poNumber($po)) ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="po_id" value="<?= intval($po['po_id']) ?>">

    <div class="modal-body">
        <div class="row g-3">
            <div class="col-sm-5">
                <label class="form-label">Carrier</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-truck"></i></span>
                    <select class="form-select select2" name="carrier">
                        <?php foreach (PO_CARRIERS as $key => [$label]) { ?>
                            <option value="<?= $key ?>"><?= escapeHtml($label) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="col-sm-7">
                <label class="form-label">Tracking number <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-barcode"></i></span>
                    <input type="text" class="form-control font-monospace" name="tracking_number" maxlength="100" required>
                </div>
            </div>
            <div class="col-sm-6">
                <label class="form-label">Due</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-calendar"></i></span>
                    <input type="date" class="form-control" name="eta">
                </div>
            </div>
        </div>
    </div>

    <div class="modal-footer">
        <button type="submit" name="po_tracking_add" class="btn btn-primary fw-bold"><i class="fas fa-check me-2"></i>Add</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
