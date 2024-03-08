<div class="modal" id="editLocationModal<?php echo $inventory_locations_id;?>" tabindex="-1">
	<div class="modal-dialog">
		<div class="modal-content bg-dark">
			<div class="modal-header text-white">
				<h5 class="modal-title"><i class="fas fa-fw fa-map-marker-alt mr-2"></i>Edit Location</h5>
				<button type="button" class="close text-white" data-dismiss="modal">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<form action="post.php" method="post" autocomplete="off">
				<input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">
				<input type="hidden" name="inventory_location_id" value="<?php echo $inventory_locations_id; ?>">

				<div class="modal-body bg-white">
					<div class="form-group">
						<label>Name <strong class="text-danger">*</strong></label>
						<input type="text" class="form-control" name="name" value="<?php echo $inventory_locations_name; ?>" placeholder="Location name" required autofocus>
					</div>

					<div class="form-group">
						<label>Description <strong class="text-danger">*</strong></label>
						<input type="text" class="form-control" name="description" value="<?php echo $inventory_locations_description; ?>" placeholder="Description" required>
					</div>

					<div class="form-group">
						<label>Address</label>
						<input type="text" class="form-control" name="address" value="<?php echo $inventory_locations_address; ?>" placeholder="Address (Optional)">
					</div>

					<div class="form-group">
						<label>City</label>
						<input type="text" class="form-control" name="city" value="<?php echo $inventory_locations_city; ?>" placeholder="City (Optional)">
					</div>

					<div class="form-group">
						<label>State</label>
						<input type="text" class="form-control" name="state" value="<?php echo $inventory_locations_state; ?>" placeholder="State (Optional)">
					</div>

					<div class="form-group">
						<label>Zip</label>
						<input type="text" class="form-control" name="zip" value="<?php echo $inventory_locations_zip; ?>" placeholder="Zip (Optional)">
					</div>

					<div class="form-group">
						<label>Country</label>
						<input type="text" class="form-control" name="country" value="<?php echo $inventory_locations_country; ?>" placeholder="Country (Optional)">
					</div>

					<div class="form-group">
						<label>User Assigned<strong class="text-danger">*</strong></label>
						<select class="form-control select2" name="user_id" required>
							<option value="" selected disabled>Select a user</option>
							<?php
							$users = mysqli_query($mysqli, "SELECT users.* FROM users
							LEFT JOIN inventory_locations ON users.user_id = inventory_locations.inventory_locations_user_id
							WHERE user_status = 1 AND user_archived_at IS NULL AND inventory_locations.inventory_locations_user_id IS NULL AND users.user_id != '$inventory_locations_user_id'");
							while ($user = mysqli_fetch_array($users)) {
								$user_name = nullable_htmlentities($user['user_name']);
								$user_id = intval($user['user_id']);
								echo "<option value=\"$user[user_id]\">$user[user_name]</option>";
							}
							// Add the selected attribute to the user that is currently assigned to the location
							echo "<option value=\"$inventory_locations_user_id\" selected disabled>$inventory_locations_user_name</option>";
							?>
            			</select>
					</div>
				</div>
				<div class="modal-footer bg-white">
					<button type="submit" name="edit_inventory_locations" class="btn btn-primary text-bold"><i class="fa fa-check mr- 2"></i>Create</button>
					<button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
				</div>
			</form>
		</div>
	</div>
</div>