<div class="modal" id="linkSoftwareModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-cube mr-2"></i>License Software to <strong><?php echo $asset_name; ?></strong></h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="asset_id" value="<?php echo $asset_id; ?>">
                <div class="modal-body bg-white">

                    <div class="form-group">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-cube"></i></span>
                            </div>
                            <select class="form-control select2" name="software_id">
                                <option value="">- Select a Device Software License -</option>
                                <?php
                                // Check if there are any associated sofctware
                                if (!empty($linked_software)) {
                                    $excluded_software_ids = implode(",", $linked_software);
                                    $exclude_condition = "AND software_id NOT IN ($excluded_software_ids)";
                                } else {
                                    $exclude_condition = "";  // No condition if there are no displayed software
                                }

                                $sql_software_select = mysqli_query($mysqli, "SELECT * FROM software
                                    WHERE software_client_id = $client_id
                                    AND software_archived_at IS NULL
                                    AND software_license_type = 'Device'
                                    $exclude_condition
                                    ORDER BY software_name ASC"
                                );
                                while ($row = mysqli_fetch_array($sql_software_select)) {
                                    $software_id = intval($row['software_id']);
                                    $software_name = nullable_htmlentities($row['software_name']);

                                    ?>
                                    <option value="<?php echo $software_id ?>"><?php echo $software_name; ?></option>
                                    <?php
                                }
                                ?>

                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="link_software_to_asset" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Link</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
