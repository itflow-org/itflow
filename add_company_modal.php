<div class="modal" id="addCompanyModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-fw fa-building"></i> New Company</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
        <div class="modal-body bg-white">

          <div class="form-group">
            <label>Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
              </div>
              <input type="text" class="form-control" name="name" placeholder="Company Name" required autofocus>
            </div>
          </div>

          <div class="form-group">
            <label>Address</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
              </div>
              <input type="text" class="form-control" name="address" placeholder="Street Address">
            </div>
          </div>

          <div class="form-group">
            <label>City</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-city"></i></span>
              </div>
              <input type="text" class="form-control" name="city" placeholder="City">
            </div>
          </div>

          <div class="form-group">
            <label>State</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-flag"></i></span>
              </div>
              <select class="form-control select2" name="state">
                <option value="">- State -</option>
                  <?php foreach($states_array as $state_abbr => $state_name) { ?>
                  <option value="<?php echo $state_abbr; ?>"><?php echo $state_name; ?></option>
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
              <input type="text" class="form-control" name="zip" placeholder="Postal Code">
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
                <option><?php echo $country_name; ?></option>
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
              <input type="text" class="form-control" name="phone" placeholder="Phone Number" data-inputmask="'mask': '999-999-9999'" data-mask> 
            </div>
          </div>

          <div class="form-group">
            <label>Email</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
              </div>
              <input type="email" class="form-control" name="email" placeholder="Email address"> 
            </div>
          </div>

          <div class="form-group">
            <label>Website</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
              </div>
              <input type="text" class="form-control" name="website" placeholder="Website address">
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
                <option value="<?php echo $currency_code; ?>"><?php echo "$currency_code - $currency_name"; ?></option>
                <?php } ?>
              </select>
            </div>
          </div>

          <div class="form-group mb-4">
            <label>Logo</label>
            <input type="file" class="form-control-file" name="file">
          </div>

        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_company" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>
