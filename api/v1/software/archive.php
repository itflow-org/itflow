<?php

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

// Parse ID
$software_id = intval($_POST['software_id']);

// Default
$update_count = false;

if (!empty($software_id)) {

    // Fetch software info
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "
        SELECT software_name, software_client_id
        FROM software
        WHERE software_id = $software_id AND software_client_id = $client_id AND software_archived_at IS NULL
        LIMIT 1
    "));

    if ($row) {

        $software_name = escapeSql($row['software_name']);

        // Archive software
        $update_sql = mysqli_query($mysqli, "
            UPDATE software SET software_archived_at = NOW()
            WHERE software_id = $software_id AND software_client_id = $client_id
        ");

        if ($update_sql) {
            $update_count = mysqli_affected_rows($mysqli);

            // Logging
            logAudit("Software", "Archive", "$software_name archived via API ($api_key_name)", $client_id, $software_id);
        }
    }
}

// Output
require_once '../update_output.php';