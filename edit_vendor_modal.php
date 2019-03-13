<div class="modal fade" id="editVendorModal<?php echo $vendor_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-edit"></i> Edit Vendor</h5>
        <button type="button" class="close" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <div class="modal-body">
          <div class="form-group">
            <label>Name</label>
            <input type="text" class="form-control" name="name" value="<?php echo "$vendor_name"; ?>" required>
          </div>
          <div class="form-group">
            <label>Phone</label>
            <input type="text" class="form-control" name="phone" data-inputmask="'mask': '999-999-9999'" value="<?php echo "$vendor_phone"; ?>" required> 
          </div>
          <div class="form-group">
            <label>Email</label>
            <input type="email" class="form-control" name="email" value="<?php echo "$vendor_email"; ?>">
          </div>
          <div class="form-group">
            <label>Website</label>
            <input type="text" class="form-control" name="website" value="<?php echo "$vendor_website"; ?>">
          </div>
          <div class="form-group">
            <label>Address</label>
            <input type="text" class="form-control" name="address" value="<?php echo "$vendor_address"; ?>">
          </div>
          <div class="form-group">
            <label>City</label>
            <input type="text" class="form-control" name="city" value="<?php echo "$vendor_city"; ?>">
          </div>
          <div class="form-group">
            <label>State</label>
            <input type="text" class="form-control" name="state" value="<?php echo "$vendor_state"; ?>">
          </div>
          <div class="form-group">
            <label>Zip</label>
            <input type="text" class="form-control" name="zip" value="<?php echo "$vendor_zip"; ?>">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="_vendor" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>