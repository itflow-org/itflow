<?php

/*
 * New contract template. The fields are contract_template_form.php.
 */

require_once '../../includes/modal_header.php';

$template = null;

ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-file-contract me-2"></i>New Contract Template</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <div class="modal-body">
        <?php require __DIR__ . '/contract_template_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_contract_template" class="btn btn-primary fw-bold"><i class="fas fa-check me-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
