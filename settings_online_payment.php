<?php include("inc_all_settings.php"); ?>

<div class="alert alert-warning">
  Work in Progress
</div>

<div class="card card-dark">
  <div class="card-header">
    <h3 class="card-title"><i class="fa fa-fw fa-credit-card"></i> Online Payment</h3>
  </div>
  <div class="card-body">
    <form action="post.php" method="post" autocomplete="off">

      <div class="custom-control custom-switch mb-2">
        <input type="checkbox" class="custom-control-input" name="config_stripe_enable" <?php if($config_stripe_enable == 1){ echo "checked"; } ?> value="1" id="customSwitch1">
        <label class="custom-control-label" for="customSwitch1">Enable Stripe</label>
      </div>

      <?php if($config_stripe_enable == 1){ ?>

      <div class="form-group">
        <label>Publishable</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
          </div>
          <input type="text" class="form-control" name="config_stripe_publishable" placeholder="Stripe Publishable API Key" value="<?php echo htmlentities($config_stripe_publishable); ?>">
        </div>
      </div>

      <div class="form-group">
        <label>Secret</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
          </div>
          <input type="text" class="form-control" name="config_stripe_secret" placeholder="Stripe Secret API Key" value="<?php echo htmlentities($config_stripe_secret); ?>">
        </div>
      </div>

      <?php } ?>
      
      <hr>
      
      <button type="submit" name="edit_online_payment_settings" class="btn btn-primary">Save</button>
        
    </form>
  </div>
</div>

<?php include("footer.php");