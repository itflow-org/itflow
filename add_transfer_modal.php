<div class="modal" id="addTransferModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header text-white">
        <h5 class="modal-title"><i class="fa fa-fw fa-exchange-alt mr-2"></i>Transfer Money</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <div class="modal-body bg-white">
          <div class="form-row">
            <div class="form-group col">
              <label>Date</label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                </div>
                <input type="date" class="form-control" name="date" value="<?php echo date("Y-m-d"); ?>" required>
              </div>
            </div>
            <div class="form-group col">
              <label>Amount</label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                </div>
                <input type="number" class="form-control" step="0.01" min="0" name="amount" placeholder="Amount to transfer" required autofocus>
              </div>
            </div>
          </div>
       
          <div class="form-group">
            <label>Transfer</label>
            <div class="input-group"> 
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-piggy-bank"></i></span>
              </div> 
              <select class="form-control" name="account_from" required>
                <option value="">- Account From -</option>
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
                  <option value="<?php echo $account_id; ?>"><?php echo $account_name; ?> [$<?php echo number_format($balance,2); ?>]</option>
                
                <?php
                }
                ?>
              </select>
            </div>
          </div>
          <div class="form-group">
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-arrow-right"></i></span>
              </div>
              <select class="form-control" name="account_to" required>
                <option value="">- Account To -</option>
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
                  <option value="<?php echo $account_id; ?>"><?php echo $account_name; ?> [$<?php echo number_format($balance,2); ?>]</option>
                
                <?php
                }
                ?>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_transfer" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>