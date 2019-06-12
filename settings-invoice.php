<?php include("header.php"); ?>

<?php include("settings-nav.php"); ?>

<div class="card mb-3">
  <div class="card-header">
    <h6 class="float-left mt-1"><i class="fa fa-fw fa-file mr-2"></i>Invoice Settings</h6>
  </div>
  <div class="card-body">
    <form class="p-3" action="post.php" method="post" autocomplete="off">
      
      <div class="form-group">
        <label>Invoice Prefix</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-barcode"></i></span>
          </div>
          <input type="text" class="form-control" name="config_invoice_prefix" placeholder="Invoice Prefix" value="<?php echo $config_invoice_prefix; ?>" required>
        </div>
      </div>

      <div class="form-group">
        <label>Next Number</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-barcode"></i></span>
          </div>
          <input type="text" class="form-control" name="config_next_invoice_number" placeholder="Next Invoice Number" value="<?php echo $config_next_invoice_number; ?>" required>
        </div>
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
      
      <div class="form-group">
        <label>Email From</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
          </div>
          <input type="email" class="form-control" name="config_mail_from_email" placeholder="Email Address" value="<?php echo $config_mail_from_email; ?>">
        </div>
      </div>

      <div class="form-group">
        <label>Email Name From</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
          </div>
          <input type="text" class="form-control" name="config_mail_from_name" placeholder="Name" value="<?php echo $config_mail_from_name; ?>">
        </div>
      </div>

      <?php } ?>

      <div class="form-group">
        <label>Invoice Footer</label>
        <textarea class="form-control" rows="4" name="config_invoice_footer"><?php echo $config_invoice_footer; ?></textarea>
      </div>

      <div class="form-group mb-5">
        <label>Quote Footer</label>
        <textarea class="form-control" rows="4" name="config_quote_footer"><?php echo $config_quote_footer; ?></textarea>
      </div>
      
      <hr>
      <button type="submit" name="edit_invoice_settings" class="btn btn-primary">Save</button>        
    
    </form>
  </div>
</div>

<?php include("footer.php");