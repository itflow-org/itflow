<div class="modal" id="editAccountModal<?php echo $account_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-fw fa-piggy-bank"></i> <?php echo $account_name; ?></h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="account_id" value="<?php echo $account_id; ?>">
        <div class="modal-body bg-white">
          
          <div class="form-group">
            <label>Account Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-piggy-bank"></i></span>
              </div>
              <input type="text" class="form-control" name="name" value="<?php echo $account_name; ?>" placeholder="Account name" required>
            </div>
          </div>
        
          <div class="form-group">
            <label>Notes</label>
            <textarea class="form-control" rows="5" placeholder="Enter some notes" name="notes"><?php echo $account_notes; ?></textarea>
          </div>

        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_account" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>