<?php 

//Paging
if(isset($_GET['p'])){
  $p = intval($_GET['p']);
  $record_from = (($p)-1)*$_SESSION['records_per_page'];
  $record_to = $_SESSION['records_per_page'];
}else{
  $record_from = 0;
  $record_to = $_SESSION['records_per_page'];
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

//Rebuild URL
$url_query_strings_sb = http_build_query(array_merge($_GET,array('sb' => $sb, 'o' => $o)));

$sql = mysqli_query($mysqli,"SELECT SQL_CALC_FOUND_ROWS *, AES_DECRYPT(login_password, '$config_aes_key') AS login_password FROM assets LEFT JOIN contacts ON asset_contact_id = contact_id LEFT JOIN locations ON asset_location_id = location_id LEFT JOIN logins ON login_asset_id = asset_id
  WHERE asset_client_id = $client_id 
  AND (asset_name LIKE '%$q%' OR asset_type LIKE '%$q%' OR asset_ip LIKE '%$q%' OR asset_make LIKE '%$q%' OR asset_model LIKE '%$q%' OR asset_serial LIKE '%$q%' OR asset_os LIKE '%$q%' OR contact_name LIKE '%$q%' OR location_name LIKE '%$q%') 
  ORDER BY $sb $o LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli,"SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
  <div class="card-header py-2">
    <h3 class="card-title mt-2"><i class="fa fa-fw fa-desktop"></i> Assets</h3>
    <div class="card-tools">
      <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addAssetModal"><i class="fas fa-fw fa-plus"></i> New Asset</button>
    </div>
  </div>
  <div class="card-body">
    <form autocomplete="off">
      <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
      <input type="hidden" name="tab" value="<?php echo $_GET['tab']; ?>">
      <div class="row">
        
        <div class="col-md-4">
          <div class="input-group mb-3 mb-md-0">
            <input type="search" class="form-control" name="q" value="<?php if(isset($q)){echo stripslashes($q);} ?>" placeholder="Search <?php echo ucwords($_GET['tab']); ?>">
            <div class="input-group-append">
              <button class="btn btn-dark"><i class="fa fa-search"></i></button>
            </div>
          </div>
        </div>

        <div class="col-md-8">
          <div class="float-right">
            <a href="post.php?export_client_<?php echo $_GET['tab']; ?>_csv=<?php echo $client_id; ?>" class="btn btn-default"><i class="fa fa-fw fa-download"></i> Export</a>
            <button type="button" class="btn btn-default" data-toggle="modal" data-target="#addAssetCSVModal"><i class="fa fa-fw fa-upload"></i> Import</button>
          </div>
        </div>

      </div>
    </form>
    <hr>
    <div class="table-responsive">
      <table class="table border table-hover">
        <thead class="thead-light <?php if($num_rows[0] == 0){ echo "d-none"; } ?>">
          <tr>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=asset_name&o=<?php echo $disp; ?>">Name</a></th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=asset_type&o=<?php echo $disp; ?>">Type</a></th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=asset_make&o=<?php echo $disp; ?>">Make/Model</a></th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=asset_serial&o=<?php echo $disp; ?>">Serial Number</a></th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=asset_os&o=<?php echo $disp; ?>">Operating System</a></th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=asset_install_date&o=<?php echo $disp; ?>">Install Date</a></th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=contact_name&o=<?php echo $disp; ?>">Contact</a></th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=location_name&o=<?php echo $disp; ?>">Location</a></th>
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
            if(empty($asset_serial)){
              $asset_serial_display = "-";
            }else{
              $asset_serial_display = $asset_serial;
            }
            $asset_os = $row['asset_os'];
            if(empty($asset_os)){
              $asset_os_display = "-";
            }else{
              $asset_os_display = $asset_os;
            }
            $asset_ip = $row['asset_ip'];
            if(empty($asset_ip)){
              $asset_ip_display = "-";
            }else{
              $asset_ip_display = "$asset_ip<button class='btn btn-sm' data-clipboard-text='$asset_ip'><i class='far fa-copy text-secondary'></i></button>";
            }
            $asset_mac = $row['asset_mac'];
            $asset_purchase_date = $row['asset_purchase_date'];
            $asset_warranty_expire = $row['asset_warranty_expire'];
            $asset_install_date = $row['asset_install_date'];
            if(empty($asset_install_date)){
              $asset_install_date_display = "-";
            }else{
              $asset_install_date_display = $asset_install_date;
            }
            $asset_notes = $row['asset_notes'];
            $asset_created_at = $row['asset_created_at'];
            $asset_vendor_id = $row['asset_vendor_id'];
            $asset_location_id = $row['asset_location_id'];
            $asset_contact_id = $row['asset_contact_id'];
            $asset_network_id = $row['asset_network_id'];

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

            $contact_name = $row['contact_name'];
            if(empty($contact_name)){
              $contact_name = "-";
            }
    
            $location_name = $row['location_name'];
            if(empty($location_name)){
              $location_name = "-";
            }

            $login_id = $row['login_id'];
            $login_username = $row['login_username'];
            $login_password = $row['login_password'];
      
          ?>
          <tr>
            <th>
              <i class="fa fa-fw text-secondary fa-<?php echo $device_icon; ?> mr-2"></i>
              <a class="text-secondary" href="#" data-toggle="modal" data-target="#editAssetModal<?php echo $asset_id; ?>"><?php echo $asset_name; ?></a>
              <?php
              if($login_id > 0){
              ?>  
              <button type="button" class="btn btn-link btn-sm" data-toggle="modal" data-target="#viewPasswordModal<?php echo $login_id; ?>"><i class="fas fa-key text-dark"></i></button>

              <div class="modal" id="viewPasswordModal<?php echo $login_id; ?>" tabindex="-1">
                <div class="modal-dialog modal-sm">
                  <div class="modal-content bg-dark">
                    <div class="modal-header">
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
            <td><?php echo $asset_type; ?></td>
            <td><?php echo "$asset_make $asset_model"; ?></td>
            <td><?php echo $asset_serial_display; ?></td>
            <td><?php echo $asset_os_display; ?></td>
            <td><?php echo $asset_install_date_display; ?></td>
            <td><?php echo $contact_name; ?></td>
            <td><?php echo $location_name; ?></td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown"><i class="fas fa-ellipsis-h"></i></button>
                <div class="dropdown-menu">
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editAssetModal<?php echo $asset_id; ?>">Edit</a>
                  <div class="dropdown-divider"></div>
                  <a class="dropdown-item text-danger" href="post.php?delete_asset=<?php echo $asset_id; ?>">Delete</a>
                </div>
              </div>
            </td>
          </tr>

          <?php
          
          include("edit_asset_modal.php");
          }
          
          ?>

        </tbody>
      </table>
    </div>
    <?php include("pagination.php"); ?>
  </div>
</div>

<?php
include("add_asset_modal.php");
include("add_asset_csv_modal.php");
?>