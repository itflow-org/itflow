<?php include("header.php"); ?>

<?php include("settings-nav.php"); ?>

<div class="card">
  <div class="card-header bg-dark text-white">
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
          <input type="number" min="0" class="form-control" name="config_invoice_next_number" placeholder="Next Invoice Number" value="<?php echo $config_invoice_next_number; ?>" required>
        </div>
      </div>

      <div class="form-group">
        <label>Invoice Footer</label>
        <textarea class="form-control" rows="4" name="config_invoice_footer"><?php echo $config_invoice_footer; ?></textarea>
      </div>
    
      <hr>
      <button type="submit" name="edit_invoice_settings" class="btn btn-primary">Save</button>        
    
    </form>
  </div>
</div>

<?php include("footer.php");