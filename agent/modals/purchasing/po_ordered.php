<?php

/*
 * Mark a purchase order ordered - placed with the vendor by phone, on their
 * website or by email from elsewhere: when, and their order number. Posts
 * po_mark_ordered.
 */

require_once '../../../includes/modal_header.php';

poRequireLevel(2);

$po = poRow(intval($_GET['po_id'] ?? 0));
if (!$po || !poAccessible($po['po_id'])) {
    exit(json_encode(['error' => 'That purchase order no longer exists.']));
}
if (!poCanOrder($po)) {
    exit(json_encode(['error' => $po['po_status'] === 'draft' ? poNumber($po) . ' needs approving before it is ordered.' : poNumber($po) . ' is ' . strtolower(PO_STATUSES[$po['po_status']]['label'] ?? $po['po_status']) . '.']));
}

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-check me-2"></i>Mark Ordered <span class="fw-normal text-white-50 ms-1"><?= escapeHtml(poNumber($po)) ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="po_id" value="<?= intval($po['po_id']) ?>">

    <div class="modal-body">
        <p class="small text-body-secondary">For an order placed with <?= escapeHtml((string) $po['vendor_name']) ?> some other way - their website, or on the phone. Its lines then show on Receiving.</p>
        <div class="row g-3">
            <div class="col-sm-6">
                <label class="form-label">Ordered on <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-calendar"></i></span>
                    <input type="date" class="form-control" name="order_date" required value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                </div>
            </div>
            <div class="col-sm-6">
                <label class="form-label">Their order number</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-hashtag"></i></span>
                    <input type="text" class="form-control" name="vendor_reference" maxlength="200" value="<?= escapeHtml((string) $po['po_vendor_reference']) ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="modal-footer">
        <button type="submit" name="po_mark_ordered" class="btn btn-primary fw-bold"><i class="fas fa-check me-2"></i>Mark Ordered</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
