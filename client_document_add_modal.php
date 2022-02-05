<div class="modal" id="addDocumentModal" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-fw fa-file-alt"></i> New Document</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
        <div class="modal-body bg-white">  
          
          <div class="form-group">
            <input type="text" class="form-control" name="name" placeholder="Name" required autofocus>
          </div>
          <?php
          if($document_tags) {
          ?>
            <!-- Document Tags select start -->
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
                                <input class="form-check-input" type="checkbox" value="<?php echo $document_tag['tag_id'] ?>" name="tags_ids[<?php echo $document_tag['tag_id']; ?>]"> <?php echo htmlentities($document_tag['tag_name']); ?>
                            </label>
                        </div>
                    </li>
                    <?php
                    }
                    ?>
                  </ul>
              </div>
          </div>
            <!-- Document tags select end -->
          <?php
          }
          ?>
          
          <div class="form-group">
            <textarea class="form-control summernote" name="content"></textarea>
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