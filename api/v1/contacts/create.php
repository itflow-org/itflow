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

        // Insert contact
        $insert_sql = mysqli_query($mysqli, "INSERT INTO contacts SET contact_name = '$name', contact_title = '$title', contact_department = '$department', contact_email = '$email', contact_phone = '$phone', contact_extension = '$extension', contact_mobile = '$mobile', contact_notes = '$notes', contact_primary = '$primary', contact_important = '$important', contact_billing = '$billing', contact_technical = '$technical', contact_location_id = $location_id, contact_client_id = $client_id");

        // Check insert & get insert ID
        if ($insert_sql) {
            $insert_id = mysqli_insert_id($mysqli);
            //Logging
            mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Contact', log_action = 'Created', log_description = '$name via API ($api_key_name)', log_ip = '$ip', log_user_agent = '$user_agent', log_client_id = $client_id");
            mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'API', log_action = 'Success', log_description = 'Created contact $name via API ($api_key_name)', log_ip = '$ip', log_user_agent = '$user_agent', log_client_id = $client_id");
        }

    }
}

// Output
require_once '../create_output.php';

