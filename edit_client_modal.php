<div class="modal fade" id="editClientModal<?php echo $client_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-edit"></i> Edit Client</h5>
        <button type="button" class="close" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <div class="modal-body">
          <div class="form-group">
            <label>Name</label>
            <input type="text" class="form-control" name="name" value="<?php echo "$client_name"; ?>" required>
          </div>
          <div class="form-group">
            <label>Phone</label>
            <input type="text" class="form-control" name="phone" data-inputmask="'mask': '999-999-9999'" value="<?php echo "$client_phone"; ?>" required> 
          </div>
          <div class="form-group">
            <label>Email</label>
            <input type="email" class="form-control" name="email" value="<?php echo "$client_email"; ?>" required>
          </div>
          <div class="form-group">
            <label>Website</label>
            <input type="text" class="form-control" name="website" value="<?php echo "$client_website"; ?>">
          </div>
          <div class="form-group">
            <label>Address</label>
            <input type="text" class="form-control" name="address" value="<?php echo "$client_address"; ?>" required>
          </div>
          <div class="form-group">
            <label>City</label>
            <input type="text" class="form-control" name="city" value="<?php echo "$client_city"; ?>" required>
          </div>
          <div class="form-group">
            <label>State</label>
            <input type="text" class="form-control" name="state" value="<?php echo "$client_state"; ?>" required>
          </div>
          <div class="form-group">
            <label>Zip</label>
            <input type="text" class="form-control" name="zip" value="<?php echo "$client_zip"; ?>" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="_client" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>