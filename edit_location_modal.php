<div class="modal" id="editLocationModal<?php echo $location_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-fw fa-map-marker-alt"></i> <?php echo $location_name; ?></h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
        <input type="hidden" name="location_id" value="<?php echo $location_id; ?>">
        <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
        <input type="hidden" name="existing_file_name" value="<?php echo $location_photo; ?>">
        <div class="modal-body bg-white">

          <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
              <a class="nav-link active" data-toggle="pill" href="#pills-address<?php echo $location_id; ?>">Address</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" data-toggle="pill" href="#pills-contact<?php echo $location_id; ?>">Contact</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" data-toggle="pill" href="#pills-photo<?php echo $location_id; ?>">Photo</a>
            </li>
          </ul>

          <hr>

          <div class="tab-content">

            <div class="tab-pane fade show active" id="pills-address<?php echo $location_id; ?>">

              <div class="form-group">
                <label>Location Name / Primary <strong class="text-danger">*</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-map-marker"></i></span>
                  </div>
                  <input type="text" class="form-control" name="name" placeholder="Name of location" value="<?php echo $location_name; ?>" required>
                  <div class="input-group-append">
                    <div class="input-group-text">
                      <input type="checkbox" name="primary_location" value="1" <?php if($location_id == $primary_location){ echo "checked"; } ?>>
                    </div>
                  </div>
                </div>
              </div>

              <div class="form-group">
                <label>Address</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                  </div>
                  <input type="text" class="form-control" name="address" placeholder="Street Address" value="<?php echo $location_address; ?>">
                </div>
              </div>

              <div class="form-group">
                <label>City</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-city"></i></span>
                  </div>
                  <input type="text" class="form-control" name="city" placeholder="City" value="<?php echo $location_city; ?>">
                </div>
              </div>
              
              <div class="form-group">
                <label>State / Province</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-flag"></i></span>
                  </div>
                  <input type="text" class="form-control" name="state" placeholder="State or Province" value="<?php echo $location_state; ?>">
                </div>
              </div>
              
              <div class="form-group">
                <label>Zip / Postal Code</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fab fa-fw fa-usps"></i></span>
                  </div>
                  <input type="text" class="form-control" name="zip" placeholder="Zip or Postal Code" value="<?php echo $location_zip; ?>">
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
                    <option <?php if($location_country == $country_name) { echo "selected"; } ?>><?php echo $country_name; ?></option>
                    <?php } ?>
                  </select>
                </div>
              </div>

            </div>

            <div class="tab-pane fade" id="pills-contact<?php echo $location_id; ?>">

              <div class="form-group">
                <label>Contact</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                  </div>
                  <select class="form-control" name="contact">
                    <option value="">- Contact -</option>
                    <?php 
                    
                    $sql_contacts = mysqli_query($mysqli,"SELECT * FROM contacts WHERE (contact_archived_at > '$location_created_at' OR contact_archived_at IS NULL) AND contact_client_id = $client_id ORDER BY contact_name ASC"); 
                    while($row = mysqli_fetch_array($sql_contacts)){
                      $contact_id_select = $row['contact_id'];
                      $contact_name_select = $row['contact_name'];

                    ?>
                      <option <?php if($location_contact_id == $contact_id_select){ echo "selected"; } ?> value="<?php echo $contact_id_select; ?>"><?php echo $contact_name_select; ?></option>
                    
                    <?php
                    }
                    ?>
                  </select>
                </div>
              </div>

              <div class="form-group">
                <label>Phone</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-phone"></i></span>
                  </div>
                  <input type="text" class="form-control" name="phone" placeholder="Phone Number" value="<?php echo $location_phone; ?>"> 
                </div>
              </div>
              
              <div class="form-group">
                <label>Hours</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-clock"></i></span>
                  </div>
                  <input type="text" class="form-control" name="hours" placeholder="Hours of operation" value="<?php echo $location_hours; ?>"> 
                </div>
              </div>

              <div class="form-group">
                <textarea class="form-control" rows="6" name="notes" placeholder="Enter some notes"><?php echo $location_notes; ?></textarea>
              </div>

            </div>

            <div class="tab-pane fade" id="pills-photo<?php echo $location_id; ?>">

              <div class="form-group">

                <center>
                  <?php if(!empty($location_photo)){ ?>
                  <img class="img-fluid rounded-circle" src="<?php echo "uploads/clients/$session_company_id/$client_id/$location_photo"; ?>" height="256" width="256">
                  <?php } ?>
                </center>

                <input type="file" class="form-control-file" name="file">
              </div>

            </div>

          </div>

        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_location" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>
