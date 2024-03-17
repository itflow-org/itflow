<?php

// Asset Related Functions
// Compatible with api/v2

function createAsset(
    $parameters
) {
    $client_id = $parameters['client_id'];
    $name = $parameters['name'];
    $description = $parameters['description'];
    $type = $parameters['type'];
    $make = $parameters['make'];
    $model = $parameters['model'];
    $serial = $parameters['serial'];
    $os = $parameters['os'];
    $ip = $parameters['ip'];
    $nat_ip = $parameters['nat_ip'];
    $mac = $parameters['mac'];
    $uri = $parameters['uri'];
    $uri_2 = $parameters['uri_2'];
    $status = $parameters['status'];
    $location = $parameters['location'];
    $vendor = $parameters['vendor'];
    $contact = $parameters['contact'];
    $network = $parameters['network'];
    $purchase_date = $parameters['purchase_date'];
    $warranty_expire = $parameters['warranty_expire'];
    $install_date = $parameters['install_date'];
    $notes = $parameters['notes'];

    global $mysqli, $session_ip, $session_user_agent, $session_user_id, $session_name;

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
    if ($asset_id == 'all') {
        $result = mysqli_query($mysqli,"SELECT * FROM assets");
    } else {
        $result = mysqli_query($mysqli,"SELECT * FROM assets WHERE asset_id = $asset_id");
        if (mysqli_num_rows($result) == 0) {
            return ['status' => 'error', 'message' => 'Asset not found'];
        }
    }

    $assets = [];
    while ($row = mysqli_fetch_assoc($result)) {
        foreach ($row as $key => $value) {
            $row[$key] = sanitizeInput($value);
        }
        $assets[] = $row;
    }
    return $assets;
}

function updateAsset(
    $parameters,
    $archive = -1
) {
    if ($archive == 1) {
        // Archive
        archiveAsset($parameters['asset_id']);
        exit;
    } elseif ($archive == 0) {
        // Unarchive
        unarchiveAsset($parameters['asset_id']);
        exit;
    }

    $name = $parameters['name'];
    $description = $parameters['description'];
    $type = $parameters['type'];
    $make = $parameters['make'];
    $model = $parameters['model'];
    $serial = $parameters['serial'];
    $os = $parameters['os'];
    $ip = $parameters['ip'];
    $nat_ip = $parameters['nat_ip'];
    $mac = $parameters['mac'];
    $uri = $parameters['uri'];
    $uri_2 = $parameters['uri_2'];
    $status = $parameters['status'];
    $location = $parameters['location'];
    $vendor = $parameters['vendor'];
    $contact = $parameters['contact'];
    $network = $parameters['network'];
    $purchase_date = $parameters['purchase_date'];
    $warranty_expire = $parameters['warranty_expire'];
    $install_date = $parameters['install_date'];
    $notes = $parameters['notes'];
    $asset_id = $parameters['asset_id'];
    $client_id = $parameters['client_id'];

    global $mysqli, $session_ip, $session_user_agent, $session_user_id, $session_name;

    if (!empty($dhcp)) {
        $dhcp = 1;
    } else {
        $dhcp = 0;
    }

    mysqli_query($mysqli,"UPDATE assets SET asset_name = '$name', asset_description = '$description', asset_type = '$type', asset_make = '$make', asset_model = '$model', asset_serial = '$serial', asset_os = '$os', asset_ip = '$ip', asset_nat_ip = '$nat_ip', asset_mac = '$mac', asset_uri = '$uri', asset_uri_2 = '$uri_2', asset_location_id = $location, asset_vendor_id = $vendor, asset_contact_id = $contact, asset_status = '$status', asset_purchase_date = $purchase_date, asset_warranty_expire = $warranty_expire, asset_install_date = $install_date, asset_notes = '$notes', asset_network_id = $network WHERE asset_id = $asset_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Asset', log_action = 'Modify', log_description = '$session_name modified asset $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $asset_id");

    return ['status' => 'success'];
}

function archiveAsset(
    $asset_id
) {
    global $mysqli, $session_ip, $session_user_agent, $session_user_id, $session_name;

    // Get Asset Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT asset_name, asset_client_id FROM assets WHERE asset_id = $asset_id");
    $row = mysqli_fetch_array($sql);
    $asset_name = sanitizeInput($row['asset_name']);
    $client_id = intval($row['asset_client_id']);

    mysqli_query($mysqli,"UPDATE assets SET asset_archived_at = NOW() WHERE asset_id = $asset_id");

    //logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Asset', log_action = 'Archive', log_description = '$session_name archived asset $asset_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $asset_id");

    return ['status' => 'success'];
}

function unarchiveAsset(
    $asset_id
) {
    global $mysqli, $session_ip, $session_user_agent, $session_user_id, $session_name;

    // Get Asset Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT asset_name, asset_client_id FROM assets WHERE asset_id = $asset_id");
    $row = mysqli_fetch_array($sql);
    $asset_name = sanitizeInput($row['asset_name']);
    $client_id = intval($row['asset_client_id']);

    mysqli_query($mysqli,"UPDATE assets SET asset_archived_at = NULL WHERE asset_id = $asset_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Asset', log_action = 'Unarchive', log_description = '$session_name unarchived asset $asset_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $asset_id");

    return ['status' => 'success'];
}

function deleteAsset(
    $parameters
) {
    $asset_id = $parameters['asset_id'];

    global $mysqli, $session_ip, $session_user_agent, $session_user_id, $session_name;

    // Get Asset Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT asset_name, asset_client_id FROM assets WHERE asset_id = $asset_id");
    $row = mysqli_fetch_array($sql);
    $asset_name = sanitizeInput($row['asset_name']);
    $client_id = intval($row['asset_client_id']);

    mysqli_query($mysqli,"DELETE FROM assets WHERE asset_id = $asset_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Asset', log_action = 'Delete', log_description = '$session_name deleted asset $asset_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $asset_id");

    return ['status' => 'success'];
}