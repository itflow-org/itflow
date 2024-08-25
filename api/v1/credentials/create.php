<?php

require_once '../validate_api_key.php';

require_once '../require_post_method.php';

// Parse info
require_once 'credential_model.php';

// Default
$insert_id = false;

if (!empty($api_key_decrypt_password) && !empty($name) && !(empty($password))) {

    // Add credential
    $insert_sql = mysqli_query($mysqli,"INSERT INTO logins SET login_name = '$name', login_description = '$description', login_uri = '$uri', login_uri_2 = '$uri_2', login_username = '$username', login_password = '$password', login_otp_secret = '$otp_secret', login_note = '$note', login_important = $important, login_contact_id = $contact_id, login_vendor_id = $vendor_id, login_asset_id = $asset_id, login_software_id = $software_id, login_client_id = $client_id");

    // Check insert & get insert ID
    if ($insert_sql) {
        $insert_id = mysqli_insert_id($mysqli);

        //Logging
        mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Credential', log_action = 'Create', log_description = '$name via API ($api_key_name)', log_ip = '$ip', log_user_agent = '$user_agent', log_client_id = $client_id");
        mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'API', log_action = 'Success', log_description = 'Created credential $name via API ($api_key_name)', log_ip = '$ip', log_user_agent = '$user_agent', log_client_id = $client_id");
    }

}

// Output
require_once '../create_output.php';
