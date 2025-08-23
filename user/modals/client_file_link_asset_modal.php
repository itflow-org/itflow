<div class="modal" id="linkAssetToFileModal<?php echo $file_id; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark">
                <h5 class="modal-title"><i class="fa fa-fw fa-desktop mr-2"></i>Link Asset to <strong><?php echo $file_name; ?></strong></h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="file_id" value="<?php echo $file_id; ?>">
                <div class="modal-body">

                    <div class="form-group">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-desktop"></i></span>
                            </div>
                            <select class="form-control select2" name="asset_id">
                                <option value="">- Select an Asset -</option>
                                <?php

                                $sql_assets_select = mysqli_query($mysqli, "SELECT * FROM assets
                                    WHERE asset_client_id = $client_id 
                                    AND asset_archived_at IS NULL
                                    ORDER BY asset_name ASC"
                                );
                                while ($row = mysqli_fetch_array($sql_assets_select)) {
                                    $asset_id = intval($row['asset_id']);
                                    $asset_name = nullable_htmlentities($row['asset_name']);

                                    ?>
                                    <option value="<?php echo $asset_id ?>"><?php echo $asset_name; ?></option>
                                    <?php
                                }
                                ?>

                            </select>
                        </div>
                    </div>
                        <?php
                            $sql_assets = mysqli_query($mysqli, "SELECT * FROM assets, asset_files
                                WHERE assets.asset_id = asset_files.asset_id
                                AND asset_files.file_id = $file_id
                                ORDER BY asset_name ASC"
                            );

                            $linked_assets = array();

                            while ($row = mysqli_fetch_array($sql_assets)) {
                                $asset_id = intval($row['asset_id']);
                                $asset_name = nullable_htmlentities($row['asset_name']);

                                $linked_assets[] = $asset_id;

                        ?>
                                <div class="ml-2">
                                    <a href="asset_details.php?client_id=<?php echo $client_id; ?>&asset_id=<?php echo $asset_id; ?>" target="_blank"><?php echo $asset_name; ?></a>
                                    <a class="confirm-link float-right" href="post.php?unlink_asset_from_file&asset_id=<?php echo $asset_id; ?>&file_id=<?php echo $file_id; ?>">
                                        <i class="fas fa-fw fa-trash-alt text-secondary"></i>
                                    </a>
                                </div>
                        <?php
                            }
                        ?>

                </div>
                <div class="modal-footer">
                    <button type="submit" name="link_asset_to_file" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Link</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
