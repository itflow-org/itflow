<div class="modal" id="addVendorModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content bg-dark">
      <div class="modal-header text-white">
        <h5 class="modal-title"><i class="fa fa-fw fa-building mr-2"></i>New Vendor</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <div class="modal-body bg-white">
          <div class="form-group">
            <label>Name</label>
            <input type="text" class="form-control" name="name" required autofocus>
          </div>
          <div class="form-group">
            <label>Description</label>
            <input type="text" class="form-control" name="description">
          </div>
          <div class="form-group">
            <label>Address</label>
            <input type="text" class="form-control" name="address">
          </div>
          <div class="form-row">
            <div class="form-group col">
              <label>City</label>
              <input type="text" class="form-control" name="city">
            </div>
            <div class="form-group col">
              <label>State</label>
              <select class="form-control" name="state">
                <option value="">- State -</option>
                <?php foreach($states_array as $state_abbr => $state_name) { ?>
                <option value="<?php echo $state_abbr; ?>"><?php echo $state_name; ?></option>
                <?php } ?>
              </select> 
            </div>
            <div class="form-group col">
              <label>Zip</label>
              <input type="text" class="form-control" name="zip">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group col">
              <label>Phone</label>
              <input type="text" class="form-control" name="phone">
            </div>
            <div class="form-group col">
              <label>Email</label>
              <input type="email" class="form-control" name="email">
            </div>
            <div class="form-group col">
              <label>Website</label>
              <input type="text" class="form-control" name="website">
            </div>
          </div>
          <div class="form-group">
            <label>Account Number</label>
            <input type="text" class="form-control" name="account_number">
          </div>
        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_vendor" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>