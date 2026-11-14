<?php

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

// Parse ID
$vendor_id = intval($_POST['vendor_id'] ?? 0);

// Default
$delete_count = false;

if (!empty($vendor_id)) {

    // Archived first, as in the app
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT vendor_name FROM vendors WHERE vendor_id = $vendor_id AND vendor_client_id = $client_id AND vendor_archived_at IS NOT NULL LIMIT 1"));

    if ($row) {

        $vendor_name = escapeSql($row['vendor_name']);

        // A vendor with expenses booked to it stays (functions/vendor.php)
        $blocker = vendorDeleteBlocker($vendor_id);
        if ($blocker) {
            http_response_code(400);
            $return_arr['success'] = "False";
            $return_arr['message'] = $row['vendor_name'] . " wasn't deleted - " . str_replace(' - archive it instead', '', $blocker) . ".";
            echo json_encode($return_arr);
            exit();
        }

        // What came from them stays, from no vendor; tags and links go with it
        vendorClearReferences($vendor_id);
        mysqli_query($mysqli, "DELETE FROM vendors WHERE vendor_id = $vendor_id AND vendor_client_id = $client_id LIMIT 1");
        $delete_count = mysqli_affected_rows($mysqli);

        if ($delete_count > 0) {
            logAudit("Vendor", "Delete", "$vendor_name via API ($api_key_name)", $client_id, $vendor_id);
            logAudit("API", "Success", "Deleted vendor $vendor_name via API ($api_key_name)", $client_id);
        }
    }
}

// Output
require_once '../delete_output.php';
