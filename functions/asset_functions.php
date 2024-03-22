<?php

// Asset Related Functions

// These functions compatible with api/v2
function createAsset(
    $parameters
) {
    // Set parameters to the new values or the old values if not set, and sanitize
    $client_id = intval($parameters['client_id']);
    $name = sanitizeInput($parameters['asset_name']);
    $description = isset($parameters['asset_description']) ? sanitizeInput($parameters['asset_description']) : '';
    $type = isset($parameters['asset_type']) ? sanitizeInput($parameters['asset_type']) : 'Other';
    $make = isset($parameters['asset_make']) ? sanitizeInput($parameters['asset_make']) : '';
    $model = isset($parameters['asset_model']) ? sanitizeInput($parameters['asset_model']) : '';
    $serial = isset($parameters['asset_serial']) ? sanitizeInput($parameters['asset_serial']) : '';
    $os = isset($parameters['asset_os']) ? sanitizeInput($parameters['asset_os']) : '';
    $ip = isset($parameters['asset_ip']) ? sanitizeInput($parameters['asset_ip']) : '';
    $nat_ip = isset($parameters['asset_nat_ip']) ? sanitizeInput($parameters['asset_nat_ip']) : '';
    $mac = isset($parameters['asset_mac']) ? sanitizeInput($parameters['asset_mac']) : '';
    $uri = isset($parameters['asset_uri']) ? sanitizeInput($parameters['asset_uri']) : '';
    $uri_2 = isset($parameters['asset_uri_2']) ? sanitizeInput($parameters['asset_uri_2']) : '';
    $status = isset($parameters['asset_status']) ? sanitizeInput($parameters['asset_status']) : 'Ready To Deploy';
    $location = isset($parameters['asset_location']) ? intval($parameters['asset_location']) : 0;
    $vendor = isset($parameters['asset_vendor']) ? intval($parameters['asset_vendor']) : 0;
    $contact = isset($parameters['asset_contact']) ? intval($parameters['asset_contact']) : 0;
    $network = isset($parameters['asset_network']) ? intval($parameters['asset_network']) : 0;
    $purchase_date = isset($parameters['asset_purchase_date']) ? date('Y-m-d', strtotime($parameters['asset_purchase_date'])) : 'NULL';
    $warranty_expire = isset($parameters['asset_warranty_expire']) ? date($parameters['asset_warranty_expire']) : 'NULL';
    $install_date = isset($parameters['asset_install_date']) ? date($parameters['asset_install_date']) : 'NULL';
    $notes = isset($parameters['asset_notes']) ? sanitizeInput($parameters['asset_notes']) : '';
    $rmm_id = isset($parameters['asset_rmm_id']) ? intval($parameters['asset_rmm_id']) : 0;

    // Check for required fields
    $return_message = "";
    if (empty($name)) {
        $return_message .= "Asset Name is required. ";
    }
    if (empty($client_id)) {
        $return_message .= "Client ID is required. ";
    }
    if (empty($type)) {
        $return_message .= "Asset Type is required. ";
    }

    // if $parameters['asset_ip'] is array and not empty, sanitize each value and implode with comma
    if (is_array($parameters['asset_ip']) && !empty($parameters['asset_ip'])) {
        $ip = implode(',', array_map('sanitizeInput', $parameters['asset_ip']));
    }


    //if $type starts with WINDOWS, LINUX, or MAC, remove the prefix plus a character, and set $os to the prefix (if not already set)
    if (substr(strtoupper($type), 0, 7) == "WINDOWS") {
        $os = $os??"Windows";
        $type = ucfirst(strtolower(substr($type, 8)));
    } elseif (substr(strtoupper($type), 0, 5) == "LINUX") {
        $os = $os??"Linux";
        $type = ucfirst(strtolower(substr($type, 6)));
    } elseif (substr(strtoupper($type), 0, 3) == "MAC") {
        $os = $os??"Mac";
        $type = "Laptop";
    }

    //  If $type is "Workstation", set it to "Desktop"
    if ($type == "Workstation") {
        $type = "Desktop";
    }

    // If $type is not in the list of valid types, set it to "Other"
    // If $type is a number, set it to a corresponding type
    if (!in_array($type, ['Server', 'Desktop', 'Laptop', 'Tablet', 'Phone', 'Printer', 'Switch', 'Router', 'Firewall', 'Access Point', 'Other'])) {
        $return_message .= "Invalid asset type. ";
    }
    
    // If return_message is not empty, return an error
    if ($return_message != "") {
        return ['status' => 'error', 'message' => $return_message];
    }

    global $mysqli, $session_ip, $session_user_agent, $session_user_id, $session_name;

    if (!isset($session_ip)) {
        //Assume API is making changes
        $session_ip = $parameters['api_ip'];
        $session_user_agent = $parameters['api_key_name'];
        $session_name = "API";
        $session_user_id = 0;
    }

    $alert_extended = "";
    $sql_query = "INSERT INTO assets SET asset_name = '$name', asset_description = '$description', asset_type = '$type', asset_make = '$make', asset_model = '$model', asset_serial = '$serial', asset_os = '$os', asset_ip = '$ip', asset_nat_ip = '$nat_ip', asset_mac = '$mac', asset_uri = '$uri', asset_uri_2 = '$uri_2', asset_location_id = $location, asset_vendor_id = $vendor, asset_contact_id = $contact, asset_status = '$status', asset_purchase_date = $purchase_date, asset_warranty_expire = $warranty_expire, asset_install_date = $install_date, asset_notes = '$notes', asset_network_id = $network, asset_client_id = $client_id, asset_rmm_id = $rmm_id, asset_created_at = NOW()";
    mysqli_query($mysqli,$sql_query);
    $sql_error = mysqli_error($mysqli);
    if ($sql_error) {
        echo json_encode(['status' => 'error', 'message' => $sql_error, 'sql' => $sql_query]);
    }


    $asset_id = mysqli_insert_id($mysqli);

    if (!empty($_POST['username'])) {
        $username = trim(mysqli_real_escape_string($mysqli, encryptLoginEntry($_POST['username'])));
        $password = trim(mysqli_real_escape_string($mysqli, encryptLoginEntry($_POST['password'])));

        mysqli_query($mysqli,"INSERT INTO logins SET login_name = '$name', login_username = '$username', login_password = '$password', login_asset_id = $asset_id, login_client_id = $client_id");

        $login_id = mysqli_insert_id($mysqli);

        //Logging
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Login', log_action = 'Create', log_description = '$session_name created login credentials for asset $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $login_id");

        $alert_extended = " along with login credentials";

    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Asset', log_action = 'Create', log_description = '$session_name created asset $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $asset_id");

    $return_data = [];
    $return_data['alert_extended'] = $alert_extended;
    $return_data['status'] = "success";
    return $return_data;
}

function readAsset(
    $parameters
) {
    global $mysqli;

    if (!empty($parameters['asset_id'])) {
        $asset_id = sanitizeInput($parameters['asset_id']);
        $api_client_id = isset($parameters['api_key_client_id']) ? sanitizeInput($parameters['api_key_client_id']) : 0;
        $where_clause = getAPIWhereClause("asset", $asset_id, $api_client_id);
    } elseif (!empty($parameters['asset_rmm_id'])) {
        $asset_rmm_id = $parameters['asset_rmm_id'];
        $api_client_id = isset($parameters['api_key_client_id']) ? sanitizeInput($parameters['api_key_client_id']) : 0;
        $where_clause = getAPIWhereClause("asset_rmm", $asset_rmm_id, $api_client_id);
    } else {
        return ['status' => 'error', 'message' => 'No asset ID or RMM ID provided'];
    }
    
    $columns = isset($parameters['columns']) ? sanitizeInput($parameters['columns']) : '*';

    $query = "SELECT $columns FROM assets $where_clause";
    $result = mysqli_query($mysqli, $query);

    $assets = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $assets[] = $row;
    }

    if (empty($assets)) {
        return ['status' => 'error', 'message' => 'No assets found'];
    }

    return $assets;
}

function updateAsset(
    $parameters,
) {
    if (isset($parameters['archived'])) {
        $archived = $parameters['archived'];
    } else {
        $archived = "not";
    }

    if ($archived == 1) {
        // Archive
        return archiveAsset($parameters['asset_id']);
    } elseif ($archived == 0) {
        // Unarchive
        return unarchiveAsset($parameters['asset_id']);
    }

    $asset_id = $parameters['asset_id'];
    $asset_data = readAsset(['asset_id' => $asset_id])[$asset_id];

    // Set parameters to the new values or the old values if not set
    $name = $parameters['asset_name']??$asset_data['asset_name'];
    $description = $parameters['description']??$asset_data['asset_description'];
    $type = $parameters['asset_type']??$asset_data['asset_type'];
    $make = $parameters['asset_make']??$asset_data['asset_make'];
    $model = $parameters['asset_model']??$asset_data['asset_model'];
    $serial = $parameters['asset_serial']??$asset_data['asset_serial'];
    $os = $parameters['asset_os']??$asset_data['asset_os'];
    $ip = $parameters['asset_ip']??$asset_data['asset_ip'];
    $nat_ip = $parameters['asset_nat_ip']??$asset_data['asset_nat_ip'];
    $mac = $parameters['asset_mac']??$asset_data['asset_mac'];
    $uri = $parameters['asset_uri']??$asset_data['asset_uri'];
    $uri_2 = $parameters['asset_uri_2']??$asset_data['asset_uri_2'];
    $location = $parameters['location']??$asset_data['asset_location_id'];
    $vendor = $parameters['vendor']??$asset_data['asset_vendor_id'];
    $contact = $parameters['contact']??$asset_data['asset_contact_id'];
    $network = $parameters['network']??$asset_data['asset_network_id'];
    $status = $parameters['status']??$asset_data['asset_status'];
    $notes = $parameters['notes']??$asset_data['asset_notes'];



    // if null, output 'NULL' for SQL, else output the data
    $purchase_date = $parameters['purchase_date']??($asset_data['asset_purchase_date'] == NULL ? 'NULL' : $asset_data['asset_purchase_date']);
    $warranty_expire = $parameters['warranty_expire']??($asset_data['asset_warranty_expire'] == NULL ? 'NULL' : $asset_data['asset_warranty_expire']);
    $install_date = $parameters['install_date']??($asset_data['asset_install_date'] == NULL ? 'NULL' : $asset_data['asset_install_date']);
    
    $notes = $parameters['notes']??$asset_data['asset_notes'];
    $client_id = $parameters['client_id']??$asset_data['asset_client_id'];

    if (isset($parameters['api_client_id']) && $parameters['api_client_id'] != 0){

        $api_client_id = $parameters['api_client_id'];

        if ($api_client_id != $client_id) {
            return ['status' => 'error', 'message' => 'You are not permitted to do that!'];
        } 
    }

    global $mysqli, $session_ip, $session_user_agent, $session_user_id, $session_name;

    if (!isset($session_ip)) {
        //Assume API is making changes
        $session_ip = "API";
        $session_user_agent = "API";
        $session_user_id = 0;
        $session_name = "API";
    }

    mysqli_query($mysqli,"UPDATE assets SET asset_name = '$name', asset_description = '$description', asset_type = '$type', asset_make = '$make', asset_model = '$model', asset_serial = '$serial', asset_os = '$os', asset_ip = '$ip', asset_nat_ip = '$nat_ip', asset_mac = '$mac', asset_uri = '$uri', asset_uri_2 = '$uri_2', asset_location_id = $location, asset_vendor_id = $vendor, asset_contact_id = $contact, asset_status = '$status', asset_purchase_date = $purchase_date, asset_warranty_expire = $warranty_expire, asset_install_date = $install_date, asset_notes = '$notes', asset_network_id = $network WHERE asset_id = $asset_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Asset', log_action = 'Modify', log_description = '$session_name modified asset $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $asset_id");

    $return_data = [
        'status' => 'success',
        'message' => "Asset $name has been updated",
        'asset' => readAsset(['asset_id' => $asset_id])
    ];

    return $return_data;
}

function deleteAsset(
    $parameters
) {
    $asset_id = $parameters['asset_id'];

    if (!isset($session_ip)) {
        //Assume API is making changes
        $session_ip = "API";
        $session_user_agent = "API";
        $session_user_id = 0;
        $session_name = "API";
    }

    global $mysqli, $session_ip, $session_user_agent, $session_user_id, $session_name;

    // Get Asset Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT asset_name, asset_client_id FROM assets WHERE asset_id = $asset_id");
    $row = mysqli_fetch_array($sql);
    $asset_name = sanitizeInput($row['asset_name']);
    $client_id = intval($row['asset_client_id']);

    mysqli_query($mysqli,"DELETE FROM assets WHERE asset_id = $asset_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Asset', log_action = 'Delete', log_description = '$session_name deleted asset $asset_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $asset_id");

    $return_data = [
        'status' => 'success',
        'message' => "Asset $asset_name has been deleted"
    ];
    return $return_data;
}



//Functions below this are not used by the API, but are used by the web interface. (They are not compatible with C.R.U.D.)
function archiveAsset(
    $asset_id
) {
    global $mysqli, $session_ip, $session_user_agent, $session_user_id, $session_name;

    if (!isset($session_ip)) {
        //Assume API is making changes
        $session_ip = "API";
        $session_user_agent = "API";
        $session_user_id = 0;
        $session_name = "API";
    }

    // Get Asset Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT asset_name, asset_client_id FROM assets WHERE asset_id = $asset_id");
    $row = mysqli_fetch_array($sql);
    $asset_name = sanitizeInput($row['asset_name']);
    $client_id = intval($row['asset_client_id']);

    mysqli_query($mysqli,"UPDATE assets SET asset_archived_at = NOW() WHERE asset_id = $asset_id");

    //logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Asset', log_action = 'Archive', log_description = '$session_name archived asset $asset_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $asset_id");

    $return_data = [
        'status' => 'success',
        'message' => "Asset $asset_name has been archived",
        'asset' => readAsset(['asset_id' => $asset_id])
    ];

    return $return_data;
}

function unarchiveAsset(
    $asset_id
) {
    global $mysqli, $session_ip, $session_user_agent, $session_user_id, $session_name;

    if (!isset($session_ip)) {
        //Assume API is making changes
        $session_ip = "API";
        $session_user_agent = "API";
        $session_user_id = 0;
        $session_name = "API";
    }

    // Get Asset Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT asset_name, asset_client_id FROM assets WHERE asset_id = $asset_id");
    $row = mysqli_fetch_array($sql);
    $asset_name = sanitizeInput($row['asset_name']);
    $client_id = intval($row['asset_client_id']);

    mysqli_query($mysqli,"UPDATE assets SET asset_archived_at = NULL WHERE asset_id = $asset_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Asset', log_action = 'Unarchive', log_description = '$session_name unarchived asset $asset_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $asset_id");

    $return_data = [
        'status' => 'success',
        'message' => "Asset $asset_name has been unarchived",
        'asset' => readAsset(['asset_id' => $asset_id])
    ];
    return $return_data;
}
