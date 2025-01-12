<div class="modal" id="addRackUnitModal<?php echo $rack_id; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-server mr-2"></i>Adding Device to Rack <strong><?php echo $rack_name; ?></strong></h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>

            <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">

                <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                <input type="hidden" name="rack_id" value="<?php echo $rack_id; ?>">

                <div class="modal-body bg-white">

                    <div class="form-group">
                        <label>Custom Device</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-server"></i></span>
                            </div>
                            <input type="text" class="form-control" name="name" placeholder="Device Name">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Or Select a Device</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-desktop"></i></span>
                            </div>
                            <select class="form-control select2" name="asset">
                                <option value="">- Asset -</option>
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
                                $sql_assets = mysqli_query($mysqli, "SELECT * FROM assets WHERE asset_archived_at IS NULL AND asset_client_id = $client_id AND asset_id NOT IN ($assigned_assets_list) ORDER BY asset_name ASC");
                                while ($row = mysqli_fetch_array($sql_assets)) {
                                    $asset_id = intval($row['asset_id']);
                                    $asset_name = nullable_htmlentities($row['asset_name']);
                                    ?>
                                    <option value="<?php echo $asset_id; ?>"><?php echo $asset_name; ?></option>
                                <?php } ?>

                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Unit Number Start - End <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-sort-numeric-up-alt"></i></span>
                            </div>
                            <input type="number" class="form-control" name="unit_start" placeholder="Unit Start" min="1" max="<?php echo $rack_units; ?>" required>
                            <input type="number" class="form-control" name="unit_end" placeholder="Unit End" min="1" max="<?php echo $rack_units; ?>" required>
                        </div>
                    </div>

                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="add_rack_unit" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Add to Rack</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
