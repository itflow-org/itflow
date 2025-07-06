<?php

require_once '../validate_api_key.php';

require_once '../require_post_method.php';


// Parse POST info
require_once 'asset_model.php';


// Default
$insert_id = false;

if (!empty($name) && !empty($client_id)) {
    // Insert into Database
    $insert_sql = mysqli_query($mysqli, "INSERT INTO assets SET asset_name = '$name', asset_description = '$description', asset_type = '$type', asset_make = '$make', asset_model = '$model', asset_serial = '$serial', asset_os = '$os', asset_uri = '$uri', asset_status = '$status', asset_location_id = $location, asset_vendor_id = $vendor, asset_contact_id = $contact, asset_purchase_date = $purchase_date, asset_warranty_expire = $warranty_expire, asset_install_date = $install_date, asset_notes = '$notes', asset_client_id = $client_id");

    if ($insert_sql) {
        $insert_id = mysqli_insert_id($mysqli);

        // Add Primary Interface
        mysqli_query($mysqli,"INSERT INTO asset_interfaces SET interface_name = '1', interface_mac = '$mac', interface_ip = '$ip', interface_primary = 1, interface_network_id = $network, interface_asset_id = $insert_id");

        // Logging
        logAction("Asset", "Create", "$name via API ($api_key_name)", $client_id, $insert_id);
        logAction("API", "Success", "Created asset $name via API ($api_key_name)", $client_id);
    }
}

// Output
require_once '../create_output.php';

