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

    $api_key_ID = createAPIKey($secret, $name, $expire, $client);

    referWithAlert("API Key $api_key_id created", "success");

}

if (isset($_GET['delete_api_key'])) {

    validateAdminRole();

    // CSRF Check
    validateCSRFToken($_GET['csrf_token']);

    $api_key_id = intval($_GET['delete_api_key']);

    deleteAPIKey($api_key_id);

    referWithAlert("API Key $api_key_id deleted", "success");

}

if (isset($_POST['bulk_delete_api_keys'])) {
    validateAdminRole();
    validateCSRFToken($_POST['csrf_token']);

    $count = 0; // Default 0
    $api_key_ids = $_POST['api_key_ids']; // Get array of API key IDs to be deleted

    foreach ($api_key_ids as $api_key_id) {
        deleteAPIKey($api_key_id);
        $count++;
    }
    referWithAlert("$count API Keys deleted");
}
