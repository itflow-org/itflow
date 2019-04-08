<div class="modal" id="addTransferModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-exchange-alt"></i> New Transfer</h5>
        <button type="button" class="close" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <div class="modal-body">
          <div class="form-row">
            <div class="form-group col-sm">
              <label>Date</label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-calendar"></i></span>
                </div>
                <input type="date" class="form-control" name="date" value="<?php echo date("Y-m-d"); ?>" required>
              </div>
            </div>
            <div class="form-group col-sm">
              <label>Amount</label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-hand-holding-usd"></i></span>
                </div>
                <input type="number" class="form-control" step="0.01" min="0" name="amount" placeholder="Amount to transfer" required autofocus>
              </div>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group col-sm">
              <label>Account From</label>
              <div class="input-group"> 
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-university"></i></span>
                </div> 
                <select class="form-control" name="account_from" required>
                  <option value="">- Transfer From -</option>
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
            <div class="form-group col-sm">
               <label>Account To</label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-arrow-right"></i></span>
                </div>
                <select class="form-control" name="account_to" required>
                  <option value="">- Transfer To -</option>
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
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_transfer" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>