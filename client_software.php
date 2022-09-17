<?php 

if(!empty($_GET['sb'])){
  $sb = mysqli_real_escape_string($mysqli,$_GET['sb']);
}else{
  $sb = "software_name";
}

//Rebuild URL
$url_query_strings_sb = http_build_query(array_merge($_GET,array('sb' => $sb, 'o' => $o)));

$sql = mysqli_query($mysqli,"SELECT SQL_CALC_FOUND_ROWS * FROM software 
  LEFT JOIN logins ON login_software_id = software_id
  WHERE software_client_id = $client_id 
  AND (software_name LIKE '%$q%' OR software_type LIKE '%$q%' OR software_key LIKE '%$q%') 
  ORDER BY $sb $o LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli,"SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
  <div class="card-header py-2">
    <h3 class="card-title mt-2"><i class="fa fa-fw fa-cube"></i> Licenses</h3>
    <div class="card-tools">
      <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addSoftwareModal"><i class="fas fa-fw fa-plus"></i> New License</button>
    </div>
  </div>
  <div class="card-body">
    <form autocomplete="off">
      <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
      <input type="hidden" name="tab" value="<?php echo strip_tags($_GET['tab']); ?>">
      <div class="row">
        
        <div class="col-md-4">
          <div class="input-group mb-3 mb-md-0">
            <input type="search" class="form-control" name="q" value="<?php if(isset($q)){echo stripslashes($q);} ?>" placeholder="Search Licenses">
            <div class="input-group-append">
              <button class="btn btn-dark"><i class="fa fa-search"></i></button>
            </div>
          </div>
        </div>

        <div class="col-md-8">
          <div class="float-right">
            <a href="post.php?export_client_<?php echo strip_tags($_GET['tab']); ?>_csv=<?php echo $client_id; ?>" class="btn btn-default"><i class="fa fa-fw fa-download"></i> Export</a>
          </div>
        </div>

      </div>
    </form>
    <hr>
    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover">
        <thead class="text-dark <?php if($num_rows[0] == 0){ echo "d-none"; } ?>">
          <tr>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=software_name&o=<?php echo $disp; ?>">Software</a></th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=software_type&o=<?php echo $disp; ?>">Type</a></th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=software_license_type&o=<?php echo $disp; ?>">License Type</a></th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=software_seats&o=<?php echo $disp; ?>">Seats</a></th>
            <th></th>
            <th class="text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php
    
          while($row = mysqli_fetch_array($sql)){    
            $software_id = $row['software_id'];
            $software_name = $row['software_name'];
            $software_version = $row['software_version'];
            $software_type = $row['software_type'];
            $software_license_type = $row['software_license_type'];
            $software_key = $row['software_key'];
            $software_seats = $row['software_seats'];
            $software_purchase = $row['software_purchase'];
            $software_expire = $row['software_expire'];
            $software_notes = $row['software_notes'];

            // Get Login
            $login_id = $row['login_id'];
            $login_username = $row['login_username'];
            $login_password = decryptLoginEntry($row['login_password']);

            $seat_count = 0;

            // Asset Licenses
            $asset_licenses_sql = mysqli_query($mysqli,"SELECT asset_id FROM software_assets WHERE software_id = $software_id");
            $asset_licenses_array = array();
            while($row = mysqli_fetch_array($asset_licenses_sql)){
              $asset_licenses_array[] = $row['asset_id'];
              $seat_count = $seat_count + 1;
            }
            $asset_licenses = implode(',',$asset_licenses_array);

            // Contact Licenses
            $contact_licenses_sql = mysqli_query($mysqli,"SELECT contact_id FROM software_contacts WHERE software_id = $software_id");
            $contact_licenses_array = array();
            while($row = mysqli_fetch_array($contact_licenses_sql)){
              $contact_licenses_array[] = $row['contact_id'];
              $seat_count = $seat_count + 1;
            }
            $contact_licenses = implode(',',$contact_licenses_array);

            

          ?>
          <tr>
            <td><a class="text-dark" href="#" data-toggle="modal" data-target="#editSoftwareModal<?php echo $software_id; ?>"><?php echo "$software_name<br><span class='text-secondary'>$software_version</span>"; ?></a></td>
            <td><?php echo $software_type; ?></td>
            <td><?php echo $software_license_type; ?></td>
            <td><?php echo "$seat_count / $software_seats"; ?></td>
            <td>
              <?php
              if($login_id > 0){
              ?>  
              <button type="button" class="btn btn-dark btn-sm" data-toggle="modal" data-target="#viewPasswordModal<?php echo $login_id; ?>"><i class="fas fa-key"></i></button>

              <div class="modal" id="viewPasswordModal<?php echo $login_id; ?>" tabindex="-1">
                <div class="modal-dialog">
                  <div class="modal-content bg-dark">
                    <div class="modal-header">
                      <h5 class="modal-title"><i class="fa fa-fw fa-key"></i> <?php echo $software_name; ?></h5>
                      <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                      </button>
                    </div>
                    <div class="modal-body bg-white">
                      <div class="form-group">
                        <div class="input-group">
                          <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-user"></i></span>
                          </div>
                          <input type="text" class="form-control" value="<?php echo $login_username; ?>" readonly>
                        </div>
                      </div>
                      <div class="form-group">
                        <div class="input-group">
                          <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-lock"></i></span>
                          </div>
                          <input type="text" class="form-control" value="<?php echo $login_password; ?>" readonly>
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
                <button class="btn btn-secondary btn-sm" data-toggle="dropdown">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu">
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editSoftwareModal<?php echo $software_id; ?>">Edit</a>
                  <?php if($session_user_role == 3) { ?>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item text-danger" href="post.php?delete_software=<?php echo $software_id; ?>">Delete</a>
                  <?php } ?>
                </div>
              </div> 
            </td>
          </tr>

          <?php

          include("client_software_edit_modal.php");
          }
          
          ?>

        </tbody>
      </table>      
    </div>
    <?php include("pagination.php"); ?>
  </div>
</div>

<?php include("client_software_add_modal.php"); ?>