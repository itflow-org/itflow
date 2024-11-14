<?php

require_once '../validate_api_key.php';

require_once '../require_post_method.php';

// Parse ID
$login_id = intval($_POST['login_id']);

// Default
$update_count = false;

if (!empty($_POST['api_key_decrypt_password']) && !empty($login_id)) {

    $credential_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM logins WHERE login_id = '$login_id' AND login_client_id = $client_id LIMIT 1"));

    // Variable assignment from POST - assigning the current database value if a value is not provided
    require_once 'credential_model.php';

    $update_sql = mysqli_query($mysqli,"UPDATE logins SET login_name = '$name', login_description = '$description', login_uri = '$uri', login_uri_2 = '$uri_2', login_username = '$username', login_password = '$password', login_otp_secret = '$otp_secret', login_note = '$note', login_important = $important, login_contact_id = $contact_id, login_vendor_id = $vendor_id, login_asset_id = $asset_id, login_software_id = $software_id, login_client_id = $client_id WHERE login_id = '$login_id' AND login_client_id = $client_id LIMIT 1");

    // Check insert & get insert ID
    if ($update_sql) {
        $update_count = mysqli_affected_rows($mysqli);

        if ($password_changed) {
            mysqli_query($mysqli, "UPDATE logins SET login_password_changed_at = NOW() WHERE login_id = $login_id LIMIT 1");
        }

        // Logging
        logAction("Credential", "Edit", "$name via API ($api_key_name)", $client_id, $login_id);
        logAction("API", "Success", "Updated credential $name via API ($api_key_name)", $client_id);
    }

}

// Output
require_once '../update_output.php';
