<?php

/*
 * Mark a quote sent - for one that went out some other way than
 * ITFlow's email: posted, handed over, sent from your own mail. How, and a
 * note, go in its history. ?quote_id=
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_sales', 2);

$doc_type = 'quote';
$doc = billingRow($doc_type, intval($_GET['quote_id'] ?? 0));
if (!$doc) {
    exit(json_encode(['error' => 'That quote no longer exists.']));
}
$client_id = intval($doc['client_id']);
enforceClientAccess();
$doc_number = billingNumber($doc_type, $doc);

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-check me-2"></i>Mark Sent <span class="fw-normal text-white-50 ms-1"><?= escapeHtml($doc_number) ?> &middot; <?= escapeHtml($doc['client_name']) ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="quote_id" value="<?= intval($doc['quote_id']) ?>">

    <div class="modal-body">
        <p class="text-body-secondary small">For a quote that went out some other way than ITFlow's email. It stops being a draft, and the client can open it from its link.</p>
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label">How was it sent? <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-paper-plane"></i></span>
                    <select class="form-select" name="sent_method" required>
                        <?php foreach (getSentMethods() as $sent_method) { ?>
                            <option value="<?= escapeHtml($sent_method) ?>"><?= escapeHtml($sent_method) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="col-12">
                <label class="form-label">Note</label>
                <div class="input-group">
                    <span class="input-group-text align-items-start pt-2"><i class="fa fa-fw fa-sticky-note"></i></span>
                    <textarea class="form-control" name="note" rows="3" maxlength="500" placeholder="Tracking number, who it went to, anything worth keeping"></textarea>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="mark_quote_sent" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Mark Sent</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
