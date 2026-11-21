<?php

/*
 * Edit one of your asset types - ITFlow's own can only be switched off and
 * on, from the list. The fields are asset_type_form.php.
 */

require_once '../../includes/modal_header.php';

$asset_type = assetTypeRow(intval($_GET['id'] ?? 0));

ob_start();

if (!$asset_type) { ?>
    <div class="modal-body text-center text-secondary py-5">That type is gone - it may have been deleted.</div>
<?php } else {
    $t = assetTypeFormValues($asset_type);
    $t_new = false; ?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-layer-group me-2"></i>Edit Asset Type <span class="fw-normal text-white-50 ms-1"><?= escapeHtml($asset_type['asset_type_name']) ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="asset_type_id" value="<?= intval($asset_type['asset_type_id']) ?>">

    <div class="modal-body">
        <?php if (intval($asset_type['asset_type_active']) !== 1) { ?>
            <div class="alert alert-secondary small py-2"><i class="fas fa-fw fa-power-off me-1"></i>Switched off - it can't be picked, and its assets keep it. Switch it on from the list.</div>
        <?php } ?>
        <?php require __DIR__ . '/asset_type_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_asset_type" class="btn btn-primary fw-bold"><i class="fas fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php }
require_once '../../../includes/modal_footer.php';
