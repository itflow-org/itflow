<div class="modal fade" id="editTransferModal<?php echo $transfer_id; ?>" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-exchange-alt"></i> Modify Transfer</h5>
        <button type="button" class="close" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <div class="modal-body">
          <input type="hidden" name="transfer_id" value="<?php echo $transfer_id; ?>">
          <input type="hidden" name="expense_id" value="<?php echo $expense_id; ?>">
          <input type="hidden" name="payment_id" value="<?php echo $payment_id; ?>">
          <div class="form-row">
            <div class="form-group col-sm">
              <label>Date</label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-calendar"></i></span>
                </div>
                <input type="date" class="form-control" name="date" value="<?php echo $transfer_date; ?>" required>
              </div>
            </div>
            <div class="form-group col-sm">
              <label>Amount</label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-hand-holding-usd"></i></span>
                </div>
                <input type="number" class="form-control" step="0.01" min="0" name="amount" placeholder="Amount to transfer" value="<?php echo $transfer_amount; ?>" required>
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
                  <?php 
                  
                  $sql2 = mysqli_query($mysqli,"SELECT * FROM accounts"); 
                  while($row = mysqli_fetch_array($sql2)){
                    $account_id2 = $row['account_id'];
                    $account_name = $row['account_name'];
                  ?>
                  <option <?php if($transfer_account_from == $account_id2){ ?> selected <?php } ?> value="<?php echo $account_id2; ?>"><?php echo $account_name; ?></option>
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
                  <?php 
                  
                  $sql2 = mysqli_query($mysqli,"SELECT * FROM accounts"); 
                  while($row = mysqli_fetch_array($sql2)){
                    $account_id2 = $row['account_id'];
                    $account_name = $row['account_name'];
                  ?>
                  <option <?php if($transfer_account_to == $account_id2){ ?> selected <?php } ?> value="<?php echo $account_id2; ?>"><?php echo $account_name; ?></option>
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
          <button type="submit" name="edit_transfer" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>