<?php

/*
 * API - validate_api_key.php
 * Called by API endpoint to validate API key is valid
 * Allows execution to continue or exits returning errors to the user
 */

// Includes
require_once __DIR__ . '../../../functions.php';
require_once __DIR__ . "../../../config.php";

// ITFlow's timezone on both clocks, as the pages and cron have it - what the API
// stamps, the SLA times it works out and when a key expires all go by it
require_once __DIR__ . "/../../includes/inc_set_timezone.php";

// JSON header
header('Content-Type: application/json');

// POST data
$_POST = json_decode(file_get_contents('php://input'), true);

// Get IP & UA
$ip = escapeSql(getIP());
$user_agent = escapeSql($_SERVER['HTTP_USER_AGENT']);

// Temp Added this to work with the new logAction function
$session_ip = $ip;
$session_user_agent = $user_agent;

// Setup return array
$return_arr = array();

// Unauthorised wording
DEFINE("WORDING_UNAUTHORIZED", "HTTP/1.1 401 Unauthorized");

/*
 * API Notes:
 *
 * To avoid over-complicating the app by using PUT and DELETE methods, only going to allow the use of GET and POST methods.
 * GET - Retrieving (READ) data
 * POST - Inserting (CREATE), Updating (UPDATE) or Deleting (DELETE) data
 *
 * Data returned as json encoded $return_arr:-
     * Success - True/False
     * Message - Brief info about a request / failure
     * Count - Count of rows affected/returned
     * Data - Requested data
 *
 */

// Decline methods other than GET/POST
if ($_SERVER['REQUEST_METHOD'] !== "GET" && $_SERVER['REQUEST_METHOD'] !== "POST") {
    header("HTTP/1.1 405 Method Not Allowed");
    var_dump($_SERVER['REQUEST_METHOD']);
    exit();
}

// Check API key is provided
if (!isset($_GET['api_key']) && !isset($_POST['api_key'])) {
    header(WORDING_UNAUTHORIZED);
    exit();
}

// Set API key variable
if (isset($_GET['api_key'])) {
    $api_key = escapeSql($_GET['api_key']);
}
if (isset($_POST['api_key'])) {
    $api_key = escapeSql($_POST['api_key']);
}

// Validate API key
if (isset($api_key)) {
    $api_key = escapeSql($api_key);

    $sql = mysqli_query($mysqli, "SELECT api_key_id, api_key_decrypt_hash, api_key_name, api_key_user_id FROM api_keys WHERE api_key_secret = '$api_key' AND api_key_expire > NOW() LIMIT 1");

    // Failed
    if (mysqli_num_rows($sql) !== 1) {
        // Invalid Key - an expired one is logged against it (functions/api_keys.php), and a run of them from one address tells the admins

        $url_path = escapeSql(parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH));
        $expired_key = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT api_key_id, api_key_name, api_key_expire FROM api_keys WHERE api_key_secret = '$api_key' LIMIT 1"));
        if ($expired_key) {
            $failed_description = escapeSql("Expired API key " . $expired_key['api_key_name'] . " used - it ended " . $expired_key['api_key_expire']) . " (endpoint: $url_path)";
        } else {
            $failed_description = "Unknown API key used (endpoint: $url_path)";
        }
        mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'API', log_action = 'Failed', log_description = '$failed_description', log_ip = '$ip', log_user_agent = '$user_agent',
            log_entity_id = " . intval($expired_key['api_key_id'] ?? 0));
        apiKeyWatch();

        $return_arr['success'] = "False";
        $return_arr['message'] = "Authentication failed. API key is invalid or has expired.";

        header(WORDING_UNAUTHORIZED);
        echo json_encode($return_arr);
        exit();

    } else {

        // SUCCESS

        // Set client ID, company ID & key name
        $row = mysqli_fetch_assoc($sql);
        $api_key_name = htmlentities($row['api_key_name']);
        $api_key_decrypt_hash = $row['api_key_decrypt_hash']; // No sanitization
        $api_key_user_id = intval($row['api_key_user_id']);

        // When it was last used, for Admin > API Keys - at most once a minute, not every call
        mysqli_query($mysqli, "UPDATE api_keys SET api_key_last_used_at = NOW() WHERE api_key_id = " . intval($row['api_key_id']) . "
            AND (api_key_last_used_at IS NULL OR api_key_last_used_at < NOW() - INTERVAL 1 MINUTE)");

        // What it is used for - each call counted to its day and endpoint (functions/api_keys.php)
        apiKeyUsageRecord($row['api_key_id'], apiKeyUsageEndpoint($_SERVER['REQUEST_URI'] ?? ''), getIP());

        // Set limit & offset for queries
        if (isset($_GET['limit'])) {
            $limit = intval($_GET['limit']);
        } elseif (isset($_POST['limit'])) {
            $limit = intval($_POST['limit']);
        } else {
            $limit = 50;
        }

        if (isset($_GET['offset'])) {
            $offset = intval($_GET['offset']);
        } elseif (isset($_POST['offset'])) {
            $offset = intval($_POST['offset']);
        } else {
            $offset = 0;
        }

        // When the key is tied to a user, enforce that user's RBAC (module + operation + client scope)
        require __DIR__ . '/enforce_api_rbac.php';

    }
}
