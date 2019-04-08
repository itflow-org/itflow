<div class="modal" id="addProductModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-box"></i> New Product</h5>
        <button type="button" class="close" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <div class="modal-body">
          <div class="form-group">
            <label>Name</label>
            <input type="text" class="form-control" name="name" required autofocus>
          </div>
          <div class="form-group">
            <label>Description</label>
            <input type="text" class="form-control" name="description">
          </div>
          <div class="form-group">
            <label>Cost</label>
            <input type="text" class="form-control" name="cost" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_product" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>