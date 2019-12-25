<?php include("header.php"); ?>

<?php include("settings-nav.php"); ?>

<div class="card mb-3">
  <div class="card-header bg-dark text-white">
    <h6 class="float-left mt-1"><i class="fa fa-fw fa-cog mr-2"></i>Defaults Settings</h6>
  </div>
  <div class="card-body">
    <form class="p-3" action="post.php" method="post" autocomplete="off">
      <div class="form-group">
        <label>Transfer From Account</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-exchange-alt"></i></span>
          </div>
          <select class="form-control select2" name="config_default_transfer_from_account">
            <option value="0">- None -</option>
            <?php 
            
            $sql = mysqli_query($mysqli,"SELECT * FROM accounts WHERE company_id = $session_company_id"); 
            while($row = mysqli_fetch_array($sql)){
              $account_id = $row['account_id'];
              $account_name = $row['account_name'];

            ?>
              <option <?php if($config_default_transfer_from_account == $account_id){ echo "selected"; } ?> value="<?php echo $account_id; ?>"><?php echo $account_name; ?></option>
            
            <?php
            }
            ?>
          </select>
        </div>
      </div>
      
      <div class="form-group">
        <label>Transfer To Account</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-exchange-alt"></i></span>
          </div>
          <select class="form-control select2" name="config_default_transfer_to_account">
            <option value="0">- None -</option>
            <?php 
            
            $sql = mysqli_query($mysqli,"SELECT * FROM accounts WHERE company_id = $session_company_id"); 
            while($row = mysqli_fetch_array($sql)){
              $account_id = $row['account_id'];
              $account_name = $row['account_name'];

            ?>
              <option <?php if($config_default_transfer_to_account == $account_id){ echo "selected"; } ?> value="<?php echo $account_id; ?>"><?php echo $account_name; ?></option>
            
            <?php
            }
            ?>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label>Payment Account</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-credit-card"></i></span>
          </div>
          <select class="form-control select2" name="config_default_payment_account">
            <option value="0">- None -</option>
            <?php 
            
            $sql = mysqli_query($mysqli,"SELECT * FROM accounts WHERE company_id = $session_company_id"); 
            while($row = mysqli_fetch_array($sql)){
              $account_id = $row['account_id'];
              $account_name = $row['account_name'];

            ?>
              <option <?php if($config_default_payment_account == $account_id){ echo "selected"; } ?> value="<?php echo $account_id; ?>"><?php echo $account_name; ?></option>
            
            <?php
            }
            ?>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label>Expense Account</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-shopping-cart"></i></span>
          </div>
          <select class="form-control select2" name="config_default_expense_account">
            <option value="0">- None -</option>
            <?php 
            
            $sql = mysqli_query($mysqli,"SELECT * FROM accounts WHERE company_id = $session_company_id"); 
            while($row = mysqli_fetch_array($sql)){
              $account_id = $row['account_id'];
              $account_name = $row['account_name'];

            ?>
              <option <?php if($config_default_expense_account == $account_id){ echo "selected"; } ?> value="<?php echo $account_id; ?>"><?php echo $account_name; ?></option>
            
            <?php
            }
            ?>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label>Payment Method</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-credit-card"></i></span>
          </div>
          <select class="form-control select2" name="config_default_payment_method">
            <option value="">- None -</option>
            <?php 
            
            $sql = mysqli_query($mysqli,"SELECT * FROM categories WHERE category_type = 'Payment Method' AND company_id = $session_company_id"); 
            while($row = mysqli_fetch_array($sql)){
              $payment_method = $row['category_name'];

            ?>
              <option <?php if($config_default_payment_method == $payment_method){ echo "selected"; } ?>><?php echo $payment_method; ?></option>
            
            <?php
            }
            ?>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label>Expense Payment Method</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-credit-card"></i></span>
          </div>
          <select class="form-control select2" name="config_default_expense_payment_method">
            <option value="">- None -</option>
            <?php 
            
            $sql = mysqli_query($mysqli,"SELECT * FROM categories WHERE category_type = 'Payment Method' AND company_id = $session_company_id"); 
            while($row = mysqli_fetch_array($sql)){
              $payment_method = $row['category_name'];

            ?>
              <option <?php if($config_default_expense_payment_method == $payment_method){ echo "selected"; } ?>><?php echo $payment_method; ?></option>
            
            <?php
            }
            ?>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label>Calendar</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
          </div>
          <select class="form-control select2" name="config_default_calendar">
            <option value="0">- None -</option>
            <?php 
            
            $sql = mysqli_query($mysqli,"SELECT * FROM calendars WHERE company_id = $session_company_id"); 
            while($row = mysqli_fetch_array($sql)){
              $calendar_id = $row['calendar_id'];
              $calendar_name = $row['calendar_name'];

            ?>
              <option <?php if($config_default_calendar == $calendar_id){ echo "selected"; } ?> value="<?php echo $calendar_id; ?>"><?php echo $calendar_name; ?></option>
            
            <?php
            }
            ?>
          </select>
        </div>
      </div>

      <div class="form-group mb-5">
        <label>Net Terms</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
          </div>
          <select class="form-control select2" name="config_default_net_terms">
            <?php foreach($net_terms_array as $net_term_value => $net_term_name) { ?>
            <option <?php if($config_default_net_terms == $net_term_value){ echo "selected"; } ?> value="<?php echo $net_term_value; ?>"><?php echo $net_term_name; ?></option>
            <?php } ?>
          </select>
        </div>
      </div>

      <hr>
      <button type="submit" name="edit_default_settings" class="btn btn-primary">Save</button>
        
    </form>
  </div>
</div>

<?php include("footer.php");