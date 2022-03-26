<?php
require('../validate_api_key.php');

if($_SERVER['REQUEST_METHOD'] !== "POST"){
  header("HTTP/1.1 405 Method Not Allowed");
  $return_arr['success'] = "False";
  $return_arr['message'] = "Can only send POST requests to this endpoint.";
  echo json_encode($return_arr);
  exit();
}

// Parse info
$type = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['asset_type'])));
$name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['asset_name'])));
$make = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['asset_make'])));
$model = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['asset_model'])));
$serial = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['asset_serial'])));
$os = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['asset_os'])));
$ip = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['asset_ip'])));
$mac = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['asset_mac'])));
$purchase_date = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['asset_purchase_date'])));
if(empty($purchase_date)){
  $purchase_date = "0000-00-00";
}
$warranty_expire = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['asset_warranty_expire'])));
if(empty($warranty_expire)){
  $warranty_expire = "0000-00-00";
}
$install_date = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['install_date'])));
if(empty($install_date)){
  $install_date = "0000-00-00";
}
$notes = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['asset_notes'])));
$meshcentral_id = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['asset_meshcentral_id'])));
$location = intval($_POST['location']);
$vendor = intval($_POST['vendor']);
$contact = intval($_POST['contact']);
$network = intval($_POST['network']);
$client_id = intval(json_decode($_POST['client_id']));

if(!empty($name)){
  // Insert into Database
  $insert_sql = mysqli_query($mysqli,"INSERT INTO assets SET asset_name = '$name', asset_type = '$type', asset_make = '$make', asset_model = '$model', asset_serial = '$serial', asset_os = '$os', asset_ip = '$ip', asset_mac = '$mac', asset_location_id = $location, asset_vendor_id = $vendor, asset_contact_id = $contact, asset_purchase_date = '$purchase_date', asset_warranty_expire = '$warranty_expire', asset_install_date = '$install_date', asset_notes = '$notes', asset_created_at = NOW(), asset_network_id = $network, asset_client_id = $client_id, company_id = '$company_id'");
  if($insert_sql){
    $insert_id = $mysqli->insert_id;

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Asset', log_action = 'Created', log_description = '$name via API ($api_key_name)', log_ip = '$ip', log_created_at = NOW(), company_id = $company_id");
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'API', log_action = 'Success', log_description = 'Created asset $name via API ($api_key_name)', log_ip = '$ip', log_created_at = NOW(), company_id = $company_id");
  }
}
else{
  $insert_id = FALSE;
}

// Output
include('../create_output.php');