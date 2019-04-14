<div class="modal" id="addVendorModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-building"></i> New vendor</h5>
        <button type="button" class="close" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <div class="modal-body">
          <div class="form-group">
            <label>Name</label>
            <input type="text" class="form-control" name="name" required autofocus>
          </div>
          <div class="form-group">
            <label>Description</label>
            <input type="text" class="form-control" name="description">
          </div>
          <div class="form-group">
            <label>Account Number</label>
            <input type="text" class="form-control" name="account_number">
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