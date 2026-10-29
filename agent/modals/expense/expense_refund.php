<?php

/*
 * Refund - money back from the vendor against an expense. It is saved as a
 * negative expense with the same vendor, category, account and client,
 * linked to the one it gives back. Starts at what hasn't come back yet.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_financial', 2);

$form_row = expenseRow(intval($_GET['id'] ?? 0));
if (!$form_row || $form_row['transfer_id']) {
    echo json_encode(['error' => 'That expense no longer exists.']);
    exit;
}
if (floatval($form_row['expense_amount']) <= 0) {
    echo json_encode(['error' => 'That is a refund already - there is nothing to give back.']);
    exit;
}
$form_mode = 'refund';

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-undo-alt me-2"></i>Record a Refund</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <div class="modal-body">
        <?php require __DIR__ . '/expense_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_expense" class="btn btn-primary text-bold"><i class="fas fa-undo-alt me-2"></i>Record Refund</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
