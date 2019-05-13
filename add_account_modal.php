<div class="modal" id="addAccountModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header text-white">
        <h5 class="modal-title"><i class="fa fa-fw fa-piggy-bank mr-2"></i>New Account</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <div class="modal-body bg-white">
          <div class="form-group">
            <label>Account Name</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-university"></i></span>
              </div>
              <input type="text" class="form-control" name="name" placeholder="Account name" required autofocus>
            </div>
          </div>
          <div class="form-group">
            <label>Opening Balance</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
              </div>
              <input type="number" class="form-control" step="0.01" min="0" name="opening_balance" placeholder="Opening Balance" required>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_account" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>