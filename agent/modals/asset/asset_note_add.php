<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$asset_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT asset_name, asset_client_id FROM assets WHERE asset_id = $asset_id LIMIT 1");
$row = mysqli_fetch_assoc($sql);
$asset_name = escapeHtml($row['asset_name']);
$client_id = intval($row['asset_client_id']);

enforceClientAccess();

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class='fa fa-fw fa-sticky-note me-2'></i>Creating note: <strong><?= $asset_name ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="asset_id" value="<?= $asset_id ?>">

    <div class="modal-body">

        <div class="mb-3">
            <label>Type</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-comment"></i></span>
                <select class="form-control select2" name="type">
                    <?php
                    $sql_asset_note_types_select = mysqli_query($mysqli, "
                        SELECT category_name FROM categories
                        WHERE category_type = 'asset_note_type'
                        AND category_archived_at IS NULL
                        ORDER BY category_order ASC, category_name ASC
                    ");
                    while ($row = mysqli_fetch_assoc($sql_asset_note_types_select)) {
                        $asset_note_type_select = escapeHtml($row['category_name']);
                        ?>
                        <option><?= $asset_note_type_select ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>

        <div class="mb-3">
            <textarea class="form-control" rows="6" name="note" placeholder="Notes, eg what was done, parts replaced, config changed, etc"></textarea>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="add_asset_note" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once '../../../includes/modal_footer.php';
