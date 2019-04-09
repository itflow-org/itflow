<div class="modal" id="addClientInvoiceModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-file"></i> New Invoice</h5>
        <button type="button" class="close" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="client" value="<?php echo $client_id; ?>">
        <div class="modal-body">
          <div class="form-row">
            <div class="form-group col">
              <label>Invoice Date</label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-calendar"></i></span>
                </div>
                <input type="date" class="form-control" name="date" value="<?php echo date("Y-m-d"); ?>" required>
              </div>
            </div>
            <div class="form-group col">
              <label>Payment Due</label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-calendar"></i></span>
                </div>
                <input type="date" class="form-control" name="due" required>
              </div>
            </div>
          </div>
      
          <div class="form-group">
            <label>Category</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-user"></i></span>
              </div>
              <select class="form-control" name="category" required>
                <option value="">- Category -</option>
                <?php 
                
                $sql = mysqli_query($mysqli,"SELECT * FROM categories WHERE category_type = 'Income'"); 
                while($row = mysqli_fetch_array($sql)){
                  $category_id = $row['category_id'];
                  $category_name = $row['category_name'];
                ?>
                <option value="<?php echo $category_id; ?>"><?php echo $category_name; ?></option>
                
                <?php
                }
                ?>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_invoice" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>