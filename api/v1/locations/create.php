<?php

require_once '../validate_api_key.php';

require_once '../require_post_method.php';

// Parse Info
require_once 'location_model.php';

// Default
$insert_id = false;

if (!empty($name) && !empty($client_id)) {

    // Reset primary location
    if ($primary == '1') {
        mysqli_query($mysqli, "UPDATE locations SET location_primary = '0' WHERE location_client_id = '$client_id'");
    }

    // Insert location
    $insert_sql = mysqli_query($mysqli, "INSERT INTO locations SET location_name = '$name', location_description = '$description', location_country = '$country', location_address = '$address', location_city = '$city', location_state = '$state', location_zip = '$zip', location_hours = '$hours', location_notes = '$notes', location_primary = '$primary', location_client_id = $client_id");

    // Check insert & get insert ID
    if ($insert_sql) {
        $insert_id = mysqli_insert_id($mysqli);

        // Logging
        logAction("Location", "Create", "$name via API ($api_key_name)", $client_id, $insert_id);
        logAction("API", "Success", "Created location $name via API ($api_key_name)", $client_id);
    }

}

// Output
require_once '../create_output.php';
