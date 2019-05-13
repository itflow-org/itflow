<div class="modal" id="addCategoryModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header text-white">
        <h5 class="modal-title"><i class="fa fa-fw fa-list mr-2"></i>New Category</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <div class="modal-body bg-white">
          <div class="form-group">
            <label>Name</label>
            <input type="text" class="form-control" name="name" required autofocus>
          </div>
          <div class="form-group">
            <label>Type</label>
            <select class="form-control" name="type" required>
              <option value="">Select a type...</option>
              <?php foreach($category_types_array as $category_type) { ?>
              <option><?php echo $category_type; ?></option>
              <?php } ?>
            </select>
          </div>
          <div class="form-group">
            <label>Color</label>
            <input type="color" class="form-control" name="color">
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