<?php
require('../validate_api_key.php');

require('../require_post_method.php');

// Parse ID
$asset_id = intval($_POST['asset_id']);

// Default
$update_id = FALSE;

if(!empty($asset_id)){

  $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM assets WHERE asset_id = '$asset_id' AND asset_client_id = $client_id AND company_id = '$company_id' LIMIT 1"));

  // Variable assignment - assigning the current database value if a value is not provided
  if(isset($_POST['asset_name'])){
    $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['asset_name'])));
  } else{
    $name = $row['asset_name'];
  }
  if(isset($_POST['asset_type'])){
    $type = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['asset_type'])));
  } else{
    $type = $row['asset_type'];
  }
  if(isset($_POST['asset_make'])){
    $make = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['asset_make'])));
  } else{
    $make = $row['asset_make'];
  }
  if(isset($_POST['asset_model'])){
    $model = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['asset_model'])));
  } else{
    $model = $row['asset_model'];
  }
  if(isset($_POST['asset_serial'])){
    $serial = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['asset_serial'])));
  } else{
    $serial = $row['asset_serial'];
  }
  if(isset($_POST['asset_os'])){
    $os = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['asset_os'])));
  } else{
    $os = $row['asset_os'];
  }
  if(isset($_POST['asset_os'])){
    $os = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['asset_os'])));
  } else{
    $os = $row['asset_os'];
  }
  if(isset($_POST['asset_ip'])){
    $aip = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['asset_ip'])));
  } else{
    $aip = $row['asset_ip'];
  }
  if(isset($_POST['asset_mac'])){
    $mac = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['asset_mac'])));
  } else{
    $mac = $row['asset_mac'];
  }
  if(isset($_POST['asset_purchase_date'])){
    $purchase_date = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['asset_purchase_date'])));
  } else{
    $purchase_date = $row['asset_purchase_date'];
  }
  if(isset($_POST['asset_warranty_expire'])){
    $warranty_expire = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['asset_warranty_expire'])));
  } else{
    $warranty_expire = $row['asset_warranty_expire'];
  }
  if(isset($_POST['asset_install_date'])){
    $install_date = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['asset_install_date'])));
  } else{
    $install_date = $row['asset_install_date'];
  }
  if(isset($_POST['asset_notes'])){
    $notes = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['asset_notes'])));
  } else{
    $notes = $row['asset_notes'];
  }
  if(isset($_POST['asset_meshcentral_id'])){
    $meshcentral_id = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['asset_meshcentral_id'])));
  } else{
    $meshcentral_id = $row['asset_meshcentral_id'];
  }
  if(isset($_POST['asset_vendor_id'])){
    $vendor = intval($_POST['asset_vendor_id']);
  } else{
    $vendor = $row['asset_vendor_id'];
  }
  if(isset($_POST['asset_location_id'])){
    $location = intval($_POST['asset_location_id']);
  } else{
    $location = $row['asset_location_id'];
  }
  if(isset($_POST['asset_contact_id'])){
    $contact = intval($_POST['asset_contact_id']);
  } else{
    $contact = $row['asset_contact_id'];
  }
  if(isset($_POST['asset_network_id'])){
    $network = intval($_POST['asset_network_id']);
  } else{
    $network = $row['asset_network_id'];
  }

  $update_sql = mysqli_query($mysqli,"UPDATE assets SET asset_name = '$name', asset_type = '$type', asset_make = '$make', asset_model = '$model', asset_serial = '$serial', asset_os = '$os', asset_ip = '$aip', asset_mac = '$mac', asset_location_id = $location, asset_vendor_id = $vendor, asset_contact_id = $contact, asset_purchase_date = '$purchase_date', asset_warranty_expire = '$warranty_expire', asset_install_date = '$install_date', asset_notes = '$notes', asset_updated_at = NOW(), asset_network_id = $network WHERE asset_id = $asset_id AND asset_client_id = $client_id AND company_id = '$company_id' LIMIT 1");

  // Check insert & get insert ID
  if($update_sql){
    $update_id = mysqli_affected_rows($mysqli);

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Asset', log_action = 'Updated', log_description = '$name via API ($api_key_name)', log_ip = '$ip', log_client_id = $client_id, company_id = $company_id");
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'API', log_action = 'Success', log_description = 'Updated asset $name via API ($api_key_name)', log_ip = '$ip', log_client_id = $client_id, company_id = $company_id");
  }
}

// Output
include('../update_output.php');