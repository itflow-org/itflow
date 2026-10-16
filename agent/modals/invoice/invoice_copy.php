<?php

/*
 * Copy an invoice - a new draft for the same client with the same lines,
 * scope, category, discount and notes, on the date given and due on the
 * client's terms. ?id=
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_sales', 2);

$doc_type = 'invoice';
$doc = billingRow($doc_type, intval($_GET['id'] ?? 0));
if (!$doc) {
    exit(json_encode(['error' => 'That invoice no longer exists.']));
}
$client_id = intval($doc['client_id']);
enforceClientAccess();
$doc_number = billingNumber($doc_type, $doc);
$lines = count(billingItems('invoice', intval($doc['invoice_id'])));
$terms = intval($doc['client_net_terms']) ?: intval($config_default_net_terms);

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-copy me-2"></i>Copy Invoice <span class="fw-normal text-white-50 ms-1"><?= escapeHtml($doc_number) ?> &middot; <?= escapeHtml($doc['client_name']) ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="invoice_id" value="<?= intval($doc['invoice_id']) ?>">

    <div class="modal-body">
        <p class="text-body-secondary small">A new draft for <?= escapeHtml($doc['client_name']) ?> with the same <?= $lines ?> line<?= $lines === 1 ? '' : 's' ?>, scope, discount and notes - <?= escapeHtml(billingMoney($doc['invoice_amount'], $doc['invoice_currency_code'])) ?>.</p>
        <label class="form-label">Invoice date <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
            <input type="date" class="form-control" name="date" max="2999-12-31" value="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="form-text">Due <?= $terms ?> days after it - the client's terms.</div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_invoice_copy" class="btn btn-primary text-bold"><i class="fas fa-copy me-2"></i>Copy</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
