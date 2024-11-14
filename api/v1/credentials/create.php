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

        // Logging
        logAction("Credential", "Create", "$name via API ($api_key_name)", $client_id, $insert_id);
        logAction("API", "Success", "Created credential $name via API ($api_key_name)", $client_id);
    }

}

// Output
require_once '../create_output.php';
