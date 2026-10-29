<?php

/* New Account. ?type= starts it as that kind. */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_financial', 2);

$form_row = null;
$form_type = intval($_GET['type'] ?? 1);

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-piggy-bank me-2"></i>New Account</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <div class="modal-body">
        <?php require __DIR__ . '/account_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_account" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
