<?php $sql = mysqli_query($mysqli,"SELECT * FROM vendors WHERE client_id = $client_id ORDER BY vendor_id DESC"); ?>

<div class="card">
  <div class="card-header">
    <h6 class="float-left mt-1"><i class="fa fa-fw fa-building mr-2"></i>Vendors</h6>
    <button class="btn btn-primary btn-sm float-right" data-toggle="modal" data-target="#addVendorModal"><i class="fa fa-plus"></i></button>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover" id="dataTable" width="100%" cellspacing="0">
        <thead>
          <tr>
            <th>Vendor</th>
            <th>Description</th>
            <th>Account Number</th>
            <th></th>
            <th class="text-center">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $vendor_id = $row['vendor_id'];
            $vendor_name = $row['vendor_name'];
            $vendor_description = $row['vendor_description'];
            $vendor_account_number = $row['vendor_account_number'];
            $vendor_address = $row['vendor_address'];
            $vendor_city = $row['vendor_city'];
            $vendor_state = $row['vendor_state'];
            $vendor_zip = $row['vendor_zip'];
            $vendor_phone = $row['vendor_phone'];
            if(strlen($vendor_phone)>2){ 
              $vendor_phone = substr($row['vendor_phone'],0,3)."-".substr($row['vendor_phone'],3,3)."-".substr($row['vendor_phone'],6,4);
            }
            $vendor_email = $row['vendor_email'];
            $vendor_website = $row['vendor_website'];

            $sql2 = mysqli_query($mysqli,"SELECT * FROM client_logins WHERE vendor_id = $vendor_id");
            $row = mysqli_fetch_array($sql2);
            $client_login_id = $row['client_login_id'];
            $client_login_username = $row['client_login_username'];
            $client_login_password = $row['client_login_password'];
            $vendor_id_relation = $row['vendor_id'];
              
          ?>
          <tr>
            <td><?php echo $vendor_name; ?></td>
            <td><?php echo $vendor_description; ?></td>
            <td><?php echo $vendor_account_number; ?></td>
            <td>
              <?php
              if($vendor_id == $vendor_id_relation){
              ?>  
              <button type="button" class="btn btn-dark btn-sm" data-toggle="modal" data-target="#viewPasswordModal<?php echo $client_login_id; ?>"><i class="fas fa-key"></i></button>

              <div class="modal" id="viewPasswordModal<?php echo $client_login_id; ?>" tabindex="-1">
                <div class="modal-dialog modal-sm">
                  <div class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title"><i class="fa fa-key"></i> <?php echo $vendor_name; ?> Login</h5>
                      <button type="button" class="close" data-dismiss="modal">
                        <span aria-hidden="true">&times;</span>
                      </button>
                    </div>
                    <div class="modal-body">
                      <div class="form-group">
                        <div class="input-group">
                          <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-user"></i></span>
                          </div>
                          <input type="text" class="form-control" value="<?php echo $client_login_username; ?>" readonly>
                        </div>
                      </div>
                      <div class="form-group">
                        <div class="input-group">
                          <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-lock"></i></span>
                          </div>
                          <input type="text" class="form-control" value="<?php echo $client_login_password; ?>" readonly>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <?php
              }
              ?>
            </td>

            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editVendorModal<?php echo $vendor_id; ?>">Edit</a>
                  <a class="dropdown-item" href="post.php?delete_vendor=<?php echo $vendor_id; ?>">Delete</a>
                </div>
              </div>
              <?php include("edit_vendor_modal.php"); ?>      
            </td>
          </tr>

          <?php
          
          }
          
          ?>

        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include("add_vendor_modal.php"); ?>