<?php
require('../validate_api_key.php');

require('../require_post_method.php');

// Parse Info
include('contact_model.php');

// Default
$insert_id = FALSE;

if(!empty($name) && !empty($email) && !empty($client_id)){

  // Check contact with $email doesn't already exist
  $email_duplication_sql = mysqli_query($mysqli, "SELECT * FROM contacts WHERE contact_email = '$email' AND contact_client_id = '$client_id'");

  if(mysqli_num_rows($email_duplication_sql) == 0){

    // Insert contact
    $insert_sql = mysqli_query($mysqli,"INSERT INTO contacts SET contact_name = '$name', contact_title = '$title', contact_phone = '$phone', contact_extension = '$extension', contact_mobile = '$mobile', contact_email = '$email', contact_notes = '$notes', contact_auth_method = '$auth_method', contact_created_at = NOW(), contact_department = '$department', contact_location_id = $location_id, contact_client_id = $client_id, company_id = $company_id");

    // Check insert & get insert ID
    if($insert_sql){
      $insert_id = mysqli_insert_id($mysqli);
      //Logging
      mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Contact', log_action = 'Created', log_description = '$name via API ($api_key_name)', log_ip = '$ip', log_created_at = NOW(), log_client_id = $client_id, company_id = $company_id");
      mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'API', log_action = 'Success', log_description = 'Created contact $name via API ($api_key_name)', log_ip = '$ip', log_created_at = NOW(), log_client_id = $client_id, company_id = $company_id");
    }

  }
}

// Output
include('../create_output.php');