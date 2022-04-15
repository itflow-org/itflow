<?php include("inc_all_admin.php"); ?>

<div class="card card-dark">
  <div class="card-header">
    <h3 class="card-title"><i class="fa fa-fw fa-puzzle-piece"></i> Modules</h3>
  </div>
  <div class="card-body">
    <form action="post.php" method="post" autocomplete="off">

      <div class="custom-control custom-switch mb-3">
        <input type="checkbox" class="custom-control-input" name="config_module_enable_itdoc" <?php if($config_module_enable_itdoc == 1){ echo "checked"; } ?> value="1" id="customSwitch1">
        <label class="custom-control-label" for="customSwitch1">Enable IT Documentation</label>
      </div>

      <div class="custom-control custom-switch mb-3">
        <input type="checkbox" class="custom-control-input" name="config_module_enable_ticketing" <?php if($config_module_enable_ticketing == 1){ echo "checked"; } ?> value="1" id="customSwitch2">
        <label class="custom-control-label" for="customSwitch2">Enable Ticketing</label>
      </div>

      <div class="custom-control custom-switch mb-3">
        <input type="checkbox" class="custom-control-input" name="config_module_enable_accounting" <?php if($config_module_enable_accounting == 1){ echo "checked"; } ?> value="1" id="customSwitch3">
        <label class="custom-control-label" for="customSwitch3">Enable Invoicing / Accounting</label>
      </div>

      <hr>

      <button type="submit" name="edit_module_settings" class="btn btn-primary">Save</button>
        
    </form>
  </div>
</div>

<?php include("footer.php");