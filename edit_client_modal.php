<div class="modal" id="editClientModal<?php echo $client_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header text-white">
        <h5 class="modal-title"><i class="fa fa-fw fa-user-edit mr-2"></i><?php echo $client_name; ?></h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
        <div class="modal-body bg-white">
          
          <ul class="nav nav-pills nav-justified mb-3" id="pills-tab<?php echo $client_id; ?>" role="tablist">
            <li class="nav-item">
              <a class="nav-link active" id="pills-basic-tab<?php echo $client_id; ?>" data-toggle="pill" href="#pills-basic<?php echo $client_id; ?>" role="tab" aria-controls="pills-basic<?php echo $client_id; ?>" aria-selected="true">Basic</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" id="pills-address-tab<?php echo $client_id; ?>" data-toggle="pill" href="#pills-address<?php echo $client_id; ?>" role="tab" aria-controls="pills-address<?php echo $client_id; ?>" aria-selected="false">Address</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" id="pills-contact-tab<?php echo $client_id; ?>" data-toggle="pill" href="#pills-contact<?php echo $client_id; ?>" role="tab" aria-controls="pills-contact<?php echo $client_id; ?>" aria-selected="false">Contact</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" id="pills-more-tab<?php echo $client_id; ?>" data-toggle="pill" href="#pills-more<?php echo $client_id; ?>" role="tab" aria-controls="pills-more<?php echo $client_id; ?>" aria-selected="false">More</a>
            </li>
          </ul>

          <hr>
          
          <div class="tab-content" id="pills-tabContent<?php echo $client_id; ?>">

            <div class="tab-pane fade show active" id="pills-basic<?php echo $client_id; ?>" role="tabpanel" aria-labelledby="pills-basic-tab<?php echo $client_id; ?>">

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
                <label>Type <strong class="text-danger">*</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-list"></i></span>
                  </div>
                  <select class="form-control selectpicker show-tick" data-live-search="true" name="type">
                  
                    <?php foreach($client_types_array as $client_type_select) { ?>
                    <option 
                      value="<?php echo $client_type_select; ?>"
                      <?php if($client_type_select == $client_type) { echo "selected"; } ?> >
                      <?php echo $client_type_select; ?>  
                    </option>
                    <?php } ?>
                  </select> 
                </div>
              </div>

            </div>
          
            <div class="tab-pane fade" id="pills-address<?php echo $client_id; ?>" role="tabpanel" aria-labelledby="pills-address-tab<?php echo $client_id; ?>">

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
                  <select class="form-control selectpicker show-tick" data-live-search="true" name="state">
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

            <div class="tab-pane fade" id="pills-contact<?php echo $client_id; ?>" role="tabpanel" aria-labelledby="pills-contact-tab<?php echo $client_id; ?>">

              <div class="form-group">
                <label>Phone</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-phone"></i></span>
                  </div>
                  <input type="text" class="form-control" name="phone" placeholder="Phone Number" value="<?php echo $client_phone; ?>" data-inputmask="'mask': '999-999-9999'"> 
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
                  <input type="text" class="form-control" name="website" placeholder="Web Address" value="<?php echo $client_website; ?>">
                </div>
              </div>
            
            </div>

            <div class="tab-pane fade" id="pills-more<?php echo $client_id; ?>" role="tabpanel" aria-labelledby="pills-more-tab<?php echo $client_id; ?>">

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
                  <select class="form-control selectpicker show-tick" name="net_terms">
                    <option value="">- Net Terms -</option>
                    <?php foreach($net_terms_array as $net_term_value => $net_term_name) { ?>
                    <option <?php if($net_term_value == $client_net_terms) { echo "selected"; } ?> value="<?php echo $net_term_value; ?>"><?php echo $net_term_name; ?></option>
                    <?php } ?>
                  </select>
                </div>
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