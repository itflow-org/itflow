<div class="modal" id="addClientModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-fw fa-user-plus"></i> New Client</h5>
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
              <a class="nav-link" data-toggle="pill" href="#pills-location">Location</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" data-toggle="pill" href="#pills-contact" id="contactNavPill">Contact</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" data-toggle="pill" href="#pills-additional">Additional</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" data-toggle="pill" href="#pills-tag">Tag</a>
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
                <label>Industry</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-briefcase"></i></span>
                  </div>
                  <input type="text" class="form-control" name="type" placeholder="Company Type">
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
                      $referral = htmlentities($row['category_name']);
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
                <label>Website</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                  </div>
                  <input type="text" class="form-control" name="website" placeholder="ex. google.com">
                </div>
              </div>


            </div>
          
            <div class="tab-pane fade" id="pills-location">
  
              <label>Location Phone</label>
        
              <div class="form-group">
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-phone"></i></span>
                  </div>
                  <input type="text" class="form-control" name="location_phone" placeholder="Location's Phone Number"> 
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
                <label>State / Province</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-flag"></i></span>
                  </div>
                 <input type="text" class="form-control" name="state" placeholder="State or Province">
                </div>
              </div>
              
              <div class="form-group">
                <label>Zip / Postal Code</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fab fa-fw fa-usps"></i></span>
                  </div>
                  <input type="text" class="form-control" name="zip" placeholder="Zip or Postal Code">
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
                    <option <?php if($session_company_country == $country_name){ echo "selected"; } ?> ><?php echo $country_name; ?></option>
                    <?php } ?>
                  </select>
                </div>
              </div>
            
            </div>

            <div class="tab-pane fade" id="pills-contact">

              <div class="form-group">
                <label>Primary Contact <strong class="text-danger">*</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                  </div>
                  <input type="text" class="form-control" id="primaryContact" name="contact" placeholder="Primary Contact Person" required autofocus>
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

              <label>Contact Phone</label>
              <div class="form-row">
                <div class="col-8">
                  <div class="form-group">
                    <div class="input-group">
                      <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-phone"></i></span>
                      </div>
                      <input type="text" class="form-control" name="contact_phone" placeholder="Contact's Phone Number"> 
                    </div>
                  </div>
                </div>
                <div class="col-4">
                  <input type="text" class="form-control" name="contact_extension" placeholder="Extension">
                </div>
              </div>

              <label>Contact Mobile</label>
          
              <div class="form-group">
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-mobile-alt"></i></span>
                  </div>
                  <input type="text" class="form-control" name="contact_mobile" placeholder="Contact's Mobile Number"> 
                </div>
              </div>
              
              <div class="form-group">
                <label>Contact Email</label>
                <div class="input-group"> 
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                  </div>
                  <input type="email" class="form-control" name="contact_email" placeholder="Contact's Email Address">
                </div>
              </div>
            
            </div>

            <div class="tab-pane fade" id="pills-additional">

              <?php if($config_module_enable_accounting){ ?>
              <div class="form-group">
                <label>Currency <strong class="text-danger">*</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-money-bill"></i></span>
                  </div>
                  <select class="form-control select2" name="currency_code" required>
                    <option value="">- Currency -</option>
                    <?php foreach($currencies_array as $currency_code => $currency_name) { ?>
                    <option <?php if($session_company_currency == $currency_code){ echo "selected"; } ?> value="<?php echo $currency_code; ?>"><?php echo "$currency_code - $currency_name"; ?></option>
                    <?php } ?>
                  </select>
                </div>
              </div>

              <div class="form-group">
                <label>Payment Terms</label>
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
              
              <?php }else{ ?>
              
              <input type="hidden" name="currency_code" value="<?php echo $session_company_currency; ?>">
              <input type="hidden" name="net_terms" value="0">
              <?php } ?>

              <div class="form-group">
                <label>Notes</label>
                <textarea class="form-control" rows="6" name="notes" placeholder="Enter some notes"></textarea>
              </div>
            
            </div>

            <div class="tab-pane fade" id="pills-tag">

              <ul class="list-group">

                <?php
                $sql_tags_select = mysqli_query($mysqli,"SELECT * FROM tags WHERE tag_type = 1 AND company_id = $session_company_id ORDER BY tag_name ASC");

                while($row = mysqli_fetch_array($sql_tags_select)){
                  $tag_id_select = $row['tag_id'];
                  $tag_name_select = htmlentities($row['tag_name']);
                  $tag_color_select = htmlentities($row['tag_color']);
                  $tag_icon_select = htmlentities($row['tag_icon']);

                ?>
                  <li class="list-group-item">
                    <div class="form-check">
                      <input type="checkbox" class="form-check-input" name="tags[]" value="<?php echo $tag_id_select; ?>">
                      <label class="form-check-label ml-2 badge bg-<?php echo $tag_color_select; ?>"><?php echo "<i class='fa fw fa-$tag_icon_select'></i>"; ?> <?php echo $tag_name_select; ?></label>
                    </div>
                  </li>

                <?php
                }
                ?>

              </ul>

            </div>

          </div>    
        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_client" class="btn btn-primary" onclick="promptPrimaryContact()"><strong><i class="fas fa-check"></i> Create</strong></button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
    // Checks/prompts that the primary contact field (required) is populated
    function promptPrimaryContact(){
        let primaryContactField = document.getElementById("primaryContact").value;
        if (primaryContactField == null || primaryContactField === ""){
            document.getElementById("contactNavPill").click();
        }
    }
</script>