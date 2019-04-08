<div class="modal" id="editCategoryModal<?php echo $category_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-modify"></i> Modify Category</h5>
        <button type="button" class="close" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <div class="modal-body">
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
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_category" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>