<?php

require_once '../../../includes/modal_header.php';

$selected_ids = array_map('intval', $_GET['selected_ids'] ?? []);

$count = count($selected_ids);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-info mr-2"></i>Set Status for <strong><?= $count ?></strong> Assets</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php foreach ($selected_ids as $id) { ?><input type="hidden" name="asset_ids[]" value="<?= $id ?>"><?php } ?>
    <div class="modal-body">

        <div class="form-group">
            <label>Status</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-info"></i></span>
                </div>
                <select class="form-control select2" name="bulk_status">
                    <option value="">- Status -</option>
                    <?php foreach($asset_status_array as $asset_status) { ?>
                        <option><?php echo $asset_status; ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="bulk_edit_asset_status" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Set Status</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
