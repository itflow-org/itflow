<?php

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

// Parse ID
$location_id = intval($_POST['location_id']);

// Default
$update_count = false;

if (!empty($location_id)) {

    // Fetch location info
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "
        SELECT location_name
        FROM locations
        WHERE location_id = $location_id AND location_client_id = $client_id AND location_archived_at IS NOT NULL
        LIMIT 1
    "));

    if ($row) {

        $location_name = escapeSql($row['location_name']);

        // Unarchive location
        $update_sql = mysqli_query($mysqli, "
            UPDATE locations SET location_archived_at = NULL
            WHERE location_id = $location_id AND location_client_id = $client_id
        ");

        if ($update_sql) {
            $update_count = mysqli_affected_rows($mysqli);

            // Logging
            logAudit("Location", "Unarchive", "$location_name unarchived via API ($api_key_name)", $client_id, $location_id);
            logAudit("API", "Success", "Unarchived location $location_name via API ($api_key_name)", $client_id);
        }
    }
}

// Output
require_once '../update_output.php';
