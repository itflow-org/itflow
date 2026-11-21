<?php

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

// Parse ID
$service_id = intval($_POST['service_id'] ?? 0);

// Default
$delete_count = false;

if (!empty($service_id)) {

    // Archived first, as for the other records the API deletes - its links and tags go with it
    $row =mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT service_id, service_name, service_client_id FROM services WHERE service_id = $service_id AND service_client_id = $client_id AND service_archived_at IS NOT NULL LIMIT 1"));

    if ($row) {

        // As the app does it, audit log included (functions/service.php)
        serviceDelete($row);

        if (!mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM services WHERE service_id = $service_id"))) {
            $delete_count = 1;

            $service_name = escapeSql($row['service_name']);
            logAudit("API", "Success", "Deleted service $service_name via API ($api_key_name)", $client_id);
        }
    }
}

// Output
require_once '../delete_output.php';
