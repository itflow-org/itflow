<?php
require('../validate_api_key.php');

require('../require_post_method.php');

// Parse ID
$asset_id = intval($_POST['asset_id']);

// Default
$delete_count = FALSE;

if(!empty($asset_id)){
  $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM assets WHERE asset_id = $asset_id AND asset_client_id = $client_id AND company_id = '$company_id' LIMIT 1"));
  $asset_name = $row['asset_name'];

  $delete_sql = mysqli_query($mysqli, "DELETE FROM assets WHERE asset_id = $asset_id AND asset_client_id = $client_id AND company_id = '$company_id' LIMIT 1");

  // Check delete & get affected rows
  if($delete_sql && !empty($asset_name)){
    $delete_count = mysqli_affected_rows($mysqli);

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Asset', log_action = 'Deleted', log_description = '$asset_name via API ($api_key_name)', log_ip = '$ip', log_client_id = $client_id, company_id = $company_id");
  }
}

// Output
include('../delete_output.php');