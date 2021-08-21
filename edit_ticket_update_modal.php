<div class="modal" id="editTicketUpdateModal<?php echo $ticket_update_id; ?>" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-fw fa-edit"></i> Editing Ticket Update</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="ticket_update_id" value="<?php echo $ticket_update_id; ?>">
        
        <div class="modal-body bg-white">
          
          <div class="form-group">
            <textarea class="form-control summernote" rows="8" name="ticket_update"><?php echo $ticket_update; ?></textarea>
          </div>

        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_ticket_update" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>