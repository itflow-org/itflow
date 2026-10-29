<?php

/*
 * New product or service. ?type=service|product picks what it starts as -
 * the window can switch it.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_sales', 2);

$form_product = null;
$form_type = (string) ($_GET['type'] ?? 'service');

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-box-open me-2"></i>New Product or Service</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">
        <?php require __DIR__ . '/product_form.php'; ?>
    </div>

    <div class="modal-footer">
        <button type="submit" name="add_product" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Add</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
