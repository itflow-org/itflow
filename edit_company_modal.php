<div class="modal" id="editCompanyModal<?php echo $company_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header text-white">
        <h5 class="modal-title"><i class="fa fa-fw fa-building mr-2"></i><?php echo $company_name; ?></h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="company_id" value="<?php echo $company_id; ?>">
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
              <select class="form-control selectpicker show-tick" data-live-search="true" name="state">
                <option value="">- State -</option>
                <?php foreach($states_array as $state_abbr => $state_name) { ?>
                <option <?php if($company_state == $state_abbr) { echo "selected"; } ?> value="<?php echo $state_abbr; ?>"><?php echo $state_name; ?></option>
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
              <input type="text" class="form-control" name="zip" placeholder="Zip Code" data-inputmask="'mask': '99999'" value="<?php echo $company_zip; ?>">
            </div>
          </div>

          <div class="form-group">
            <label>Phone</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-phone"></i></span>
              </div>
              <input type="text" class="form-control" name="phone" placeholder="Phone Number" data-inputmask="'mask': '999-999-9999'" value="<?php echo $company_phone; ?>"> 
            </div>
          </div>

          <div class="form-group mb-5">
            <label>Website</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
              </div>
              <input type="text" class="form-control" name="site" placeholder="Website address" value="<?php echo $company_site; ?>">
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