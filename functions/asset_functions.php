<?php

// Asset Related Functions

// These functions compatible with api/v2
function createAsset(
    $parameters
) {
    $client_id = $parameters['client_id'];
    $name = $parameters['asset_name'];
    $description = $parameters['asset_description']??'';
    $type = $parameters['asset_type'];
    $make = $parameters['asset_make']??'';
    $model = $parameters['asset_model']??'';
    $serial = $parameters['asset_serial']??'';
    $os = $parameters['asset_os']??'';
    $ip = $parameters['asset_ip']??'';
    $nat_ip = $parameters['asset_nat_ip']??'';
    $mac = $parameters['asset_mac']??'';
    $uri = $parameters['asset_uri']??'';
    $uri_2 = $parameters['asset_uri_2']??'';
    $status = $parameters['asset_status']??'';
    $location = $parameters['asset_location']??'NULL';
    $vendor = $parameters['asset_vendor']??'NULL';
    $contact = $parameters['asset_contact']??'NULL';
    $network = $parameters['asset_network']??'NULL';
    $purchase_date = $parameters['asset_purchase_date']??'NULL';
    $warranty_expire = $parameters['asset_warranty_expire']??'NULL';
    $install_date = $parameters['asset_install_date']??'NULL';
    $notes = $parameters['asset_notes']??'';


    $return_message = "";
    if (empty($name)) {
        $return_message .= "Asset Name is required. ";
    }
    if (empty($client_id)) {
        $return_message .= "Client ID is required. ";
    }
    if (empty($type)) {
        $return_message .= "Asset Type is required. ";
    }elseif (!in_array($type, ['Server', 'Desktop', 'Laptop', 'Tablet', 'Phone', 'Printer', 'Switch', 'Router', 'Firewall', 'Access Point', 'Other'])) {
        $return_message .= "Invalid Asset Type. ";
    }


    global $mysqli, $session_ip, $session_user_agent, $session_user_id, $session_name;

    if (!isset($session_ip)) {
        //Assume API is making changes
        $session_ip = $parameters['api_ip'];
        $session_user_agent = $parameters['api_key_name'];
        $session_user_id = 0;
        $session_name = "API";
    }

    $alert_extended = "";

    mysqli_query($mysqli,"INSERT INTO assets SET asset_name = '$name', asset_description = '$description', asset_type = '$type', asset_make = '$make', asset_model = '$model', asset_serial = '$serial', asset_os = '$os', asset_ip = '$ip', asset_nat_ip = '$nat_ip', asset_mac = '$mac', asset_uri = '$uri', asset_uri_2 = '$uri_2', asset_location_id = $location, asset_vendor_id = $vendor, asset_contact_id = $contact, asset_status = '$status', asset_purchase_date = $purchase_date, asset_warranty_expire = $warranty_expire, asset_install_date = $install_date, asset_notes = '$notes', asset_network_id = $network, asset_client_id = $client_id");

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
    $asset_id = sanitizeInput($parameters['asset_id']);

    global $mysqli;

    // Check if there is an API Key Client ID parameter, if so, use it. Otherwise, default to 'all'
    $api_client_id = isset($parameters['api_key_client_id']) ? sanitizeInput($parameters['api_key_client_id']) : 0;
    // Get the where clause for the query
    $where_clause = getAPIWhereClause("asset", $asset_id, $api_client_id);

    $query = "SELECT * FROM assets $where_clause";
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
