<?php

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

// Parse ID
$network_id = intval($_POST['network_id'] ?? 0);

// Default
$delete_count = false;

if (!empty($network_id)) {

    // Archived first, as in the app
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT network_id, network_name, network_client_id FROM networks WHERE network_id = $network_id AND network_client_id = $client_id AND network_archived_at IS NOT NULL LIMIT 1"));

    if ($row) {

        // Interfaces on it stay, on no network (functions/network.php)
        networkDelete($row);

        // Gone? (networkDelete() logs after its delete, so affected rows would count the log)
        if (!mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM networks WHERE network_id = $network_id"))) {
            $delete_count = 1;
            $network_name = escapeSql($row['network_name']);
            logAudit("API", "Success", "Deleted network $network_name via API ($api_key_name)", $client_id);
        }
    }
}

// Output
require_once '../delete_output.php';
