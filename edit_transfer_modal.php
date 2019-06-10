<div class="modal" id="editTransferModal<?php echo $transfer_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title text-white"><i class="fa fa-fw fa-exchange-alt mr-2"></i>Modify Transfer</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <div class="modal-body bg-white">
          <input type="hidden" name="transfer_id" value="<?php echo $transfer_id; ?>">
          <input type="hidden" name="expense_id" value="<?php echo $expense_id; ?>">
          <input type="hidden" name="payment_id" value="<?php echo $payment_id; ?>">
          <div class="form-row">
            <div class="form-group col-sm">
              <label>Date</label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                </div>
                <input type="date" class="form-control" name="date" value="<?php echo $transfer_date; ?>" required>
              </div>
            </div>
            <div class="form-group col-sm">
              <label>Amount</label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                </div>
                <input type="number" class="form-control" step="0.01" min="0" name="amount" placeholder="Amount to transfer" value="<?php echo $transfer_amount; ?>" required>
              </div>
            </div>
          </div>
          <div class="form-group">
            <label>Transfer</label>
            <div class="input-group"> 
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-piggy-bank"></i></span>
              </div> 
              <select class="form-control selectpicker show-tick" name="account_from" required>
                <?php 
                
                $sql2 = mysqli_query($mysqli,"SELECT * FROM accounts"); 
                while($row = mysqli_fetch_array($sql2)){
                  $account_id2 = $row['account_id'];
                  $account_name = $row['account_name'];
                  $opening_balance = $row['opening_balance'];

                  $sql_payments = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS total_payments FROM payments WHERE account_id = $account_id2");
                  $row = mysqli_fetch_array($sql_payments);
                  $total_payments = $row['total_payments'];
                  
                  $sql_expenses = mysqli_query($mysqli,"SELECT SUM(expense_amount) AS total_expenses FROM expenses WHERE account_id = $account_id2");
                  $row = mysqli_fetch_array($sql_expenses);
                  $total_expenses = $row['total_expenses'];

                  $balance = $opening_balance + $total_payments - $total_expenses;
                
                ?>
                <option <?php if($transfer_account_from == $account_id2){ ?> selected <?php } ?> value="<?php echo $account_id2; ?>"><?php echo $account_name; ?> [$<?php echo number_format($balance,2); ?>]</option>
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
              <select class="form-control selectpicker show-tick" name="account_to" required>
                <?php 
                
                $sql2 = mysqli_query($mysqli,"SELECT * FROM accounts"); 
                while($row = mysqli_fetch_array($sql2)){
                  $account_id2 = $row['account_id'];
                  $account_name = $row['account_name'];
                  $opening_balance = $row['opening_balance'];

                  $sql_payments = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS total_payments FROM payments WHERE account_id = $account_id2");
                  $row = mysqli_fetch_array($sql_payments);
                  $total_payments = $row['total_payments'];
                  
                  $sql_expenses = mysqli_query($mysqli,"SELECT SUM(expense_amount) AS total_expenses FROM expenses WHERE account_id = $account_id2");
                  $row = mysqli_fetch_array($sql_expenses);
                  $total_expenses = $row['total_expenses'];

                  $balance = $opening_balance + $total_payments - $total_expenses;

                ?>
                <option <?php if($transfer_account_to == $account_id2){ ?> selected <?php } ?> value="<?php echo $account_id2; ?>"><?php echo $account_name; ?> [$<?php echo number_format($balance,2); ?>]</option>
                <?php
                }
                
                ?>
              </select>
            </div>
          </div>

        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_transfer" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>