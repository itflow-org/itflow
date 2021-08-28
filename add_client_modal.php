<div class="modal" id="addClientModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-fw fa-user"></i> New Client</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <div class="modal-body bg-white">
          
          <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
              <a class="nav-link active" data-toggle="pill" href="#pills-details">Details</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" data-toggle="pill" href="#pills-address">Address</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" data-toggle="pill" href="#pills-contact">Contact</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" data-toggle="pill" href="#pills-notes">Notes</a>
            </li>
          </ul>

          <hr>
          
          <div class="tab-content">

            <div class="tab-pane fade show active" id="pills-details">

              <div class="form-group">
                <label>Name <strong class="text-danger">*</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                  </div>
                  <input type="text" class="form-control" name="name" placeholder="Name or Company" required autofocus>
                </div>
              </div>

              <div class="form-group">
                <label>Type</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-list"></i></span>
                  </div>
                  <input type="text" class="form-control" name="type" placeholder="Company Type">
                </div>
              </div>

              <div class="form-group">
                <label>Support</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-handshake"></i></span>
                  </div>
                  <select class="form-control select2" name="support">
                    <option>Non-Maintenance</option>
                    <option>Maintenance</option>
                  </select>
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
                    
                    $referral_sql = mysqli_query($mysqli,"SELECT * FROM categories WHERE category_type = 'Referral' AND category_archived_at IS NULL AND company_id = $session_company_id ORDER BY category_name ASC"); 
                    while($row = mysqli_fetch_array($referral_sql)){
                      $referral = $row['category_name'];
                    ?>
                      <option><?php echo $referral; ?></option>
                    
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
                <label>Currency <strong class="text-danger">*</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-money-bill"></i></span>
                  </div>
                  <select class="form-control select2" name="currency_code" required>
                    <option value="">- Currency -</option>
                    <?php foreach($currencies_array as $currency_code => $currency_name) { ?>
                    <option <?php if($config_default_currency == $currency_code){ echo "selected"; } ?> value="<?php echo $currency_code; ?>"><?php echo "$currency_code - $currency_name"; ?></option>
                    <?php } ?>
                  </select>
                </div>
              </div>

              <div class="form-group">
                <label>Invoice Net Terms</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                  </div>
                  <select class="form-control select2" name="net_terms">
                    <?php foreach($net_terms_array as $net_term_value => $net_term_name) { ?>
                    <option <?php if($config_default_net_terms == $net_term_value){ echo "selected"; } ?> value="<?php echo $net_term_value; ?>"><?php echo $net_term_name; ?></option>
                    <?php } ?>
                  </select>
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
                    <option <?php if($config_default_country == $country_name){ echo "selected"; } ?> ><?php echo $country_name; ?></option>
                    <?php } ?>
                  </select>
                </div>
              </div>
            
            </div>

            <div class="tab-pane fade" id="pills-contact">

              <div class="form-group">
                <label>Primary Contact</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                  </div>
                  <input type="text" class="form-control" name="contact" placeholder="Primary Contact Person"> 
                </div>
              </div>

              <div class="form-group">
                <label>Title</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-id-badge"></i></span>
                  </div>
                  <input type="text" class="form-control" name="title" placeholder="Title">
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
                      <input type="text" class="form-control" name="phone" placeholder="Phone Number" data-inputmask="'mask': '999-999-9999'" data-mask> 
                    </div>
                  </div>
                </div>
                <div class="col-4">
                  <input type="text" class="form-control" name="extension" placeholder="Extension">
                </div>
              </div>

              <label>Mobile</label>
          
              <div class="form-group">
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-mobile-alt"></i></span>
                  </div>
                  <input type="text" class="form-control" name="mobile" placeholder="Mobile Number" data-inputmask="'mask': '999-999-9999'" data-mask> 
                </div>
              </div>
              
              <div class="form-group">
                <label>Email</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                  </div>
                  <input type="email" class="form-control" name="email" placeholder="Email Address">
                </div>
              </div>

              <div class="form-group">
                <label>Website</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                  </div>
                  <input type="text" class="form-control" name="website" placeholder="ex. google.com">
                </div>
              </div>
            
            </div>

            <div class="tab-pane fade" id="pills-notes">

              <div class="form-group">
                <textarea class="form-control" rows="8" name="notes" placeholder="Enter some notes"></textarea>
              </div>
            
            </div>

          </div>    
        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_client" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>
