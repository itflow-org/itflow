<div class="modal" id="addDocumentModal" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content bg-dark">
      <div class="modal-header text-white">
        <h5 class="modal-title"><i class="fa fa-fw fa-file-alt mr-2"></i>New Document</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
        <div class="modal-body bg-white">  
          <div class="form-group">
            <input type="text" class="form-control" name="name" placeholder="Name" required autofocus>
          </div>
          <div class="form-group">
            <textarea class="form-control summernote" name="details"></textarea>
          </div>
        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_document" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>