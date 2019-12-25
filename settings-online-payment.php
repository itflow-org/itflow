<?php include("header.php"); ?>

<?php include("settings-nav.php"); ?>

<div class="card">
  <div class="card-header bg-dark text-white">
    <h6 class="float-left mt-1"><i class="fa fa-credit-card mr-2"></i>Online Payment</h6>
  </div>
  <div class="card-body">
    <form class="p-3" action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">

      <div class="custom-control custom-switch mb-2">
        <input type="checkbox" class="custom-control-input" name="config_stripe_enable" <?php if($config_stripe_enable == 1){ echo "checked"; } ?> value="1" id="customSwitch1">
        <label class="custom-control-label" for="customSwitch1">Enable Stripe</label>
      </div>

      <div class="form-group">
        <label>Publishable</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
          </div>
          <input type="text" class="form-control" name="config_stripe_publishable" placeholder="Stripe Publishable API Key" value="<?php echo $config_stripe_publishable; ?>">
        </div>
      </div>

      <div class="form-group">
        <label>Secret</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
          </div>
          <input type="text" class="form-control" name="config_stripe_secret" placeholder="Stripe Secret API Key" value="<?php echo $config_stripe_secret; ?>">
        </div>
      </div>
      
      <hr>
      
      <button type="submit" name="edit_online_payment_settings" class="btn btn-primary">Save</button>
        
    </form>
  </div>
</div>

<?php include("footer.php");