<div class="modal" id="editClientAssetModal<?php echo $client_asset_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title text-white"><i class="fa fa-fw fa-<?php echo $device_icon; ?> mr-2"></i>Edit Asset</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="client_asset_id" value="<?php echo $client_asset_id; ?>">
        <input type="hidden" name="client_login_id" value="<?php echo $client_login_id; ?>">
        <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
        
        <div class="modal-body bg-white">
          
          <ul class="nav nav-pills nav-justified mb-3" id="pills-tab<?php echo $client_asset_id; ?>" role="tablist">
            <li class="nav-item">
              <a class="nav-link active" id="pills-basic-tab<?php echo $client_asset_id; ?>" data-toggle="pill" href="#pills-basic<?php echo $client_asset_id; ?>" role="tab" aria-controls="pills-home<?php echo $client_asset_id; ?>" aria-selected="true">Basic</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" id="pills-assignment-tab<?php echo $client_asset_id; ?>" data-toggle="pill" href="#pills-assignment<?php echo $client_asset_id; ?>" role="tab" aria-controls="pills-assignment<?php echo $client_asset_id; ?>" aria-selected="false">Assignment</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" id="pills-purchase-tab<?php echo $client_asset_id; ?>" data-toggle="pill" href="#pills-purchase<?php echo $client_asset_id; ?>" role="tab" aria-controls="pills-purchase<?php echo $client_asset_id; ?>" aria-selected="false">Purchase</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" id="pills-login-tab<?php echo $client_asset_id; ?>" data-toggle="pill" href="#pills-login<?php echo $client_asset_id; ?>" role="tab" aria-controls="pills-login<?php echo $client_asset_id; ?>" aria-selected="false">Login</a>
            </li>
          </ul>
          
          <hr>
          
          <div class="tab-content" id="pills-tabContent<?php echo $client_asset_id; ?>">

            <div class="tab-pane fade show active" id="pills-basic<?php echo $client_asset_id; ?>" role="tabpanel" aria-labelledby="pills-basic-tab<?php echo $client_asset_id; ?>">
          
              <div class="form-group">
                <label>Asset Name</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                  </div>
                  <input type="text" class="form-control" name="name" placeholder="Name the asset" value="<?php echo $client_asset_name; ?>" required>
                </div>
              </div>
              
              <div class="form-group">
                <label>Asset Type</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-tags"></i></span>
                  </div>
                  <select class="form-control" name="type" required>
                    <?php foreach($asset_types_array as $asset_type) { ?>
                    <option <?php if($client_asset_type == $asset_type) { echo "selected"; } ?>><?php echo $asset_type; ?></option>
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
                  <input type="text" class="form-control" name="make" placeholder="Manufacturer" value="<?php echo $client_asset_make; ?>" required>
                </div>
              </div>
              
              <div class="form-group">
                <label>Model</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                  </div>
                  <input type="text" class="form-control" name="model" placeholder="Model Number" value="<?php echo $client_asset_model; ?>" required>
                </div>
              </div>
              
              <div class="form-group">
                <label>Serial Number</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-barcode"></i></span>
                  </div>
                  <input type="text" class="form-control" name="serial" placeholder="Serial number" value="<?php echo $client_asset_serial; ?>" required>
                </div>
              </div>
            
            </div>

            <div class="tab-pane fade" id="pills-assignment<?php echo $client_asset_id; ?>" role="tabpanel" aria-labelledby="pills-assignment-tab<?php echo $client_asset_id; ?>">

              <div class="form-group">
                <label>Location</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                  </div>
                  <select class="form-control" name="location">
                    <option value="">- Location -</option>
                    <?php 
                    
                    $sql_client_locations = mysqli_query($mysqli,"SELECT * FROM client_locations WHERE client_id = $client_id"); 
                    while($row = mysqli_fetch_array($sql_client_locations)){
                      $location_id = $row['client_location_id'];
                      $location_name = $row['client_location_name'];
                    ?>
                    <option <?php if($client_location_id == $location_id){ echo "selected"; } ?> value="<?php echo $location_id; ?>"><?php echo $location_name; ?></option>
                    
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
                    
                    $sql_client_contacts = mysqli_query($mysqli,"SELECT * FROM client_contacts WHERE client_id = $client_id"); 
                    while($row = mysqli_fetch_array($sql_client_contacts)){
                      $contact_id = $row['client_contact_id'];
                      $contact_name = $row['client_contact_name'];
                    ?>
                    <option <?php if($client_contact_id == $contact_id){ echo "selected"; } ?> value="<?php echo $contact_id; ?>"><?php echo $contact_name; ?></option>
                    
                    <?php
                    }
                    ?>
                  </select>
                </div>
              </div>
            </div>

            <div class="tab-pane fade" id="pills-purchase<?php echo $client_asset_id; ?>" role="tabpanel" aria-labelledby="pills-purchase-tab<?php echo $client_asset_id; ?>">

              <div class="form-group">
                <label>Vendor</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                  </div>
                  <select class="form-control" name="vendor">
                    <option value="">- Vendor -</option>
                    <?php 
                    
                    $sql_client_vendors = mysqli_query($mysqli,"SELECT * FROM client_vendors WHERE client_id = $client_id"); 
                    while($row = mysqli_fetch_array($sql_client_vendors)){
                      $vendor_id = $row['client_vendor_id'];
                      $vendor_name = $row['client_vendor_name'];
                    ?>
                    <option <?php if($client_vendor_id == $vendor_id){ echo "selected"; } ?> value="<?php echo $vendor_id; ?>"><?php echo $vendor_name; ?></option>
                    
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
                  <input type="date" class="form-control" name="purchase_date" value="<?php echo $client_asset_purchase_date; ?>">
                </div>
              </div>
              
              <div class="form-group">
                <label>Warranty Expire</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                  </div>
                  <input type="date" class="form-control" name="warranty_expire" value="<?php echo $client_asset_warranty_expire; ?>">
                </div>
              </div>
            
            </div>

            <div class="tab-pane fade" id="pills-login<?php echo $client_asset_id; ?>" role="tabpanel" aria-labelledby="pills-login-tab<?php echo $client_asset_id; ?>">
              <div class="form-group">
                <label>Username</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa  fa-fw fa-user"></i></span>
                  </div>
                  <input type="text" class="form-control" name="username" placeholder="Username" value="<?php echo $client_login_username; ?>">
                </div>
              </div>
              <div class="form-group">
                <label>Password</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                  </div>
                  <input type="text" class="form-control" name="password" placeholder="Password" value="<?php echo $client_login_password; ?>">
                </div>
              </div>

            </div>

          </div>
        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_client_asset" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>