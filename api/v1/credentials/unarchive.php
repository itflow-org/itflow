<?php

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

// Parse ID
$credential_id = intval($_POST['credential_id']);

// Default
$update_count = false;

if (!empty($credential_id)) {

    // Fetch credential info
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "
        SELECT credential_name, credential_client_id
        FROM credentials
        WHERE credential_id = $credential_id AND credential_client_id = $client_id AND credential_archived_at IS NOT NULL
        LIMIT 1
    "));

    if ($row) {

        $credential_name = escapeSql($row['credential_name']);

        // Unarchive credential
        $update_sql = mysqli_query($mysqli, "
            UPDATE credentials SET credential_archived_at = NULL
            WHERE credential_id = $credential_id AND credential_client_id = $client_id
        ");

        if ($update_sql) {
            $update_count = mysqli_affected_rows($mysqli);

            // Logging
            logAudit("Credential", "Unarchive", "$credential_name unarchived via API ($api_key_name)", $client_id, $credential_id);
        }
    }
}

// Output
require_once '../update_output.php';
