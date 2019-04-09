<div class="modal" id="addExpenseModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-cart-plus"></i> New Expense</h5>
        <button type="button" class="close" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
        <div class="modal-body">
          <div class="form-row"> 
            <div class="form-group col-md">
              <label>Date</label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-calendar"></i></span>
                </div>
                <input type="date" class="form-control" name="date" value="<?php echo date("Y-m-d"); ?>" required>
              </div>
            </div>
            <div class="form-group col-md">
              <label>Amount</label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-dollar-sign"></i></span>
                </div>
                <input type="number" class="form-control" step="0.01" name="amount" autofocus="autofocus" required>
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
                  
                  $sql = mysqli_query($mysqli,"SELECT * FROM accounts"); 
                  while($row = mysqli_fetch_array($sql)){
                    $account_id = $row['account_id'];
                    $account_name = $row['account_name'];

                    $sql_accounts = mysqli_query($mysqli,"SELECT * FROM accounts WHERE account_id = $account_id");
                    $row = mysqli_fetch_array($sql_accounts);
                    $opening_balance = $row['opening_balance'];

                    $sql_payments = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS total_payments FROM payments WHERE account_id = $account_id");
                    $row = mysqli_fetch_array($sql_payments);
                    $total_payments = $row['total_payments'];
                    
                    $sql_expenses = mysqli_query($mysqli,"SELECT SUM(expense_amount) AS total_expenses FROM expenses WHERE account_id = $account_id");
                    $row = mysqli_fetch_array($sql_expenses);
                    $total_expenses = $row['total_expenses'];

                    $balance = $opening_balance + $total_payments - $total_expenses;

                  ?>
                    <option value="<?php echo $account_id; ?>"><div class="float-left"><?php echo $account_name; ?></div><div class="float-right"> [$<?php echo number_format($balance,2); ?>]</div></option>
                  
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
                  <option value="">- Select Vendor -</option>
                  <?php 
                  
                  $sql = mysqli_query($mysqli,"SELECT * FROM vendors"); 
                  while($row = mysqli_fetch_array($sql)){
                    $vendor_id = $row['vendor_id'];
                    $vendor_name = $row['vendor_name'];
                  ?>
                    <option value="<?php echo "$vendor_id"; ?>"><?php echo "$vendor_name"; ?></option>
                  
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
                  <option value="">- Select Category -</option>
                  <?php 
                  
                  $sql = mysqli_query($mysqli,"SELECT * FROM categories WHERE category_type = 'Expense'"); 
                  while($row = mysqli_fetch_array($sql)){
                    $category_id = $row['category_id'];
                    $category_name = $row['category_name'];
                  ?>
                    <option value="<?php echo "$category_id"; ?>"><?php echo "$category_name"; ?></option>
                  
                  <?php
                  }
                  ?>
                </select>
              </div>
            </div>
          </div>
          <div class="form-group">
            <label>Description</label>
            <textarea class="form-control" rows="4" name="description" required></textarea>
          </div>
          <div class="custom-file">
            <input type="file" class="custom-file-input" name="file">
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