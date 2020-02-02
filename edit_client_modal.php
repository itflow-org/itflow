<div class="modal" id="editClientModal<?php echo $client_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header text-white">
        <h5 class="modal-title"><i class="fa fa-fw fa-user mr-2"></i><?php echo $client_name; ?></h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
        <div class="modal-body bg-white">
          
          <ul class="nav nav-pills nav-justified mb-3" id="pills-tab<?php echo $client_id; ?>" role="tablist">
            <li class="nav-item">
              <a class="nav-link active" id="pills-details-tab<?php echo $client_id; ?>" data-toggle="pill" href="#pills-details<?php echo $client_id; ?>">Details</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" id="pills-address-tab<?php echo $client_id; ?>" data-toggle="pill" href="#pills-address<?php echo $client_id; ?>">Address</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" id="pills-contact-tab<?php echo $client_id; ?>" data-toggle="pill" href="#pills-contact<?php echo $client_id; ?>">Contact</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" id="pills-more-tab<?php echo $client_id; ?>" data-toggle="pill" href="#pills-more<?php echo $client_id; ?>">More</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" id="pills-notes-tab<?php echo $client_id; ?>" data-toggle="pill" href="#pills-notes<?php echo $client_id; ?>">Notes</a>
            </li>
          </ul>

          <hr>
          
          <div class="tab-content" id="pills-tabContent<?php echo $client_id; ?>">

            <div class="tab-pane fade show active" id="pills-details<?php echo $client_id; ?>">

              <div class="form-group">
                <label>Name <strong class="text-danger">*</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                  </div>
                  <input type="text" class="form-control" name="name" placeholder="Name or Company" value="<?php echo $client_name; ?>" required>
                </div>
              </div>

              <div class="form-group">
                <label>Type</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-list"></i></span>
                  </div>
                  <input type="text" class="form-control" name="type" placeholder="Company Type" value="<?php echo $client_type; ?>">
                </div>
              </div>

              <label>Phone</label>
              <div class="form-group">
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-phone"></i></span>
                  </div>
                  <input type="text" class="form-control" name="phone" placeholder="Phone Number" data-inputmask="'mask': '999-999-9999'" value="<?php echo $client_phone; ?>"> 
                </div>
              </div>

              <div class="form-group">
                <label>Website</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                  </div>
                  <input type="url" class="form-control" name="website" placeholder="ex. https://google.com" value="<?php echo $client_website; ?>">
                </div>
              </div>

            </div>
          
            <div class="tab-pane fade" id="pills-address<?php echo $client_id; ?>">

              <div class="form-group">
                <label>Address</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                  </div>
                  <input type="text" class="form-control" name="address" placeholder="Address" value="<?php echo $client_address; ?>">
                </div>
              </div>
          
              <div class="form-group">
                <label>City</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-city"></i></span>
                  </div>
                  <input type="text" class="form-control" name="city" placeholder="City" value="<?php echo $client_city; ?>">
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
                    <option <?php if($client_state == $state_abbr) { echo "selected"; } ?> value="<?php echo $state_abbr; ?>"><?php echo $state_name; ?></option>
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
                  <input type="text" class="form-control" name="zip" placeholder="Zip" value="<?php echo $client_zip; ?>" data-inputmask="'mask': '99999'">
                </div>
              </div>
            
            </div>

            <div class="tab-pane fade" id="pills-contact<?php echo $client_id; ?>">

              <div class="form-group">
                <label>Primary Contact</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                  </div>
                  <input type="text" class="form-control" name="contact" placeholder="Primary contact name" value="<?php echo $client_contact; ?>"> 
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
                      <input type="text" class="form-control" name="phone" placeholder="Phone Number" value="<?php echo $client_phone; ?>" data-inputmask="'mask': '999-999-9999'" data-mask> 
                    </div>
                  </div>
                </div>
                <div class="col-4">
                  <input type="text" class="form-control" name="extension" placeholder="Extension" value="<?php echo $client_extension; ?>">
                </div>
              </div>

              <div class="form-group">
                <label>Mobile</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-mobile-alt"></i></span>
                  </div>
                  <input type="text" class="form-control" name="mobile" placeholder="Mobile Number" value="<?php echo $client_mobile; ?>" data-inputmask="'mask': '999-999-9999'"> 
                </div>
              </div>
              
              <div class="form-group">
                <label>Email</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                  </div>
                  <input type="email" class="form-control" name="email" placeholder="Email Address" value="<?php echo $client_email; ?>">
                </div>
              </div>
            
            </div>

            <div class="tab-pane fade" id="pills-more<?php echo $client_id; ?>">

              <div class="form-group">
                <label>Hours</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-clock"></i></span>
                  </div>
                  <input type="text" class="form-control" name="hours" placeholder="Hours of operation" value="<?php echo $client_hours; ?>">
                </div>
              </div>

              <div class="form-group">
                <label>Invoice Net Terms</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                  </div>
                  <select class="form-control select2" name="net_terms">
                    <option value="">- Net Terms -</option>
                    <?php foreach($net_terms_array as $net_term_value => $net_term_name) { ?>
                    <option <?php if($net_term_value == $client_net_terms) { echo "selected"; } ?> value="<?php echo $net_term_value; ?>"><?php echo $net_term_name; ?></option>
                    <?php } ?>
                  </select>
                </div>
              </div>

              <div class="form-group">
                <label>Company Size</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-users"></i></span>
                  </div>
                  <select class="form-control select2" name="company_size">
                    <option <?php if($client_company_size == "1 - 3"){ echo "selected"; } ?>>1 - 3</option>
                    <option <?php if($client_company_size == "4 - 10"){ echo "selected"; } ?>>4 - 10</option>
                    <option <?php if($client_company_size == "11 - 50"){ echo "selected"; } ?>>11 - 50</option>
                    <option <?php if($client_company_size == "51 - 100"){ echo "selected"; } ?>>51 - 100</option>
                    <option <?php if($client_company_size == "101 - 500"){ echo "selected"; } ?>>101 - 500</option>
                    <option <?php if($client_company_size == "500+"){ echo "selected"; } ?>>500+</option>
                  </select>
                </div>
              </div>
            
            </div>

            <div class="tab-pane fade" id="pills-notes<?php echo $client_id; ?>">

              <div class="form-group">
                <textarea class="form-control" rows="8" name="notes"><?php echo $client_notes; ?></textarea>
              </div>
            
            </div>
          
          </div>    
        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_client" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>