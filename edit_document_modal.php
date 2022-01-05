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

          <!-- Document Tags select start -->
          <?php
          if($document_tags) { ?>
              <div class="form-group">
                    <div class="button-group">
                        <button type="button" class="btn btn-default btn-sm dropdown-toggle" data-toggle="dropdown">
                            <span class="fa fa-fw fa-tag"></span> <span class="caret"></span>
                        </button>
                        <ul class="dropdown-menu">
                                <?php
                                foreach($document_tags as $document_tag) {
                                    ?>
                                    <li>
                                        <div class="form-check">
                                            <label>
                                                <input class="form-check-input" type="checkbox" value="<?php echo $document_tag['tag_id'] ?>" name="tags_ids[<?php echo $document_tag['tag_id']; ?>]" <?php if(in_array($document_tag['tag_id'],$document_tags_set)) {echo "checked";} ?>> <?php echo htmlentities($document_tag['tag_name']); ?>
                                            </label>
                                        </div>
                                    </li>
                                <?php
                                } ?>
                        </ul>
                    </div>
              </div>
          <?php
          } ?>
          <!-- Document tags select end -->
          
          <div class="form-group">
            <textarea class="form-control summernote" name="content"><?php echo $document_content; ?></textarea>
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