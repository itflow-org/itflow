<div class="modal" id="editCategoryModal<?php echo $category_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-fw fa-list"></i> Editing category: <strong><?php echo $category_name; ?></strong></h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="category_id" value="<?php echo $category_id; ?>">
        <input type="hidden" name="type" value="<?php echo $category; ?>">
        <div class="modal-body bg-white">
          
          <div class="form-group">
            <label>Name <strong class="text-danger">*</strong></label>
            <input type="text" class="form-control" name="name" value="<?php echo $category_name; ?>" required>
          </div>
          
          <label>Color <strong class="text-danger">*</strong></label>
          <div class="mb-3">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="color" value="<?php echo $category_color; ?>" checked>
                <label class="form-check-label">
                  <i class="fa fa-fw fa-4x fa-circle" style="color:<?php echo $category_color; ?>"></i>
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
          <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_category" class="btn btn-primary text-bold"><i class="fa fa-check"></i> Save</button>
        </div>
      </form>
    </div>
  </div>
</div>