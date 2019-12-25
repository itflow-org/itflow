<div class="modal" id="editLoginModal<?php echo $login_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header text-white">
        <h5 class="modal-title"><i class="fa fa-fw fa-lock mr-2"></i><?php echo $login_description; ?></h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="login_id" value="<?php echo $login_id; ?>">
        <div class="modal-body bg-white">  

          <ul class="nav nav-pills nav-justified mb-3" id="pills-tab<?php echo $login_id; ?>">
            <li class="nav-item">
              <a class="nav-link active" id="pills-login-tab<?php echo $login_id; ?>" data-toggle="pill" href="#pills-login<?php echo $login_id; ?>">Login</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" id="pills-link-tab<?php echo $login_id; ?>" data-toggle="pill" href="#pills-link<?php echo $login_id; ?>">Link</a>
            </li>
          </ul>

          <hr>
          
          <div class="tab-content" id="pills-tabContent<?php echo $login_id; ?>">

            <div class="tab-pane fade show active" id="pills-login<?php echo $login_id; ?>">

              <div class="form-group">
                <label>Description <strong class="text-danger">*</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-info-circle"></i></span>
                  </div>
                  <input type="text" class="form-control" name="description" placeholder="Description of the login" value="<?php echo $login_description; ?>" required>
                </div>
              </div>
            
              <div class="form-group">
                <label>Username <strong class="text-danger">*</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                  </div>
                  <input type="text" class="form-control" name="username" placeholder="Username" value="<?php echo $login_username; ?>" required>
                </div>
              </div>
              
              <div class="form-group">
                <label>Password <strong class="text-danger">*</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                  </div>
                  <input type="text" class="form-control" name="password" placeholder="Password" value="<?php echo $login_password; ?>" required>
                </div>
              </div>

            </div>

            <div class="tab-pane fade" id="pills-link<?php echo $login_id; ?>">
  
              <div class="form-group">
                <label>Web Link</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-link"></i></span>
                  </div>
                  <input type="url" class="form-control" name="web_link" placeholder="Please include http://" value="<?php echo $login_web_link; ?>">
                </div>
              </div>

              <div class="form-group">
                <label>Vendor</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                  </div>
                  <select class="form-control select2" name="vendor">
                    <option value="0">- None -</option>
                    <?php 
                    
                    $sql_vendors = mysqli_query($mysqli,"SELECT * FROM vendors WHERE client_id = $client_id"); 
                    while($row = mysqli_fetch_array($sql_vendors)){
                      $vendor_id_select = $row['vendor_id'];
                      $vendor_name_select = $row['vendor_name'];
                    ?>
                      <option <?php if($vendor_id == $vendor_id_select){ echo "selected"; } ?> value="<?php echo $vendor_id_select; ?>"><?php echo $vendor_name_select; ?></option>
                    
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
                  <select class="form-control select2" name="asset">
                    <option value="0">- None -</option>
                    <?php 
                    
                    $sql_assets = mysqli_query($mysqli,"SELECT * FROM assets WHERE client_id = $client_id"); 
                    while($row = mysqli_fetch_array($sql_assets)){
                      $asset_id_select = $row['asset_id'];
                      $asset_name_select = $row['asset_name'];
                    ?>
                      <option <?php if($asset_id == $asset_id_select){ echo "selected"; } ?> value="<?php echo $asset_id_select; ?>"><?php echo $asset_name_select; ?></option>
                    
                    <?php
                    }
                    ?>
                  </select>
                </div>
              </div>
            
              <div class="form-group">
                <label>Software</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-box"></i></span>
                  </div>
                  <select class="form-control select2" name="software">
                    <option value="0">- None -</option>
                    <?php 
                    
                    $sql_software = mysqli_query($mysqli,"SELECT * FROM software WHERE client_id = $client_id"); 
                    while($row = mysqli_fetch_array($sql_applications)){
                      $software_id_select = $row['software_id'];
                      $software_name_select = $row['software_name'];
                    ?>
                      <option <?php if($software_id == $software_id_select){ echo "selected"; } ?> value="<?php echo $software_id_select; ?>"><?php echo $software_name_select; ?></option>
                    
                    <?php
                    }
                    ?>
                  </select>
                </div>
              </div>

            </div>
          </div>
        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_login" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>