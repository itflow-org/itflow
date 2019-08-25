<?php include("header.php"); ?>

<?php include("settings-nav.php"); ?>

<div class="card mb-3">
  <div class="card-header bg-dark text-white">
    <h6 class="float-left mt-1"><i class="fa fa-fw fa-bell mr-2"></i>Alerts</h6>
  </div>
  <div class="card-body">
    <form class="p-3" action="post.php" method="post" autocomplete="off">

      <div class="custom-control custom-switch mb-2">
        <input type="checkbox" class="custom-control-input" name="config_enable_cron" <?php if($config_enable_cron == 1){ echo "checked"; } ?> value="1" id="customSwitch1">
        <label class="custom-control-label" for="customSwitch1">Enable Cron <small>(cron.php must also be added to cron and run nightly at 11:00PM for alerts to work)</small></label>
      </div>

      <div class="custom-control custom-switch mb-2">
        <input type="checkbox" class="custom-control-input" name="config_alerts_domains" <?php if($config_alerts_low_balance == 1){ echo "checked"; } ?> value="1" id="customSwitch2">
        <label class="custom-control-label" for="customSwitch2">Enable Low Balance Alerts</label>
      </div>

      <?php if($config_alert_low_balance == 1){ ?>

      <div class="form-group">
        <label>Threshold</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-piggy-bank"></i></span>
          </div>
          <input type="text" class="form-control" name="config_account_balance_threshold" placeholder="Set an alert for dollar amount" value="<?php echo $config_account_balance_threshold; ?>">
        </div>
      </div>

      <?php } ?>

      <div class="custom-control custom-switch mb-2">
        <input type="checkbox" class="custom-control-input" name="config_alerts_domains" <?php if($config_alert_domain_expire == 1){ echo "checked"; } ?> value="1" id="customSwitch3">
        <label class="custom-control-label" for="customSwitch3">Enable Domain Expiration Alerts</label>
      </div>

      <div class="custom-control custom-switch mb-2">
        <input type="checkbox" class="custom-control-input" name="config_send_invoice_reminders" <?php if($config_send_invoice_reminders == 1){ echo "checked"; } ?> value="1" id="customSwitch1">
        <label class="custom-control-label" for="customSwitch1">Send Invoice Reminders</label>
      </div>

      <?php if($config_send_invoice_reminders == 1){ ?>

      <div class="form-group">
        <label>Overdue Reminders</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-barcode"></i></span>
          </div>
          <input type="text" class="form-control" name="config_invoice_overdue_reminders" placeholder="Send After Due Days" value="<?php echo $config_invoice_overdue_reminders; ?>">
        </div>
      </div>

      <?php } ?>

      <hr class="mt-5">
      <button type="submit" name="edit_alert_settings" class="btn btn-primary">Save</button>
        
    </form>
  </div>
</div>

<?php include("footer.php");