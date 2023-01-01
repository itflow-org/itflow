<?php

/*
 * API - validate_api_key.php
 * Called by API endpoint to validate API key is valid
 * Allows execution to continue or exits returning errors to the user
 */

// Includes
require_once( __DIR__ . '../../../functions.php');
require_once(__DIR__ . "../../../config.php");

// JSON header
header('Content-Type: application/json');

// POST data
$_POST = json_decode(file_get_contents('php://input'), true);

// Get user IP
$ip = strip_tags(mysqli_real_escape_string($mysqli, get_ip()));
// Get user agent
$user_agent = strip_tags(mysqli_real_escape_string($mysqli, $_SERVER['HTTP_USER_AGENT']));

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
    $api_key = $_GET['api_key'];
}
if (isset($_POST['api_key'])) {
    $api_key = $_POST['api_key'];
}

// Validate API key
if (isset($api_key)) {
    $api_key = mysqli_real_escape_string($mysqli, $api_key);

    $sql = mysqli_query($mysqli, "SELECT * FROM api_keys WHERE api_key_secret = '$api_key' AND api_key_expire > NOW() LIMIT 1");

    // Failed
    if (mysqli_num_rows($sql) !== 1) {
        // Invalid Key
        header(WORDING_UNAUTHORIZED);
        mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'API', log_action = 'Failed', log_description = 'Incorrect or expired Key', log_ip = '$ip', log_user_agent = '$user_agent', log_created_at = NOW()");

        $return_arr['success'] = "False";
        $return_arr['message'] = "API Key authentication failure or expired.";

        header(WORDING_UNAUTHORIZED);
        echo json_encode($return_arr);
        exit();
    }

    // Success
    else {

        // Set client ID, company ID & key name
        $row = mysqli_fetch_array($sql);
        $api_key_name = $row['api_key_name'];
        $client_id = $row['api_key_client_id'];
        $company_id = $row['company_id'];

        // Set limit & offset for queries
        if (isset($_GET['limit'])) {
            $limit = intval($_GET['limit']);
        }
        elseif (isset($_POST['limit'])) {
            $limit = intval($_POST['limit']);
        }
        else {
            $limit = 50;
        }

        if (isset($_GET['offset'])) {
            $offset = intval($_GET['offset']);
        }
        elseif (isset($_POST['offset'])) {
            $offset = intval($_POST['offset']);
        }
        else {
            $offset = 0;
        }

    }
}