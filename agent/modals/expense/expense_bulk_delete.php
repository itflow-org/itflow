<?php

/*
 * Delete the ticked expenses, with their receipts. Transfers between
 * accounts are left alone.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_financial', 3);

$expense_ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_GET['expense_ids'] ?? [])))));
$count = count($expense_ids);
if (!$count) {
    echo json_encode(['error' => 'Tick the expenses to delete first.']);
    exit;
}

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-trash me-2"></i>Delete <?= $count ?> Expense<?= $count === 1 ? '' : 's' ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php foreach ($expense_ids as $expense_id) { ?>
        <input type="hidden" name="expense_ids[]" value="<?= $expense_id ?>">
    <?php } ?>
    <div class="modal-body">
        <div class="d-flex gap-3 align-items-start">
            <i class="fas fa-exclamation-triangle fa-2x text-danger"></i>
            <div>
                <div class="fw-semibold"><?= $count === 1 ? 'This expense goes' : "These $count expenses go" ?> for good, with <?= $count === 1 ? 'its receipt' : 'their receipts' ?>.</div>
                <div class="text-body-secondary small mt-1">They come out of the account balances and the reports. Stock deliveries that named them stay, unlinked. This can't be undone.</div>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="bulk_delete_expenses" class="btn btn-danger text-bold"><i class="fas fa-trash me-2"></i>Delete <?= $count ?></button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
