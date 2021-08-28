<div class="modal" id="editExpenseModal<?php echo $expense_id; ?>" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title text-white"><i class="fa fa-fw fa-edit mr-2"></i>Edit Expense</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
        <div class="modal-body bg-white">
          <input type="hidden" name="expense_id" value="<?php echo $expense_id; ?>">
          <input type="hidden" name="expense_receipt" value="<?php echo $expense_receipt; ?>">
          
          <div class="form-row"> 
            
            <div class="form-group col-md">
              <label>Date <strong class="text-danger">*</strong></label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                </div>
                <input type="date" class="form-control" name="date" value="<?php echo $expense_date; ?>" required>
              </div>
            </div>
            
            <div class="form-group col-md">
              <label>Amount <strong class="text-danger">*</strong></label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                </div>
                <input type="number" class="form-control" step="0.01" name="amount" value="<?php echo $expense_amount; ?>" required>
              </div>
            </div>

          </div>
          
          <div class="form-row">
            <div class="form-group col-md">
              <label>Account <strong class="text-danger">*</strong></label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-fw fa-piggy-bank"></i></span>
                </div>
                <select class="form-control select2" name="account" required>
                  <?php 
                  
                  $sql_accounts = mysqli_query($mysqli,"SELECT * FROM accounts WHERE (account_archived_at > '$expense_created_at' OR account_archived_at IS NULL) AND company_id = $session_company_id ORDER BY account_name ASC"); 
                  while($row = mysqli_fetch_array($sql_accounts)){
                    $account_id_select = $row['account_id'];
                    $account_name_select = $row['account_name'];
                    $opening_balance = $row['opening_balance'];
                    
                    $sql_payments = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS total_payments FROM payments WHERE payment_account_id = $account_id_select");
                    $row = mysqli_fetch_array($sql_payments);
                    $total_payments = $row['total_payments'];
                    
                    $sql_revenues = mysqli_query($mysqli,"SELECT SUM(revenue_amount) AS total_revenues FROM revenues WHERE revenue_account_id = $account_id_select");
                    $row = mysqli_fetch_array($sql_revenues);
                    $total_revenues = $row['total_revenues'];

                    $sql_expenses = mysqli_query($mysqli,"SELECT SUM(expense_amount) AS total_expenses FROM expenses WHERE expense_account_id = $account_id_select");
                    $row = mysqli_fetch_array($sql_expenses);
                    $total_expenses = $row['total_expenses'];

                    $balance = $opening_balance + $total_payments + $total_revenues - $total_expenses;

                  ?>
                  <option <?php if($expense_account_id == $account_id_select){ ?> selected <?php } ?> value="<?php echo $account_id_select; ?>"><?php echo $account_name_select; ?> [$<?php echo number_format($balance,2); ?>]</option>
                  <?php
                  }
                  
                  ?>
                </select>
              </div>
            </div>

            <div class="form-group col-md">
              <label>Vendor <strong class="text-danger">*</strong></label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                </div>
                <select class="form-control select2" name="vendor" required>
                  <?php 
                  
                  $sql_select = mysqli_query($mysqli,"SELECT * FROM vendors WHERE vendor_client_id = 0 AND (vendor_archived_at > '$expense_created_at' OR vendor_archived_at IS NULL) AND company_id = $session_company_id ORDER BY vendor_name ASC"); 
                  while($row = mysqli_fetch_array($sql_select)){
                    $vendor_id_select = $row['vendor_id'];
                    $vendor_name_select = $row['vendor_name'];
                  ?>
                  <option <?php if($expense_vendor_id == $vendor_id_select){ ?> selected <?php } ?> value="<?php echo $vendor_id_select; ?>"><?php echo $vendor_name_select; ?></option>
                  <?php
                  }
                  
                  ?>
                </select>
                <div class="input-group-append">
                  <button type="button" class="btn btn-secondary" data-toggle="modal" data-target="#addQuickVendorModal"><i class="fas fa-fw fa-plus"></i></button>
                </div>
              </div>
            </div>
            
          </div>
            
          <div class="form-group">
            <label>Description <strong class="text-danger">*</strong></label>
            <textarea class="form-control" rows="4" name="description" required><?php echo $expense_description; ?></textarea>
          </div>
          
          <div class="form-row">

            <div class="form-group col-md">
              <label>Category <strong class="text-danger">*</strong></label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-fw fa-list"></i></span>
                </div>
                <select class="form-control select2" name="category" required>
                  <?php 
                  
                  $sql_select = mysqli_query($mysqli,"SELECT * FROM categories WHERE category_type = 'Expense' AND (category_archived_at > '$expense_created_at' OR category_archived_at IS NULL) AND company_id = $session_company_id ORDER BY category_name ASC"); 
                  while($row = mysqli_fetch_array($sql_select)){
                    $category_id_select = $row['category_id'];
                    $category_name_select = $row['category_name'];
                  ?>
                  <option <?php if($expense_category_id == $category_id_select){ ?> selected <?php } ?> value="<?php echo $category_id_select; ?>"><?php echo $category_name_select; ?></option>
                  <?php
                  }
                  
                  ?>
                </select>
                <div class="input-group-append">
                  <button type="button" class="btn btn-secondary" data-toggle="modal" data-target="#addQuickCategoryExpenseModal"><i class="fas fa-fw fa-plus"></i></button>
                </div>
              </div>
            </div>

            <div class="form-group col-md">
              <label>Reference</label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-fw fa-file-alt"></i></span>
                </div>
                <input type="text" class="form-control" name="reference" placeholder="Enter a reference" value="<?php echo $expense_reference; ?>">
              </div>
            </div>
          
          </div>

          <div class="form-group">
            <label>Receipt</label>
            <input type="file" class="form-control-file" name="file">
          </div>

          <?php if(!empty($expense_receipt)){ ?>
            <hr>
            <a class="text-secondary" href="<?php echo $expense_receipt; ?>"><i class="fa fa-fw fa-2x fa-file-pdf text-secondary"></i> <?php echo basename($expense_receipt); ?></a>
          <?php } ?>
        
        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_expense" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>