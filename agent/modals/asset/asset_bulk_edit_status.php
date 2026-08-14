<?php

require_once '../../../includes/modal_header.php';

$asset_ids = array_map('intval', $_GET['asset_ids'] ?? []);

$count = count($asset_ids);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-info me-2"></i>Set Status for <strong><?= $count ?></strong> Assets</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php foreach ($asset_ids as $asset_id) { ?><input type="hidden" name="asset_ids[]" value="<?= $asset_id ?>"><?php } ?>
    <div class="modal-body">

        <div class="mb-3">
            <label>Status</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-circle"></i></span>
                <select class="form-select select2" name="bulk_status">
                    <option value="">- Select Status -</option>
                    <?php
                    $sql_interface_types_select = mysqli_query($mysqli, "
                        SELECT category_name FROM categories
                        WHERE category_type = 'asset_status'
                        AND category_archived_at IS NULL
                        ORDER BY category_order ASC, category_name ASC
                    ");
                    while ($row = mysqli_fetch_assoc($sql_interface_types_select)) {
                        $asset_status_select = escapeHtml($row['category_name']);
                        ?>
                        <option><?= $asset_status_select ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="bulk_edit_asset_status" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Set Status</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
