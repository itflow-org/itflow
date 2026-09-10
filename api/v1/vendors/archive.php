<?php

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

// Parse ID
$vendor_id = intval($_POST['vendor_id']);

// Default
$update_count = false;

if (!empty($vendor_id)) {

    // Fetch vendor info
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT vendor_name FROM vendors WHERE vendor_id = $vendor_id AND vendor_client_id = $client_id AND vendor_archived_at IS NULL LIMIT 1"));

    if ($row) {
        $vendor_name = escapeSql($row['vendor_name']);

        // Archive vendor
        $update_sql = mysqli_query($mysqli, "UPDATE vendors SET vendor_archived_at = NOW() WHERE vendor_id = $vendor_id AND vendor_client_id = $client_id AND vendor_archived_at IS NULL");

        if ($update_sql) {
            $update_count = mysqli_affected_rows($mysqli);

            // Logging
            logAudit("Vendor", "Archive", "$vendor_name archived via API ($api_key_name)", $client_id, $vendor_id);
        }
    }
}

// Output
require_once '../update_output.php';