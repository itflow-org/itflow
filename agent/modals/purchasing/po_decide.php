<?php

/*
 * Approve a purchase order waiting for approval (?approve=1), or send it back
 * to draft (?approve=0) - with a note for whoever made it. Posts po_decide.
 */

require_once '../../../includes/modal_header.php';

poRequireLevel(3);

$po = poRow(intval($_GET['po_id'] ?? 0));
if (!$po || !poAccessible($po['po_id'])) {
    exit(json_encode(['error' => 'That purchase order no longer exists.']));
}
if (!poMayApprove($po)) {
    exit(json_encode(['error' => $po['po_status'] !== 'pending' ? poNumber($po) . " isn't waiting for approval." : "You can't approve this one - it needs someone else with full Purchasing access."]));
}
$approve = intval($_GET['approve'] ?? 1) === 1;
$own = intval($po['po_created_by']) === intval($session_user_id ?? 0);

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw <?= $approve ? 'fa-thumbs-up' : 'fa-undo' ?> me-2"></i><?= $approve ? 'Approve' : 'Send Back' ?> <span class="fw-normal text-white-50 ms-1"><?= escapeHtml(poNumber($po)) ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="po_id" value="<?= intval($po['po_id']) ?>">
    <input type="hidden" name="approve" value="<?= $approve ? 1 : 0 ?>">

    <div class="modal-body">
        <div class="d-flex align-items-center gap-3 mb-3 p-3 rounded bg-body-tertiary small">
            <div class="flex-grow-1">
                <div class="fw-semibold"><?= escapeHtml((string) $po['vendor_name']) ?></div>
                <div class="text-body-secondary"><?= poItemsCount($po['po_id']) ?> line<?= poItemsCount($po['po_id']) === 1 ? '' : 's' ?>, from <?= escapeHtml((string) $po['created_by_name']) ?></div>
            </div>
            <div class="fs-5 fw-bold"><?= escapeHtml(poMoney($po['po_total'], $po['po_currency_code'])) ?></div>
        </div>
        <?php if ($approve && $own) { ?>
            <div class="alert alert-warning small"><i class="fas fa-fw fa-exclamation-triangle me-1"></i>This is your own purchase order - its history will say you approved it yourself.</div>
        <?php } ?>
        <label class="form-label">Note<?= $approve ? '' : ' <strong class="text-danger">*</strong>' ?></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-comment"></i></span>
            <input type="text" class="form-control" name="note" maxlength="255" <?= $approve ? '' : 'required' ?> placeholder="<?= $approve ? 'Anything to say - optional' : 'What to change before it comes back' ?>">
        </div>
        <div class="form-text"><?= escapeHtml((string) $po['created_by_name']) ?> is told.</div>
    </div>

    <div class="modal-footer">
        <button type="submit" name="po_decide" class="btn <?= $approve ? 'btn-success' : 'btn-secondary' ?> fw-bold"><i class="fas <?= $approve ? 'fa-thumbs-up' : 'fa-undo' ?> me-2"></i><?= $approve ? 'Approve' : 'Send Back' ?></button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
