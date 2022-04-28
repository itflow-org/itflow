<div class="modal" id="editDocumentModal<?php echo $document_id; ?>" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-fw fa-file-alt"></i> <?php echo $document_name; ?></h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="document_id" value="<?php echo $document_id; ?>">
        <div class="modal-body bg-white">  
          
          <div class="form-group">
            <input type="text" class="form-control" name="name" value="<?php echo $document_name; ?>" placeholder="Name" required>
          </div>
          
          <div class="form-group">
            <textarea class="form-control summernote" name="content"><?php echo $document_content; ?></textarea>
          </div>
        
        </div>
        <div class="modal-footer bg-white">

          <div class="form-group mr-auto">
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-info-circle"></i></span>
              </div>
              <select class="form-control" name="template">
                <option value="0">Document</option>
                <option value="1">Template</option>
                <option value="3">Global Template</option>
              </select>
            </div>
          </div>

          <div class="form-group ml-auto">
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-folder"></i></span>
              </div>
              <select class="form-control" name="folder">
                <option value="0">/</option>
                <?php
                $sql_folders_select = mysqli_query($mysqli,"SELECT * FROM folders WHERE folder_client_id = $client_id ORDER BY folder_name ASC");
                while($row = mysqli_fetch_array($sql_folders_select)){
                  $folder_id_select = $row['folder_id'];
                  $folder_name_select = $row['folder_name'];
                ?>
                <option <?php if($folder_id_select == $document_folder_id) echo "selected"; ?> value="<?php echo $folder_id_select ?>"><?php echo $folder_name_select; ?></option>
                <?php
                } 
                ?>
              </select>
            </div>
          </div>

          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_document" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>