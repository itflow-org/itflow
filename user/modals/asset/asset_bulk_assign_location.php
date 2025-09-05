<div class="modal" id="bulkAssignLocationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark">
                <h5 class="modal-title"><i class="fa fa-fw fa-map-marker-alt mr-2"></i>Bulk Assign Location</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>

            <div class="modal-body">

                <div class="form-group">
                    <label>Location</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                        </div>
                        <select class="form-control select2" name="bulk_location_id">
                            <option value="">- Location -</option>
                            <?php

                            $sql = mysqli_query($mysqli, "SELECT location_id, location_name FROM locations WHERE location_archived_at IS NULL AND location_client_id = $client_id ORDER BY location_name ASC");
                            while ($row = mysqli_fetch_array($sql)) {
                                $location_id = intval($row['location_id']);
                                $location_name = nullable_htmlentities($row['location_name']);
                            ?>
                                <option value="<?php echo $location_id; ?>"><?php echo $location_name; ?></option>
                            <?php } ?>

                        </select>
                    </div>
                </div>

            </div>

            <div class="modal-footer">
                <button type="submit" name="bulk_assign_asset_location" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Assign</button>
                <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
            </div>
        </div>
    </div>
</div>