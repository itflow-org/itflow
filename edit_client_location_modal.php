<div class="modal" id="editClientLocationModal<?php echo $client_location_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-map-marker"></i> Edit Location</h5>
        <button type="button" class="close" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="client_location_id" value="<?php echo $client_location_id; ?>">
        <div class="modal-body">
          <div class="form-group">
            <label>Location Name</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-user"></i></span>
              </div>
              <input type="text" class="form-control" name="name" placeholder="Name of location" value="<?php echo $client_location_name; ?>" required>
            </div>
          </div>
          <div class="form-group">
            <label>Address</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-map-marker"></i></span>
              </div>
              <input type="text" class="form-control" name="address" placeholder="Address" value="<?php echo $client_location_address; ?>" required>
            </div>
          </div>
         
          <div class="form-group">
            <input type="text" class="form-control" name="city" placeholder="City" value="<?php echo $client_location_city; ?>" required>
          </div>
          <div class="form-group">
            <select class="form-control" name="state" required>
                <?php foreach($states_array as $state_abbr => $state_name) { ?>
                <option <?php if($client_location_state == $state_abbr) { echo "selected"; } ?> value="<?php echo $state_abbr; ?>"><?php echo $state_name; ?></option>
                <?php } ?>
            </select>
          </div>
          <div class="form-group">
            <input type="text" class="form-control" name="zip" placeholder="Zip" value="<?php echo $client_location_zip; ?>" required>
          </div>
          <div class="form-group">
            <label>Phone</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-phone"></i></span>
              </div>
              <input type="text" class="form-control" name="phone" placeholder="Phone Number" data-inputmask="'mask': '999-999-9999'" value="<?php echo $client_location_phone; ?>" required> 
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_client_location" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>