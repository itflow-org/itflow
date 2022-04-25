<div class="modal" id="addLoginModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-fw fa-key"></i> New Login</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
        <div class="modal-body bg-white">  

          <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
              <a class="nav-link active" data-toggle="pill" href="#pills-details">Details</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" data-toggle="pill" href="#pills-relation">Relation</a>
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
                    <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                  </div>
                  <input type="text" class="form-control" name="name" placeholder="Name of Login" required autofocus>
                </div>
              </div>
            
              <div class="form-group">
                <label>Username</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                  </div>
                  <input type="text" class="form-control" name="username" placeholder="Username">
                </div>
              </div>
              
              <div class="form-group">
                <label>Password <strong class="text-danger">*</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                  </div>
                  <input type="password" class="form-control" data-toggle="password" id="password" name="password" placeholder="Password" required autocomplete="new-password">
                  <div class="input-group-append">
                    <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                  </div>
                  <div class="input-group-append">
                    <span class="btn btn-default"><i class="fa fa-fw fa-question" onclick="generatePassword()"></i></span>
                  </div>
                </div>
              </div>

              <div class="form-group">
                <label>OTP</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                  </div>
                  <input type="password" class="form-control" data-toggle="password" name="otp_secret" placeholder="Insert secret key">
                  <div class="input-group-append">
                    <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                  </div>
                </div>
              </div>

              <div class="form-group">
                <label>URL/Host</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-link"></i></span>
                  </div>
                  <input type="text" class="form-control" name="uri" placeholder="ex. google.com">
                </div>
              </div>

            </div>

            <div class="tab-pane fade" id="pills-relation">

              <div class="form-group">
                <label>Contact</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                  </div>
                  <select class="form-control" name="contact">
                    <option value="">- Contact -</option>
                    <?php 
                    
                    $sql = mysqli_query($mysqli,"SELECT * FROM contacts WHERE contact_client_id = $client_id ORDER BY contact_name ASC"); 
                    while($row = mysqli_fetch_array($sql)){
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
                <label>Vendor</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                  </div>
                  <select class="form-control" name="vendor">
                    <option value="">- Vendor -</option>
                    <?php 
                    
                    $sql_vendors = mysqli_query($mysqli,"SELECT * FROM vendors WHERE vendor_client_id = $client_id ORDER BY vendor_name ASC"); 
                    while($row = mysqli_fetch_array($sql_vendors)){
                      $vendor_id = $row['vendor_id'];
                      $vendor_name = $row['vendor_name'];
                    ?>
                      <option value="<?php echo $vendor_id; ?>"><?php echo $vendor_name; ?></option>
                    
                    <?php
                    }
                    ?>
                  </select>
                </div>
              </div>
            
              <div class="form-group">
                <label>Asset</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                  </div>
                  <select class="form-control" name="asset">
                    <option value="">- Asset -</option>
                    <?php 
                    
                    $sql = mysqli_query($mysqli,"SELECT * FROM assets WHERE asset_client_id = $client_id ORDER BY asset_name ASC"); 
                    while($row = mysqli_fetch_array($sql)){
                      $asset_id = $row['asset_id'];
                      $asset_name = $row['asset_name'];
                    ?>
                      <option value="<?php echo $asset_id; ?>"><?php echo $asset_name; ?></option>
                    
                    <?php
                    }
                    ?>
                  </select>
                </div>
              </div>
            
              <div class="form-group">
                <label>software</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-box"></i></span>
                  </div>
                  <select class="form-control" name="software">
                    <option value="">- software -</option>
                    <?php 
                    
                    $sql = mysqli_query($mysqli,"SELECT * FROM software WHERE software_client_id = $client_id ORDER BY software_name ASC"); 
                    while($row = mysqli_fetch_array($sql)){
                      $software_id = $row['software_id'];
                      $software_name = $row['software_name'];
                    ?>
                      <option value="<?php echo $software_id; ?>"><?php echo $software_name; ?></option>
                    
                    <?php
                    }
                    ?>
                  </select>
                </div>
              </div>

            </div>

            <div class="tab-pane fade" id="pills-notes">

              <div class="form-group">
                <textarea class="form-control" rows="8" placeholder="Enter some notes" name="note"></textarea>
              </div>

            </div>
            
          </div>
        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_login" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>
