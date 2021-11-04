<div class="modal" id="editCompanyModal<?php echo $company_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-fw fa-building"></i> <?php echo $company_name; ?></h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
        <input type="hidden" name="company_id" value="<?php echo $company_id; ?>">
        <input type="hidden" name="existing_file_name" value="<?php echo $company_logo; ?>">
        <div class="modal-body bg-white">

          <div class="form-group">
            <label>Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
              </div>
              <input type="text" class="form-control" name="name" placeholder="Company Name" value="<?php echo $company_name; ?>" required>
            </div>
          </div>

          <div class="form-group">
            <label>Address</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
              </div>
              <input type="text" class="form-control" name="address" placeholder="Street Address" value="<?php echo $company_address; ?>">
            </div>
          </div>

          <div class="form-group">
            <label>City</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-city"></i></span>
              </div>
              <input type="text" class="form-control" name="city" placeholder="City" value="<?php echo $company_city; ?>">
            </div>
          </div>

          <div class="form-group">
            <label>State</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-flag"></i></span>
              </div>
              <input type="text" class="form-control" name="state" placeholder="State" value="<?php echo $company_state; ?>">
            </div>
          </div>

          <div class="form-group">
            <label>Zip</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fab fa-fw fa-usps"></i></span>
              </div>
              <input type="text" class="form-control" name="zip" placeholder="Postal Code" value="<?php echo $company_zip; ?>">
            </div>
          </div>

          <div class="form-group">
            <label>Country</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-flag"></i></span>
              </div>
              <select class="form-control select2" name="country">
                <option value="">- Country -</option>
                <?php foreach($countries_array as $country_name) { ?>
                <option <?php if($company_country == $country_name) { echo "selected"; } ?>><?php echo $country_name; ?></option>
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
              <input type="text" class="form-control" name="phone" placeholder="Phone Number" value="<?php echo $company_phone; ?>"> 
            </div>
          </div>

          <div class="form-group">
            <label>Email</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
              </div>
              <input type="email" class="form-control" name="email" placeholder="Email address" value="<?php echo $company_email; ?>">
            </div>
          </div>

          <div class="form-group">
            <label>Website</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
              </div>
              <input type="text" class="form-control" name="website" placeholder="Website address" value="<?php echo $company_website; ?>">
            </div>
          </div>

          <div class="form-group">
            <label>Currency <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-money-bill"></i></span>
              </div>
              <select class="form-control select2" name="currency_code" required>
                <option value="">- Currency -</option>
                <?php foreach($currencies_array as $currency_code => $currency_name) { ?>
                <option <?php if($company_currency_code == $currency_code){ echo "selected"; } ?> value="<?php echo $currency_code; ?>"><?php echo "$currency_code - $currency_name"; ?></option>
                <?php } ?>
              </select>
            </div>
          </div>

          <div class="form-group mb-4">
            <label>Logo</label>
            <input type="file" class="form-control-file" name="file">
          </div>

          <div class="card col-md-2">
            <div class="card-body">
              <img class="img-fluid" src="<?php echo "uploads/settings/$company_id/$company_logo"; ?>">
            </div>
          </div>

        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_company" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>
