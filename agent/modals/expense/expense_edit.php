<?php

/*
 * Edit an expense - every field, and its receipt replaced or removed. The
 * out side of a transfer is changed from Transfers instead.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_financial', 2);

$form_row = expenseRow(intval($_GET['id'] ?? 0));
if (!$form_row) {
    echo json_encode(['error' => 'That expense no longer exists.']);
    exit;
}
if ($form_row['transfer_id']) {
    echo json_encode(['error' => 'That is the out side of a transfer between accounts - change it from Transfers.']);
    exit;
}
$form_mode = 'edit';

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fas fa-fw fa-edit me-2"></i>Edit Expense <span class="fw-normal text-white-50 ms-1"><?= escapeHtml(expenseLabel($form_row)) ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="expense_id" value="<?= intval($form_row['expense_id']) ?>">
    <div class="modal-body">
        <?php require __DIR__ . '/expense_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_expense" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
