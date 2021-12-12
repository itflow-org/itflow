<?php include("header.php");

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
  
//Custom Query Filter  
if(isset($_GET['q'])){
  $q = mysqli_real_escape_string($mysqli,$_GET['q']);
}else{
  $q = "";
}

//Column Filter
if(!empty($_GET['sb'])){
  $sb = mysqli_real_escape_string($mysqli,$_GET['sb']);
}else{
  $sb = "asset_name";
}

//Column Order Filter
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

//Date From and Date To Filter
if(!empty($_GET['dtf'])){
  $dtf = mysqli_real_escape_string($mysqli,$_GET['dtf']);
  $dtt = mysqli_real_escape_string($mysqli,$_GET['dtt']);
}else{
  $dtf = "0000-00-00";
  $dtt = "9999-00-00";
}

//Rebuild URL

$url_query_strings_sb = http_build_query(array_merge($_GET,array('sb' => $sb, 'o' => $o)));

$sql = mysqli_query($mysqli,"SELECT SQL_CALC_FOUND_ROWS * FROM assets LEFT JOIN clients ON asset_client_id = client_id 
  WHERE (asset_name LIKE '%$q%' OR asset_type LIKE '%$q%' OR asset_make LIKE '%$q%' OR asset_model LIKE '%$q%' OR asset_serial LIKE '%$q%' OR asset_os LIKE '%$q%' 
  OR asset_ip LIKE '%$q%' OR asset_mac LIKE '%$q%' OR client_name LIKE '%$q%') 
  AND DATE(asset_created_at) BETWEEN '$dtf' AND '$dtt'
  AND assets.company_id = $session_company_id 
  ORDER BY $sb $o LIMIT $record_from, $record_to"
); 

$num_rows = mysqli_fetch_row(mysqli_query($mysqli,"SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
  <div class="card-header py-2">
    <h3 class="card-title mt-2"><i class="fa fa-fw fa-desktop"></i> Client Assets</h3>
    <div class="card-tools">
    </div>
  </div>

  <div class="card-body">
    <form class="mb-4" autocomplete="off">
      <div class="row">
        <div class="col-sm-4">
          <div class="input-group">
            <input type="search" class="form-control" name="q" value="<?php if(isset($q)){echo stripslashes($q);} ?>" placeholder="Search all client assets">
            <div class="input-group-append">
              <button class="btn btn-secondary" type="button" data-toggle="collapse" data-target="#advancedFilter"><i class="fas fa-filter"></i></button>
              <button class="btn btn-primary"><i class="fa fa-search"></i></button>
            </div>
          </div>
        </div>
      </div>
      <div class="collapse mt-3 <?php if(!empty($_GET['dtf'])){ echo "show"; } ?>" id="advancedFilter">
        <div class="row">
          <div class="col-md-2">
            <div class="form-group">
              <label>Date From</label>
              <input type="date" class="form-control" name="dtf" value="<?php echo $dtf; ?>">
            </div>
          </div>
          <div class="col-md-2">
            <div class="form-group">
              <label>Date To</label>
              <input type="date" class="form-control" name="dtt" value="<?php echo $dtt; ?>">
            </div>
          </div>
        </div>    
      </div>
    </form>
    <hr>
    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover">
        <thead class="text-dark <?php if($num_rows[0] == 0){ echo "d-none"; } ?>">
          <tr>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=asset_name&o=<?php echo $disp; ?>">Name</a></th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=asset_type&o=<?php echo $disp; ?>">Type</a></th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=asset_make&o=<?php echo $disp; ?>">Make</a></th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=asset_model&o=<?php echo $disp; ?>">Model</a></th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=asset_serial&o=<?php echo $disp; ?>">Serial</a></th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=client_name&o=<?php echo $disp; ?>">Client</a></th>
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
            $client_id = $row['client_id'];
            $client_name = $row['client_name'];
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
      
          ?>
          <tr>
            
            <td><i class="fa fa-fw text-secondary fa-<?php echo $device_icon; ?> mr-2"></i><?php echo $asset_name; ?></td>
            <td><?php echo $asset_type; ?></td>
            <td><?php echo $asset_make; ?></td>
            <td><?php echo $asset_model; ?></td>
            <td><?php echo $asset_serial; ?></td>
            <td><a href="client.php?client_id=<?php echo $client_id; ?>&tab=assets"><?php echo $client_name; ?></a></td>
          </tr>

          <?php
          }
          ?>

        </tbody>
      </table>
    </div>
    <?php include("pagination.php"); ?>
  </div>
</div>

<?php include("add_asset_modal.php"); ?>

<?php include("footer.php");