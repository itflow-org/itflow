<div class="modal fade" id="addAccountModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-piggy-bank"></i> New Account</h5>
        <button type="button" class="close" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <div class="modal-body">
          <div class="form-group">
            <label>Account Name</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-university"></i></span>
              </div>
              <input type="text" class="form-control" name="name" placeholder="Account name" required>
            </div>
          </div>
          <div class="form-group">
            <label>Opening Balance</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-dollar-sign"></i></span>
              </div>
              <input type="number" class="form-control" step="0.01" min="0" name="opening_balance" placeholder="Opening Balance" required>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_account" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>