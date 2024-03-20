<?php

// api/v2/api.php

/*  This file is the entry point for the API.
    
    It receives requests from the client, validates them, and then calls the appropriate function to handle the request.
    It then returns the result to the client.

    Only the functions with objects in the objects.php file are permitted to be called by the API.
    They must be in the format of actionObject($parameters) where action is one of create, read, update, or delete.

    The API key is required for all requests and is used to authenticate the client.

    The parameters are passed as an associative array and are validated and sanitized before being passed to the function.
    
    The API returns the result of the function as a JSON object.

    Example usage:

    --- Create a new client ---

        POST /api/v2/api.php?object=client&action=create&parameters[See objects.php for valid parameters]&api_key=[API Key


    --- Read a client ---

        GET /api/v2/api.php?object=client&action=read&parameters[See objects.php for valid parameters]&api_key=[API Key]


    --- Expected Result ---
        {
            "status": "success",
                ...
        }
        
*/

require '/var/www/develop.twe.tech/config.php';
require '/var/www/develop.twe.tech/functions.php';
require '/var/www/develop.twe.tech/api/v2/objects.php';

// Check the request method and get the object, parameters, and action from the request
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize the object
    $object = strtolower(sanitizeInput($_POST['object']));
    // Decode the parameters
    $parameters = $_POST['parameters'];
    // Sanitize the API key
    $api_key = sanitizeInput($_POST['api_key']);
    // Check if the action is set
    if (!isset($_POST['action'])) {
        // Default to create if no action is specified
        $action = 'create';
    } else {
        // Sanitize the action
        $action = strtolower(sanitizeInput($_POST['action']));
    }
} elseif ($_SERVER['REQUEST_METHOD'] == 'GET') {
    // Sanitize the object
    $object = strtolower(sanitizeInput($_GET['object']));
    // Decode the parameters
    $parameters = $_GET['parameters'];
    // Sanitize the API key
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
    echo json_encode(['ERROR' => 'Invalid request method. Use GET for read requests and POST for create, update, and delete requests']); 
    exit;
}

// Check if the API key is valid
$api_key_data = tryAPIKey($api_key);
if (isset($api_key_data['api_key_client_id'])) {
    $api_client_id = $api_key_data['api_key_client_id'];
}

// Check if action is CRUD
if (!in_array($action, ['create', 'read', 'update', 'delete'])) {
    echo json_encode(['error' => 'Invalid action in request. ' . $action . ' must be one of create, read, update, or delete']);
    exit;
}

// Check the parameters
if (is_string($parameters)) {
    $parameters = json_decode($parameters, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        // Handle JSON decode error (e.g., invalid JSON format)
        echo json_encode(['error' => 'Invalid JSON format in parameters. ' . json_last_error_msg() . ' in ' . $parameters]);
        exit;
    }
}

// Sanitize the parameters
$sanitized_parameters = [];
if (is_array($parameters)) {
    foreach ($parameters as $key => $value) {
        $sanitized_parameters[sanitizeInput($key)] = sanitizeInput($value);
    }
} else{
    echo json_encode(['WARN' => 'Invalid parameters in request. Parameters must be included as an associative array. is not an array.']);
    exit;
}

// Replace the parameters with the sanitized parameters
$parameters = $sanitized_parameters;

// Add the api_key_name to the parameters if it is not already set
if (!isset($parameters['api_key_name'])) {
    $parameters['api_key_name'] = $api_key_data['api_key_name'];
    // Add IP address to the parameters
    $parameters['api_ip'] = $_SERVER['REMOTE_ADDR'];
}
// Check if the object is valid
if (!in_array($object, $valid_objects)) {
    echo json_encode(['error' => 'Invalid object in request. ' . $object . ' is not an object to be manipulated via the API. Valid objects are: ' . implode(',', $valid_objects) . '.']);
    exit;
}
//Uppercase every first letter of the object
$object = ucwords($object);

// Remove spaces in object
$object = str_replace(' ', '', $object);

// Create function
$function = $action . $object;

// Check if the function exists
if (!function_exists($function)) {
    echo json_encode(['error' => 'Invalid function in request. This is probably a bug. Please report this to the developer. ' . $function . '() does not exist.']);
    exit;
}
// Call the function
try{
    $function_result = $function($parameters);
}
// Catch any exceptions
catch (Exception $e) {
    echo json_encode(['error' => 'Invalid function result. Function returned exception. This is probably a bug. Please report this to the developer. ' . $e->getMessage()]);
    exit;
}

// Return the result as formatted JSON objects
if ($function_result) {
    if (is_array($function_result)) {
        foreach ($function_result as $key => $value) {
            echo json_encode($value);
        }
    } else {
        echo json_encode(['error' => 'Invalid function result. This is probably a bug. Please report this to the developer. ERR: ' . json_encode($function_result) . ' is not an array.']);
    }
} else {
    echo json_encode(['error' => 'Invalid function result. This is probably a bug. Please report this to the developer. ERR: ' . json_encode($function_result) . ' is not a valid result.']);
}
exit;
