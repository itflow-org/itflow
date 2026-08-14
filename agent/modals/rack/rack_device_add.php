<?php

require_once '../../../includes/modal_header.php';

$rack_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT rack_client_id, rack_name FROM racks WHERE rack_id = $rack_id LIMIT 1");

$row = mysqli_fetch_assoc($sql);
$rack_name = escapeHtml($row['rack_name']);
$client_id = intval($row['rack_client_id']);

enforceClientAccess();

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-server me-2"></i>Adding Device to Rack <strong><?= $rack_name ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="rack_id" value="<?= $rack_id ?>">

    <div class="modal-body">

        <div class="mb-3">
            <label>Custom Device</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-server"></i></span>
                <input type="text" class="form-control" name="name" placeholder="Device Name" maxlength="200">
            </div>
        </div>

        <div class="mb-3">
            <label>Or Select a Device</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-desktop"></i></span>
                <select class="form-control select2" name="asset">
                    <option value="">- Select Asset -</option>
                    <?php
                    // Fetch IDs of all assets already assigned to any rack
                    $assigned_assets = [];
                    $assigned_sql = mysqli_query($mysqli, "SELECT unit_asset_id FROM rack_units");
                    while ($assigned_row = mysqli_fetch_assoc($assigned_sql)) {
                        $assigned_assets[] = intval($assigned_row['unit_asset_id']);
                    }
                    $assigned_assets_list = implode(',', $assigned_assets);
                    $assigned_assets_list = empty($assigned_assets_list) ? '0' : $assigned_assets_list;

                    // Fetch assets not assigned to any rack
                    $sql_assets = mysqli_query($mysqli, "SELECT asset_id, asset_name FROM assets WHERE asset_archived_at IS NULL AND asset_client_id = $client_id AND asset_id NOT IN ($assigned_assets_list) ORDER BY asset_name ASC");
                    while ($row = mysqli_fetch_assoc($sql_assets)) {
                        $asset_id = intval($row['asset_id']);
                        $asset_name = escapeHtml($row['asset_name']);
                        ?>
                        <option value="<?= $asset_id ?>"><?= $asset_name ?></option>
                    <?php } ?>

                </select>
            </div>
        </div>

        <div class="mb-3">
            <label>Unit Number Start - End <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-sort-numeric-up-alt"></i></span>
                <input type="number" class="form-control" name="unit_start" placeholder="Unit Start" min="1" max="<?= $rack_units ?>" required>
                <input type="number" class="form-control" name="unit_end" placeholder="Unit End" min="1" max="<?= $rack_units ?>" required>
            </div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="add_rack_unit" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Add to Rack</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
