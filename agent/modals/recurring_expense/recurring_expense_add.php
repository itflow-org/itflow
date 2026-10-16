<?php

/*
 * New Recurring Expense - who it is paid to, how much, how often and from
 * when (and until when, for a lease or financing), what for, from which
 * account and how. ?vendor_id= starts it with one of our vendors (from the
 * vendor's page), ?client_id= with a client.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_financial', 2);

$form_row = null;
$form_vendor_id = intval($_GET['vendor_id'] ?? 0);
$form_client_id = intval($_GET['client_id'] ?? 0);
if ($form_client_id) {
    $client_id = $form_client_id;
    enforceClientAccess();
}

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-redo-alt me-2"></i>New Recurring Expense</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <div class="modal-body">
        <?php require __DIR__ . '/recurring_expense_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="create_recurring_expense" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Set Up</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
