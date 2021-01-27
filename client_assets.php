<?php 

//Rebuild URL

$url_query_strings_sb = http_build_query(array_merge($_GET,array('sb' => $sb, 'o' => $o)));

//Paging
if(isset($_GET['p'])){
  $p = intval($_GET['p']);
  $record_from = (($p)-1)*$config_records_per_page;
  $record_to = $config_records_per_page;
}else{
  $record_from = 0;
  $record_to = $config_records_per_page;
  $p = 1;
}
  
if(isset($_GET['q'])){
  $q = mysqli_real_escape_string($mysqli,$_GET['q']);
}else{
  $q = "";
}

if(!empty($_GET['sb'])){
  $sb = mysqli_real_escape_string($mysqli,$_GET['sb']);
}else{
  $sb = "asset_name";
}

if(isset($_GET['o'])){
  if($_GET['o'] == 'ASC'){
    $o = "ASC";
    $disp = "DESC";
  }else{
    $o = "DESC";
    $disp = "ASC";
  }
}else{
  $o = "ASC";
  $disp = "DESC";
}

$sql = mysqli_query($mysqli,"SELECT SQL_CALC_FOUND_ROWS * FROM assets
  WHERE client_id = $client_id 
  AND (asset_name LIKE '%$q%' OR asset_type LIKE '%$q%' OR asset_ip LIKE '%$q%' OR asset_make LIKE '%$q%' OR asset_model LIKE '%$q%' OR asset_serial LIKE '%$q%') 
  ORDER BY $sb $o LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli,"SELECT FOUND_ROWS()"));
$total_found_rows = $num_rows[0];
$total_pages = ceil($total_found_rows / 10);

?>

<div class="card">
  <div class="card-header bg-dark text-white">
    <h6 class="float-left mt-1"><i class="fa fa-desktop"></i> Assets</h6>
    <button class="btn btn-primary btn-sm float-right" data-toggle="modal" data-target="#addAssetModal"><i class="fa fa-plus"></i></button>
  </div>
  <div class="card-body">
    <form autocomplete="off">
      <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
      <input type="hidden" name="tab" value="<?php echo $_GET['tab']; ?>">
      <div class="input-group mb-3">
        <input type="search" class="form-control " name="q" value="<?php if(isset($q)){echo stripslashes($q);} ?>" placeholder="Search <?php echo ucwords($_GET['tab']); ?>">
        <div class="input-group-append">
          <button class="btn btn-secondary"><i class="fa fa-search"></i></button>
        </div>
      </div>
    </form>
    <div class="table-responsive">
      <table class="table border table-hover">
        <thead class="thead-light <?php if($num_rows[0] == 0){ echo "d-none"; } ?>">
          <tr>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=asset_name&o=<?php echo $disp; ?>">Name</a></th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=asset_type&o=<?php echo $disp; ?>">Type</a></th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=asset_make&o=<?php echo $disp; ?>">Make/Model</a></th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=asset_ip&o=<?php echo $disp; ?>">Primary IP</a></th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=asset_serial&o=<?php echo $disp; ?>">Serial Number</a></th>
            <th>Contact</th>
            <th>Location</th>
            <th class="text-center">Action</th>  
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $asset_id = $row['asset_id'];
            $asset_type = $row['asset_type'];
            $asset_name = $row['asset_name'];
            $asset_make = $row['asset_make'];
            $asset_model = $row['asset_model'];
            $asset_serial = $row['asset_serial'];
            $asset_os = $row['asset_os'];
            $asset_ip = $row['asset_ip'];
            $asset_mac = $row['asset_mac'];
            $asset_purchase_date = $row['asset_purchase_date'];
            $asset_warranty_expire = $row['asset_warranty_expire'];
            $asset_notes = $row['asset_notes'];
            $vendor_id = $row['vendor_id'];
            $location_id = $row['location_id'];
            $contact_id = $row['contact_id'];
            $network_id = $row['network_id'];

            if($asset_type == 'Laptop'){
              $device_icon = "laptop";
            }elseif($asset_type == 'Desktop'){
              $device_icon = "desktop";
            }elseif($asset_type == 'Server'){
              $device_icon = "server";
            }elseif($asset_type == 'Printer'){
              $device_icon = "print";
            }elseif($asset_type == 'Camera'){
              $device_icon = "video";
            }elseif($asset_type == 'Switch' or $asset_type == 'Firewall/Router'){
              $device_icon = "network-wired";
            }elseif($asset_type == 'Access Point'){
              $device_icon = "wifi";
            }elseif($asset_type == 'Phone'){
              $device_icon = "phone";
            }elseif($asset_type == 'Mobile Phone'){
              $device_icon = "mobile-alt";
            }elseif($asset_type == 'Tablet'){
              $device_icon = "tablet-alt";
            }elseif($asset_type == 'TV'){
              $device_icon = "tv";
            }elseif($asset_type == 'Virtual Machine'){
              $device_icon = "cloud";
            }else{
              $device_icon = "tag";
            }

            $sql_logins = mysqli_query($mysqli,"SELECT *, AES_DECRYPT(login_password, '$config_aes_key') AS login_password FROM logins WHERE asset_id = $asset_id");
            $row = mysqli_fetch_array($sql_logins);
            $login_id = $row['login_id'];
            $login_username = $row['login_username'];
            $login_password = $row['login_password'];
            $asset_id_relation = $row['asset_id'];

            $sql_contact = mysqli_query($mysqli,"SELECT * FROM contacts WHERE contact_id = $contact_id");
            $row = mysqli_fetch_array($sql_contact);
            $contact_name = $row['contact_name'];
            if(empty($contact_name)){
              $contact_name = "-";
            }

            $sql_location = mysqli_query($mysqli,"SELECT * FROM locations WHERE location_id = $location_id");
            $row = mysqli_fetch_array($sql_location);
            $location_name = $row['location_name'];
            if(empty($location_name)){
              $location_name = "-";
            }
      
          ?>
          <tr>
            <th>
              <?php
              echo $asset_name;

              if($asset_id == $asset_id_relation){
              ?>  
              <button type="button" class="btn btn-link btn-sm" data-toggle="modal" data-target="#viewPasswordModal<?php echo $login_id; ?>"><i class="fas fa-key text-dark"></i></button>

              <div class="modal" id="viewPasswordModal<?php echo $login_id; ?>" tabindex="-1">
                <div class="modal-dialog modal-sm">
                  <div class="modal-content bg-dark">
                    <div class="modal-header text-white">
                      <h5 class="modal-title"><i class="fa fa-fw fa-key mr-2"></i><?php echo $asset_name; ?></h5>
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
              
            </th>
            <td><i class="fa fa-fw text-secondary fa-<?php echo $device_icon; ?> mr-2"></i><?php echo $asset_type; ?></td>
            <td><?php echo "$asset_make $asset_model"; ?></td>
            <td><?php echo $asset_ip; ?></td>
            <td><?php echo $asset_serial; ?></td>
            <td><?php echo $contact_name; ?></td>
            <td><?php echo $location_name; ?></td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu">
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editAssetModal<?php echo $asset_id; ?>">Edit</a>
                  <div class="dropdown-divider"></div>
                  <a class="dropdown-item" href="post.php?delete_asset=<?php echo $asset_id; ?>">Delete</a>
                </div>
              </div>
              <?php include("edit_domain_modal.php"); ?>     
            </td>
          </tr>
          <?php include("edit_asset_modal.php"); ?>
          <?php
          }
          ?>

        </tbody>
      </table>

      <?php include("pagination.php"); ?>

    </div>
  </div>
</div>

<?php include("add_asset_modal.php"); ?>