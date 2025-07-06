<?php
require_once '../validate_api_key.php';


require_once '../require_post_method.php';


// Parse ID
$asset_id = intval($_POST['asset_id']);

// Default
$delete_count = false;

if (!empty($asset_id)) {
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM assets WHERE asset_id = $asset_id AND asset_client_id = $client_id LIMIT 1"));
    $asset_name = $row['asset_name'];

    $delete_sql = mysqli_query($mysqli, "DELETE FROM assets WHERE asset_id = $asset_id AND asset_client_id = $client_id LIMIT 1");

    // Delete Interfaces
    mysqli_query($mysqli,"DELETE FROM asset_interfaces WHERE interface_asset_id = $asset_id");

    // Check delete & get affected rows
    if ($delete_sql && !empty($asset_name)) {
        $delete_count = mysqli_affected_rows($mysqli);

        // Logging
        logAction("Asset", "Delete", "$asset_name via API ($api_key_name)", $client_id);
    }
}

// Output
require_once '../delete_output.php';

