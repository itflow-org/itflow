<?php

require_once '../validate_api_key.php';

require_once '../require_post_method.php';

// Parse ID
$vendor_id = intval($_POST['vendor_id']);

// Default
$update_count = false;

if (!empty($vendor_id)) {

    $vendor_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM vendors WHERE vendor_id = $vendor_id AND vendor_client_id = $client_id AND vendor_archived_at IS NULL LIMIT 1"));

    // Variable assignment from POST - assigning the current database value if a value is not provided
    require_once 'vendor_model.php';

    if ($vendor_row) {
        $update_sql = mysqli_query($mysqli, "UPDATE vendors SET vendor_name = '$name', vendor_description = '$description', vendor_contact_name = '$contact_name', vendor_phone_country_code = '$phone_country_code', vendor_phone = '$phone', vendor_extension = '$extension', vendor_email = '$email', vendor_website = '$website', vendor_hours = '$hours', vendor_sla = '$sla', vendor_code = '$code', vendor_account_number = '$account_number', vendor_notes = '$notes' WHERE vendor_id = $vendor_id AND vendor_client_id = $client_id AND vendor_archived_at IS NULL LIMIT 1");

        // Check update & get affected rows
        if ($update_sql) {
            $update_count = mysqli_affected_rows($mysqli);

            // Logging
            logAudit("Vendor", "Edit", "$name via API ($api_key_name)", $client_id, $vendor_id);
            logAudit("API", "Success", "Edited vendor $name via API ($api_key_name)", $client_id, $vendor_id);
        }
    }
}

// Output
require_once '../update_output.php';