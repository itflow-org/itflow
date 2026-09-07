<?php

require_once '../validate_api_key.php';

require_once '../require_post_method.php';


// Parse ID
$location_id = intval($_POST['location_id']);

// Default
$update_count = false;

if (!empty($location_id)) {

    $location_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM locations WHERE location_id = '$location_id' AND location_client_id = $client_id AND location_archived_at IS NULL LIMIT 1"));

    // Variable assignment from POST - assigning the current database value if a value is not provided
    require_once 'location_model.php';

    if ($location_row) {

        // Reset primary location
        if ($primary == 1) {
            mysqli_query($mysqli, "UPDATE locations SET location_primary = 0 WHERE location_client_id = $client_id");
        }

        $update_sql = mysqli_query($mysqli, "UPDATE locations SET location_name = '$name', location_description = '$description', location_country = '$country', location_address = '$address', location_city = '$city', location_state = '$state', location_zip = '$zip', location_hours = '$hours', location_notes = '$notes', location_primary = '$primary' WHERE location_id = $location_id AND location_client_id = $client_id AND location_archived_at IS NULL LIMIT 1");

        // Check update & get affected rows
        if ($update_sql) {
            $update_count = mysqli_affected_rows($mysqli);

            // Logging
            logAudit("Location", "Edit", "$name via API ($api_key_name)", $client_id, $location_id);
            logAudit("API", "Success", "Edited location $name via API ($api_key_name)", $client_id);
        }
    }
}

// Output
require_once '../update_output.php';
