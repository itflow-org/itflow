<div class="modal" id="editTicketModal<?php echo $ticket_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header text-white">
        <h5 class="modal-title"><i class="fa fa-fw fa-tag mr-2"></i>Ticket # <?php echo $ticket_id; ?></h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="ticket_id" value="<?php echo $ticket_id; ?>">
        <div class="modal-body bg-white">
          
          <div class="form-group">
            <label>Client</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
              </div>
              <input type="text" class="form-control" value="<?php echo $client_name; ?>" disabled>
            </div>
          </div>

          <div class="form-group">
            <label>Subject</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
              </div>
              <input type="text" class="form-control" name="subject" value="<?php echo $ticket_subject; ?>" required>
            </div>
          </div>
          
          <div class="form-group">
            <label>Details</label>
            <textarea class="form-control" rows="8" name="details" required><?php echo $ticket_details; ?></textarea>
          </div>

        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_ticket" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>