<div class="modal" id="editNoteModal<?php echo $note_id; ?>" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title text-white"><i class="fa fa-fw fa-edit mr-2"></i><?php echo $note_subject; ?></h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="note_id" value="<?php echo $note_id; ?>">
        <div class="modal-body bg-white">  
          <div class="form-group">
            <label>Subject <strong class="text-danger">*</strong></label>
            <input type="text" class="form-control" name="subject" value="<?php echo $note_subject; ?>" placeholder="Subject" required>
          </div>
          <div class="form-group">
            <label>Note <strong class="text-danger">*</strong></label>
            <textarea class="form-control summernote" name="note"><?php echo $note_body; ?></textarea>
          </div>
        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_note" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>