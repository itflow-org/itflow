<div class="modal" id="addPaymentModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-money-bill-alt"></i> New Payment</h5>
        <button type="button" class="close" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="invoice_id" value="<?php echo $invoice_id; ?>">
        <input type="hidden" name="balance" value="<?php echo $balance; ?>">
        <div class="modal-body">
          <div class="form-row">
            <div class="form-group col">
              <label>Date</label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-calendar"></i></span>
                </div>
                <input type="date" class="form-control" name="date" value="<?php echo date("Y-m-d"); ?>" required>
              </div>
            </div>
            <div class="form-group col">
              <label>Amount</label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-dollar-sign"></i></span>
                </div>
                <input type="number" class="form-control" step="0.01" min="0.00" name="amount" value="<?php echo $balance; ?>" required>
              </div>
            </div>
          </div>
          <div class="form-row">  
            <div class="form-group col">
              <label>Account</label>
              <div class="input-group"> 
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-university"></i></span>
                </div> 
                <select class="form-control" name="account" required>
                  <option value="">- Account -</option>
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
                    <option value="<?php echo $account_id; ?>"><?php echo $account_name; ?> [$<?php echo number_format($balance,2); ?>]</option>
                  
                  <?php
                  }
                  ?>
                </select>
              </div>
            </div>
            <div class="form-group col">
              <label>Payment Method</label>
              <div class="input-group"> 
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-money-check-alt"></i></span>
                </div> 
                <select class="form-control" name="payment_method" required>
                  <option value="">- Payment Method -</option>
                  <?php 
                  
                  $sql = mysqli_query($mysqli,"SELECT * FROM categories WHERE category_type = 'Payment Method'"); 
                  while($row = mysqli_fetch_array($sql)){
                    $category_name = $row['category_name'];
                  ?>
                    <option><?php echo "$category_name"; ?></option>
                  
                  <?php
                  }
                  ?>
                </select>
              </div>
            </div>
          </div>
          <div class="custom-control custom-checkbox">
            <input type="checkbox" class="custom-control-input" id="customControlAutosizing" name="email_receipt" value="1" >
            <label class="custom-control-label" for="customControlAutosizing">Email Reciept</label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_payment" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>