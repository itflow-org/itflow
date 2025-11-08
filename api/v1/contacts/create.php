<?php

require_once '../validate_api_key.php';

require_once '../require_post_method.php';


// Parse Info
require_once 'contact_model.php';


// Default
$insert_id = false;

if (!empty($name) && !empty($email) && !empty($client_id)) {

    // Check contact with $email doesn't already exist
    $email_duplication_sql = mysqli_query($mysqli, "SELECT * FROM contacts WHERE contact_email = '$email' AND contact_client_id = '$client_id'");

    if (mysqli_num_rows($email_duplication_sql) == 0) {

        // Remove other primary contact in clients if primary contact is selected
        if ($primary == 1) {
            mysqli_query($mysqli,"UPDATE contacts SET contact_primary = 0 WHERE contact_client_id = $client_id");
        }

        // Insert contact
        $insert_sql = mysqli_query($mysqli, "INSERT INTO contacts SET contact_name = '$name', contact_title = '$title', contact_department = '$department', contact_email = '$email', contact_phone = '$phone', contact_extension = '$extension', contact_mobile = '$mobile', contact_notes = '$notes', contact_primary = '$primary', contact_important = '$important', contact_billing = '$billing', contact_technical = '$technical', contact_location_id = $location_id, contact_client_id = $client_id");

        // Check insert & get insert ID
        if ($insert_sql) {
            $insert_id = mysqli_insert_id($mysqli);
            
            // Logging
            logAction("Contact", "Create", "$name via API ($api_key_name)", $client_id, $insert_id);
            logAction("API", "Success", "Created contact $name via API ($api_key_name)", $client_id);
        }

    }
}

// Output
require_once '../create_output.php';

