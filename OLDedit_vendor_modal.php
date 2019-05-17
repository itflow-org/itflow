<div class="modal" id="editVendorModal<?php echo $vendor_id; ?>" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title text-white"><i class="fa fa-fw fa-building mr-2"></i><?php echo $vendor_name; ?></h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="vendor_id" value="<?php echo $vendor_id; ?>">
        <div class="modal-body bg-white">
          <div class="form-group">
            <label>Name</label>
            <input type="text" class="form-control" name="name" value="<?php echo "$vendor_name"; ?>" required>
          </div>
          <div class="form-group">
            <label>Description</label>
            <input type="text" class="form-control" name="description" value="<?php echo $vendor_description; ?>">
          </div>
          <div class="form-group">
            <label>Address</label>
            <input type="text" class="form-control" name="address" value="<?php echo $vendor_address; ?>">
          </div>
          <div class="form-row">
            <div class="form-group col">
              <label>City</label>
              <input type="text" class="form-control" name="city" value="<?php echo $vendor_city; ?>">
            </div>
            <div class="form-group col">
              <label>State</label>
              <select class="form-control" name="state">
                <?php foreach($states_array as $state_abbr => $state_name) { ?>
                <option <?php if($vendor_state == $state_abbr){ echo "selected"; } ?> value="<?php echo $state_abbr; ?>"><?php echo $state_name; ?></option>
                <?php } ?>
              </select> 
            </div>
            <div class="form-group col">
              <label>Zip</label>
              <input type="text" class="form-control" name="zip" value="<?php echo $vendor_zip; ?>">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group col">
              <label>Phone</label>
              <input type="text" class="form-control" name="phone" value="<?php echo $vendor_phone; ?>">
            </div>
            <div class="form-group col">
              <label>Email</label>
              <input type="email" class="form-control" name="email" value="<?php echo $vendor_email; ?>">
            </div>
            <div class="form-group col">
              <label>Website</label>
              <input type="text" class="form-control" name="website" value="<?php echo $vendor_website; ?>">
            </div>
          </div>

          <div class="form-group">
            <label>Account Number</label>
            <input type="text" class="form-control" name="account_number" value="<?php echo $vendor_account_number; ?>">
          </div>
        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_vendor" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>