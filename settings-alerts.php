<?php include("header.php"); ?>

<?php include("settings-nav.php"); ?>

<div class="card mb-3">
  <div class="card-header bg-dark text-white">
    <h6 class="float-left mt-1"><i class="fa fa-fw fa-bell mr-2"></i>Alerts and Reminders</h6>
  </div>
  <div class="card-body">
    <form class="p-3" action="post.php" method="post" autocomplete="off">
      
      <div class="custom-control custom-switch mb-2">
        <input type="checkbox" class="custom-control-input" name="config_alerts_domains" <?php if($config_alerts_domains == 1){ echo "checked"; } ?> value="1" id="customSwitch">
        <label class="custom-control-label" for="customSwitch">Enable Domain Expiration Alerts</label>
      </div>

      <?php if($config_alerts_domains == 1){ ?>

      <div class="form-group">
        <label>Domain (Number of Days)</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-barcode"></i></span>
          </div>
          <input type="text" class="form-control" name="config_alert_domain_days" placeholder="Alert Before Days" value="<?php echo $config_alert_domain_days; ?>">
        </div>
      </div>

      <?php } ?>

      <div class="custom-control custom-switch mb-2">
        <input type="checkbox" class="custom-control-input" name="config_alerts_domains" <?php if($config_alerts_low_balance == 1){ echo "checked"; } ?> value="1" id="customSwitch2">
        <label class="custom-control-label" for="customSwitch2">Enable Low Balance Alerts</label>
      </div>

      <?php if($config_alerts_domains == 1){ ?>

      <div class="form-group">
        <label>Account Threshold</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-piggy-bank"></i></span>
          </div>
          <input type="text" class="form-control" name="config_account_balance_threshold" placeholder="Set an alert for dollar amount" value="<?php echo $config_account_balance_threshold; ?>">
        </div>
      </div>

      <?php } ?>

      <div class="custom-control custom-switch mb-5">
        <input type="checkbox" class="custom-control-input" name="config_alerts_domains" <?php if($config_alerts_domains == 1){ echo "checked"; } ?> value="1" id="customSwitch3">
        <label class="custom-control-label" for="customSwitch3">Enable Domain Expiration Alerts</label>
      </div>

      <hr>
      <button type="submit" name="edit_default_settings" class="btn btn-primary">Save</button>
        
    </form>
  </div>
</div>

<?php include("footer.php");