<?php

/*
 * ITFlow - GET/POST request handler for documented IP addresses
 *
 * IPs hang off a network (subnet). The two safeguards - valid + inside the
 * subnet, and not already documented on that network - live in
 * checkIpForNetwork() in functions/network.php so add, edit and CSV import all
 * apply exactly the same rules. There is a UNIQUE(ip_network_id, ip_address)
 * index behind them as a backstop.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_network_ip'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    require_once 'network_ip_model.php';

    $network_id = intval($_POST['network_id']);

    $client_id = intval(getFieldById('networks', $network_id, 'network_client_id'));

    enforceClientAccess();

    $ip_error = checkIpForNetwork($ip_address, $network_id);

    if ($ip_error) {
        flashAlert($ip_error, 'error');
        redirect();
    }

    $ip_address_escaped = escapeSql($ip_address);

    mysqli_query($mysqli, "INSERT INTO network_ips SET ip_address = '$ip_address_escaped', ip_hostname = '$hostname', ip_description = '$description', ip_network_id = $network_id");

    $ip_id = mysqli_insert_id($mysqli);

    $network_name = escapeSql(getFieldById('networks', $network_id, 'network_name'));

    logAudit("IP Address", "Create", "$session_name documented IP $ip_address_escaped on network $network_name", $client_id, $ip_id);

    flashAlert("IP <strong>$ip_address</strong> documented");

    redirect();

}

if (isset($_POST['edit_network_ip'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    require_once 'network_ip_model.php';

    $ip_id = intval($_POST['ip_id']);

    $network_id = intval(getFieldById('network_ips', $ip_id, 'ip_network_id'));

    $client_id = intval(getFieldById('networks', $network_id, 'network_client_id'));

    enforceClientAccess();

    // The row's own id is excluded so saving without changing the address
    // doesn't trip the duplicate check against itself
    $ip_error = checkIpForNetwork($ip_address, $network_id, $ip_id);

    if ($ip_error) {
        flashAlert($ip_error, 'error');
        redirect();
    }

    $ip_address_escaped = escapeSql($ip_address);

    mysqli_query($mysqli, "UPDATE network_ips SET ip_address = '$ip_address_escaped', ip_hostname = '$hostname', ip_description = '$description' WHERE ip_id = $ip_id");

    $network_name = escapeSql(getFieldById('networks', $network_id, 'network_name'));

    logAudit("IP Address", "Edit", "$session_name edited IP $ip_address_escaped on network $network_name", $client_id, $ip_id);

    flashAlert("IP <strong>$ip_address</strong> updated");

    redirect();

}

if (isset($_GET['delete_network_ip'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $ip_id = intval($_GET['delete_network_ip']);

    // Get IP and Client ID for logging and alert message
    $sql = mysqli_query($mysqli, "SELECT ip_address, network_client_id FROM network_ips LEFT JOIN networks ON network_id = ip_network_id WHERE ip_id = $ip_id");
    $row = mysqli_fetch_assoc($sql);
    $ip_address = escapeSql($row['ip_address']);
    $client_id = intval($row['network_client_id']);

    enforceClientAccess();

    mysqli_query($mysqli, "DELETE FROM network_ips WHERE ip_id = $ip_id");

    logAudit("IP Address", "Delete", "$session_name deleted IP $ip_address", $client_id);

    flashAlert("IP <strong>$ip_address</strong> deleted", 'error');

    redirect();

}

if (isset($_POST['bulk_delete_network_ips'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    if (isset($_POST['network_ip_ids'])) {

        // Get Selected Count
        $count = count($_POST['network_ip_ids']);

        $client_id = 0;

        // Cycle through array and delete each IP
        foreach ($_POST['network_ip_ids'] as $ip_id) {

            $ip_id = intval($ip_id);

            // Get IP and Client ID for logging and the access check
            $sql = mysqli_query($mysqli, "SELECT ip_address, network_client_id FROM network_ips LEFT JOIN networks ON network_id = ip_network_id WHERE ip_id = $ip_id");
            $row = mysqli_fetch_assoc($sql);
            $ip_address = escapeSql($row['ip_address']);
            $client_id = intval($row['network_client_id']);

            enforceClientAccess();

            mysqli_query($mysqli, "DELETE FROM network_ips WHERE ip_id = $ip_id");

            logAudit("IP Address", "Delete", "$session_name deleted IP $ip_address", $client_id);

        }

        logAudit("IP Address", "Bulk Delete", "$session_name deleted $count IP(s)", $client_id);

        flashAlert("Deleted <strong>$count</strong> IP(s)", 'error');

    }

    redirect();

}

if (isset($_GET['download_network_ips_csv_template'])) {

    enforceUserPermission('module_support');

    $network_id = intval($_GET['download_network_ips_csv_template']);

    $client_id = intval(getFieldById('networks', $network_id, 'network_client_id'));

    enforceClientAccess();

    $network_name = getFieldById('networks', $network_id, 'network_name');

    $delimiter = ",";
    $enclosure = '"';
    $escape    = '\\';
    $filename  = toAlphanumeric($network_name) . "-IPs-Template.csv";

    // Create a file pointer
    $f = fopen('php://memory', 'w');

    // Set column headers
    $fields = array('IP Address', 'Hostname', 'Description');
    fputcsv($f, $fields, $delimiter, $enclosure, $escape);

    // Move back to beginning of file
    fseek($f, 0);

    // Set headers to download file rather than displayed
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '";');

    // Output all remaining data on a file pointer
    fpassthru($f);
    exit;

}

if (isset($_POST['import_network_ips_csv'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $network_id = intval($_POST['network_id']);

    $client_id = intval(getFieldById('networks', $network_id, 'network_client_id'));

    enforceClientAccess();

    $network_name = escapeSql(getFieldById('networks', $network_id, 'network_name'));

    $error = false;

    // File provided?
    if (!empty($_FILES['file']['tmp_name'])) {
        $file_name = $_FILES['file']['tmp_name'];
    } else {
        flashAlert("Please select a file to upload.", 'error');
        redirect();
    }

    // Check file is CSV
    $file_parts = explode('.', $_FILES['file']['name']);
    $file_extension = strtolower(end($file_parts));
    if ($file_extension !== 'csv') {
        $error = true;
        flashAlert("Bad file extension - only .csv files are accepted.", 'error');
    }

    // Check file isn't empty
    elseif ($_FILES['file']['size'] < 1) {
        $error = true;
        flashAlert("Bad file size (empty file?).", 'error');
    }

    // Check column count (IP Address, Hostname, Description)
    else {
        $f = fopen($file_name, 'r');
        $f_columns = fgetcsv($f, 1000, ',');
        fclose($f);

        if (count($f_columns) !== 3) {
            $error = true;
            flashAlert("Bad column count - expected 3 columns: IP Address, Hostname, Description.", 'error');
        }
    }

    // Parse and insert
    if (!$error) {

        $file = fopen($file_name, 'r');
        fgetcsv($file, 1000, ','); // Skip header row

        $row_count = 0;
        $skipped_count = 0;

        while (($column = fgetcsv($file, 1000, ',')) !== false) {

            $ip_address = trim($column[0] ?? '');
            $hostname = escapeSql($column[1] ?? '');
            $description = escapeSql($column[2] ?? '');

            // Skip blank lines
            if ($ip_address === '') {
                continue;
            }

            // Same safeguards as the add form - invalid, out of subnet or
            // already documented all mean skip. Rows inserted earlier in this
            // same run are in the table already, so a file that repeats an
            // address imports it once
            if (checkIpForNetwork($ip_address, $network_id) !== '') {
                $skipped_count++;
                continue;
            }

            $ip_address_escaped = escapeSql($ip_address);

            mysqli_query($mysqli, "INSERT INTO network_ips SET ip_address = '$ip_address_escaped', ip_hostname = '$hostname', ip_description = '$description', ip_network_id = $network_id");

            $row_count++;

        }

        fclose($file);

        logAudit("IP Address", "Import", "$session_name imported $row_count IP(s) to network $network_name. $skipped_count row(s) skipped", $client_id);

        flashAlert("<strong>$row_count</strong> IP(s) imported, <strong>$skipped_count</strong> skipped (invalid, outside the subnet or already documented)");

    }

    redirect();

}

if (isExportRequest('export_network_ips')) {

    validateCSRFToken();

    // Exports are reads - see CONTRIBUTING.md
    enforceUserPermission('module_support');

    $format = resolveExportFormat($_POST['export_network_ips']);

    $network_id = intval($_POST['network_id']);

    $client_id = intval(getFieldById('networks', $network_id, 'network_client_id'));

    enforceClientAccess();

    $network_name = getFieldById('networks', $network_id, 'network_name');

    // Scoped to the one network, so no page filters apply
    $sql = mysqli_query(
        $mysqli,
        "SELECT ip_address, ip_hostname, ip_description, network_name, client_name FROM network_ips
        LEFT JOIN networks ON network_id = ip_network_id
        LEFT JOIN clients ON client_id = network_client_id
        WHERE ip_network_id = $network_id
        ORDER BY INET6_ATON(ip_address) ASC"
    );

    $num_rows = mysqli_num_rows($sql);

    if ($num_rows > 0) {

        guardExportPdfRowCount($format, $num_rows);

        $export = beginExport('network_ips', $format, toAlphanumeric($network_name) . '-IPs', "$network_name - IP Addresses", '');

        while ($row = mysqli_fetch_assoc($sql)) {
            addExportRow($export, $row);
        }

        finishExport($export);
    }

    logAudit("IP Address", "Export", "$session_name exported $num_rows IP(s) to a " . strtoupper($format) . " file", $client_id);

    exit;

}
