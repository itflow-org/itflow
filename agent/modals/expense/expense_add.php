<?php

/*
 * New Expense - who it was paid to, how much, what for, from which account
 * and how, for a client or not, with the receipt. ?vendor_id= starts it with
 * one of our vendors (from the vendor's page), ?client_id= with a client,
 * ?account_id= from an account (from Reconcile).
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_financial', 2);

$form_mode = 'new';
$form_row = null;
$form_vendor_id = intval($_GET['vendor_id'] ?? 0);
$form_client_id = intval($_GET['client_id'] ?? 0);
$form_account_id = intval($_GET['account_id'] ?? 0);
if ($form_client_id) {
    $client_id = $form_client_id;
    enforceClientAccess();
}

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-cart-plus me-2"></i>New Expense</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <div class="modal-body">
        <?php require __DIR__ . '/expense_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_expense" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Add Expense</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
