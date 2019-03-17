<div class="modal fade" id="editAccountModal<?php echo $account_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-piggy-bank"></i> Modify Account</h5>
        <button type="button" class="close" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="account_id" value="<?php echo $account_id; ?>">
        <div class="modal-body">
          <div class="form-group">
            <label>Account Name</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-university"></i></span>
              </div>
              <input type="text" class="form-control" name="name" value="<?php echo $account_name; ?>" placeholder="Account name" required>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_account" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>