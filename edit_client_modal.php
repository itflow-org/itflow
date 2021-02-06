<div class="modal" id="editClientModal<?php echo $client_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-fw fa-user"></i> <?php echo $client_name; ?></h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
        <div class="modal-body bg-white">
          
          <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
              <a class="nav-link active" data-toggle="pill" href="#pills-details<?php echo $client_id; ?>">Details</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" data-toggle="pill" href="#pills-address<?php echo $client_id; ?>">Address</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" data-toggle="pill" href="#pills-contact<?php echo $client_id; ?>">Contact</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" data-toggle="pill" href="#pills-notes<?php echo $client_id; ?>">Notes</a>
            </li>
          </ul>

          <hr>
          
          <div class="tab-content">

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

              <div class="form-group">
                <label>Referral</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-smile-wink"></i></span>
                  </div>
                  <select class="form-control select2" name="referral">
                    <option value="">N/A</option>
                    <?php 
                    
                    $referral_sql = mysqli_query($mysqli,"SELECT * FROM categories WHERE category_type = 'Referral' AND company_id = $session_company_id ORDER BY category_name ASC"); 
                    while($row = mysqli_fetch_array($referral_sql)){
                      $referral = $row['category_name'];
                    ?>
                      <option <?php if($client_referral == $referral){ echo "selected"; } ?> > <?php echo $referral; ?></option>
                    
                    <?php
                    }
                    ?>
                  </select>
                  <div class="input-group-append">
                    <button type="button" class="btn btn-secondary" data-toggle="modal" data-target="#addQuickReferralModal"><i class="fas fa-fw fa-plus"></i></button>
                  </div>
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
                  <input type="text" class="form-control" name="zip" placeholder="Postal Code" value="<?php echo $client_zip; ?>">
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
                    <option <?php if($client_country == $country_name) { echo "selected"; } ?>><?php echo $country_name; ?></option>
                    <?php } ?>
                  </select>
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

              <div class="form-group">
                <label>Website</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                  </div>
                  <input type="text" class="form-control" name="website" placeholder="ex. google.com" value="<?php echo $client_website; ?>">
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