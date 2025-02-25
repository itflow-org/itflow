<div class="modal" id="linkServiceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-stream mr-2"></i>Link Service to <strong><?php echo $asset_name; ?></strong></h5>
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
                                <span class="input-group-text"><i class="fa fa-fw fa-stream"></i></span>
                            </div>
                            <select class="form-control select2" name="service_id">
                                <option value="">- Select a Service -</option>
                                <?php
                                // Check if there are any associated services
                                if (!empty($linked_services)) {
                                    $excluded_service_ids = implode(",", $linked_services);
                                    $exclude_condition = "AND service_id NOT IN ($excluded_service_ids)";
                                } else {
                                    $exclude_condition = "";  // No condition if there are no displayed services
                                }

                                $sql_services_select = mysqli_query($mysqli, "SELECT * FROM services
                                    WHERE service_client_id = $client_id
                                    $exclude_condition
                                    ORDER BY service_name ASC"
                                );
                                while ($row = mysqli_fetch_array($sql_services_select)) {
                                    $service_id = intval($row['service_id']);
                                    $service_name = nullable_htmlentities($row['service_name']);

                                    ?>
                                    <option value="<?php echo $service_id ?>"><?php echo $service_name; ?></option>
                                    <?php
                                }
                                ?>

                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="link_service_to_asset" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Link</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
