<div class="modal" id="editVendorModal<?php echo $vendor_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header text-white">
        <h5 class="modal-title"><i class="fa fa-fw fa-building mr-2"></i><?php echo $vendor_name; ?></h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="vendor_id" value="<?php echo $vendor_id; ?>">
        <div class="modal-body bg-white">

          <ul class="nav nav-pills nav-justified mb-3" id="pills-tab<?php echo $vendor_id; ?>" role="tablist">
            <li class="nav-item">
              <a class="nav-link active" id="pills-basic-tab<?php echo $vendor_id; ?>" data-toggle="pill" href="#pills-basic<?php echo $vendor_id; ?>" role="tab" aria-controls="pills-basic<?php echo $vendor_id; ?>" aria-selected="true">Basic</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" id="pills-address-tab<?php echo $vendor_id; ?>" data-toggle="pill" href="#pills-address<?php echo $vendor_id; ?>" role="tab" aria-controls="pills-address<?php echo $vendor_id; ?>" aria-selected="false">Address</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" id="pills-contact-tab<?php echo $vendor_id; ?>" data-toggle="pill" href="#pills-contact<?php echo $vendor_id; ?>" role="tab" aria-controls="pills-contact<?php echo $vendor_id; ?>" aria-selected="false">Contact</a>
            </li>
          </ul>

          <hr>
          
          <div class="tab-content" id="pills-tabContent<?php echo $vendor_id; ?>">

            <div class="tab-pane fade show active" id="pills-basic<?php echo $vendor_id; ?>" role="tabpanel" aria-labelledby="pills-basic-tab<?php echo $vendor_id; ?>">

              <div class="form-group">
                <label>Name <strong class="text-danger">*</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                  </div>
                  <input type="text" class="form-control" name="name" placeholder="Name" value="<?php echo "$vendor_name"; ?>" required>
                </div>
              </div>
              
              <div class="form-group">
                <label>Description <strong class="text-danger">*</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-angle-right"></i></span>
                  </div>
                  <input type="text" class="form-control" name="description" placeholder="Description" value="<?php echo $vendor_description; ?>">
                </div>
              </div>

              <div class="form-group">
                <label>Account Number</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-fingerprint"></i></span>
                  </div>
                  <input type="text" class="form-control" name="account_number" placeholder="Account number" value="<?php echo $vendor_account_number; ?>">
                </div>
              </div>

            </div>

            <div class="tab-pane fade" id="pills-address<?php echo $vendor_id; ?>" role="tabpanel" aria-labelledby="pills-address-tab<?php echo $vendor_id; ?>">

              <div class="form-group">
                <label>Address</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                  </div>
                  <input type="text" class="form-control" name="address"placeholder="Street address" value="<?php echo $vendor_address; ?>">
                </div>
              </div>
              
              <div class="form-group">
                <label>City</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-city"></i></span>
                  </div>
                  <input type="text" class="form-control" name="city" placeholder="City" value="<?php echo $vendor_city; ?>">
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
                    <option <?php if($vendor_state == $state_abbr){ echo "selected"; } ?> value="<?php echo $state_abbr; ?>"><?php echo $state_name; ?></option>
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
                  <input type="text" class="form-control" name="zip" placeholder="Zip code" value="<?php echo $vendor_zip; ?>" data-inputmask="'mask': '99999'">
                </div>
              </div>

            </div>
            
            <div class="tab-pane fade" id="pills-contact<?php echo $vendor_id; ?>" role="tabpanel" aria-labelledby="pills-contact-tab<?php echo $vendor_id; ?>">

              <div class="form-group">
                <label>Contact Name</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                  </div>
                  <input type="text" class="form-control" name="contact_name" value="<?php echo $vendor_contact_name; ?>" placeholder="Vendor contact name">
                </div>
              </div>

              <label>Phone</label>
              <div class="form-row">
                <div class="col-8">
                  <div class="form-group">
                    <div class="input-group">
                      <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-phone"></i></span>
                      </div>
                      <input type="text" class="form-control" name="phone" placeholder="Phone Number" value="<?php echo $vendor_phone; ?>" data-inputmask="'mask': '999-999-9999'"> 
                    </div>
                  </div>
                </div>
                <div class="col-4">
                  <input type="text" class="form-control" name="extension" placeholder="Extension" value="<?php echo $vendor_extension; ?>">
                </div>
              </div>
              
              <div class="form-group">
                <label>Email</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                  </div>
                  <input type="email" class="form-control" name="email" placeholder="Email" value="<?php echo $vendor_email; ?>">
                </div>
              </div>
              
              <div class="form-group">
                <label>Website</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                  </div>
                  <input type="text" class="form-control" name="website" placeholder="Website include http://" value="<?php echo $vendor_website; ?>">
                </div>
              </div>
            
            </div>

          </div>
          
        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_vendor" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>