<?php

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

// Parse ID
$asset_id = intval($_POST['asset_id'] ?? 0);

// Default
$update_count = false;

if (!empty($asset_id)) {

    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "
        SELECT asset_name
        FROM assets
        WHERE asset_id = $asset_id AND asset_client_id = $client_id AND asset_archived_at IS NULL
        LIMIT 1
    "));

    if ($row) {

        $asset_name = escapeSql($row['asset_name']);

        $update_sql = mysqli_query($mysqli, "UPDATE assets SET asset_archived_at = NOW() WHERE asset_id = $asset_id AND asset_client_id = $client_id LIMIT 1");

        if ($update_sql) {
            $update_count = mysqli_affected_rows($mysqli);

            mysqli_query($mysqli, "INSERT INTO asset_history SET asset_history_status = 'Archived', asset_history_description = 'API archived $asset_name ($api_key_name)', asset_history_asset_id = $asset_id");

            // Logging
            logAudit("Asset", "Archive", "$asset_name archived via API ($api_key_name)", $client_id, $asset_id);
            logAudit("API", "Success", "Archived asset $asset_name via API ($api_key_name)", $client_id);
        }
    }
}

// Output
require_once '../update_output.php';
