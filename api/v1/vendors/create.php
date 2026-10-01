<?php

require_once '../validate_api_key.php';

require_once '../require_post_method.php';

// Parse Info
require_once 'vendor_model.php';

// Default
$insert_id = false;

if (!empty($name)) {

    // Insert vendor
    $insert_sql = mysqli_query($mysqli, "INSERT INTO vendors SET vendor_name = '$name', vendor_description = '$description', vendor_contact_name = '$contact_name', vendor_phone_country_code = '$phone_country_code', vendor_phone = '$phone', vendor_extension = '$extension', vendor_email = '$email', vendor_website = '$website', vendor_hours = '$hours', vendor_sla = '$sla', vendor_code = '$code', vendor_account_number = '$account_number', vendor_notes = '$notes', vendor_client_id = $client_id");

    // Check insert & get insert ID
    if ($insert_sql) {
        $insert_id = mysqli_insert_id($mysqli);

        // Logging
        logAudit("Vendor", "Create", "$name via API ($api_key_name)", $client_id, $insert_id);
        logAudit("API", "Success", "Created vendor $name via API ($api_key_name)", $client_id, $insert_id);
    }
}

// Output
require_once '../create_output.php';