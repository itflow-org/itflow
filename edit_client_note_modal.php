<div class="modal" id="editClientNoteModal<?php echo $client_note_id; ?>" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title text-white"><i class="fa fa-fw fa-edit mr-2"></i>Edit Note</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="client_note_id" value="<?php echo $client_note_id; ?>">
        <div class="modal-body bg-white">  
          <div class="form-group">
            <label>Subject</label>
            <input type="text" class="form-control" name="subject" value="<?php echo $client_note_subject; ?>" required>
          </div>
          <div class="form-group">
            <label>Note</label>
            <textarea rows="8" class="form-control" id="editClientNote" name="note"><?php echo $client_note_body; ?></textarea>
          </div>
        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_client_note" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>