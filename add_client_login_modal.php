<div class="modal" id="addClientLoginModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-lock"></i> New Login</h5>
        <button type="button" class="close" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
        <div class="modal-body">  
          <div class="form-group">
            <label>Description</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-info-circle"></i></span>
              </div>
              <input type="text" class="form-control" name="description" required autofocus>
            </div>
          </div>
          <div class="form-group">
            <label>Username</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-user"></i></span>
              </div>
              <input type="text" class="form-control" name="username" required>
            </div>
          </div>
          <div class="form-group">
            <label>Password</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-lock"></i></span>
              </div>
              <input type="text" class="form-control" name="password" required>
            </div>
          </div>
          <center><a class="btn btn-link" data-toggle="collapse" href="#optionsCollapse" role="button" aria-expanded="false" aria-controls="optionsCollapse">Link Options</a></center>
          <div class="collapse multi-collapse" id="optionsCollapse">
            <ul class="nav nav-pills mb-3" id="pills-tab" role="tablist">
              <li class="nav-item">
                <a class="nav-link" id="pills-vendor-tab" data-toggle="pill" href="#pills-vendor" role="tab" aria-controls="pills-vendor" aria-selected="true">Vendor</a>
              </li>
              <li class="nav-item">
                <a class="nav-link" id="pills-asset-tab" data-toggle="pill" href="#pills-asset" role="tab" aria-controls="pills-asset" aria-selected="false">Asset</a>
              </li>
              <li class="nav-item">
                <a class="nav-link" id="pills-application-tab" data-toggle="pill" href="#pills-application" role="tab" aria-controls="pill-application" aria-selected="false">Application</a>
              </li>
            </ul>
            <div class="tab-content" id="pills-tabContent">
              <div class="tab-pane fade" id="pills-vendor" role="tabpanel" aria-labelledby="pills-vendor-tab">
                <div class="form-group">
                  <label>Vendor</label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text"><i class="fa fa-building"></i></span>
                    </div>
                    <select class="form-control" name="vendor">
                      <option value="">- Vendor -</option>
                      <?php 
                      
                      $sql = mysqli_query($mysqli,"SELECT * FROM client_vendors WHERE client_id = $client_id"); 
                      while($row = mysqli_fetch_array($sql)){
                        $vendor_id = $row['client_vendor_id'];
                        $vendor_name = $row['client_vendor_name'];
                      ?>
                        <option value="<?php echo $vendor_id; ?>"><?php echo $vendor_name; ?></option>
                      
                      <?php
                      }
                      ?>
                    </select>
                  </div>
                </div>
              </div>
              <div class="tab-pane fade" id="pills-asset" role="tabpanel" aria-labelledby="pills-asset-tab">
                <div class="form-group">
                  <label>Asset</label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text"><i class="fa fa-tag"></i></span>
                    </div>
                    <select class="form-control" name="asset">
                      <option value="">- Asset -</option>
                      <?php 
                      
                      $sql = mysqli_query($mysqli,"SELECT * FROM client_assets WHERE client_id = $client_id"); 
                      while($row = mysqli_fetch_array($sql)){
                        $asset_id = $row['client_asset_id'];
                        $asset_name = $row['client_asset_name'];
                      ?>
                        <option value="<?php echo $asset_id; ?>"><?php echo $asset_name; ?></option>
                      
                      <?php
                      }
                      ?>
                    </select>
                  </div>
                </div>
              </div>
              <div class="tab-pane fade" id="pills-application" role="tabpanel" aria-labelledby="pills-application-tab">
                <div class="form-group">
                  <label>Application</label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text"><i class="fa fa-box"></i></span>
                    </div>
                    <select class="form-control" name="application">
                      <option value="">- Application -</option>
                      <?php 
                      
                      $sql = mysqli_query($mysqli,"SELECT * FROM client_applications WHERE client_id = $client_id"); 
                      while($row = mysqli_fetch_array($sql)){
                        $client_application_id = $row['client_application_id'];
                        $client_application_name = $row['client_application_name'];
                      ?>
                        <option value="<?php echo $client_application_id; ?>"><?php echo $client_application_name; ?></option>
                      
                      <?php
                      }
                      ?>
                    </select>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_client_login" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>