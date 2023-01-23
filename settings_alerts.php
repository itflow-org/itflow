<?php require_once("inc_all_settings.php"); ?>

<div class="card card-dark">
  <div class="card-header py-3">
    <h3 class="card-title"><i class="fa fa-fw fa-bell"></i> Alerts</h3>
  </div>
  <div class="card-body">
    <form action="post.php" method="post" autocomplete="off">

      <div class="custom-control custom-switch mb-2">
        <input type="checkbox" class="custom-control-input" name="config_enable_cron" <?php if ($config_enable_cron == 1) { echo "checked"; } ?> value="1" id="customSwitch1">
        <label class="custom-control-label" for="customSwitch1">Enable Cron <small>(cron.php must also be added to cron and run daily at 1:00AM for alerts to work)</small></label>
      </div>

      <div class="custom-control custom-switch mb-2">
        <input type="checkbox" class="custom-control-input" name="config_enable_alert_domain_expire" <?php if ($config_enable_alert_domain_expire == 1) { echo "checked"; } ?> value="1" id="customSwitch3">
        <label class="custom-control-label" for="customSwitch3">Enable Domain Expiration Alerts</label>
      </div>

      <div class="custom-control custom-switch mb-2">
        <input type="checkbox" class="custom-control-input" name="config_send_invoice_reminders" <?php if ($config_send_invoice_reminders == 1) { echo "checked"; } ?> value="1" id="customSwitch4">
        <label class="custom-control-label" for="customSwitch4">Send Invoice Reminders</label>
      </div>

      <?php if ($config_send_invoice_reminders == 1) { ?>

      <div class="form-group">
        <label>Overdue Reminders</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-bell"></i></span>
          </div>
          <input type="text" class="form-control" name="config_invoice_overdue_reminders" placeholder="Send After Due Days" value="<?php echo htmlentities($config_invoice_overdue_reminders); ?>">
        </div>
      </div>

      <?php } ?>

      <hr>

      <button type="submit" name="edit_alert_settings" class="btn btn-primary text-bold"><i class="fa fa-check"></i> Save</button>
        
    </form>
  </div>
</div>

<?php include("footer.php");
