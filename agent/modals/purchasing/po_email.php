<?php

/*
 * Email a purchase order to the vendor with its PDF, from the invoice
 * mailbox - and, when it isn't ordered yet, mark it ordered as it goes.
 * Posts po_email.
 */

require_once '../../../includes/modal_header.php';

poRequireLevel(2);

$po = poRow(intval($_GET['po_id'] ?? 0));
if (!$po || !poAccessible($po['po_id'])) {
    exit(json_encode(['error' => 'That purchase order no longer exists.']));
}
$can_order = poCanOrder($po);
if (!$can_order && !in_array($po['po_status'], ['ordered', 'partial', 'received'], true)) {
    exit(json_encode(['error' => $po['po_status'] === 'draft' || $po['po_status'] === 'pending' ? poNumber($po) . ' needs approving before it goes to the vendor.' : poNumber($po) . ' is ' . strtolower(PO_STATUSES[$po['po_status']]['label'] ?? $po['po_status']) . '.']));
}
$to = (string) ($po['vendor_contact_email'] ?: $po['vendor_email']);
$from = billingEmailFrom('invoice');

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-paper-plane me-2"></i>Email to Vendor <span class="fw-normal text-white-50 ms-1"><?= escapeHtml(poNumber($po)) ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="po_id" value="<?= intval($po['po_id']) ?>">

    <div class="modal-body">
        <?php if (!emailReady()) { ?>
            <div class="alert alert-warning small"><i class="fas fa-fw fa-exclamation-triangle me-1"></i>Mail isn't set up to send yet - Admin > Settings > Mail.</div>
        <?php } ?>
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label">To <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-envelope"></i></span>
                    <input type="email" class="form-control" name="to" required maxlength="200" value="<?= escapeHtml($to) ?>" placeholder="orders@vendor.example">
                </div>
                <div class="form-text">From <?= escapeHtml(trim($from['name'] . ' <' . $from['email'] . '>')) ?>, with <?= escapeHtml(poNumber($po)) ?> as a PDF.<?= $to === '' ? ' ' . escapeHtml((string) $po['vendor_name']) . ' has no email address on file.' : '' ?></div>
            </div>
            <div class="col-12">
                <label class="form-label">Message</label>
                <div class="input-group">
                    <span class="input-group-text align-items-start pt-2"><i class="fas fa-fw fa-comment"></i></span>
                    <textarea class="form-control" name="message" rows="3" placeholder="Anything to add - kept as you type it"></textarea>
                </div>
            </div>
            <?php if ($can_order) { ?>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" name="mark_ordered" value="1" id="poEmailOrdered<?= intval($po['po_id']) ?>" checked>
                        <label class="form-check-label" for="poEmailOrdered<?= intval($po['po_id']) ?>">Mark it ordered today</label>
                        <div class="form-text">Its lines then show on Receiving.</div>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>

    <div class="modal-footer">
        <button type="submit" name="po_email" class="btn btn-primary text-bold"><i class="fas fa-paper-plane me-2"></i>Send</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
