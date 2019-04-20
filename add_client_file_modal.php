<div class="modal" id="addClientFileModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-upload"></i> Upload File</h5>
        <button type="button" class="close" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
        <input type="hidden" name="client_id" value="<?php echo $_GET['client_id']; ?>">
        <div class="modal-body">    
          
          <div class="form-group">
            <label>New Name</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-id-badge"></i></span>
              </div>
              <input type="text" class="form-control" name="new_name" placeholder="Leave blank to use the original filename">
            </div>
          </div>
          
          <div class="form-group">
            <label>Type</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-file"></i></span>
              </div>
              <select class="form-control" name="file_type" required>
                <option>Picture</option>
                <option>Document</option>
                <option>Backup</option>
                <option>Other</option>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label>File</label>
            <input type="file" class="form-control-file" name="file">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_client_file" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>