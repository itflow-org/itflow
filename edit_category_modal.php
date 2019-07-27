<div class="modal" id="editCategoryModal<?php echo $category_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title text-white"><i class="fa fa-fw fa-list mr-2"></i><?php echo $category_name; ?></h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="category_id" value="<?php echo $category_id; ?>">
        <div class="modal-body bg-white">
          <div class="form-group">
            <label>Name <strong class="text-danger">*</strong></label>
            <input type="text" class="form-control" name="name" value="<?php echo $category_name; ?>" required>
          </div>
          <div class="form-group">
            <label>Type <strong class="text-danger">*</strong></label>
            <select class="form-control selectpicker show-tick" name="type" required>
              <?php foreach($category_types_array as $category_type_select) { ?>
              <option <?php if($category_type == $category_type_select) { echo "selected"; } ?>><?php echo $category_type_select; ?></option>
              <?php } ?>
            </select>
          </div>
          <div class="form-group">
            <label>Color <strong class="text-danger">*</strong></label>
            <input type="color" class="form-control col-md-2" name="color" value="<?php echo $category_color; ?>">
          </div>
        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_category" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>