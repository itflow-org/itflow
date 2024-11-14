<?php

require_once '../validate_api_key.php';

require_once '../require_post_method.php';


// Parse Info
$contact_id = intval($_POST['contact_id']);

// Default
$update_count = false;

if (!empty($contact_id)) {

    $contact_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM contacts WHERE contact_id = '$contact_id' AND contact_client_id = $client_id LIMIT 1"));

    // Variable assignment from POST - assigning the current database value if a value is not provided
    require_once 'contact_model.php';


    $update_sql = mysqli_query($mysqli, "UPDATE contacts SET contact_name = '$name', contact_title = '$title', contact_department = '$department', contact_email = '$email', contact_phone = '$phone', contact_extension = '$extension', contact_mobile = '$mobile',  contact_notes = '$notes', contact_primary = '$primary', contact_important = '$important', contact_billing = '$billing', contact_technical = '$technical', contact_location_id = $location_id, contact_client_id = $client_id WHERE contact_id = $contact_id LIMIT 1");

    // Check insert & get insert ID
    if ($update_sql) {
        $update_count = mysqli_affected_rows($mysqli);

        // Logging
        logAction("Contact", "Edit", "$name via API ($api_key_name)", $client_id, $contact_id);
        logAction("API", "Success", "Edited contact $name via API ($api_key_name)", $client_id);
    }
}

// Output
require_once '../update_output.php';
