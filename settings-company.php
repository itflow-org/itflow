<?php include("header.php"); ?>

<?php include("settings-nav.php"); ?>

<div class="card mb-3">
  <div class="card-header">
    <h6 class="float-left mt-1"><i class="fa fa-building"></i> Company Settings</h6>
  </div>
  <div class="card-body">
    <form class="p-3" action="post.php" method="post"  autocomplete="off">
      <div class="form-group">
        <label>Company Name</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-building"></i></span>
          </div>
          <input type="text" class="form-control" name="config_company_name" placeholder="Company Name" value="<?php echo $config_company_name; ?>" required autofocus>  
        </div>
      </div>
      
      <div class="form-group">
        <label>Address</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-map-marker"></i></span>
          </div>
          <input type="text" class="form-control" name="config_company_address" placeholder="Street Address" value="<?php echo $config_company_address; ?>" >
        </div>
      </div>

      <div class="form-group">
        <label>City</label>
        <input type="text" class="form-control" name="config_company_city" placeholder="City" value="<?php echo $config_company_city; ?>" >
      </div>

      <div class="form-group">
        <label>State</label>
        <select class="form-control" name="config_company_state">
          <option value="">Select a state...</option>
            <?php foreach($states_array as $state_abbr => $state_name) { ?>
            <option <?php if($config_company_state == $state_abbr) { echo "selected"; } ?> value="<?php echo $state_abbr; ?>"><?php echo $state_name; ?></option>
            <?php } ?>
        </select>
      </div>

      <div class="form-group">
        <label>Zip</label>
        <input type="text" class="form-control" name="config_company_zip" placeholder="Zip Code" value="<?php echo $config_company_zip; ?>" >
      </div>

      <div class="form-group">
        <label>Phone</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-phone"></i></span>
          </div>
          <input type="text" class="form-control" name="config_company_phone" placeholder="Phone Number" value="<?php echo $config_company_phone; ?>" data-inputmask="'mask': '999-999-9999'" > 
        </div>
      </div>

      <div class="form-group mb-5">
        <label>Website</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-globe"></i></span>
          </div>
          <input type="text" class="form-control" name="config_company_site" placeholder="Website address https://" value="<?php echo $config_company_site; ?>" >
        </div>
      </div>
      
      <hr>
      
      <button type="submit" name="edit_company_settings" class="btn btn-primary">Save</button>
        
    </form>
  </div>
</div>

<?php include("footer.php");