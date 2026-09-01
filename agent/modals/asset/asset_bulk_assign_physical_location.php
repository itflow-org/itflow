<?php

require_once '../../../includes/modal_header.php';

$asset_ids = array_map('intval', $_GET['asset_ids'] ?? []);

$count = count($asset_ids);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-map-marker-alt me-2"></i>Set Physical Location for <strong><?= $count ?></strong> Assets</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php foreach ($asset_ids as $asset_id) { ?><input type="hidden" name="asset_ids[]" value="<?= $asset_id ?>"><?php } ?>
    <div class="modal-body">

        <div class="mb-3">
            <label>Physical Location</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                <input type="text" class="form-control" name="physical_location" placeholder="Physical location eg. Floor 2, Closet B" maxlength="200">
            </div>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="bulk_assign_asset_physical_location" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Set Physical Location</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
