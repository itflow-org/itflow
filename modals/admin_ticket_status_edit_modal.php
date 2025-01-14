<div class="modal" id="editTicketStatusModal<?php echo $ticket_status_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-fw fa-info-circle mr-2"></i>Editing Ticket Status: <strong><?php echo $ticket_status_name; ?></strong></h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="ticket_status_id" value="<?php echo $ticket_status_id; ?>">
        <div class="modal-body bg-white">
          
          <div class="form-group">
            <label>Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
              </div>
              <input type="text" class="form-control" name="name" maxlength="200" value="<?php echo $ticket_status_name; ?>" required>
            </div>
          </div>
          
          <div class="form-group">
            <label>Color <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-paint-brush"></i></span>
              </div>
              <input type="color" class="form-control col-3" name="color" value="<?php echo $ticket_status_color; ?>" required>
            </div>
          </div>

          <div class="form-group">
            <label>Status <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-info-circle"></i></span>
              </div>
              <select class="form-control select2" name="status" required>
                <option <?php if ($ticket_status_active == 1) { echo "selected"; } ?> value="1">Active</option>
                <option <?php if ($ticket_status_active == 0) { echo "selected"; } ?> value="0">Disabled</option>
              </select>
            </div>
          </div>
        
        </div>
        <div class="modal-footer bg-white">
          <button type="submit" name="edit_ticket_status" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save</button>
          <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>
