<div class="modal" id="editLocationModal<?php echo $location_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title text-white"><i class="fa fa-fw fa-map-marker-alt mr-2"></i><?php echo $location_name; ?></h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="location_id" value="<?php echo $location_id; ?>">
        <div class="modal-body bg-white">
          <div class="form-group">
            <label>Location Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-map-marker"></i></span>
              </div>
              <input type="text" class="form-control" name="name" placeholder="Name of location" value="<?php echo $location_name; ?>" required>
            </div>
          </div>
          <div class="form-group">
            <label>Address <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
              </div>
              <input type="text" class="form-control" name="address" placeholder="Address" value="<?php echo $location_address; ?>" required>
            </div>
          </div>
         
          <div class="form-group">
            <label>City <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-city"></i></span>
              </div>
              <input type="text" class="form-control" name="city" placeholder="City" value="<?php echo $location_city; ?>" required>
            </div>
          </div>
          <div class="form-group">
            <label>State <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-flag"></i></span>
              </div>
              <select class="form-control select2" name="state" required>
                  <?php foreach($states_array as $state_abbr => $state_name) { ?>
                  <option <?php if($location_state == $state_abbr) { echo "selected"; } ?> value="<?php echo $state_abbr; ?>"><?php echo $state_name; ?></option>
                  <?php } ?>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label>Zip <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fab fa-fw fa-usps"></i></span>
              </div>
              <input type="text" class="form-control" name="zip" placeholder="Zip" value="<?php echo $location_zip; ?>" required data-inputmask="'mask': '99999'">
            </div>
          </div>
          <div class="form-group">
            <label>Phone <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-phone"></i></span>
              </div>
              <input type="text" class="form-control" name="phone" placeholder="Phone Number" data-inputmask="'mask': '999-999-9999'" value="<?php echo $location_phone; ?>" required> 
            </div>
          </div>

          <div class="form-group">
            <label>Hours</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-clock"></i></span>
              </div>
              <input type="text" class="form-control" name="hours" placeholder="Hours of operation" value="<?php echo $location_hours; ?>"> 
            </div>
          </div>

        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_location" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>