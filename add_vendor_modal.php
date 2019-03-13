<div class="modal fade" id="addVendorModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-user"></i> New vendor</h5>
        <button type="button" class="close" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <div class="modal-body">
          <div class="form-group">
            <label>Name</label>
            <input type="text" class="form-control" name="name" required autofocus="autofocus">
          </div>
          <div class="form-group">
            <label>Phone</label>
            <input type="text" class="form-control" name="phone" data-inputmask="'mask': '999-999-9999'"> 
          </div>
          <div class="form-group">
            <label>Email</label>
            <input type="email" class="form-control" name="email">
          </div>
          <div class="form-group">
            <label>Website</label>
            <input type="text" class="form-control" name="website">
          </div>
          <div class="form-group">
            <label>Address</label>
            <input type="text" class="form-control" name="address">
          </div>
          <div class="form-group">
            <label>City</label>
            <input type="text" class="form-control" name="city">
          </div>
          <div class="form-group">
            <label>State</label>
            <input type="text" class="form-control" name="state">
          </div>
          <div class="form-group">
            <label>Zip</label>
            <input type="text" class="form-control" name="zip">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_vendor" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>