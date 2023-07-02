<?php

/*
 * ITFlow - GET/POST request handler for API settings
 */

if (isset($_POST['add_api_key'])) {

    validateAdminRole();

    // CSRF Check
    validateCSRFToken($_POST['csrf_token']);

    $secret = sanitizeInput($_POST['key']);
    $name = sanitizeInput($_POST['name']);
    $expire = sanitizeInput($_POST['expire']);
    $client = intval($_POST['client']);

    mysqli_query($mysqli,"INSERT INTO api_keys SET api_key_name = '$name', api_key_secret = '$secret', api_key_expire = '$expire', api_key_client_id = $client");

    $api_key_id = mysqli_insert_id($mysqli);

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'API', log_action = 'Create', log_description = '$session_name created API Key $name set to expire on $expire', log_ip = '$session_ip', log_user_agent = '$session_user_agent',  log_client_id = $client, log_user_id = $session_user_id, log_entity_id = $api_key_id");

    $_SESSION['alert_message'] = "API Key <strong>$name</strong> created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['delete_api_key'])) {

    validateAdminRole();

    // CSRF Check
    validateCSRFToken($_GET['csrf_token']);

    $api_key_id = intval($_GET['delete_api_key']);

    // Get API Key Name
    $row = mysqli_fetch_array(mysqli_query($mysqli,"SELECT * FROM api_keys WHERE api_key_id = $api_key_id"));
    $name = sanitizeInput($row['api_key_name']);

    mysqli_query($mysqli,"DELETE FROM api_keys WHERE api_key_id = $api_key_id");

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'API Key', log_action = 'Delete', log_description = '$session_name deleted API key $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, log_entity_id = $api_key_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "API Key <strong>$name</strong> deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['bulk_delete_api_keys'])) {
    validateAdminRole();
    validateCSRFToken($_POST['csrf_token']);

    $count = 0; // Default 0
    $api_key_ids = $_POST['api_key_ids']; // Get array of API key IDs to be deleted

    if (!empty($api_key_ids)) {

        // Cycle through array and delete each scheduled ticket
        foreach ($api_key_ids as $api_key_id) {

            $api_key_id = intval($api_key_id);
            mysqli_query($mysqli, "DELETE FROM api_keys WHERE api_key_id = $api_key_id");
            mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'API Key', log_action = 'Delete', log_description = '$session_name deleted API key (bulk)', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, log_entity_id = $api_key_id");

            $count++;
        }

        // Logging
        mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'API Key', log_action = 'Delete', log_description = '$session_name bulk deleted $count keys', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

        $_SESSION['alert_message'] = "Deleted $count keys(s)";

    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}
