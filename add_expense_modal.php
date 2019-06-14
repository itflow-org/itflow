<div class="modal" id="addExpenseModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content bg-dark">
      <div class="modal-header text-white">
        <h5 class="modal-title"><i class="fa fa-fw fa-cart-plus mr-2"></i>New Expense</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
        <div class="modal-body bg-white">
          <div class="form-row"> 
            <div class="form-group col-md">
              <label>Date</label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                </div>
                <input type="date" class="form-control" name="date" value="<?php echo date("Y-m-d"); ?>" required>
              </div>
            </div>

            <div class="form-group col-md">
              <label>Amount</label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                </div>
                <input type="number" class="form-control" step="0.01" name="amount" placeholder="Enter amount" autofocus required>
              </div>
            </div>

          </div>

          <div class="form-row">
            <div class="form-group col-md">
              <label>Account</label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-fw fa-piggy-bank"></i></span>
                </div>
                <select class="form-control selectpicker show-tick" name="account" required>
                  <option value="">- Account -</option>
                  <?php 
                  
                  $sql = mysqli_query($mysqli,"SELECT * FROM accounts"); 
                  while($row = mysqli_fetch_array($sql)){
                    $account_id = $row['account_id'];
                    $account_name = $row['account_name'];
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

            <div class="form-group col-md">
              <label>Vendor</label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                </div>
                <select class="form-control selectpicker show-tick" data-live-search="true" name="vendor" required>
                  <option value="">- Vendor -</option>
                  <?php 
                  
                  $sql = mysqli_query($mysqli,"SELECT * FROM vendors WHERE client_id = 0 order by vendor_name ASC"); 
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
          </div>

          <div class="form-group">
            <label>Description</label>
            <textarea class="form-control" rows="4" name="description" required></textarea>
          </div>
          
          <div class="form-row">

            <div class="form-group col-md">
              <label>Category</label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-fw fa-list"></i></span>
                </div>
                <select class="form-control selectpicker show-tick" name="category" required>
                  <option value="">- Category -</option>
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

            <div class="form-group col-md">
              <label>Reference</label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-fw fa-file-alt"></i></span>
                </div>
                <input type="text" class="form-control" name="reference" placeholder="Enter a reference">
              </div>
            </div>

          </div>

          <div class="form-group">
            <label>Receipt</label>
            <input type="file" class="form-control-file" name="file">
          </div>
        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_expense" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>