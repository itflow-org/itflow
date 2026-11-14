<?php

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

// Parse ID
$software_id = intval($_POST['software_id'] ?? 0);

// Default
$delete_count = false;

if (!empty($software_id)) {

    // Archived first, as for the other records the API deletes
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT software_name FROM software WHERE software_id = $software_id AND software_client_id = $client_id AND software_archived_at IS NOT NULL LIMIT 1"));

    if ($row) {

        // Seats, keys and links go with it (foreign keys cascade)
        $delete_sql = mysqli_query($mysqli, "DELETE FROM software WHERE software_id = $software_id AND software_client_id = $client_id LIMIT 1");

        if ($delete_sql) {
            $delete_count = mysqli_affected_rows($mysqli);

            $software_name = escapeSql($row['software_name']);
            logAudit("Software", "Delete", "$software_name with its seats and keys via API ($api_key_name)", $client_id);
            logAudit("API", "Success", "Deleted license $software_name via API ($api_key_name)", $client_id);
        }
    }
}

// Output
require_once '../delete_output.php';
