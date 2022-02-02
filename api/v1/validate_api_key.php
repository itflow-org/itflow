<?php
// Includes
include( __DIR__ . '../../../functions.php');
include(__DIR__ . "../../../config.php");

// JSON header
header('Content-Type: application/json');

// Get user IP
$ip = mysqli_real_escape_string($mysqli,get_ip());

// Setup return array
$return_arr = array();

/*
 * API Notes:
 *
 * To avoid over-complicating the app by using PUT and DELETE methods, only going to allow the use of GET and POST methods.
 * GET - Retrieving (READ) data
 * POST - Inserting (CREATE), Updating (UPDATE) or Deleting (DELETE) data
 *
 * Data returned as json encoded $return_arr:-
     * Success - True/False
     * Message - Brief into about a request / failure info
     * Count - Count of rows affected/returned
     * Data - Data from GET requests
 *
 */

// Decline methods other than GET/POST
if($_SERVER['REQUEST_METHOD'] !== "GET" AND $_SERVER['REQUEST_METHOD'] !== "POST"){
    header("HTTP/1.1 405 Method Not Allowed");
    var_dump($_SERVER['REQUEST_METHOD']);
    exit();
}

// Check API key is provided
if(!isset($_GET['api_key']) AND !isset($_POST['api_key'])){
    header("HTTP/1.1 401 Unauthorized");
    exit();
}

// Set API key variable
if(isset($_GET['api_key'])){
    $api_key = $_GET['api_key'];
}
if(isset($_POST['api_key'])){
    $api_key = $_POST['api_key'];
}

// Validate API key
if(isset($api_key)){
    $api_key = mysqli_real_escape_string($mysqli,$api_key);

    $sql = mysqli_query($mysqli,"SELECT * FROM settings, companies WHERE settings.company_id = companies.company_id AND settings.config_api_key = '$api_key'");

    // Failed
    if(mysqli_num_rows($sql) != 1){
        // Invalid Key
        header("HTTP/1.1 401 Unauthorized");
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'API', log_action = 'Incorrect Key', log_description = 'Failed from $ip', log_created_at = NOW()");

        $return_arr['success'] = "False";
        $return_arr['message'] = "API Key authentication failure.";

        header("HTTP/1.1 401 Unauthorized");
        echo json_encode($return_arr);
        exit();
    }

    // Success
    else{

        // Set company ID
        $row = mysqli_fetch_array($sql);
        $company_id = $row['company_id'];

        // Set limit & offset for queries
        if(isset($_GET['limit'])){
            $limit = intval($_GET['limit']);
        }
        elseif(isset($_POST['limit'])){
            $limit = intval($_POST['limit']);
        }
        else{
            $limit = 50;
        }

        if(isset($_GET['offset'])){
            $offset = intval($_GET['offset']);
        }
        elseif(isset($_POST['offset'])){
            $offset = intval($_POST['offset']);
        }
        else{
            $offset = 0;
        }

    }
}