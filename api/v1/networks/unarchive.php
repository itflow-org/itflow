<?php

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

// Parse ID
$network_id = intval($_POST['network_id']);

// Default
$update_count = false;

if (!empty($network_id)) {

    // Fetch network info
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "
        SELECT network_name
        FROM networks
        WHERE network_id = $network_id AND network_client_id = $client_id AND network_archived_at IS NOT NULL
        LIMIT 1
    "));

    if ($row) {

        $network_name = escapeSql($row['network_name']);

        // Unarchive network
        $update_sql = mysqli_query($mysqli, "
            UPDATE networks SET network_archived_at = NULL
            WHERE network_id = $network_id AND network_client_id = $client_id
        ");

        if ($update_sql) {
            $update_count = mysqli_affected_rows($mysqli);

            // Logging
            logAudit("Network", "Unarchive", "$network_name unarchived via API ($api_key_name)", $client_id, $network_id);
            logAudit("API", "Success", "Unarchived network $network_name via API ($api_key_name)", $client_id);
        }
    }
}

// Output
require_once '../update_output.php';