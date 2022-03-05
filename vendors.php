<?php include("inc_all.php");

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
  //Phone Numbers
  $phone_query = preg_replace("/[^0-9]/", '',$q);
  if(empty($phone_query)){
    $phone_query = $q;
  }
}else{
  $q = "";
  $phone_query = "";
}

//Column Filter
if(!empty($_GET['sb'])){
  $sb = mysqli_real_escape_string($mysqli,$_GET['sb']);
}else{
  $sb = "vendor_name";
}

//Column Order
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

$sql = mysqli_query($mysqli,"SELECT SQL_CALC_FOUND_ROWS * FROM vendors 
  WHERE vendor_client_id = 0 
  AND DATE(vendor_created_at) BETWEEN '$dtf' AND '$dtt'
  AND (vendor_name LIKE '%$q%' OR vendor_description LIKE '%$q%' OR vendor_account_number LIKE '%$q%' OR vendor_website LIKE '%$q%' OR vendor_contact_name LIKE '%$q%' OR vendor_email LIKE '%$q%' OR vendor_phone LIKE '%$phone_query%')
  AND vendor_archived_at IS NULL
  AND company_id = $session_company_id
  ORDER BY $sb $o LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli,"SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
  <div class="card-header py-2">
    <h3 class="card-title mt-2"><i class="fa fa-fw fa-building"></i> Vendors</h3>
    <div class="card-tools">
      <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addVendorModal"><i class="fas fa-fw fa-plus"></i> New Vendor</button>
    </div>
  </div>
  
  <div class="card-body">
    <form class="mb-4" autocomplete="off">
      <div class="row">
        <div class="col-sm-4">
          <div class="input-group">
            <input type="search" class="form-control" name="q" value="<?php if(isset($q)){echo stripslashes($q);} ?>" placeholder="Search Vendors">
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
      <table class="table table-striped table-hover table-borderless">
        <thead class="<?php if($num_rows[0] == 0){ echo "d-none"; } ?>">
          <tr>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=vendor_name&o=<?php echo $disp; ?>">Vendor</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=vendor_description&o=<?php echo $disp; ?>">Description</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=vendor_contact_name&o=<?php echo $disp; ?>">Contact</a></th>
            <th class="text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $vendor_id = $row['vendor_id'];
            $vendor_name = $row['vendor_name'];
            $vendor_description = $row['vendor_description'];
            if(empty($vendor_description)){
              $vendor_description_display = "-";
            }else{
              $vendor_description_display = $vendor_description;
            }
            $vendor_account_number = $row['vendor_account_number'];
            $vendor_country = $row['vendor_country'];
            $vendor_address = $row['vendor_address'];
            $vendor_city = $row['vendor_city'];
            $vendor_state = $row['vendor_state'];
            $vendor_zip = $row['vendor_zip'];
            $vendor_contact_name = $row['vendor_contact_name'];
            if(empty($vendor_contact_name)){
              $vendor_contact_name_display = "-";
            }else{
              $vendor_contact_name_display = $vendor_contact_name;
            }
            $vendor_phone = formatPhoneNumber($row['vendor_phone']);
            $vendor_extension = $row['vendor_extension'];
            $vendor_email = $row['vendor_email'];
            $vendor_website = $row['vendor_website'];
            $vendor_notes = $row['vendor_notes']
            
          ?>

          <tr>
            <td>
              <a class="text-dark" href="#" data-toggle="modal" data-target="#editVendorModal<?php echo $vendor_id; ?>"><?php echo $vendor_name; ?><a>
              <?php
              if(!empty($vendor_account_number)){
              ?>
              <br>
              <small class="text-secondary"><?php echo $vendor_account_number; ?></small>
              <?php
              }
              ?>

            </td>
            <td><?php echo $vendor_description_display; ?></td>
            <td>
              <?php
              if(!empty($vendor_contact_name)){
              ?>
              <i class="fa fa-fw fa-user text-secondary mr-2 mb-2"></i><?php echo $vendor_contact_name; ?>
              <br>
              <?php
              }else{
                echo $vendor_contact_name_display;
              }
              ?>
              <?php
              if(!empty($vendor_phone)){
              ?>
              <i class="fa fa-fw fa-phone text-secondary mr-2 mb-2"></i><?php echo $vendor_phone; ?> <?php if(!empty($vendor_extension)){ echo "x$vendor_extension"; } ?>
              <br>
              <?php
              }
              ?>
              <?php
              if(!empty($vendor_email)){
              ?>
              <i class="fa fa-fw fa-envelope text-secondary mr-2 mb-2"></i><?php echo $vendor_email; ?>
              <br>
              <?php
              }
              ?>
            </td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu">
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editVendorModal<?php echo $vendor_id; ?>">Edit</a>
                  <div class="dropdown-divider"></div>
                  <a class="dropdown-item text-danger" href="post.php?archive_vendor=<?php echo $vendor_id; ?>">Archive</a>
                </div>
              </div>      
            </td>
          </tr>

          <?php

            include("vendor_edit_modal.php");
  
          }
          
          ?>

        </tbody>
      </table>
    </div>
    <?php include("pagination.php"); ?>
  </div>
</div>

<?php 
  
  include("vendor_add_modal.php");

  include("footer.php");

?>