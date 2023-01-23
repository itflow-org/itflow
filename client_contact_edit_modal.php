<div class="modal" id="editContactModal<?php echo $contact_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-fw fa-user-edit"></i> Editing: <strong><?php echo $contact_name; ?></strong></h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
        <input type="hidden" name="contact_id" value="<?php echo $contact_id; ?>">
        <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
        <input type="hidden" name="existing_file_name" value="<?php echo $contact_photo; ?>">
        <div class="modal-body bg-white">

          <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
              <a class="nav-link active" data-toggle="pill" href="#pills-details<?php echo $contact_id; ?>">Details</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" data-toggle="pill" href="#pills-photo<?php echo $contact_id; ?>">Photo</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" data-toggle="pill" href="#pills-portal<?php echo $contact_id; ?>">Portal</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" data-toggle="pill" href="#pills-notes<?php echo $contact_id; ?>">Notes</a>
            </li>
          </ul>

          <hr>
          
          <div class="tab-content">

            <div class="tab-pane fade show active" id="pills-details<?php echo $contact_id; ?>">

              <div class="form-group">
                <label>Name <strong class="text-danger">*</strong> / <span class="text-secondary">Primary Contact</span></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                  </div>
                  <input type="text" class="form-control" name="name" placeholder="Full Name" value="<?php echo $contact_name; ?>" required>
                  <div class="input-group-append">
                    <div class="input-group-text">
                      <input type="checkbox" name="primary_contact" value="1" <?php if ($contact_id == $primary_contact) { echo "checked"; } ?>>
                    </div>
                  </div>
                </div>
              </div>
              
              <div class="form-group">
                <label>Title / <span class="text-secondary">Important Contact</span></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-id-badge"></i></span>
                  </div>
                  <input type="text" class="form-control" name="title" placeholder="Title" value="<?php echo $contact_title; ?>">
                  <div class="input-group-append">
                    <div class="input-group-text">
                      <input type="checkbox" name="contact_important" value="1" <?php if ($contact_important == 1) { echo "checked"; } ?>>
                    </div>
                  </div>
                </div>
              </div>

              <div class="form-group">
                <label>Department</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                  </div>
                  <input type="text" class="form-control" name="department" placeholder="Department" value="<?php echo $contact_department; ?>">
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
                      <input type="text" class="form-control" name="phone" placeholder="Phone Number" value="<?php echo $contact_phone; ?>"> 
                    </div>
                  </div>
                </div>
                <div class="col-4">
                  <input type="text" class="form-control" name="extension" placeholder="Extension" value="<?php echo $contact_extension; ?>">
                </div>
              </div>

              <div class="form-group">
                <label>Mobile</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-mobile-alt"></i></span>
                  </div>
                  <input type="text" class="form-control" name="mobile" placeholder="Mobile Phone Number" value="<?php echo $contact_mobile; ?>"> 
                </div>
              </div>

              <div class="form-group">
                <label>Email</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                  </div>
                  <input type="email" class="form-control" name="email" placeholder="Email Address" value="<?php echo $contact_email; ?>">
                </div>
              </div>

              <div class="form-group">
                <label>Location</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                  </div>
                  <select class="form-control select2" name="location">
                    <option value="">- Location -</option>
                    <?php 
                    
                    $sql_locations = mysqli_query($mysqli,"SELECT * FROM locations WHERE (location_archived_at > '$contact_created_at' OR location_archived_at IS NULL) AND location_client_id = $client_id ORDER BY location_name ASC");
                    while ($row = mysqli_fetch_array($sql_locations)) {
                      $location_id_select = $row['location_id'];
                      $location_name_select = htmlentities($row['location_name']);
                    ?>
                    <option <?php if ($contact_location_id == $location_id_select) { echo "selected"; } ?> value="<?php echo $location_id_select; ?>"><?php echo $location_name_select; ?></option>
                    
                    <?php
                    }
                    ?>
                  </select>
                </div>
              </div>

            </div>

            <div class="tab-pane fade" id="pills-portal<?php echo $contact_id; ?>">

              <div class="form-group">
                <label>Login</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-user-circle"></i></span>
                  </div>
                  <select class="form-control select2" name="auth_method">
                    <option value="">- None -</option>
                    <option value="local" <?php if ($auth_method == "local") {echo "selected";} ?>>Local</option>
                    <option value="azure" <?php if ($auth_method == "azure") {echo "selected";} ?>>Azure</option>
                  </select>
                </div>
              </div>

              <?php if ($auth_method == "local") { ?>

                <div class="form-group">
                  <label>Password</label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                    </div>
                    <input type="password" class="form-control" data-toggle="password" name="contact_password" placeholder="Leave blank for no change" autocomplete="new-password">
                    <div class="input-group-append">
                      <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                    </div>
                  </div>
                </div>

              <?php } ?>

              <div class="form-check">
                <input type="checkbox" class="form-check-input" name="send_email" value=""/>
                <label class="form-check-label">Send user e-mail with login details?</label>
              </div>

            </div>

            <div class="tab-pane fade" id="pills-photo<?php echo $contact_id; ?>">

              <div class="mb-3 text-center">
                <?php if (!empty($contact_photo)) { ?>
                <img class="img-fluid" alt="contact_photo" src="<?php echo "uploads/clients/$session_company_id/$client_id/$contact_photo"; ?>">
                <?php }else{ ?>
                <span class="fa-stack fa-4x">
                  <i class="fa fa-circle fa-stack-2x text-secondary"></i>
                  <span class="fa fa-stack-1x text-white"><?php echo $contact_initials; ?></span>
                </span>
                <?php } ?>
              </div>

              <div class="form-group">
                <input type="file" class="form-control-file" name="file">
              </div>

            </div>

            <div class="tab-pane fade" id="pills-notes<?php echo $contact_id; ?>">
              
              <div class="form-group">
                <textarea class="form-control" rows="8" name="notes" placeholder="Enter some notes"><?php echo $contact_notes; ?></textarea>
              </div>

            </div>

          </div>

        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_contact" class="btn btn-primary"><i class="fas fa-check"></i> Save</button>
        </div>
      </form>
    </div>
  </div>
</div>
