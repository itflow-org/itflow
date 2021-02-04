<?php include("header.php"); ?>

<?php include("settings-nav.php"); ?>

<div class="card card-dark">
  <div class="card-header">
    <h3 class="card-title"><i class="fa fa-fw fa-building"></i> Company Settings</h3>
  </div>
  <div class="card-body">
    <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
      <div class="form-group">
        <label>Company Name</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
          </div>
          <input type="text" class="form-control" name="config_company_name" placeholder="Company Name" value="<?php echo $config_company_name; ?>" required>  
        </div>
      </div>

      <div class="form-group">
        <label>Address</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
          </div>
          <input type="text" class="form-control" name="config_company_address" placeholder="Street Address" value="<?php echo $config_company_address; ?>" >
        </div>
      </div>

      <div class="form-group">
        <label>City</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-city"></i></span>
          </div>
          <input type="text" class="form-control" name="config_company_city" placeholder="City" value="<?php echo $config_company_city; ?>" >
        </div>
      </div>

      <div class="form-group">
        <label>State</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-flag"></i></span>
          </div>
          <select class="form-control select2" name="config_company_state">
            <option value="">Select a state...</option>
              <?php foreach($states_array as $state_abbr => $state_name) { ?>
              <option <?php if($config_company_state == $state_abbr) { echo "selected"; } ?> value="<?php echo $state_abbr; ?>"><?php echo $state_name; ?></option>
              <?php } ?>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label>Zip</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fab fa-fw fa-usps"></i></span>
          </div>
          <input type="text" class="form-control" name="config_company_zip" placeholder="Postal Code" value="<?php echo $config_company_zip; ?>">
        </div>
      </div>

      <div class="form-group">
        <label>Country</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-flag"></i></span>
          </div>
          <select class="form-control select2" name="config_company_country">
            <option value="">- Country -</option>
            <?php foreach($countries_array as $country_name) { ?>
            <option <?php if($config_company_country == $country_name) { echo "selected"; } ?>><?php echo $country_name; ?></option>
            <?php } ?>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label>Phone</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-phone"></i></span>
          </div>
          <input type="text" class="form-control" name="config_company_phone" placeholder="Phone Number" value="<?php echo $config_company_phone; ?>" data-inputmask="'mask': '999-999-9999'" > 
        </div>
      </div>

      <div class="form-group">
        <label>Email</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
          </div>
          <input type="email" class="form-control" name="config_company_email" placeholder="Email Address" value="<?php echo $config_company_email; ?>">
        </div>
      </div>

      <div class="form-group">
        <label>Website</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
          </div>
          <input type="text" class="form-control" name="config_company_site" placeholder="Website address https://" value="<?php echo $config_company_site; ?>" >
        </div>
      </div>

      <div class="form-group mb-4">
        <label>Logo</label>
        <input type="file" class="form-control-file" name="file">
      </div>

      <div class="card col-md-2">
        <div class="card-body">
          <img class="img-fluid" src="<?php echo $config_invoice_logo; ?>">
        </div>
        
      </div>
      
      <hr>
      
      <button type="submit" name="edit_company_settings" class="btn btn-primary">Save</button>
        
    </form>
  </div>
</div>

<?php include("footer.php");