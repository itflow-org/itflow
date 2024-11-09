<div class="modal" id="linkAssetToDocumentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-desktop mr-2"></i>Link Asset to <strong><?php echo $document_name; ?></strong></h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="document_id" value="<?php echo $document_id; ?>">
                <div class="modal-body bg-white">

                    <div class="form-group">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-desktop"></i></span>
                            </div>
                            <select class="form-control select2" name="asset_id">
                                <option value="">- Select an Asset -</option>
                                <?php
                                // Check if there are any associated vendors
                                if (!empty($linked_assets)) {
                                    $excluded_asset_ids = implode(",", $linked_assets);
                                    $exclude_condition = "AND asset_id NOT IN ($excluded_asset_ids)";
                                } else {
                                    $exclude_condition = "";  // No condition if there are no displayed vendors
                                }

                                $sql_assets_select = mysqli_query($mysqli, "SELECT * FROM assets
                                    WHERE asset_client_id = $client_id 
                                    AND asset_archived_at IS NULL
                                    $exclude_condition
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
                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="link_asset_to_document" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Link</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
