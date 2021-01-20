<div class="modal" id="addPaymentModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header text-white">
        <h5 class="modal-title"><i class="fa fa-fw fa-credit-card mr-2"></i><?php echo $invoice_number; ?>: Add Payment</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="invoice_id" value="<?php echo $invoice_id; ?>">
        <input type="hidden" name="balance" value="<?php echo $balance; ?>">
        <div class="modal-body bg-white">
          
          <div class="form-row">
            <div class="col">

              <div class="form-group">
                <label>Date <strong class="text-danger">*</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                  </div>
                  <input type="date" class="form-control" name="date" value="<?php echo date("Y-m-d"); ?>" required>
                </div>
              </div>

            </div>

            <div class="col">
            
              <div class="form-group">
                <label>Amount <strong class="text-danger">*</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                  </div>
                  <input type="number" class="form-control" step="0.01" min="0" name="amount" value="<?php echo $balance; ?>" required>
                </div>
              </div>

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
                
                $sql = mysqli_query($mysqli,"SELECT * FROM accounts WHERE company_id = $session_company_id ORDER BY account_name ASC"); 
                while($row = mysqli_fetch_array($sql)){
                  $account_id = $row['account_id'];
                  $account_name = $row['account_name'];
                  $opening_balance = $row['opening_balance'];
                  
                  $sql_payments = mysqli_query($mysqli,"SELECT SUM(payment_amount) AS total_payments FROM payments WHERE account_id = $account_id");
                  $row = mysqli_fetch_array($sql_payments);
                  $total_payments = $row['total_payments'];
                  
                  $sql_revenues = mysqli_query($mysqli,"SELECT SUM(revenue_amount) AS total_revenues FROM revenues WHERE account_id = $account_id");
                  $row = mysqli_fetch_array($sql_revenues);
                  $total_revenues = $row['total_revenues'];

                  $sql_expenses = mysqli_query($mysqli,"SELECT SUM(expense_amount) AS total_expenses FROM expenses WHERE account_id = $account_id");
                  $row = mysqli_fetch_array($sql_expenses);
                  $total_expenses = $row['total_expenses'];

                  $balance = $opening_balance + $total_payments + $total_revenues - $total_expenses;
                  
                ?>
                  <option <?php if($config_default_payment_account == $account_id){ echo "selected"; } ?> value="<?php echo $account_id; ?>"><?php echo $account_name; ?> [$<?php echo number_format($balance,2); ?>]</option>
                
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
                
                $sql = mysqli_query($mysqli,"SELECT * FROM categories WHERE category_type = 'Payment Method' AND company_id = $session_company_id ORDER BY category_name ASC"); 
                while($row = mysqli_fetch_array($sql)){
                  $category_name = $row['category_name'];
                ?>
                  <option <?php if($config_default_payment_method == $category_name){ echo "selected"; } ?>><?php echo $category_name; ?></option>
                
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
              <input type="text" class="form-control" name="reference" placeholder="Enter a reference">
            </div>
          </div>
          
          <?php if(!empty($config_smtp_host)){ ?>

          <div class="form-group">
            <label>Email Receipt</label>
            <div class="custom-control custom-checkbox">
              <input type="checkbox" class="custom-control-input" id="customControlAutosizing" name="email_receipt" value="1" checked>
              <label class="custom-control-label" for="customControlAutosizing"><?php echo $client_email; ?></label>
            </div>
          </div>

          <?php } ?>

        </div>
        
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_payment" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>