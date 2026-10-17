<?php

/* Copy other income - the same category, client, account and amount, dated today. ?id= */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_financial', 2);

$form_row = incomeRevenueRow(intval($_GET['id'] ?? 0));
if (!$form_row) {
    exit(json_encode(['error' => 'That income no longer exists, or is not one you can see.']));
}
$form_mode = 'copy';

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fas fa-fw fa-copy me-2"></i>Copy Income</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <div class="modal-body">
        <?php require __DIR__ . '/revenue_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_revenue" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Add Income</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
