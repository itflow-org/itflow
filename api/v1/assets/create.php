<?php
require('../validate_api_key.php');

require('../require_post_method.php');

// Parse info

// Variable assignment - assigning blank if a value is not provided
if(isset($_POST['asset_name'])){
  $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['asset_name'])));
} else{
  $name = '';
}
if(isset($_POST['asset_type'])){
  $type = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['asset_type'])));
} else{
  $type = '';
}
if(isset($_POST['asset_make'])){
  $make = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['asset_make'])));
} else{
  $make = '';
}
if(isset($_POST['asset_model'])){
  $model = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['asset_model'])));
} else{
  $model = '';
}
if(isset($_POST['asset_serial'])){
  $serial = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['asset_serial'])));
} else{
  $serial = '';
}
if(isset($_POST['asset_os'])){
  $os = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['asset_os'])));
} else{
  $os = '';
}
if(isset($_POST['asset_ip'])){
  $aip = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['asset_ip'])));
} else{
  $aip = '';
}
if(isset($_POST['asset_mac'])){
  $mac = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['asset_mac'])));
} else{
  $mac = '';
}
if(isset($_POST['asset_purchase_date'])){
  $purchase_date = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['asset_purchase_date'])));
} else{
  $purchase_date = "0000-00-00";
}
if(isset($_POST['asset_warranty_expire'])){
  $warranty_expire = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['asset_warranty_expire'])));
} else{
  $warranty_expire = "0000-00-00";
}
if(isset($_POST['asset_install_date'])){
  $install_date = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['asset_install_date'])));
} else{
  $install_date = "0000-00-00";
}
if(isset($_POST['asset_notes'])){
  $notes = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['asset_notes'])));
} else{
  $notes = '';
}
if(isset($_POST['asset_meshcentral_id'])){
  $meshcentral_id = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['asset_meshcentral_id'])));
} else{
  $meshcentral_id = '';
}
if(isset($_POST['asset_vendor_id'])){
  $vendor = intval($_POST['asset_vendor_id']);
} else{
  $vendor = '0';
}
if(isset($_POST['asset_location_id'])){
  $location = intval($_POST['asset_location_id']);
} else{
  $location = '0';
}
if(isset($_POST['asset_contact_id'])){
  $contact = intval($_POST['asset_contact_id']);
} else{
  $contact = '0';
}
if(isset($_POST['asset_network_id'])){
  $network = intval($_POST['asset_network_id']);
} else{
  $network = '0';
}

// Default
$insert_id = FALSE;

if(!empty($name) && !empty($client_id)){
  // Insert into Database
  $insert_sql = mysqli_query($mysqli,"INSERT INTO assets SET asset_name = '$name', asset_type = '$type', asset_make = '$make', asset_model = '$model', asset_serial = '$serial', asset_os = '$os', asset_ip = '$aip', asset_mac = '$mac', asset_location_id = $location, asset_vendor_id = $vendor, asset_contact_id = $contact, asset_purchase_date = '$purchase_date', asset_warranty_expire = '$warranty_expire', asset_install_date = '$install_date', asset_notes = '$notes', asset_created_at = NOW(), asset_network_id = $network, asset_client_id = $client_id, company_id = '$company_id'");

  if($insert_sql){
    $insert_id = mysqli_insert_id($mysqli);

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Asset', log_action = 'Created', log_description = '$name via API ($api_key_name)', log_ip = '$ip', log_created_at = NOW(), log_client_id = '$client_id', company_id = $company_id");
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'API', log_action = 'Success', log_description = 'Created asset $name via API ($api_key_name)', log_ip = '$ip', log_created_at = NOW(), log_client_id = '$client_id', company_id = $company_id");
  }
}

// Output
include('../create_output.php');