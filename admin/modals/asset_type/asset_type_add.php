<?php

/*
 * New asset type - one of your own. Starts from one of ITFlow's (?from=, or
 * Other), from one of yours (?copy=, its own details coming as new ones), or
 * from a type that is on assets but isn't set up (?name=, from Admin > Asset
 * Types). The fields are asset_type_form.php.
 */

require_once '../../includes/modal_header.php';

$copy = assetTypeRow(intval($_GET['copy'] ?? 0));
$t = assetTypeFormValues($copy, true, (string) ($_GET['from'] ?? 'Other'), (string) ($_GET['name'] ?? ''));
$t_new = true;
$t_from = $copy ? $copy['asset_type_name'] : assetTypeBuiltInName($_GET['from'] ?? '');

ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-layer-group me-2"></i>New Asset Type<?php if ($t_from !== '') { ?> <span class="fw-normal text-white-50 ms-1">from <?= escapeHtml($t_from) ?></span><?php } ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">
        <?php require __DIR__ . '/asset_type_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_asset_type" class="btn btn-primary fw-bold"><i class="fas fa-check me-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
