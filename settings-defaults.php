<?php include("header.php"); ?>

<?php include("settings-nav.php"); ?>

<div class="card mb-3">
  <div class="card-header">
    <h6 class="float-left mt-1"><i class="fa fa-cog mr-2"></i>Defaults Settings</h6>
  </div>
  <div class="card-body">
    <form class="p-3" action="post.php" method="post" autocomplete="off">
      <div class="form-group">
        <label>Transfer From Account</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-exchange-alt"></i></span>
          </div>
          <select class="form-control selectpicker show-tick" name="config_default_transfer_from_account">
            <option value="">- Account -</option>
            <?php 
            
            $sql = mysqli_query($mysqli,"SELECT * FROM accounts"); 
            while($row = mysqli_fetch_array($sql)){
              $account_id = $row['account_id'];
              $account_name = $row['account_name'];

            ?>
              <option value="<?php echo $account_id; ?>"><?php echo $account_name; ?></option>
            
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
            <span class="input-group-text"><i class="fa fa-exchange-alt"></i></span>
          </div>
          <select class="form-control selectpicker show-tick" name="config_default_transfer_to_account">
            <option value="">- Account -</option>
            <?php 
            
            $sql = mysqli_query($mysqli,"SELECT * FROM accounts"); 
            while($row = mysqli_fetch_array($sql)){
              $account_id = $row['account_id'];
              $account_name = $row['account_name'];

            ?>
              <option value="<?php echo $account_id; ?>"><?php echo $account_name; ?></option>
            
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
            <span class="input-group-text"><i class="fa fa-credit-card"></i></span>
          </div>
          <select class="form-control selectpicker show-tick" name="config_default_payment_account">
            <option value="">- Account -</option>
            <?php 
            
            $sql = mysqli_query($mysqli,"SELECT * FROM accounts"); 
            while($row = mysqli_fetch_array($sql)){
              $account_id = $row['account_id'];
              $account_name = $row['account_name'];

            ?>
              <option value="<?php echo $account_id; ?>"><?php echo $account_name; ?></option>
            
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
            <span class="input-group-text"><i class="fa fa-shopping-cart"></i></span>
          </div>
          <select class="form-control selectpicker show-tick" name="config_default_expense_account">
            <option value="">- Account -</option>
            <?php 
            
            $sql = mysqli_query($mysqli,"SELECT * FROM accounts"); 
            while($row = mysqli_fetch_array($sql)){
              $account_id = $row['account_id'];
              $account_name = $row['account_name'];

            ?>
              <option value="<?php echo $account_id; ?>"><?php echo $account_name; ?></option>
            
            <?php
            }
            ?>
          </select>
        </div>
      </div>

      <div class="form-group mb-5">
        <label>Default Calendar</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-calendar"></i></span>
          </div>
          <select class="form-control selectpicker show-tick" name="config_default_calendar">
            <option value="">- Calendar -</option>
            <?php 
            
            $sql = mysqli_query($mysqli,"SELECT * FROM calendars"); 
            while($row = mysqli_fetch_array($sql)){
              $calendar_id = $row['calendar_id'];
              $calendar_name = $row['calendar_name'];

            ?>
              <option value="<?php echo $cakendar_id; ?>"><?php echo $calendar_name; ?></option>
            
            <?php
            }
            ?>
          </select>
        </div>
      </div>

      <hr>
      <button type="submit" name="add_client_contact" class="btn btn-primary">Save</button>
        
    </form>
  </div>
</div>

<?php include("footer.php");