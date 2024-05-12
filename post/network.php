<?php

/*
 * ITFlow - GET/POST request handler for client networks
 */

if (isset($_POST['add_network'])) {

    validateTechRole();

    $client_id = intval($_POST['client_id']);
    $name = sanitizeInput($_POST['name']);
    $description = sanitizeInput($_POST['description']);
    $vlan = intval($_POST['vlan']);
    $network = sanitizeInput($_POST['network']);
    $subnet = sanitizeInput($_POST['subnet']);
    $gateway = sanitizeInput($_POST['gateway']);
    $primary_dns = sanitizeInput($_POST['primary_dns']);
    $secondary_dns = sanitizeInput($_POST['secondary_dns']);
    $dhcp_range = sanitizeInput($_POST['dhcp_range']);
    $notes = sanitizeInput($_POST['notes']);
    $location_id = intval($_POST['location']);

    mysqli_query($mysqli,"INSERT INTO networks SET network_name = '$name', network_description = '$description', network_vlan = $vlan, network = '$network', network_subnet = '$subnet', network_gateway = '$gateway', network_primary_dns = '$primary_dns', network_secondary_dns = '$secondary_dns', network_dhcp_range = '$dhcp_range', network_notes = '$notes', network_location_id = $location_id, network_client_id = $client_id");

    $network_id = mysqli_insert_id($mysqli);

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Network', log_action = 'Create', log_description = '$session name created network $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $network_id");

    $_SESSION['alert_message'] = "Network <strong>$name</strong> created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_network'])) {

    validateTechRole();

    $network_id = intval($_POST['network_id']);
    $name = sanitizeInput($_POST['name']);
    $description = sanitizeInput($_POST['description']);
    $vlan = intval($_POST['vlan']);
    $network = sanitizeInput($_POST['network']);
    $subnet = sanitizeInput($_POST['subnet']);
    $gateway = sanitizeInput($_POST['gateway']);
    $primary_dns = sanitizeInput($_POST['primary_dns']);
    $secondary_dns = sanitizeInput($_POST['secondary_dns']);
    $dhcp_range = sanitizeInput($_POST['dhcp_range']);
    $notes = sanitizeInput($_POST['notes']);
    $location_id = intval($_POST['location']);
    $client_id = intval($_POST['client_id']);

    mysqli_query($mysqli,"UPDATE networks SET network_name = '$name', network_description = '$description', network_vlan = $vlan, network = '$network', network_subnet = '$subnet', network_gateway = '$gateway', network_primary_dns = '$primary_dns', network_secondary_dns = '$secondary_dns', network_dhcp_range = '$dhcp_range', network_notes = '$notes', network_location_id = $location_id WHERE network_id = $network_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Network', log_action = 'Modify', log_description = '$session_name modified network $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $network_id");

    $_SESSION['alert_message'] = "Network <strong>$name</strong> updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['archive_network'])) {

    validateTechRole();

    $network_id = intval($_GET['archive_network']);

    // Get Network Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT network_name, network_client_id FROM networks WHERE network_id = $network_id");
    $row = mysqli_fetch_array($sql);
    $network_name = sanitizeInput($row['network_name']);
    $client_id = intval($row['network_client_id']);

    mysqli_query($mysqli,"UPDATE networks SET network_archived_at = NOW() WHERE network_id = $network_id");

    //logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Network', log_action = 'Archive', log_description = '$session_name archived network $network_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $network_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Network <strong>$network_name</strong> archived";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['delete_network'])) {
    validateAdminRole();

    $network_id = intval($_GET['delete_network']);

    // Get Network Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT network_name, network_client_id FROM networks WHERE network_id = $network_id");
    $row = mysqli_fetch_array($sql);
    $network_name = sanitizeInput($row['network_name']);
    $client_id = intval($row['network_client_id']);

    mysqli_query($mysqli,"DELETE FROM networks WHERE network_id = $network_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Network', log_action = 'Delete', log_description = '$session_name deleted network $network_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $network_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Network <strong>$network_name</strong> deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['bulk_delete_networks'])) {
    validateAdminRole();
    validateCSRFToken($_POST['csrf_token']);

    $count = 0; // Default 0
    $network_ids = $_POST['network_ids']; // Get array of network IDs to be deleted
    $client_id = intval($_POST['client_id']);

    if (!empty($network_ids)) {

        // Cycle through array and delete each network
        foreach ($network_ids as $network_id) {

            $network_id = intval($network_id);
            mysqli_query($mysqli, "DELETE FROM networks WHERE network_id = $network_id AND network_client_id = $client_id");
            mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Network', log_action = 'Delete', log_description = '$session_name deleted a network (bulk)', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $network_id");

            $count++;
        }

        // Logging
        mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Certificate', log_action = 'Network', log_description = '$session_name bulk deleted $count networks', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id");

        $_SESSION['alert_message'] = "Deleted $count network(s)";

    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['export_client_networks_csv'])) {

    validateTechRole();

    $client_id = intval($_POST['client_id']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT * FROM clients WHERE client_id = $client_id");
    $row = mysqli_fetch_array($sql);

    $client_name = $row['client_name'];

    $sql = mysqli_query($mysqli,"SELECT * FROM networks WHERE network_client_id = $client_id ORDER BY network_name ASC");

    $num_rows = mysqli_num_rows($sql);

    if ($num_rows > 0) {
        $delimiter = ",";
        $filename = $client_name . "-Networks-" . date('Y-m-d') . ".csv";

        //create a file pointer
        $f = fopen('php://memory', 'w');

        //set column headers
        $fields = array('Name', 'Description', 'vLAN', 'IP/Network', 'Subnet Mask', 'Gateway', 'Primary DNS', 'Secondary DNS', 'DHCP Range');
        fputcsv($f, $fields, $delimiter);

        //output each row of the data, format line as csv and write to file pointer
        while($row = $sql->fetch_assoc()) {
            $lineData = array($row['network_name'], $row['network_description'], $row['network_vlan'], $row['network'], $row['network_subnet'], $row['network_gateway'], $row['network_primary_dns'], $row['network_secondary_dns'], $row['network_dhcp_range']);
            fputcsv($f, $lineData, $delimiter);
        }

        //move back to beginning of file
        fseek($f, 0);

        //set headers to download file rather than displayed
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '";');

        //output all remaining data on a file pointer
        fpassthru($f);
    }

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Network', log_action = 'Export', log_description = '$session_name exported $num_rows network(s) to a CSV file', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id");

    exit;

}
