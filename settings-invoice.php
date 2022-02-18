<?php include("header.php"); ?>

<?php include("settings-nav.php"); ?>

<div class="card card-dark">
  <div class="card-header">
    <h3 class="card-title"><i class="fa fa-fw fa-file"></i> Invoice Settings</h3>
  </div>
  <div class="card-body">
    <form action="post.php" method="post" autocomplete="off">
      
      <legend>Invoice</legend>

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
          <input type="number" min="0" class="form-control" name="config_invoice_next_number" placeholder="Next Invoice Number" value="<?php echo $config_invoice_next_number; ?>" required>
        </div>
      </div>

      <div class="form-group">
        <label>Invoice Footer</label>
        <textarea class="form-control" rows="4" name="config_invoice_footer"><?php echo $config_invoice_footer; ?></textarea>
      </div>

      <div class="form-group">
        <label>From Email</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
          </div>
          <input type="email" class="form-control" name="config_invoice_from_email" placeholder="From Email" value="<?php echo $config_invoice_from_email; ?>">
        </div>
      </div>

      <div class="form-group">
        <label>From Name</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
          </div>
          <input type="text" class="form-control" name="config_invoice_from_name" placeholder="From Name" value="<?php echo $config_invoice_from_name; ?>">
        </div>
      </div>

      <legend>Recurring Invoice</legend>

      <div class="form-group">
        <label>Recurring Prefix</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-barcode"></i></span>
          </div>
          <input type="text" class="form-control" name="config_recurring_prefix" placeholder="Recurring Prefix" value="<?php echo $config_recurring_prefix; ?>" required>
        </div>
      </div>

      <div class="form-group">
        <label>Next Number</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-barcode"></i></span>
          </div>
          <input type="number" min="0" class="form-control" name="config_recurring_next_number" placeholder="Next Recurring Number" value="<?php echo $config_recurring_next_number; ?>" required>
        </div>
      </div>

      
    
      <hr>

      <button type="submit" name="edit_invoice_settings" class="btn btn-primary">Save</button>        
    
    </form>
  </div>
</div>

<?php include("footer.php");