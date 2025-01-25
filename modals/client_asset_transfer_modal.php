<div class="modal" id="transferAssetModal<?php echo $asset_id; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-<?php echo $device_icon; ?> mr-2"></i>Transfer asset: <strong><?php echo $asset_name; ?></strong> to a different client</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="current_asset_id" value="<?php echo $asset_id; ?>">

                <div class="modal-body bg-white">

                    <div class="form-group">
                        <label>New Client <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-users"></i></span>
                            </div>

                            <?php $clients_sql = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_id != $client_id AND client_archived_at IS NULL"); ?>
                            <select class="form-control select2" name="new_client_id" required>
                                <?php
                                    while ($row = mysqli_fetch_array($clients_sql)) {
                                        $id = intval($row["client_id"]);
                                        $name = nullable_htmlentities($row["client_name"]);
                                        echo "<option value='$id'>$name</option>";
                                    }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="alert alert-dark" role="alert">
                        <i>The current asset will be archived and content copied to a new asset.</i>
                    </div>

                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="change_client_asset" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Transfer</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
