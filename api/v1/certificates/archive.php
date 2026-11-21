<?php

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

// Parse ID
$certificate_id = intval($_POST['certificate_id'] ?? 0);

// Default
$update_count = false;

if (!empty($certificate_id)) {

    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT certificate_name FROM certificates WHERE certificate_id = $certificate_id AND certificate_client_id = $client_id AND certificate_archived_at IS NULL LIMIT 1"));

    if ($row) {

        $update_sql = mysqli_query($mysqli, "UPDATE certificates SET certificate_archived_at = NOW() WHERE certificate_id = $certificate_id AND certificate_client_id = $client_id LIMIT 1");

        if ($update_sql) {
            $update_count = mysqli_affected_rows($mysqli);

            $certificate_name = escapeSql($row['certificate_name']);
            logAudit("Certificate", "Archive", "$certificate_name archived via API ($api_key_name)", $client_id, $certificate_id);
            logAudit("API", "Success", "Archived certificate $certificate_name via API ($api_key_name)", $client_id);
        }
    }
}

// Output
require_once '../update_output.php';
