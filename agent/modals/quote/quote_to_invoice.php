<?php

/*
 * Invoice an accepted quote - a draft invoice with the quote's lines,
 * scope, category, discount and notes, dated as given and due on the
 * client's terms. The quote is marked invoiced. ?quote_id=
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
$lines = count(billingItems('quote', intval($doc['quote_id'])));
$terms = intval($doc['client_net_terms']) ?: intval($config_default_net_terms);

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-file-invoice-dollar me-2"></i>Make Invoice <span class="fw-normal text-white-50 ms-1"><?= escapeHtml($doc_number) ?> &middot; <?= escapeHtml($doc['client_name']) ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="quote_id" value="<?= intval($doc['quote_id']) ?>">

    <div class="modal-body">
        <div class="d-flex align-items-center gap-3 mb-3 p-3 rounded bg-body-tertiary">
            <?= billingTile('quote') ?>
            <i class="fas fa-arrow-right text-body-secondary"></i>
            <?= billingTile('invoice') ?>
            <div class="small text-body-secondary">A draft invoice with the quote's <?= $lines ?> line<?= $lines === 1 ? '' : 's' ?>, discount and notes - <?= escapeHtml(billingMoney($doc['quote_amount'], $doc['quote_currency_code'])) ?>. <?= escapeHtml($doc_number) ?> is marked invoiced.</div>
        </div>
        <label class="form-label">Invoice date <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
            <input type="date" class="form-control" name="date" max="2999-12-31" value="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="form-text">Due <?= $terms ?> days after it - the client's terms.</div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_quote_to_invoice" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Make Invoice</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
