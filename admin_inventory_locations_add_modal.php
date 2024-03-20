<div class="modal" id="addLocationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header text-white">
                <h5 class="modal-title"><i class="fas fa-fw fa-map-marker-alt mr-2"></i>New Location</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <div class="modal-body bg-white">
                    <div class="form-group">
                        <label>Name <strong class="text-danger">*</strong></label>
                        <input type="text" class="form-control" name="name" placeholder="Location name" required
                            autofocus>
                    </div>

                    <div class="form-group">
                        <label>Description <strong class="text-danger">*</strong></label>
                        <input type="text" class="form-control" name="description" placeholder="Description" required>
                    </div>

                    <div class="form-group">
                        <label>Address</label>
                        <input type="text" class="form-control" name="address" placeholder="Address (Optional)">
                    </div>

                    <div class="form-group">
                        <label>City</label>
                        <input type="text" class="form-control" name="city" placeholder="City (Optional)">
                    </div>

                    <div class="form-group">
                        <label>State</label>
                        <input type="text" class="form-control" name="state" placeholder="State (Optional)">
                    </div>

                    <div class="form-group">
                        <label>Zip</label>
                        <input type="text" class="form-control" name="zip" placeholder="Zip (Optional)">
                    </div>

                    <div class="form-group">
                        <label>Country</label>
                        <input type="text" class="form-control" name="country" placeholder="Country (Optional)">
                    </div>

                    <div class="form-group">
                        <label>User Assigned<strong class="text-danger">*</strong></label>
                        <select class="form-control select2" name="user_id" required>
                            <option value="" selected disabled>Select a user</option>
                            <?php
                            $users = mysqli_query($mysqli, "SELECT users.* FROM users
							LEFT JOIN inventory_locations ON users.user_id = inventory_locations.inventory_location_user_id
							WHERE user_status = 1 AND user_archived_at IS NULL AND inventory_locations.inventory_location_user_id IS NULL AND users.user_id != '$inventory_location_user_id'");
                            while ($user = mysqli_fetch_array($users)) {
                                echo "<option value=\"$user[user_id]\">$user[user_name]</option>";
                            }
                            if (!mysqli_num_rows($users)) {
                                echo "<option value=\"\" disabled>No users available</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="add_inventory_locations" class="btn btn-primary text-bold"><i
                            class="fa fa-check mr- 2"></i>Create</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i
                            class="fas fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>