<?php

/*
 * ITFlow - GET/POST request handler for client networks
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_network'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    require_once 'network_model.php';

    $client_id = intval($_POST['client_id']);

    enforceClientAccess();

    mysqli_query($mysqli,"INSERT INTO networks SET network_name = '$name', network_description = '$description', network_vlan = $vlan, network = '$network', network_subnet = '$subnet', network_gateway = '$gateway', network_primary_dns = '$primary_dns', network_secondary_dns = '$secondary_dns', network_dhcp_range = '$dhcp_range', network_notes = '$notes', network_location_id = $location_id, network_client_id = $client_id");

    $network_id = mysqli_insert_id($mysqli);

    logAudit("Network", "Create", "$session_name created network $name", $client_id, $network_id);

    flashAlert("Network <strong>$name</strong> created");

    redirect();

}

if (isset($_POST['edit_network'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    require_once 'network_model.php';

    $network_id = intval($_POST['network_id']);

    $client_id = intval(getFieldById('networks', $network_id, 'network_client_id'));

    enforceClientAccess();

    mysqli_query($mysqli,"UPDATE networks SET network_name = '$name', network_description = '$description', network_vlan = $vlan, network = '$network', network_gateway = '$gateway', network_primary_dns = '$primary_dns', network_secondary_dns = '$secondary_dns', network_dhcp_range = '$dhcp_range', network_notes = '$notes', network_location_id = $location_id WHERE network_id = $network_id");

    logAudit("Network", "Edit", "$session_name edited network $name", $client_id, $network_id);

    flashAlert("Network <strong>$name</strong> updated");

    redirect();

}

if (isset($_GET['archive_network'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $network_id = intval($_GET['archive_network']);

    // Get Network Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT network_name, network_client_id FROM networks WHERE network_id = $network_id");
    $row = mysqli_fetch_assoc($sql);
    $network_name = escapeSql($row['network_name']);
    $client_id = intval($row['network_client_id']);

    enforceClientAccess();

    mysqli_query($mysqli,"UPDATE networks SET network_archived_at = NOW() WHERE network_id = $network_id");

    logAudit("Network", "Archive", "$session_name archived network $network_name", $client_id, $network_id);

    flashAlert("Network <strong>$network_name</strong> archived", 'error');

    redirect();

}

if (isset($_GET['restore_network'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $network_id = intval($_GET['restore_network']);

    // Get Network Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT network_name, network_client_id FROM networks WHERE network_id = $network_id");
    $row = mysqli_fetch_assoc($sql);
    $network_name = escapeSql($row['network_name']);
    $client_id = intval($row['network_client_id']);

    enforceClientAccess();

    mysqli_query($mysqli,"UPDATE networks SET network_archived_at = NULL WHERE network_id = $network_id");

    logAudit("Network", "Restore", "$session_name restored network $network_name", $client_id, $network_id);

    flashAlert("Network <strong>$network_name</strong> restored");

    redirect();

}

if (isset($_GET['delete_network'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 3);

    $network_id = intval($_GET['delete_network']);

    // Get Network Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT network_name, network_client_id FROM networks WHERE network_id = $network_id");
    $row = mysqli_fetch_assoc($sql);
    $network_name = escapeSql($row['network_name']);
    $client_id = intval($row['network_client_id']);

    enforceClientAccess();

    mysqli_query($mysqli,"DELETE FROM networks WHERE network_id = $network_id");

    logAudit("Network", "Delete", "$session_name deleted network $network_name", $client_id);

    flashAlert("Network <strong>$network_name</strong> deleted", 'error');

    redirect();

}

if (isset($_POST['bulk_delete_networks'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 3);

    if (isset($_POST['network_ids'])) {

        // Get Selected Count
        $count = count($_POST['network_ids']);

        // Cycle through array and delete each network
        foreach ($_POST['network_ids'] as $network_id) {

            $network_id = intval($network_id);

            // Get Network Name and Client ID for logging and alert message
            $sql = mysqli_query($mysqli,"SELECT network_name, network_client_id FROM networks WHERE network_id = $network_id");
            $row = mysqli_fetch_assoc($sql);
            $network_name = escapeSql($row['network_name']);
            $client_id = intval($row['network_client_id']);

            enforceClientAccess();

            mysqli_query($mysqli, "DELETE FROM networks WHERE network_id = $network_id AND network_client_id = $client_id");

            logAudit("Network", "Delete", "$session_name deleted network $network_name", $client_id);

        }

        logAudit("Network", "Bulk Delete", "$session_name deleted $count network(s)", $client_id);

        flashAlert("Deleted <strong>$count</strong> network(s)", 'error');

    }

    redirect();

}

if (isset($_POST['export_networks'])) {

    validateCSRFToken();

    // Exports are reads - see CONTRIBUTING.md
    enforceUserPermission('module_support');

    $format = resolveExportFormat($_POST['export_networks']);

    // Filters inherited from the networks page - mirrors agent/networks.php
    $filter_summary = [];

    // Archived Filter
    $archived = (isset($_POST['archived']) && $_POST['archived'] == 1);
    if ($archived) {
        $filter_summary['Archived'] = 'Archived only';
    }

    if (!empty($_POST['client_id'])) {
        $client_id = intval($_POST['client_id']);
        $client_query = "AND network_client_id = $client_id";
        $client_name = getFieldById('clients', $client_id, 'client_name');
        $file_name_prepend = "$client_name-";
        $filter_summary['Client'] = $client_name;

        enforceClientAccess();

        $archive_query = $archived ? "network_archived_at IS NOT NULL" : "network_archived_at IS NULL";
    } else {
        $client_query = '';
        $client_id = 0; // for Logging
        $file_name_prepend = "$session_company_name-";

        // Client Filter
        if (!empty($_POST['client'])) {
            $filter_client_id = intval($_POST['client']);
            $client_query = "AND (network_client_id = $filter_client_id)";
            $filter_summary['Client'] = getFieldById('clients', $filter_client_id, 'client_name');
        }

        $archive_query = $archived ? "(client_archived_at IS NOT NULL OR network_archived_at IS NOT NULL)" : "(client_archived_at IS NULL AND network_archived_at IS NULL)";
    }

    // Location Filter
    if (!empty($_POST['location'])) {
        $filter_location_id = intval($_POST['location']);
        $location_query = "AND (network_location_id = $filter_location_id)";
        $filter_summary['Location'] = getFieldById('locations', $filter_location_id, 'location_name');
    } else {
        // Default - any
        $location_query = '';
    }

    // Search Filter
    $q = escapeSql($_POST['q'] ?? '');
    if (!empty($q)) {
        $filter_summary['Search'] = $_POST['q'];
    }

    $sql = mysqli_query(
        $mysqli,
        "SELECT * FROM networks
        LEFT JOIN clients ON client_id = network_client_id
        LEFT JOIN locations ON location_id = network_location_id
        WHERE $archive_query
        AND (network_name LIKE '%$q%' OR network_description LIKE '%$q%' OR network_vlan LIKE '%$q%' OR network LIKE '%$q%' OR network_gateway LIKE '%$q%' OR network_primary_dns LIKE '%$q%' OR network_secondary_dns LIKE '%$q%' OR client_name LIKE '%$q%')
        " . clientScopeSql('network_client_id') . "
        $location_query
        $client_query
        ORDER BY network_name ASC"
    );

    $num_rows = mysqli_num_rows($sql);

    if ($num_rows > 0) {

        guardExportPdfRowCount($format, $num_rows);

        $export = beginExport('networks', $format, $file_name_prepend . 'Networks', 'Networks', summarizeExportFilters($filter_summary));

        while ($row = mysqli_fetch_assoc($sql)) {
            addExportRow($export, $row);
        }

        finishExport($export);
    }

    logAudit("Network", "Export", "$session_name exported $num_rows network(s) to a " . strtoupper($format) . " file", $client_id);

    exit;

}

// ============================================================
// Add these two blocks to agent/post/network.php
// Place them alongside the existing export_networks block.
// ============================================================

// ----------------------------------------------------------
// CSV Template Download
// GET: post.php?download_networks_csv_template=<client_id>
// ----------------------------------------------------------
if (isset($_GET['download_networks_csv_template'])) {

    $delimiter = ",";
    $enclosure = '"';
    $escape    = '\\';
    $filename  = "Networks-Template.csv";

    $f = fopen('php://memory', 'w');

    $fields = array('Name', 'Description', 'VLAN', 'Network (CIDR)', 'Gateway', 'IP Range', 'Primary DNS', 'Secondary DNS');
    fputcsv($f, $fields, $delimiter, $enclosure, $escape);

    // One example row so the user can see expected formatting
    $example = array('Office LAN', 'Main office network', '10', '192.168.1.0/24', '192.168.1.1', '192.168.1.100-192.168.1.200', '8.8.8.8', '8.8.4.4');
    fputcsv($f, $example, $delimiter, $enclosure, $escape);

    fseek($f, 0);

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '";');

    fpassthru($f);
    exit;

}

// ----------------------------------------------------------
// CSV Import
// POST: post.php  (name="import_networks_csv")
// ----------------------------------------------------------
if (isset($_POST['import_networks_csv'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $client_id = intval($_POST['client_id']);

    enforceClientAccess();

    $error = false;

    // File provided?
    if (!empty($_FILES['file']['tmp_name'])) {
        $file_name = $_FILES['file']['tmp_name'];
    } else {
        flashAlert("Please select a file to upload.", 'error');
        redirect();
    }

    // Check extension
    $file_extension = strtolower(end(explode('.', $_FILES['file']['name'])));
    if ($file_extension !== 'csv') {
        $error = true;
        flashAlert("Bad file extension — only .csv files are accepted.", 'error');
    }

    // Check not empty
    elseif ($_FILES['file']['size'] < 1) {
        $error = true;
        flashAlert("Bad file size (empty file?).", 'error');
    }

    // Check column count matches the 8-column export/template format
    else {
        $f = fopen($file_name, 'r');
        $f_columns = fgetcsv($f, 1000, ',');
        fclose($f);

        if (count($f_columns) !== 8) {
            $error = true;
            flashAlert("Bad column count — expected 8 columns: Name, Description, VLAN, Network (CIDR), Gateway, IP Range, Primary DNS, Secondary DNS.", 'error');
        }
    }

    // Parse and insert
    if (!$error) {
        $file = fopen($file_name, 'r');
        fgetcsv($file, 1000, ','); // Skip header row

        $row_count       = 0;
        $duplicate_count = 0;

        while (($column = fgetcsv($file, 1000, ',')) !== false) {

            $duplicate_detect = 0;

            $name         = isset($column[0]) ? escapeSql($column[0]) : '';
            $description  = isset($column[1]) ? escapeSql($column[1]) : '';
            $vlan         = isset($column[2]) ? intval($column[2])         : 0;
            $network      = isset($column[3]) ? escapeSql($column[3]) : '';
            $gateway      = isset($column[4]) ? escapeSql($column[4]) : '';
            $dhcp_range   = isset($column[5]) ? escapeSql($column[5]) : '';
            $primary_dns  = isset($column[6]) ? escapeSql($column[6]) : '';
            $secondary_dns = isset($column[7]) ? escapeSql($column[7]) : '';

            // Skip rows with no name
            if ($name === '') {
                continue;
            }

            // Duplicate check — same name + network address for this client
            $dup_check = mysqli_query($mysqli,
                "SELECT network_id FROM networks
                 WHERE network_name = '$name'
                   AND network = '$network'
                   AND network_client_id = $client_id
                   AND network_archived_at IS NULL
                 LIMIT 1"
            );

            if (mysqli_num_rows($dup_check) > 0) {
                $duplicate_detect = 1;
            }

            if ($duplicate_detect === 0) {
                mysqli_query($mysqli,
                    "INSERT INTO networks SET
                        network_name         = '$name',
                        network_description  = '$description',
                        network_vlan         = $vlan,
                        network              = '$network',
                        network_gateway      = '$gateway',
                        network_dhcp_range   = '$dhcp_range',
                        network_primary_dns  = '$primary_dns',
                        network_secondary_dns = '$secondary_dns',
                        network_client_id    = $client_id"
                );
                $row_count++;
            } else {
                $duplicate_count++;
            }
        }

        fclose($file);

        logAudit("Network", "Import", "$session_name imported $row_count network(s). $duplicate_count duplicate(s) found and not imported", $client_id);

        flashAlert("$row_count Network(s) imported, $duplicate_count duplicate(s) detected and not imported");

        redirect();
    }

    if ($error) {
        redirect();
    }

}
