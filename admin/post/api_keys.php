<?php

/*
 * ITFlow - GET/POST request handler for API settings
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_api_key'])) {

    validateCSRFToken($_POST['csrf_token']);

    $name = sanitizeInput($_POST['name']);
    $expire = sanitizeInput($_POST['expire']);
    $client_id = intval($_POST['client']);
    $secret = sanitizeInput($_POST['key']); // API Key

    // Credential decryption password
    $password = password_hash(trim($_POST['password']), PASSWORD_DEFAULT);
    $apikey_specific_encryption_ciphertext = encryptUserSpecificKey(trim($_POST['password']));

    mysqli_query($mysqli,"INSERT INTO api_keys SET api_key_name = '$name', api_key_secret = '$secret', api_key_decrypt_hash = '$apikey_specific_encryption_ciphertext', api_key_expire = '$expire', api_key_client_id = $client_id");

    $api_key_id = mysqli_insert_id($mysqli);

    // Logging
    logAction("API Key", "Create", "$session_name created API key $name set to expire on $expire", $client_id, $api_key_id);

    $_SESSION['alert_message'] = "API Key <strong>$name</strong> created";

    redirect();

}

if (isset($_GET['delete_api_key'])) {

    validateCSRFToken($_GET['csrf_token']);

    $api_key_id = intval($_GET['delete_api_key']);

    // Get API Key Name
    $row = mysqli_fetch_array(mysqli_query($mysqli,"SELECT api_key_name, api_key_client_id FROM api_keys WHERE api_key_id = $api_key_id"));
    $api_key_name = sanitizeInput($row['api_key_name']);
    $client_id = intval($row['api_key_client_id']);

    mysqli_query($mysqli,"DELETE FROM api_keys WHERE api_key_id = $api_key_id");

    // Logging
    logAction("API Key", "Delete", "$session_name deleted API key $name", $client_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "API Key <strong>$name</strong> deleted";

    redirect();

}

if (isset($_POST['bulk_delete_api_keys'])) {

    validateCSRFToken($_POST['csrf_token']);

    if (isset($_POST['api_key_ids'])) {

        $count = count($_POST['api_key_ids']);

        // Cycle through array and delete each record
        foreach ($_POST['api_key_ids'] as $api_key_id) {

            $api_key_id = intval($api_key_id);
            
            // Get API Key Name
            $row = mysqli_fetch_array(mysqli_query($mysqli,"SELECT api_key_name, api_key_client_id FROM api_keys WHERE api_key_id = $api_key_id"));
            $api_key_name = sanitizeInput($row['api_key_name']);
            $client_id = intval($row['api_key_client_id']);

            mysqli_query($mysqli, "DELETE FROM api_keys WHERE api_key_id = $api_key_id");

            // Logging
            logAction("API Key", "Delete", "$session_name deleted API key $name", $client_id);

        }

        // Logging
        logAction("API Key", "Bulk Delete", "$session_name deleted $count API key(s)");

        $_SESSION['alert_type'] = "error";
        $_SESSION['alert_message'] = "Deleted <strong>$count</strong> API keys(s)";

    }

    redirect();
}
