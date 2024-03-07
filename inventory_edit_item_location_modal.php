<div class="modal" id="editInventoryLocations<?php echo $inventory_product_id; ?>" tabindex="-1">
    <div class="modal-dialog modal-md">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-clone mr-2"></i>Move Inventory</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" id="inventory_product_id" name="inventory_product_id" value="<?php echo $inventory_product_id; ?>">
                <input type="hidden" id="inventory_location_id" name="inventory_location_id" value="<?php echo $inventory_location_id; ?>">
                <div class="modal-body bg-white">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="inventory_location_id">Move to location</label>
                            <select class="form-control select2" id="inventory_new_location_id" name="inventory_new_location_id">
                                <option value="">Select location</option>
                                <?php
                                $inventory_locations = mysqli_query($mysqli, "SELECT * FROM inventory_locations WHERE inventory_locations_id != $inventory_location_id");
                                while ($inventory_location = mysqli_fetch_array($inventory_locations)) {
                                    echo "<option value='" . $inventory_location['inventory_locations_id'] . "'>" . $inventory_location['inventory_locations_name'] . "</option>";
                                }
                                ?>
                            </select>
                            <small>Choose the location to move the inventory to</small>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="inventory_quantity">Quantity</label>
                            <input type="number" class="form-control" id="inventory_quantity" name="inventory_quantity" value="<?php echo $inventory_quantity; ?>">
                            <small class="form-text text-muted">Enter the quantity to move</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" id="move_inventory_btn" name="move_inventory" class="btn btn-primary text-bold"><i class="fa fa-fw fa-clone mr-2"></i>Move Inventory</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

