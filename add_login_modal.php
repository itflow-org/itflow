<div class="modal" id="addLoginModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header text-white">
        <h5 class="modal-title"><i class="fa fa-fw fa-lock mr-2"></i>New Login</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
        <div class="modal-body bg-white">  

          <ul class="nav nav-pills nav-justified mb-3" id="pills-tab">
            <li class="nav-item">
              <a class="nav-link active" id="pills-login-tab" data-toggle="pill" href="#pills-login">Login</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" id="pills-link-tab" data-toggle="pill" href="#pills-link">Link</a>
            </li>
          </ul>

          <hr>
          
          <div class="tab-content" id="pills-tabContent">

            <div class="tab-pane fade show active" id="pills-login">

              <div class="form-group">
                <label>Description <strong class="text-danger">*</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-info-circle"></i></span>
                  </div>
                  <input type="text" class="form-control" name="description" placeholder="Description of the login" required autofocus>
                </div>
              </div>
            
              <div class="form-group">
                <label>Username <strong class="text-danger">*</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                  </div>
                  <input type="text" class="form-control" name="username" placeholder="Username" required>
                </div>
              </div>
              
              <div class="form-group">
                <label>Password <strong class="text-danger">*</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                  </div>
                  <input type="text" class="form-control" name="password" placeholder="Password" required>
                </div>
              </div>

            </div>

            <div class="tab-pane fade" id="pills-link">
  
              <div class="form-group">
                <label>Web Link</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-link"></i></span>
                  </div>
                  <input type="url" class="form-control" name="web_link" placeholder="Please include http://">
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
                    
                    $sql_vendors = mysqli_query($mysqli,"SELECT * FROM vendors WHERE client_id = $client_id"); 
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
                    
                    $sql = mysqli_query($mysqli,"SELECT * FROM assets WHERE client_id = $client_id"); 
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
                    
                    $sql = mysqli_query($mysqli,"SELECT * FROM software WHERE client_id = $client_id"); 
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