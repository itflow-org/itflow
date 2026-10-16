<?php

/*
 * Edit a product or service. ?id=
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_sales', 2);

$form_product = productRow(intval($_GET['id'] ?? 0));
if (!$form_product) {
    exit(json_encode(['error' => 'That product no longer exists.']));
}
$form_type = $form_product['product_type'];
$product_id = intval($form_product['product_id']);

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-edit me-2"></i>Edit <span class="fw-normal text-white-50 ms-1"><?= escapeHtml($form_product['product_name']) ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="product_id" value="<?= $product_id ?>">

    <div class="modal-body">
        <?php require __DIR__ . '/product_form.php'; ?>
    </div>

    <div class="modal-footer">
        <button type="submit" name="edit_product" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
