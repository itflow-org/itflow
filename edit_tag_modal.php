<div class="modal" id="editTagModal<?php echo $tag_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-fw fa-tag"></i> <?php echo $tag_name; ?></h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="tag_id" value="<?php echo $tag_id; ?>">
        <div class="modal-body bg-white">
          
          <div class="form-group">
            <label>Name <strong class="text-danger">*</strong></label>
            <input type="text" class="form-control" name="name" value="<?php echo $tag_name; ?>" required>
          </div>
          
          <label>Color <strong class="text-danger">*</strong></label>
          <div class="mb-3">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="color" value="<?php echo $tag_color; ?>" checked>
                <label class="form-check-label">
                  <i class="fa fa-fw fa-4x fa-circle" style="color:<?php echo $tag_color; ?>"></i>
                </label>
              </div>
            </div>
          <div class="form-row">

            <?php 

            foreach($colors_diff as $color) { 
            ?>
            <div class="col-3 mb-3">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="color" value="<?php echo $color; ?>">
                <label class="form-check-label">
                  <i class="fa fa-fw fa-3x fa-circle" style="color:<?php echo $color; ?>"></i>
                </label>
              </div>
            </div>
           
            <?php } ?>
          </div>
        
        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_tag" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>