<?php

require_once '../validate_api_key.php';

require_once '../require_post_method.php';

// Parse info
require_once 'credential_model.php';

// Default
$insert_id = false;

if (!empty($api_key_decrypt_password) && !empty($name) && !(empty($password))) {

    // Add credential
    $insert_sql = mysqli_query($mysqli,"INSERT INTO credentials SET credential_name = '$name', credential_description = '$description', credential_uri = '$uri', credential_uri_2 = '$uri_2', credential_username = '$username', credential_password = '$password', credential_otp_secret = '$otp_secret', credential_note = '$note', credential_important = $important, credential_contact_id = $contact_id, credential_vendor_id = $vendor_id, credential_asset_id = $asset_id, credential_software_id = $software_id, credential_client_id = $client_id");

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
