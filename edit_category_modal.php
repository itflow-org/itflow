<div class="modal" id="editCategoryModal<?php echo $category_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title text-white"><i class="fa fa-fw fa-list mr-2"></i>Modify Category</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <div class="modal-body bg-white">
          <div class="form-group">
            <label>Name</label>
            <input type="text" class="form-control" name="name" value="<?php echo $category_name; ?>" required>
          </div>
          <div class="form-group">
            <label>Type</label>
            <select class="form-control" name="type" required>
              <?php foreach($category_types_array as $category_type2) { ?>
              <option <?php if($category_type == $category_type2) { echo "selected"; } ?>><?php echo $category_type2; ?></option>
              <?php } ?>
            </select>
          </div>
          <div class="form-group">
            <label>Color</label>
            <input type="color" class="form-control" name="color" value="<?php echo $category_color; ?>">
          </div>
        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_category" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>