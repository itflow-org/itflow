<div class="modal" id="editRevenueModal<?php echo $revenue_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header text-white">
        <h5 class="modal-title"><i class="fa fa-fw fa-money-bill-alt mr-2"></i>Edit Revenue</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="revenue_id" value="<?php echo $revenue_id; ?>">
        <div class="modal-body bg-white">
          
          <div class="form-group">
            <label>Date <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
              </div>
              <input type="date" class="form-control" name="date" value="<?php echo $revenue_date; ?>" required>
            </div>
          </div>
          
          <div class="form-group">
            <label>Amount <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
              </div>
              <input type="number" class="form-control" step="0.01" min="0.00" name="amount" value="<?php echo $revenue_amount; ?>" required>
            </div>
          </div>

          <div class="form-group">
            <label>Account <strong class="text-danger">*</strong></label>
            <div class="input-group"> 
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-piggy-bank"></i></span>
              </div> 
              <select class="form-control select2" name="account" required>
                <option value="">- Account -</option>
                <?php 
                
                $sql_accounts = mysqli_query($mysqli,"SELECT * FROM accounts WHERE company_id = $session_company_id ORDER BY account_name ASC"); 
                while($row = mysqli_fetch_array($sql_accounts)){
                  $account_id_select = $row['account_id'];
                  $account_name_select = $row['account_name'];
                  $opening_balance = $row['opening_balance'];
                  
                  $sql_payments = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS total_payments FROM payments WHERE account_id = $account_id_select");
                  $row = mysqli_fetch_array($sql_payments);
                  $total_payments = $row['total_payments'];
                  
                  $sql_revenues = mysqli_query($mysqli,"SELECT SUM(revenue_amount) AS total_revenues FROM revenues WHERE account_id = $account_id_select");
                  $row = mysqli_fetch_array($sql_revenues);
                  $total_revenues = $row['total_revenues'];

                  $sql_expenses = mysqli_query($mysqli,"SELECT SUM(expense_amount) AS total_expenses FROM expenses WHERE account_id = $account_id_select");
                  $row = mysqli_fetch_array($sql_expenses);
                  $total_expenses = $row['total_expenses'];

                  $balance = $opening_balance + $total_payments + $total_revenues - $total_expenses;
                  
                ?>
                  <option <?php if($account_id == $account_id_select){ echo "selected"; } ?> value="<?php echo $account_id_select; ?>"><?php echo $account_name_select; ?> [$<?php echo number_format($balance,2); ?>]</option>
                
                <?php
                }
                ?>
              </select>
            </div>
          </div>
          
          <div class="form-group">
            <label>Payment Method <strong class="text-danger">*</strong></label>
            <div class="input-group"> 
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-money-check-alt"></i></span>
              </div> 
              <select class="form-control select2" name="payment_method" required>
                <option value="">- Method of Payment -</option>
                <?php 
                
                $sql_categories = mysqli_query($mysqli,"SELECT * FROM categories WHERE category_type = 'Payment Method' AND company_id = $session_company_id ORDER BY category_name ASC"); 
                while($row = mysqli_fetch_array($sql_categories)){
                  $category_name_select = $row['category_name'];
                ?>
                  <option <?php if($revenue_payment_method == $category_name_select){ echo "selected"; } ?>><?php echo "$category_name_select"; ?></option>
                
                <?php
                }
                ?>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label>Reference</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-file-alt"></i></span>
              </div>
              <input type="text" class="form-control" name="reference" placeholder="Enter a reference" value="<?php echo $revenue_reference; ?>">
            </div>
          </div>

          <div class="form-group">
            <label>Description</label>
            <textarea class="form-control" rows="4" name="description"><?php echo $revenue_description; ?></textarea>
          </div>
  
        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_revenue" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>