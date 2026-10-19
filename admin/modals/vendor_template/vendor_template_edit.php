<?php

/*
 * Edit a vendor template - the fields are vendor_template_form.php, with
 * the fields ticked changed on the vendors made from it too.
 */

require_once '../../includes/modal_header.php';

$template = vendorTemplateInfo($_GET['id'] ?? 0);

ob_start();

if (!$template) { ?>
    <div class="modal-header bg-dark">
        <h5 class="modal-title"><i class="fa fa-fw fa-building me-2"></i>Vendor template</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body text-center text-secondary py-5">That vendor template is gone - it may have been deleted.</div>
<?php } else {
    $vt_id = intval($template['vendor_template_id']);
    $vt = vendorTemplateRowFields($template);
    $vt_vendors = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM vendors WHERE vendor_template_id = $vt_id"))[0]);
    ?>

<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fa fa-fw fa-building me-2"></i>Vendor template: <strong><?= escapeHtml($template['vendor_template_name']) ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="/admin/post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="vendor_template_id" value="<?= $vt_id ?>">

    <div class="modal-body">
        <?php require __DIR__ . '/vendor_template_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_vendor_template" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php }

require_once '../../../includes/modal_footer.php';
