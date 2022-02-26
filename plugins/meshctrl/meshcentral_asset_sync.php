<?php
/*
 * mesh_asset_sync.php
 * Wrapper around meshctrl.js to pull device data from Mesh Central
 * meshctrl.js is written in Node and requires npm packages minimist & ws
 */

// Includes
include("../../config.php");
include("../../functions.php");
include("../../check_login.php");

// Login token
$url = escapeshellarg($config_meshcentral_uri);
$usr = escapeshellarg($config_meshcentral_user);
$pass = escapeshellarg($config_meshcentral_secret);

echo "<h2>MeshCentral Asset Sync (All clients)</h2>";

// Small effort to support Windows (Note: The Apache service must be allowed to interact with desktop)
if((strtoupper(substr(PHP_OS, 0, 3)) == 'WIN')){
  // Grab a list of all devices
  $mesh_devices = json_decode(shell_exec("node meshctrl.js --url $url --loginuser $usr --loginpass $pass ListDevices --json"), true);
}
else{
  $mesh_devices = json_decode(shell_exec("./meshctrl.js --url $url --loginuser $usr --loginpass $pass ListDevices --json"), true);
}

foreach($mesh_devices as $device){
  // Reset script timeout
  set_time_limit(0);

  $mesh_id = trim(strip_tags(mysqli_real_escape_string($mysqli,$device['_id'])));
  $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$device['name'])));
  $client = trim(strip_tags(mysqli_real_escape_string($mysqli,$device['groupname'])));
  $os = trim(strip_tags(mysqli_real_escape_string($mysqli,$device['osdesc'])));
  $note = trim(strip_tags(mysqli_real_escape_string($mysqli,$device['desc'])));

  $device_lookup_meshid_sql = mysqli_query($mysqli, "SELECT asset_id FROM assets WHERE asset_meshcentral_id = '$mesh_id' LIMIT 1");
  $row = mysqli_fetch_array($device_lookup_meshid_sql);
  $asset_id = $row['asset_id'];

  // For each device in MeshCentral, check the name & group match for a client
  if($device_lookup_meshid_sql && !empty($asset_id)){
    // Found a match - sync the info
    mysqli_query($mysqli, "UPDATE assets SET asset_name = '$name', asset_os = '$os', asset_notes = '$note' WHERE asset_id = '$asset_id'");
    echo "Synced $name <br>";

  }

  else{
    // Didn't find a match on the asset ID - check if we can create a new asset or link to existing
    if(!empty($name) && !(empty($client))){
      $device_lookup_name_sql = mysqli_query($mysqli, "SELECT asset_id FROM assets LEFT JOIN clients ON assets.asset_client_id = client_id WHERE asset_name = '$name' AND asset_meshcentral_id = '' AND client_meshcentral_group = '$client' LIMIT 1");
      $row = mysqli_fetch_array($device_lookup_name_sql);
      $asset_id = $row['asset_id'];

      if($device_lookup_name_sql & !empty($asset_id)){
        // We have found an existing asset that isn't linked to MeshCentral, let's link it
        $os = trim(strip_tags(mysqli_real_escape_string($mysqli,$device['osdesc'])));
        $note = trim(strip_tags(mysqli_real_escape_string($mysqli,$device['desc'])));

        mysqli_query($mysqli, "UPDATE assets SET asset_os = '$os', asset_notes = '$note', asset_meshcentral_id = '$mesh_id' WHERE asset_id = '$asset_id'");

        echo "Synced asset via asset ID $name <br>";

        //Logging
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Asset', log_action = 'Modified', log_description = '$name linked to MeshCentral', log_created_at = NOW(), company_id = '1'");

      }
      else{
        // Create a new asset, if we recognise the group
        $group_lookup_sql = mysqli_query($mysqli, "SELECT client_id FROM clients WHERE client_meshcentral_group = '$client' LIMIT 1");
        $row = mysqli_fetch_array($group_lookup_sql);
        $client_id = $row['client_id'];

        if($group_lookup_sql && !empty($client_id)){
          mysqli_query($mysqli, "INSERT INTO assets SET asset_type = 'Desktop', asset_name = '$name', asset_os = '$os', asset_notes = '$note', asset_meshcentral_id = '$mesh_id', asset_client_id = '$client_id', company_id = '1'");

          //Logging
          mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Asset', log_action = 'Created', log_description = '$name via MeshCentral sync', log_created_at = NOW(), company_id = '1'");
          echo "Created new asset $name";
        }
      }
    }
  }
}

echo "<br><br>";
echo "<b>Sync Complete!</b>";