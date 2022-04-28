<div class="modal" id="renameFolderModal<?php echo $folder_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-fw fa-edit"></i> Rename <?php echo $folder_name; ?></h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="folder_id" value="<?php echo $folder_id; ?>">
        <div class="modal-body bg-white">

          <div class="form-group">
            <label>Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-folder"></i></span>
              </div>
              <input type="text" class="form-control" name="folder_name" placeholder="Folder Name" value="<?php echo $folder_name; ?>" required>
            </div>
          </div>

        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="rename_folder" class="btn btn-primary">Rename</button>
        </div>
      </form>
    </div>
  </div>
</div>
