<?php

/*
 * Set the income category on the invoices ticked on the invoices list.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_sales', 2);

$invoice_ids = array_values(array_filter(array_map('intval', (array) ($_GET['invoice_ids'] ?? []))));
$count = count($invoice_ids);
$categories = billingCategories();

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-tag me-2"></i>Set Category <span class="fw-normal text-white-50 ms-1"><?= $count ?> invoice<?= $count === 1 ? '' : 's' ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php foreach ($invoice_ids as $invoice_id) { ?><input type="hidden" name="invoice_ids[]" value="<?= $invoice_id ?>"><?php } ?>

    <div class="modal-body">
        <label class="form-label">Category <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
            <select class="form-select select2" name="bulk_category_id" required>
                <?php foreach ($categories as $category_id => $category) { ?>
                    <option value="<?= intval($category_id) ?>"<?= selectOptionColor($category['category_color'] ?? '', $category['category_icon'] ?? '') ?>><?= escapeHtml($category['category_name']) ?></option>
                <?php } ?>
            </select>
        </div>
        <div class="form-text">Reports group income by it.</div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="bulk_edit_invoice_category" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Set Category</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
