<div class="modal" id="manageTagsModal" tabindex="-1">
  <div class="modal-dialog modal-md">
    <div class="modal-content bg-dark">
      <div class="modal-header">
          <h5 class="modal-title"><i class="fa fa-fw fa-tags"></i> Manage Tags</h5>
          <button type="button" class="close text-white" data-dismiss="modal">
              <span>&times;</span>
          </button>
      </div>
      <div class="modal-body bg-white">
        <legend>Add Tag</legend>
        <form action="post.php" method="post" autocomplete="off">
          <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
          <div class="form-group">
            <div class="input-group">
              <input type="text" class="form-control" name="tag_name" placeholder="Tag Name" required autofocus>
              <div class="input-group-append">
                <button type="submit" name="add_document_tag" class="btn btn-primary"><i class="fa fa-check"></i></button>
              </div>
            </div>
          </div>
        </form>
        <?php
        // Only show the edit/update tags if we have tags to work with
        if($document_tags){ ?>
        <hr>
        <legend>Delete Tag</legend>
        <form action="post.php" method="post" autocomplete="off">
          <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
          <div class="form-group">
            <div class="input-group">
              <select class="form-control select2" name="tag_id" required>
                <?php
                foreach($document_tags as $document_tag) {
                  echo "<option value='$document_tag[tag_id]'>"; echo htmlentities($document_tag['tag_name']); echo "</option>";
                }
                ?>
              </select>
              <div class="input-group-append">
                <button type="submit" name="delete_document_tag" class="btn btn-danger"><i class="fa fa-trash"></i></button>
              </div>
          </div>
        </form>
        <hr>
        <legend>Rename Tag</legend>
        <form action="post.php" method="post" autocomplete="off">
          <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
          <div class="form-group">
            <select class="form-control select2" name="tag_id" required>
              <?php
              foreach($document_tags as $document_tag) {
                echo "<option value='$document_tag[tag_id]'>"; echo htmlentities($document_tag['tag_name']); echo "</option>";
              }
              ?>
            </select>
          </div>
          <div class="form-group">
            <input type="text" class="form-control" name="tag_new_name" placeholder="Rename selected tag to" required>
          </div>
          <button type="submit" name="rename_document_tag" class="btn btn-primary"><i class="fa fa-exchange-alt"></i> Rename</button>
        </form>
      </div>
      <?php 
      }
      ?>
      <div class="modal-footer bg-white">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
      </div>
    </div>
  </div>
</div>