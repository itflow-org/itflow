<?php

/*
 * ITFlow - GET/POST request handler for client networks
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_network'])) {

    enforceUserPermission('module_support', 2);

    require_once 'post/user/network_model.php';

    mysqli_query($mysqli,"INSERT INTO networks SET network_name = '$name', network_description = '$description', network_vlan = $vlan, network = '$network', network_subnet = '$subnet', network_gateway = '$gateway', network_primary_dns = '$primary_dns', network_secondary_dns = '$secondary_dns', network_dhcp_range = '$dhcp_range', network_notes = '$notes', network_location_id = $location_id, network_client_id = $client_id");

    $network_id = mysqli_insert_id($mysqli);

    // Logging
    logAction("Network", "Create", "$session_name created network $name", $client_id, $network_id);

    $_SESSION['alert_message'] = "Network <strong>$name</strong> created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_network'])) {

    enforceUserPermission('module_support', 2);

    $network_id = intval($_POST['network_id']);
    require_once 'post/user/network_model.php';

    mysqli_query($mysqli,"UPDATE networks SET network_name = '$name', network_description = '$description', network_vlan = $vlan, network = '$network', network_subnet = '$subnet', network_gateway = '$gateway', network_primary_dns = '$primary_dns', network_secondary_dns = '$secondary_dns', network_dhcp_range = '$dhcp_range', network_notes = '$notes', network_location_id = $location_id WHERE network_id = $network_id");

    // Logging
    logAction("Network", "Edit", "$session_name edited network $name", $client_id, $network_id);

    $_SESSION['alert_message'] = "Network <strong>$name</strong> updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['archive_network'])) {

    enforceUserPermission('module_support', 2);

    $network_id = intval($_GET['archive_network']);

    // Get Network Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT network_name, network_client_id FROM networks WHERE network_id = $network_id");
    $row = mysqli_fetch_array($sql);
    $network_name = sanitizeInput($row['network_name']);
    $client_id = intval($row['network_client_id']);

    mysqli_query($mysqli,"UPDATE networks SET network_archived_at = NOW() WHERE network_id = $network_id");

    // Logging
    logAction("Network", "Archive", "$session_name archived network $network_name", $client_id, $network_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Network <strong>$network_name</strong> archived";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['unarchive_network'])) {

    enforceUserPermission('module_support', 2);

    $network_id = intval($_GET['unarchive_network']);

    // Get Network Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT network_name, network_client_id FROM networks WHERE network_id = $network_id");
    $row = mysqli_fetch_array($sql);
    $network_name = sanitizeInput($row['network_name']);
    $client_id = intval($row['network_client_id']);

    mysqli_query($mysqli,"UPDATE networks SET network_archived_at = NULL WHERE network_id = $network_id");

    // logging
    logAction("Network", "Unarchive", "$session_name restored contact $contact_name", $client_id, $network_id);

    $_SESSION['alert_message'] = "Network <strong>$network_name</strong> restored";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['delete_network'])) {
    enforceUserPermission('module_support', 3);

    $network_id = intval($_GET['delete_network']);

    // Get Network Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT network_name, network_client_id FROM networks WHERE network_id = $network_id");
    $row = mysqli_fetch_array($sql);
    $network_name = sanitizeInput($row['network_name']);
    $client_id = intval($row['network_client_id']);

    mysqli_query($mysqli,"DELETE FROM networks WHERE network_id = $network_id");

    // Logging
    logAction("Network", "Delete", "$session_name deleted network $network_name", $client_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Network <strong>$network_name</strong> deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['bulk_delete_networks'])) {
    enforceUserPermission('module_support', 3);
    validateCSRFToken($_POST['csrf_token']);

    if (isset($_POST['network_ids'])) {

        // Get Selected Count
        $count = count($_POST['network_ids']);

        // Cycle through array and delete each network
        foreach ($_POST['network_ids'] as $network_id) {

            $network_id = intval($network_id);

            // Get Network Name and Client ID for logging and alert message
            $sql = mysqli_query($mysqli,"SELECT network_name, network_client_id FROM networks WHERE network_id = $network_id");
            $row = mysqli_fetch_array($sql);
            $network_name = sanitizeInput($row['network_name']);
            $client_id = intval($row['network_client_id']);

            mysqli_query($mysqli, "DELETE FROM networks WHERE network_id = $network_id AND network_client_id = $client_id");
            
            // Logging
            logAction("Network", "Delete", "$session_name deleted network $network_name", $client_id);

        }

        // Logging
        logAction("Network", "Bulk Delete", "$session_name deleted $count network(s)", $client_id);

        $_SESSION['alert_type'] = "error";
        $_SESSION['alert_message'] = "Deleted <strong>$count</strong> network(s)";

    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['export_networks_csv'])) {

    enforceUserPermission('module_support', 2);

    if (isset($_POST['client_id'])) {
        $client_id = intval($_POST['client_id']);
        $client_query = "AND network_client_id = $client_id";
    } else {
        $client_query = '';
    }

    $sql = mysqli_query($mysqli,"SELECT * FROM networks WHERE network_archived_at IS NULL $client_query ORDER BY network_name ASC");

    $num_rows = mysqli_num_rows($sql);

    if ($num_rows > 0) {
        $delimiter = ",";
        $filename = "Networks-" . date('Y-m-d') . ".csv";

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
    logAction("Network", "Export", "$session_name deleted $num_rows network(s) to a CSV file");

    exit;

}
