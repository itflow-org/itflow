<div class="modal" id="addLocationModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header text-white">
        <h5 class="modal-title"><i class="fa fa-fw fa-map-marker-alt mr-2"></i>New Location</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
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
              <a class="nav-link" id="pills-photo-tab" data-toggle="pill" href="#pills-photo">Photo</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" id="pills-notes-tab" data-toggle="pill" href="#pills-notes">Notes</a>
            </li>
          </ul>

          <hr>

          <div class="tab-content" id="pills-tabContent">

            <div class="tab-pane fade show active" id="pills-details">

              <div class="form-group">
                <label>Location Name <strong class="text-danger">*</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-map-marker"></i></span>
                  </div>
                  <input type="text" class="form-control" name="name" placeholder="Name of location" required autofocus>
                </div>
              </div>

              <div class="form-group">
                <label>Phone</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-phone"></i></span>
                  </div>
                  <input type="text" class="form-control" name="phone" placeholder="Phone Number" data-inputmask="'mask': '999-999-9999'"> 
                </div>
              </div>

            </div>

            <div class="tab-pane fade" id="pills-address">

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
                <label>Address</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                  </div>
                  <input type="text" class="form-control" name="address" placeholder="Address">
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

            </div>

            <div class="tab-pane fade" id="pills-contact">

              <div class="form-group">
                <label>Contact</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                  </div>
                  <select class="form-control" name="contact">
                    <option value="">- Contact -</option>
                    <?php 
                    
                    $sql_vendors = mysqli_query($mysqli,"SELECT * FROM contacts WHERE client_id = $client_id ORDER BY contact_name ASC"); 
                    while($row = mysqli_fetch_array($sql_vendors)){
                      $contact_id = $row['contact_id'];
                      $contact_name = $row['contact_name'];
                    ?>
                      <option value="<?php echo $contact_id; ?>"><?php echo $contact_name; ?></option>
                    
                    <?php
                    }
                    ?>
                  </select>
                </div>
              </div>

              <div class="form-group">
                <label>Hours</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-clock"></i></span>
                  </div>
                  <input type="text" class="form-control" name="hours" placeholder="Hours of operation"> 
                </div>
              </div>

            </div>

            <div class="tab-pane fade" id="pills-photo">

              <div class="form-group">
                <input type="file" class="form-control-file" name="file">
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
          <button type="submit" name="add_location" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>