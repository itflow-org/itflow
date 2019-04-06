<div class="modal fade" id="addExpenseCopyModal<?php echo $expense_id; ?>" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-copy"></i> Copy expense</h5>
        <button type="button" class="close" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <div class="modal-body">
          <div class="form-row"> 
            <div class="form-group col-md">
              <label>Date</label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-calendar"></i></span>
                </div>
                <input type="date" class="form-control" name="date" value="<?php echo date("Y-m-d",time()); ?>" required>
              </div>
            </div>
            <div class="form-group col-md">
              <label>Amount</label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-dollar-sign"></i></span>
                </div>
                <input type="number" class="form-control" step="0.01" name="amount" value="<?php echo $expense_amount; ?>" required>
              </div>
            </div>
            <div class="form-group col-md">
              <label>Account</label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-university"></i></span>
                </div>
                <select class="form-control" name="account" required>
                  <?php 
                  
                  $sql2 = mysqli_query($mysqli,"SELECT * FROM accounts"); 
                  while($row = mysqli_fetch_array($sql2)){
                    $account_id2 = $row['account_id'];
                    $account_name = $row['account_name'];
                  ?>
                  <option <?php if($account_id == $account_id2){ ?> selected <?php } ?> value="<?php echo $account_id2; ?>"><?php echo $account_name; ?></option>
                  <?php
                  }
                  
                  ?>
                </select>
              </div>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group col-md">
              <label>Vendor</label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-briefcase"></i></span>
                </div>
                <select class="form-control" name="vendor" required>
                  <?php 
                  
                  $sql2 = mysqli_query($mysqli,"SELECT * FROM vendors"); 
                  while($row = mysqli_fetch_array($sql2)){
                    $vendor_id2 = $row['vendor_id'];
                    $vendor_name = $row['vendor_name'];
                  ?>
                  <option <?php if($vendor_id == $vendor_id2){ ?> selected <?php } ?> value="<?php echo $vendor_id2; ?>"><?php echo $vendor_name; ?></option>
                  <?php
                  }
                  
                  ?>
                </select>
              </div>
            </div>
            <div class="form-group col-md">
              <label>Category</label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-file"></i></span>
                </div>
                <select class="form-control" name="category" required>
                  <?php 
                  
                  $sql2 = mysqli_query($mysqli,"SELECT * FROM categories WHERE category_type = 'Expense'"); 
                  while($row = mysqli_fetch_array($sql2)){
                    $category_id2 = $row['category_id'];
                    $category_name = $row['category_name'];
                  ?>
                  <option <?php if($category_id == $category_id2){ ?> selected <?php } ?> value="<?php echo $category_id2; ?>"><?php echo $category_name; ?></option>
                  <?php
                  }
                  
                  ?>
                </select>
              </div>
            </div>
          </div>
          <div class="form-group">
            <label>Description</label>
            <textarea class="form-control" rows="4" name="description" required><?php echo $expense_description; ?></textarea>
          </div>
          <div class="custom-file">
            <input type="file" class="custom-file-input" name="attachment">
            <label class="custom-file-label">Attach Reciept...</label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_expense" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>