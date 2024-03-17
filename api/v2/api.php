<?php

// api/v2/api.php

/*  This file is the entry point for the API.
    
    It receives requests from the client, validates them, and then calls the appropriate function to handle the request.
    It then returns the result to the client.

    example usage:

    GET /api/v2/api.php
    {
        "object": "asset",
        "parameters": {
            "asset_id": "all"
        },
    }

    example response:
    {

    }
*/

require '/var/www/develop.twe.tech/config.php';
require '/var/www/develop.twe.tech/functions.php';
require '/var/www/develop.twe.tech/api/v2/objects.php';

// Check the request method and get the object, parameters, and action from the request
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $object = strtolower(sanitizeInput($_POST['object']));
    $parameters = $_POST['parameters'];
    $api_key = sanitizeInput($_POST['api_key']);
    if (!isset($_POST['action'])) {
        // Default to create if no action is specified
        $action = 'create';
    } else {
        // Sanitize the action
        $action = strtolower(sanitizeInput($_POST['action']));
    }
} elseif ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $object = strtolower(sanitizeInput($_GET['object']));
    $parameters = $_GET['parameters'];
    $api_key = sanitizeInput($_GET['api_key']);
    if (!isset($_GET['action'])) {
        // Default to read if no action is specified
        $action = 'read';
    } else {
        // Sanitize the action
        $action = strtolower(sanitizeInput($_GET['action']));
    }
} else {
    // Invalid request method
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

// Check if the API key is valid
$api_key_data = tryAPIKey($api_key);
if (isset($api_key_data['api_key_client_id'])) {
    $api_client_id = $api_key_data['api_key_client_id'];
}

// Check if action is CRUD
if (!in_array($action, ['create', 'read', 'update', 'delete'])) {
    echo json_encode(['error' => 'Invalid action in request']);
    exit;
}

// Check the parameters
if (is_string($parameters)) {
    $parameters = json_decode($parameters, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        // Handle JSON decode error (e.g., invalid JSON format)
        echo json_encode(['error' => 'Invalid JSON format in parameters']);
        exit;
    }
}

// Sanitize the parameters
$sanitized_parameters = [];
foreach ($parameters as $key => $value) {
    $sanitized_parameters[sanitizeInput($key)] = sanitizeInput($value);
}
// Replace the parameters with the sanitized parameters
$parameters = $sanitized_parameters;

if (!isset($parameters['api_key_client_id'])) {
    $parameters['api_key_client_id'] = $api_client_id;
}
// Check if the object is valid
if (!in_array($object, $valid_objects)) {
    echo json_encode(['error' => 'Invalid object in request']);
    exit;
}
//Uppercase every first letter of the object
$object = ucwords($object);

// Remove spaces in object
$object = str_replace(' ', '', $object);

// Create function
$function = $action . $object;

if (!function_exists($function)) {
    echo json_encode(['error' => 'Invalid function in request']);
    exit;
}
if ($action == 'read') {
    // Call the function and return the result
    echo json_encode($function($parameters));
    exit;
} else {
    // Call the function and return the result
    echo json_encode($function($parameters)['status']);
    exit;
}
