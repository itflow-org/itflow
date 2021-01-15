<div class="modal" id="editTaxModal<?php echo $tax_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title text-white"><i class="fa fa-fw fa-balance-scale mr-2"></i><?php echo $tax_name; ?></h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="tax_id" value="<?php echo $tax_id; ?>">
        <div class="modal-body bg-white">
          <div class="form-group">
            <label>Name <strong class="text-danger">*</strong></label>
            <input type="text" class="form-control" name="name" value="<?php echo $tax_name; ?>" required>
          </div>
          <div class="form-group">
            <label>Percent <strong class="text-danger">*</strong></label>
            <input type="number" min="0" class="form-control col-md-2" name="percent" value="<?php echo $tax_percent; ?>">
          </div>
        </div>
        <div class="modal-footer bg-white">
          <a href="post.php?delete_category=<?php echo $category_id; ?>" class="btn btn-danger mr-auto"><i class="fa fa-trash text-white"></i></a>
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_tax" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>