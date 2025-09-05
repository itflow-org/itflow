<div class="modal" id="addDocumentFromTemplateModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-dark">
        <h5 class="modal-title"><i class="fa fa-fw fa-file-alt mr-2"></i>New Document from Template</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
        <div class="modal-body">

          <label>Template</label>
          <div class="form-group">
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-puzzle-piece"></i></span>
              </div>
              <select class="form-control" name="document_template_id" required>
                <option value="">- Select Template -</option>
                <?php
                $sql_document_templates = mysqli_query($mysqli, "SELECT * FROM document_templates WHERE document_template_archived_at IS NULL ORDER BY document_template_name ASC");
                while ($row = mysqli_fetch_array($sql_document_templates)) {
                  $document_template_id = intval($row['document_template_id']);
                  $document_template_name = nullable_htmlentities($row['document_template_name']);

                ?>
                <option value="<?php echo $document_template_id ?>"><?php echo $document_template_name; ?></option>
                <?php
                }
                ?>

              </select>
            </div>
          </div>

          <label>Document name</label>
          <div class="form-group">
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-file"></i></span>
              </div>
              <input type="text" class="form-control" name="name" placeholder="Name" required>
            </div>
          </div>

          <label>Description</label>
          <div class="form-group">
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-file"></i></span>
              </div>
              <input type="text" class="form-control" name="description" placeholder="Short summary of the document">
            </div>
          </div>

          <label>Folder</label>
          <div class="form-group">
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-folder"></i></span>
              </div>
              <select class="form-control" name="folder">
                <option value="0">/</option>
                <?php
                $sql_folders = mysqli_query($mysqli, "SELECT * FROM folders WHERE folder_location = $folder_location AND folder_client_id = $client_id ORDER BY folder_name ASC");
                while ($row = mysqli_fetch_array($sql_folders)) {
                  $folder_id = intval($row['folder_id']);
                  $folder_name = nullable_htmlentities($row['folder_name']);

                ?>
                <option <?php if (isset($_GET['folder_id']) && $_GET['folder_id'] == $folder_id) echo "selected"; ?> value="<?php echo $folder_id ?>"><?php echo $folder_name; ?></option>
                <?php
                }
                ?>

              </select>
            </div>
          </div>

        </div>

        <div class="modal-footer">

          <button type="submit" name="add_document_from_template" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Create</button>
          <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>

        </div>
      </form>
    </div>
  </div>
</div>
