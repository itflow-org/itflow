<div class="modal" id="editDocumentModal<?php echo $document_id; ?>" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title text-white"><i class="fa fa-fw fa-file-alt mr-2"></i><?php echo $document_name; ?></h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="document_id" value="<?php echo $document_id; ?>">
        <div class="modal-body bg-white">  
          <div class="form-group">
            <input type="text" class="form-control" name="name" value="<?php echo $document_name; ?>" placeholder="Name" required>
          </div>
          <div class="form-group">
            <textarea class="form-control summernote" name="details"><?php echo $document_details; ?></textarea>
          </div>
        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_document" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>