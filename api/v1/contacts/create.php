<?php
require('../validate_api_key.php');

if($_SERVER['REQUEST_METHOD'] !== "POST"){
  header("HTTP/1.1 405 Method Not Allowed");
  $return_arr['success'] = "False";
  $return_arr['message'] = "Can only send POST requests to this endpoint.";
  echo json_encode($return_arr);
  exit();
}

// Parse Info
$client_id = intval($_POST['client_id']);
$name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['contact_name'])));
$title = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['contact_title'])));
$department = intval($_POST['contact_department']);
$phone = preg_replace("/[^0-9]/", '',$_POST['contact_phone']);
$extension = preg_replace("/[^0-9]/", '',$_POST['contact_extension']);
$mobile = preg_replace("/[^0-9]/", '',$_POST['contact_mobile']);
$email = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['contact_email'])));
$notes = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['contact_notes'])));
$auth_method = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['contact_auth_method'])));
$location_id = intval($_POST['location']);

if(!empty($name)){
  // Insert contact
  $insert_sql = mysqli_query($mysqli,"INSERT INTO contacts SET contact_name = '$name', contact_title = '$title', contact_phone = '$phone', contact_extension = '$extension', contact_mobile = '$mobile', contact_email = '$email', contact_notes = '$notes', contact_auth_method = '$auth_method', contact_created_at = NOW(), contact_department_id = $department, contact_location_id = $location_id, contact_client_id = $client_id, company_id = $company_id");
  if($insert_sql){
    $insert_id = $mysqli->insert_id;

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Contact', log_action = 'Created', log_description = '$name via API ($api_key_name)', log_ip = '$ip', log_created_at = NOW(), company_id = $company_id");
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'API', log_action = 'Success', log_description = 'Created contact $name via API ($api_key_name)', log_ip = '$ip', log_created_at = NOW(), company_id = $company_id");
  }
}
else{
  $insert_id = FALSE;
}

// Output
include('../create_output.php');