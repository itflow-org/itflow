<?php

/*
 * New Income - money in that isn't for an invoice: a deposit, a rebate,
 * interest, a referral fee. ?client_id= starts it with a client (from their
 * Income page). A payment against an invoice is added on the invoice.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_financial', 2);

$form_row = null;
$form_mode = 'new';
$form_client_id = intval($_GET['client_id'] ?? 0);
if ($form_client_id) {
    $client_id = $form_client_id;
    enforceClientAccess();
}

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-hand-holding-usd me-2"></i>New Income</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <div class="modal-body">
        <div class="small text-body-secondary mb-3"><i class="fas fa-info-circle me-1"></i>For money in that isn't for an invoice. A payment on an invoice is added from the invoice, so it is marked paid.</div>
        <?php require __DIR__ . '/revenue_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_revenue" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Add Income</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
