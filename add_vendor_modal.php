<div class="modal" id="addVendorModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header text-white">
        <h5 class="modal-title"><i class="fa fa-fw fa-building mr-2"></i>New Vendor</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
        <div class="modal-body bg-white">

          <ul class="nav nav-pills nav-justified mb-3" id="pills-tab">
            <li class="nav-item">
              <a class="nav-link active" id="pills-details-tab" data-toggle="pill" href="#pills-details">Details</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" id="pills-address-tab" data-toggle="pill" href="#pills-address">Address</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" id="pills-contact-tab" data-toggle="pill" href="#pills-contact">Contact</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" id="pills-notes-tab" data-toggle="pill" href="#pills-notes">Notes</a>
            </li>
          </ul>

          <hr>
          
          <div class="tab-content" id="pills-tabContent">

            <div class="tab-pane fade show active" id="pills-details">

              <div class="form-group">
                <label>Name <strong class="text-danger">*</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                  </div>
                  <input type="text" class="form-control" name="name" placeholder="Name" required autofocus>
                </div>
              </div>
              
              <div class="form-group">
                <label>Description <strong class="text-danger">*</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-angle-right"></i></span>
                  </div>
                  <input type="text" class="form-control" name="description" placeholder="Description">
                </div>
              </div>

              <div class="form-group">
                <label>Account Number</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-fingerprint"></i></span>
                  </div>
                  <input type="text" class="form-control" name="account_number" placeholder="Account number">
                </div>
              </div>

            </div>

            <div class="tab-pane fade" id="pills-address">

              <div class="form-group">
                <label>Address</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                  </div>
                  <input type="text" class="form-control" name="address"placeholder="Street address" >
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
                  <input type="text" class="form-control" name="zip" placeholder="Zip code" data-inputmask="'mask': '99999'">
                </div>
              </div>

            </div>
            
            <div class="tab-pane fade" id="pills-contact">

              <div class="form-group">
                <label>Contact Name</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                  </div>
                  <input type="text" class="form-control" name="contact_name" placeholder="Vendor contact name">
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
                      <input type="text" class="form-control" name="phone" placeholder="Phone Number" data-inputmask="'mask': '999-999-9999'"> 
                    </div>
                  </div>
                </div>
                <div class="col-4">
                  <input type="text" class="form-control" name="extension" placeholder="Extension">
                </div>
              </div>
              
              <div class="form-group">
                <label>Email</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                  </div>
                  <input type="email" class="form-control" name="email" placeholder="Email">
                </div>
              </div>
              
              <div class="form-group">
                <label>Website</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                  </div>
                  <input type="text" class="form-control" name="website" placeholder="Website include http://">
                </div>
              </div>
            
            </div>

            <div class="tab-pane fade" id="pills-notes">
              
              <div class="form-group">
                <textarea class="form-control" rows="8" name="notes"></textarea>
              </div>

            </div>

          </div>
          
        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_vendor" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>