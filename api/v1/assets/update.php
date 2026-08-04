<?php

require_once '../validate_api_key.php';

require_once '../require_post_method.php';


// Parse ID
$asset_id = intval($_POST['asset_id']);

// Default
$update_count = false;

if (!empty($asset_id)) {

    $asset_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM assets WHERE asset_id = '$asset_id' AND asset_client_id = $client_id LIMIT 1"));

    // Variable assignment from POST - assigning the current database value if a value is not provided
    require_once 'asset_model.php';

    $update_sql = mysqli_query($mysqli, "UPDATE assets SET asset_name = '$name', asset_description = '$description', asset_type = '$type', asset_make = '$make', asset_model = '$model', asset_serial = '$serial', asset_os = '$os', asset_uri = '$uri', asset_uri_2 = '$uri_2', asset_status = '$status', asset_location_id = $location, asset_vendor_id = $vendor, asset_contact_id = $contact, asset_purchase_date = $purchase_date, asset_warranty_expire = $warranty_expire, asset_install_date = $install_date, asset_notes = '$notes' WHERE asset_id = $asset_id AND asset_client_id = $client_id LIMIT 1");

    // Check insert & get insert ID
    if ($update_sql) {
        $update_count = mysqli_affected_rows($mysqli);

        // Update Primary Interface
        mysqli_query($mysqli,"UPDATE asset_interfaces SET interface_mac = '$mac', interface_ip = '$ip', interface_network_id = $network WHERE interface_asset_id = $asset_id AND interface_primary = 1");

        // Add to History
        mysqli_query($mysqli,"INSERT INTO asset_history SET asset_history_status = '$status', asset_history_description = 'API updated $name ($api_key_name)', asset_history_asset_id = $asset_id");

        // Logging
        logAudit("Asset", "Edit", "$name via API ($api_key_name)", $client_id);
        logAudit("API", "Success", "Edited asset $name via API ($api_key_name)", $client_id);
    }
}

// Output
require_once '../update_output.php';

