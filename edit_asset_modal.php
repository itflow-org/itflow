<div class="modal" id="editAssetModal<?php echo $asset_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title text-white"><i class="fa fa-fw fa-<?php echo $device_icon; ?> mr-2"></i><?php echo $asset_name; ?></h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="asset_id" value="<?php echo $asset_id; ?>">
        <input type="hidden" name="login_id" value="<?php echo $login_id; ?>">
        <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
        
        <div class="modal-body bg-white">
          
          <ul class="nav nav-pills nav-justified mb-3" id="pills-tab<?php echo $asset_id; ?>" role="tablist">
            <li class="nav-item">
              <a class="nav-link active" id="pills-basic-tab<?php echo $asset_id; ?>" data-toggle="pill" href="#pills-basic<?php echo $asset_id; ?>" role="tab" aria-controls="pills-home<?php echo $asset_id; ?>" aria-selected="true">Basic</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" id="pills-assignment-tab<?php echo $asset_id; ?>" data-toggle="pill" href="#pills-assignment<?php echo $asset_id; ?>" role="tab" aria-controls="pills-assignment<?php echo $asset_id; ?>" aria-selected="false">Assignment</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" id="pills-purchase-tab<?php echo $asset_id; ?>" data-toggle="pill" href="#pills-purchase<?php echo $asset_id; ?>" role="tab" aria-controls="pills-purchase<?php echo $asset_id; ?>" aria-selected="false">Purchase</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" id="pills-login-tab<?php echo $asset_id; ?>" data-toggle="pill" href="#pills-login<?php echo $asset_id; ?>" role="tab" aria-controls="pills-login<?php echo $asset_id; ?>" aria-selected="false">Login</a>
            </li>
          </ul>
          
          <hr>
          
          <div class="tab-content" id="pills-tabContent<?php echo $asset_id; ?>">

            <div class="tab-pane fade show active" id="pills-basic<?php echo $asset_id; ?>" role="tabpanel" aria-labelledby="pills-basic-tab<?php echo $asset_id; ?>">
          
              <div class="form-group">
                <label>Asset Name <strong class="text-danger">*</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                  </div>
                  <input type="text" class="form-control" name="name" placeholder="Name the asset" value="<?php echo $asset_name; ?>" required>
                </div>
              </div>
              
              <div class="form-group">
                <label>Asset Type <strong class="text-danger">*</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-tags"></i></span>
                  </div>
                  <select class="form-control selectpicker show-tick" data-live-search="true" name="type" required>
                    <?php foreach($asset_types_array as $asset_type_select => $asset_icon_select) { ?>
                    <option data-icon="text-secondary fa fa-fw <?php echo $asset_icon_select; ?>" <?php if($asset_type_select == $asset_type) { echo "selected"; } ?>><?php echo $asset_type_select; ?></option>
                    <?php } ?>
                  </select>
                </div>
              </div>        
              
              <div class="form-group">
                <label>Make <strong class="text-danger">*</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                  </div>
                  <input type="text" class="form-control" name="make" placeholder="Manufacturer" value="<?php echo $asset_make; ?>" required>
                </div>
              </div>
              
              <div class="form-group">
                <label>Model <strong class="text-danger">*</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                  </div>
                  <input type="text" class="form-control" name="model" placeholder="Model Number" value="<?php echo $asset_model; ?>" required>
                </div>
              </div>
              
              <div class="form-group">
                <label>Serial Number <strong class="text-danger">*</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-barcode"></i></span>
                  </div>
                  <input type="text" class="form-control" name="serial" placeholder="Serial number" value="<?php echo $asset_serial; ?>" required>
                </div>
              </div>
            
            </div>

            <div class="tab-pane fade" id="pills-assignment<?php echo $asset_id; ?>" role="tabpanel" aria-labelledby="pills-assignment-tab<?php echo $asset_id; ?>">

              <div class="form-group">
                <label>Location</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                  </div>
                  <select class="form-control selectpicker show-tick" data-live-search="true" name="location">
                    <option value="">- Location -</option>
                    <?php 
                    
                    $sql_locations = mysqli_query($mysqli,"SELECT * FROM locations WHERE client_id = $client_id"); 
                    while($row = mysqli_fetch_array($sql_locations)){
                      $location_id_select = $row['location_id'];
                      $location_name_select = $row['location_name'];
                    ?>
                    <option <?php if($location_id == $location_id_select){ echo "selected"; } ?> value="<?php echo $location_id_select; ?>"><?php echo $location_name_select; ?></option>
                    
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
                  <select class="form-control selectpicker show-tick" data-live-search="true" name="contact">
                    <option value="">- Contact -</option>
                    <?php 
                    
                    $sql_contacts = mysqli_query($mysqli,"SELECT * FROM contacts WHERE client_id = $client_id"); 
                    while($row = mysqli_fetch_array($sql_contacts)){
                      $contact_id_select = $row['contact_id'];
                      $contact_name_select = $row['contact_name'];
                    ?>
                    <option <?php if($contact_id == $contact_id_select){ echo "selected"; } ?> value="<?php echo $contact_id_select; ?>"><?php echo $contact_name_select; ?></option>
                    
                    <?php
                    }
                    ?>
                  </select>
                </div>
              </div>

              <div class="form-group">
                <label>Network</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-network-wired"></i></span>
                  </div>
                  <select class="form-control selectpicker show-tick" data-live-search="true" name="network">
                    <option value="">- Network -</option>
                    <?php 
                    
                    $sql_networks = mysqli_query($mysqli,"SELECT * FROM networks WHERE client_id = $client_id"); 
                    while($row = mysqli_fetch_array($sql_networks)){
                      $network_id_select = $row['network_id'];
                      $network_name_select = $row['network_name'];
                      $network_select = $row['network'];

                    ?>
                    <option <?php if($network_id == $network_id_select){ echo "selected"; } ?> value="<?php echo $network_id_select; ?>"><?php echo $network_name_select; ?> - <?php echo $network_select; ?></option>
                    
                    <?php
                    }
                    ?>
                  </select>
                </div>
              </div>

              <div class="form-group">
                <label>IP</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-ethernet"></i></span>
                  </div>
                  <input type="text" class="form-control" name="ip" value="<?php echo $asset_ip; ?>" placeholder="IP Address" data-inputmask="'alias': 'ip'">
                </div>
              </div>
            
            </div>

            <div class="tab-pane fade" id="pills-purchase<?php echo $asset_id; ?>" role="tabpanel" aria-labelledby="pills-purchase-tab<?php echo $asset_id; ?>">

              <div class="form-group">
                <label>Vendor</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                  </div>
                  <select class="form-control selectpicker show-tick" data-live-search="true" name="vendor">
                    <option value="">- Vendor -</option>
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
                <label>Purchase Date</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-shopping-cart"></i></span>
                  </div>
                  <input type="date" class="form-control" name="purchase_date" value="<?php echo $asset_purchase_date; ?>">
                </div>
              </div>
              
              <div class="form-group">
                <label>Warranty Expire</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                  </div>
                  <input type="date" class="form-control" name="warranty_expire" value="<?php echo $asset_warranty_expire; ?>">
                </div>
              </div>
            
            </div>

            <div class="tab-pane fade" id="pills-login<?php echo $asset_id; ?>" role="tabpanel" aria-labelledby="pills-login-tab<?php echo $asset_id; ?>">
              <div class="form-group">
                <label>Username</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa  fa-fw fa-user"></i></span>
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
                  <input type="text" class="form-control" name="password" placeholder="Password" value="<?php echo $login_password; ?>">
                </div>
              </div>

            </div>

          </div>
        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_asset" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>