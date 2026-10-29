<?php

/*
 * Make a recurring invoice from an invoice - its lines, scope, category
 * and notes, billed every month or year from a period after the invoice's
 * date. ?invoice_id=
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_sales', 2);

$doc_type = 'invoice';
$doc = billingRow($doc_type, intval($_GET['invoice_id'] ?? 0));
if (!$doc) {
    exit(json_encode(['error' => 'That invoice no longer exists.']));
}
$client_id = intval($doc['client_id']);
enforceClientAccess();
$doc_number = billingNumber($doc_type, $doc);
$lines = count(billingItems('invoice', intval($doc['invoice_id'])));

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-redo-alt me-2"></i>Make Recurring <span class="fw-normal text-white-50 ms-1"><?= escapeHtml($doc_number) ?> &middot; <?= escapeHtml($doc['client_name']) ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="invoice_id" value="<?= intval($doc['invoice_id']) ?>">

    <div class="modal-body">
        <p class="text-body-secondary small">Bill <?= escapeHtml($doc['client_name']) ?> for the same <?= $lines ?> line<?= $lines === 1 ? '' : 's' ?> - <?= escapeHtml(billingMoney($doc['invoice_amount'], $doc['invoice_currency_code'])) ?> - again and again. The first one is made a period after <?= escapeHtml($doc_number) ?>'s date, <?= escapeHtml($doc['invoice_date']) ?>; change that and the lines on the recurring invoice after.</p>
        <label class="form-label">Billed <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-sync-alt"></i></span>
            <select class="form-select" name="frequency" required>
                <?php foreach (recurringInvoiceFrequencies() as $frequency => $label) { ?>
                    <option value="<?= $frequency ?>"><?= $label ?> - first on <?= escapeHtml(recurringInvoiceStep($doc['invoice_date'], $frequency)) ?></option>
                <?php } ?>
            </select>
        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_invoice_recurring" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Make Recurring</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
