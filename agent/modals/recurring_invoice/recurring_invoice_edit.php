<?php

/*
 * Edit a recurring invoice's details - scope, category, how often, its next
 * date, discount, whether its invoices go out by themselves, and whether it
 * is running. Its lines are edited on the recurring invoice itself. ?id=
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_sales', 2);

$form_type = 'recurring';
$form_row = billingRow($form_type, intval($_GET['id'] ?? 0));
if (!$form_row) {
    exit(json_encode(['error' => 'That recurring invoice no longer exists.']));
}
$client_id = intval($form_row['client_id']);
enforceClientAccess();
$form_client_id = $client_id;

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-redo-alt me-2"></i>Edit Recurring Invoice <span class="fw-normal text-white-50 ms-1"><?= escapeHtml(billingNumber($form_type, $form_row)) ?> &middot; <?= escapeHtml($form_row['client_name']) ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="recurring_invoice_id" value="<?= intval($form_row['recurring_invoice_id']) ?>">

    <div class="modal-body">
        <?php require __DIR__ . '/../billing/billing_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_recurring_invoice" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
