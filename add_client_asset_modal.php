<div class="modal" id="addClientAssetModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header text-white">
        <h5 class="modal-title"><i class="fa fa-fw fa-tag mr-2"></i>New Asset</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
        <div class="modal-body bg-white">
          <ul class="nav nav-pills nav-justified mb-3" id="pills-tab" role="tablist">
            <li class="nav-item">
              <a class="nav-link active" id="pills-basic-tab" data-toggle="pill" href="#pills-basic" role="tab" aria-controls="pills-home" aria-selected="true">Basic</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" id="pills-assignment-tab" data-toggle="pill" href="#pills-assignment" role="tab" aria-controls="pills-assignment" aria-selected="false">Assignment</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" id="pills-purchase-tab" data-toggle="pill" href="#pills-purchase" role="tab" aria-controls="pills-purchase" aria-selected="false">Purchase</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" id="pills-login-tab" data-toggle="pill" href="#pills-login" role="tab" aria-controls="pills-login" aria-selected="false">Login</a>
            </li>
          </ul>
          <hr>
          <div class="tab-content" id="pills-tabContent">
            
            <div class="tab-pane fade show active" id="pills-basic" role="tabpanel" aria-labelledby="pills-basic-tab">
              
              <div class="form-group">
                <label>Asset Name</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                  </div>
                  <input type="text" class="form-control" name="name" placeholder="Name the asset" required autofocus>
                </div>
              </div>
              
              <div class="form-group">
                <label>Asset Type</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-tags"></i></span>
                  </div>
                  <select class="form-control" name="type" required>
                    <option value="">- Type -</option>
                    <?php foreach($asset_types_array as $asset_type) { ?>
                    <option><?php echo $asset_type; ?></option>
                    <?php } ?>
                  </select>
                </div>
              </div>        
              
              <div class="form-group">
                <label>Make</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                  </div>
                  <input type="text" class="form-control" name="make" placeholder="Manufacturer" required>
                </div>
              </div>
              
              <div class="form-group">
                <label>Model</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                  </div>
                  <input type="text" class="form-control" name="model" placeholder="Model Number" required>
                </div>
              </div>
              
              <div class="form-group">
                <label>Serial Number</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-barcode"></i></span>
                  </div>
                  <input type="text" class="form-control" name="serial" placeholder="Serial number" required>
                </div>
              </div>
            </div>

            <div class="tab-pane fade" id="pills-assignment" role="tabpanel" aria-labelledby="pills-assignment-tab">
              
              <div class="form-group">
                <label>Location</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                  </div>
                  <select class="form-control" name="location">
                    <option value="">- Location -</option>
                    <?php 
                    
                    $sql = mysqli_query($mysqli,"SELECT * FROM client_locations WHERE client_id = $client_id"); 
                    while($row = mysqli_fetch_array($sql)){
                      $location_id = $row['client_location_id'];
                      $location_name = $row['client_location_name'];
                    ?>
                    <option value="<?php echo $location_id; ?>"><?php echo $location_name; ?></option>
                    
                    <?php
                    }
                    ?>
                  </select>
                </div>
              </div>

              <div class="form-group">
                <label>Assigned To</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                  </div>
                  <select class="form-control" name="contact">
                    <option value="">- Contact -</option>
                    <?php 
                    
                    $sql = mysqli_query($mysqli,"SELECT * FROM client_contacts WHERE client_id = $client_id"); 
                    while($row = mysqli_fetch_array($sql)){
                      $client_contact_id = $row['client_contact_id'];
                      $client_contact_name = $row['client_contact_name'];
                    ?>
                    <option value="<?php echo $client_contact_id; ?>"><?php echo $client_contact_name; ?></option>
                    
                    <?php
                    }
                    ?>
                  </select>
                </div>
              </div>
            
            </div>
            
            <div class="tab-pane fade" id="pills-purchase" role="tabpanel" aria-labelledby="pills-purchase-tab">
              
              <div class="form-group">
                <label>Vendor</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                  </div>
                  <select class="form-control" name="vendor">
                    <option value="">- Vendor -</option>
                    <?php 
                    
                    $sql = mysqli_query($mysqli,"SELECT * FROM client_vendors WHERE client_id = $client_id"); 
                    while($row = mysqli_fetch_array($sql)){
                      $client_vendor_id = $row['client_vendor_id'];
                      $client_vendor_name = $row['client_vendor_name'];
                    ?>
                    <option value="<?php echo $client_vendor_id; ?>"><?php echo $client_vendor_name; ?></option>
                    
                    <?php
                    }
                    ?>
                  </select>
                </div>
              </div>
              
              <div class="form-group">
                <label>Purchase Date</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-shopping-cart"></i></span>
                  </div>
                  <input type="date" class="form-control" name="purchase_date">
                </div>
              </div>
              <div class="form-group">
                <label>Warranty Expire</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                  </div>
                  <input type="date" class="form-control" name="warranty_expire">
                </div>
              </div>

            </div>

            <div class="tab-pane fade" id="pills-login" role="tabpanel" aria-labelledby="pills-login-tab">

              <div class="form-group">
                <label>Username</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa  fa-fw fa-user"></i></span>
                  </div>
                  <input type="text" class="form-control" name="username" placeholder="Username">
                </div>
              </div>
              <div class="form-group">
                <label>Password</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                  </div>
                  <input type="text" class="form-control" name="password" placeholder="Password">
                </div>
              </div>

            </div>
          </div>
        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_client_asset" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>