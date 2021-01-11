<div class="modal" id="editRecurringItemModal<?php echo $item_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header text-white">
        <h5 class="modal-title"><i class="fa fa-fw fa-edit mr-2"></i>Edit Line Item: <?php echo $item_name; ?></h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="recurring_id" value="<?php echo $recurring_id; ?>">
        <input type="hidden" name="item_id" value="<?php echo $item_id; ?>">
        <div class="modal-body bg-white">
          
          <div class="form-group">
            <label>Item <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-box"></i></span>
              </div>
              <input type="text" class="form-control" name="name" value="<?php echo $item_name; ?>" required>
            </div>
          </div>

          <div class="form-group">
            <label>Description</label>
            <div class="input-group">
              <textarea class="form-control" rows="5" name="description"><?php echo $item_description; ?></textarea>
            </div>
          </div>
          
          <div class="form-row">
            
            <div class="col">

              <div class="form-group">
                <label>QTY <strong class="text-danger">*</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-balance-scale"></i></span>
                  </div>
                  <input type="number" class="form-control" step="0.01" min="0" name="qty" value="<?php echo $item_quantity; ?>" required>
                </div>
              </div>

            </div>

            <div class="col">

              <div class="form-group">
                <label>Price <strong class="text-danger">*</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                  </div>
                  <input type="number" class="form-control" step="0.01" min="0" name="price" value="<?php echo $item_price; ?>" required>
                </div>
              </div>
            
            </div>
          
          </div>

          <div class="form-group">
            <label>Tax <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-piggy-bank"></i></span>
              </div>
              <select class="form-control select2" name="tax">
                <?php include"taxes.php"; ?>
              </select>
            </div>
          </div>
          
        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_recurring_item" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>
