<?php

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

// Parse ID
$certificate_id = intval($_POST['certificate_id'] ?? 0);

// Default
$delete_count = false;

if (!empty($certificate_id)) {

    // Archived first, as for the other records the API deletes
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT certificate_name FROM certificates WHERE certificate_id = $certificate_id AND certificate_client_id = $client_id AND certificate_archived_at IS NOT NULL LIMIT 1"));

    if ($row) {

        // Its history and its links to services go with it (ON DELETE CASCADE)
        $delete_sql = mysqli_query($mysqli, "DELETE FROM certificates WHERE certificate_id = $certificate_id AND certificate_client_id = $client_id LIMIT 1");

        if ($delete_sql) {
            $delete_count = mysqli_affected_rows($mysqli);

            $certificate_name = escapeSql($row['certificate_name']);
            logAudit("Certificate", "Delete", "$certificate_name via API ($api_key_name)", $client_id);
            logAudit("API", "Success", "Deleted certificate $certificate_name via API ($api_key_name)", $client_id);
        }
    }
}

// Output
require_once '../delete_output.php';
