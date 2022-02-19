<div class="modal" id="editSoftwareModal<?php echo $software_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-fw fa-rocket"></i> <?php echo $software_name; ?></h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="software_id" value="<?php echo $software_id; ?>">
        <input type="hidden" name="login_id" value="<?php echo $login_id; ?>">
        <div class="modal-body bg-white">    
          
          <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
              <a class="nav-link active" data-toggle="pill" href="#pills-details<?php echo $software_id; ?>">Details</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" data-toggle="pill" href="#pills-device-licenses<?php echo $software_id; ?>">Device Licenses</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" data-toggle="pill" href="#pills-user-licenses<?php echo $software_id; ?>">User Licenses</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" data-toggle="pill" href="#pills-notes<?php echo $software_id; ?>">Notes</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" data-toggle="pill" href="#pills-login<?php echo $software_id; ?>">Login</a>
            </li>
          </ul>

          <hr>
          
          <div class="tab-content">

            <div class="tab-pane fade show active" id="pills-details<?php echo $software_id; ?>">

              <div class="form-group">
                <label>Software Name <strong class="text-danger">*</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-cube"></i></span>
                  </div>
                  <input type="text" class="form-control" name="name" placeholder="Software name" value="<?php echo $software_name; ?>" required>
                </div>
              </div>

              <div class="form-group">
                <label>Version</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-cube"></i></span>
                  </div>
                  <input type="text" class="form-control" name="version" placeholder="Software version" value="<?php echo $software_version; ?>">
                </div>
              </div>
              
              <div class="form-group">
                <label>Type <strong class="text-danger">*</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                  </div>
                  <select class="form-control select2" name="type" required>
                    <?php foreach($software_types_array as $software_type_select) { ?>
                    <option <?php if($software_type == $software_type_select) { echo "selected"; } ?>><?php echo $software_type_select; ?></option>
                    <?php } ?>
                  </select>
                </div>
              </div>

              <div class="form-group">
                <label>License Type</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-cube"></i></span>
                  </div>
                  <select class="form-control select2" name="license_type">
                    <option value="">- Select a License Type -</option>
                    <?php foreach($license_types_array as $license_type_select) { ?>
                    <option <?php if($license_type_select == $software_license_type){ echo "selected"; } ?>><?php echo $license_type_select; ?></option>
                    <?php } ?>
                  </select> 
                </div>
              </div>

              <div class="form-group">
                <label>Seats</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-cube"></i></span>
                  </div>
                  <input type="number" class="form-control" name="seats" placeholder="Number of seats" value="<?php echo $software_seats; ?>"> 
                </div>
              </div>
            
              <div class="form-group">
                <label>License Key</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                  </div>
                  <input type="text" class="form-control" name="key" placeholder="License key" value="<?php echo $software_key; ?>"> 
                </div>
              </div>

              <div class="form-group">
                <label>Purchase Date</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                  </div>
                  <input type="date" class="form-control" name="purchase" value="<?php echo $software_purchase; ?>"> 
                </div>
              </div>

              <div class="form-group">
                <label>Expire</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                  </div>
                  <input type="date" class="form-control" name="expire" value="<?php echo $software_expire; ?>"> 
                </div>
              </div>

            </div>

            <div class="tab-pane fade" id="pills-device-licenses<?php echo $software_id; ?>">

              <div class="alert alert-info">
                Select Assets that are licensed for this software
              </div>

              <ul class="list-group">

                <?php
                $sql_assets_select = mysqli_query($mysqli,"SELECT * FROM assets LEFT JOIN contacts ON asset_contact_id = contact_id WHERE asset_client_id = $client_id ORDER BY asset_name ASC");

                while($row = mysqli_fetch_array($sql_assets_select)){
                  $asset_id_select = $row['asset_id'];
                  $asset_name_select = $row['asset_name'];
                  $asset_type_select = $row['asset_type'];
                  $contact_name_select = $row['contact_name'];

                ?>
                  <li class="list-group-item">
                    <div class="form-check">
                      <input type="checkbox" class="form-check-input" name="assets[]" value="<?php echo $asset_id_select; ?>" <?php if(in_array($asset_id_select,$asset_licenses_array)){ echo "checked"; } ?>>
                      <label class="form-check-label ml-2"><?php echo "$asset_name_select - $contact_name_select"; ?></label>
                    </div>
                  </li>

                <?php
                }
                ?>

              </ul>
            
            </div>

            <div class="tab-pane fade" id="pills-user-licenses<?php echo $software_id; ?>">

              <div class="alert alert-info">
                Select Users that are licensed for this software
              </div>

              <ul class="list-group">

                <?php
                $sql_contacts_select = mysqli_query($mysqli,"SELECT * FROM contacts WHERE contact_client_id = $client_id ORDER BY contact_name ASC");

                while($row = mysqli_fetch_array($sql_contacts_select)){
                  $contact_id_select = $row['contact_id'];
                  $contact_name_select = $row['contact_name'];
                  $contact_email_select = $row['contact_email'];

                ?>
                  <li class="list-group-item">
                    <div class="form-check">
                      <input type="checkbox" class="form-check-input" name="contacts[]" value="<?php echo $contact_id_select; ?>" <?php if(in_array("$contact_id_select",$contact_licenses_array)){ echo "checked"; } ?>>
                      <label class="form-check-label ml-2"><?php echo "$contact_name_select - $contact_email_select"; ?></label>
                    </div>
                  </li>

                <?php
                }
                ?>

              </ul>
            
            </div>

            <div class="tab-pane fade" id="pills-notes<?php echo $software_id; ?>">
              
              <textarea class="form-control" rows="8" placeholder="Enter some notes" name="notes"><?php echo $software_notes; ?></textarea>

            </div>

            <div class="tab-pane fade" id="pills-login<?php echo $software_id; ?>">

              <div class="form-group">
                <label>Username</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                  </div>
                  <input type="text" class="form-control" name="username" placeholder="Username" value="<?php echo $login_username; ?>">
                </div>
              </div>
              
              <div class="form-group">
                <label>Password</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                  </div>
                  <input type="password" class="form-control" data-toggle="password" name="password" placeholder="Password" value="<?php echo $login_password; ?>">
                  <div class="input-group-append">
                    <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                  </div>
                </div>
              </div>
            
            </div>

          </div>

        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_software" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>