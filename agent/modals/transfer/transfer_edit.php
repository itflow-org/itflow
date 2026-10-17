<?php

/* Edit a transfer - both sides of it. ?id= */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_financial', 2);

$form_row = transferRow(intval($_GET['id'] ?? 0));
if (!$form_row) {
    exit(json_encode(['error' => 'That transfer no longer exists.']));
}

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fas fa-fw fa-exchange-alt me-2"></i>Edit <?= transferWhat($form_row) ?> <span class="fw-normal text-white-50 ms-1"><?= escapeHtml($form_row['transfer_date']) ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="transfer_id" value="<?= intval($form_row['transfer_id']) ?>">
    <div class="modal-body">
        <?php require __DIR__ . '/transfer_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_transfer" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
