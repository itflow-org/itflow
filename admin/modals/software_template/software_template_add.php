<?php

/*
 * New license template - the fields are software_template_form.php.
 */

require_once '../../includes/modal_header.php';

$template = [];

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-cube me-2"></i>New License Template</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">
        <?php require __DIR__ . '/software_template_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_software_template" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Create License Template</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
