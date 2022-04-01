<div class="modal" id="editNextDateRecurringModal<?php echo $recurring_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-fw fa-sync-alt"></i> <?php echo "$recurring_prefix$recurring_number"; ?></h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="recurring_id" value="<?php echo $recurring_id; ?>">

        <div class="modal-body bg-white">

          <div class="form-group">
            <label>Next Date <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
              </div>
              <input type="date" class="form-control" name="next_date" value="<?php echo $recurring_next_date; ?>" required>
            </div>
          </div>

        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_recurring_next_date" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>