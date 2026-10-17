<?php

/*
 * ITFlow - GET/POST request handler for client networks
 *
 * A network is checked and saved by networkFormSet() (functions/network.php):
 * a real subnet, stored as its network address; a gateway and DHCP range
 * inside it; addresses for the DNS servers; a site and provider that are the
 * client's. The addresses documented on a network are agent/post/network_ip.php,
 * the interfaces on it agent/post/asset_interface.php.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

/** What Add / Edit say after saving - the stored subnet when it differs from what was typed, and any overlap. */
function networkSavedMessage($verb, $network_id, $note) {
    $row = networkRow($network_id);
    $message = "Network <strong>" . $row['network_name'] . "</strong> $verb";
    if ($note !== '') {
        $message .= ' - ' . $note;
    }
    $overlaps = networkOverlaps($row);
    if ($overlaps) {
        $message .= ' It overlaps <strong>' . implode(', ', array_map(function ($overlap) {
            return $overlap['network_name'] . ' (' . $overlap['network'] . ')';
        }, array_slice($overlaps, 0, 3))) . '</strong>' . (count($overlaps) > 3 ? ' and ' . (count($overlaps) - 3) . ' more' : '') . '.';
    }
    flashAlert($message, $overlaps ? 'warning' : 'success');
}

if (isset($_POST['add_network'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $client_id = intval($_POST['client_id'] ?? 0);
    enforceClientAccess();
    if (!$client_id || !mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM clients WHERE client_id = $client_id AND client_archived_at IS NULL"))) {
        flashAlert('Pick the client the network is at.', 'error');
        redirect();
    }

    $error = $note = '';
    $set = networkFormSet($_POST, $client_id, $error, $note);
    if ($set === '') {
        flashAlert($error, 'error');
        redirect();
    }

    $notes = escapeSql((string) ($_POST['notes'] ?? ''));
    mysqli_query($mysqli, "INSERT INTO networks SET $set, network_notes = '$notes', network_client_id = $client_id");
    $network_id = mysqli_insert_id($mysqli);

    logAudit("Network", "Create", "$session_name created network " . escapeSql(trim($_POST['name'])), $client_id, $network_id);

    tagSetForRecord('network', $network_id, (array) ($_POST['tags'] ?? []));

    networkSavedMessage('created', $network_id, $note);

    redirect(networkUrl($network_id, $client_id));

}

if (isset($_POST['edit_network'])) {

    validateCSRFToken();

    $row = networkForRequest($_POST['network_id'] ?? 0, 2);
    $network_id = intval($row['network_id']);

    $error = $note = '';
    $set = networkFormSet($_POST, $client_id, $error, $note);
    if ($set === '') {
        flashAlert($error, 'error');
        redirect();
    }
    if (isset($_POST['notes'])) {
        $set .= ", network_notes = '" . escapeSql((string) $_POST['notes']) . "'";
    }

    mysqli_query($mysqli, "UPDATE networks SET $set WHERE network_id = $network_id");

    // Only a form that showed the tags changes them
    if (isset($_POST['tags_form'])) {
        tagSetForRecord('network', $network_id, (array) ($_POST['tags'] ?? []));
    }

    logAudit("Network", "Edit", "$session_name edited network " . escapeSql(trim($_POST['name'])), $client_id, $network_id);

    networkSavedMessage('saved', $network_id, $note);

    redirect();

}

// Notes save from the network page when you click away
if (isset($_POST['network_set_notes'])) {

    validateCSRFToken();

    $row = networkForRequest($_POST['network_id'] ?? 0, 2);
    $notes = escapeSql((string) ($_POST['notes'] ?? ''));
    mysqli_query($mysqli, "UPDATE networks SET network_notes = '$notes' WHERE network_id = " . intval($row['network_id']));

    logAudit("Network", "Edit", "$session_name updated the notes of network " . escapeSql($row['network_name']), $client_id, intval($row['network_id']));

    if (!empty($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }

    flashAlert("Notes saved");

    redirect();

}

if (isset($_GET['favorite_network']) || isset($_GET['unfavorite_network'])) {

    validateCSRFToken();

    $favorite = isset($_GET['favorite_network']) ? 1 : 0;
    $row = networkForRequest($favorite ? $_GET['favorite_network'] : $_GET['unfavorite_network'], 2);

    mysqli_query($mysqli, "UPDATE networks SET network_favorite = $favorite WHERE network_id = " . intval($row['network_id']));

    logAudit("Network", "Edit", "$session_name " . ($favorite ? 'starred' : 'unstarred') . " network " . escapeSql($row['network_name']), $client_id, intval($row['network_id']));

    flashAlert("Network <strong>" . $row['network_name'] . "</strong> " . ($favorite ? 'starred' : 'unstarred'));

    redirect();

}

if (isset($_GET['archive_network'])) {

    validateCSRFToken();

    $row = networkForRequest($_GET['archive_network'], 2);

    networkArchive($row);

    flashAlert("Network <strong>" . $row['network_name'] . "</strong> archived", 'error');

    redirect();

}

if (isset($_GET['restore_network'])) {

    validateCSRFToken();

    $row = networkForRequest($_GET['restore_network'], 2);

    networkRestore($row);

    flashAlert("Network <strong>" . $row['network_name'] . "</strong> restored");

    redirect();

}

if (isset($_GET['delete_network'])) {

    validateCSRFToken();

    $row = networkForRequest($_GET['delete_network'], 3);

    networkDelete($row);

    flashAlert("Network <strong>" . $row['network_name'] . "</strong> deleted", 'error');

    // Not back to the page of a network that is gone
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    redirect(preg_match('#/network\.php#', $referer) ? 'networks.php?client_id=' . intval($row['network_client_id']) : null);

}

if (isset($_POST['bulk_edit_networks'])) {

    validateCSRFToken();

    $rows = networksForBulk(2);

    // Each field only when one was picked - blank leaves it as it is
    $type = (string) ($_POST['type'] ?? '');
    $set_type = isset(NETWORK_TYPES[$type]);
    $location = (string) ($_POST['location_id'] ?? '');
    $set_location = $location !== '';

    if (!$set_type && !$set_location) {
        flashAlert('Nothing picked to change.', 'warning');
        redirect();
    }

    $count = 0;
    $skipped = 0;
    foreach ($rows as $network_id => $row) {
        $sets = [];
        $changes = [];
        if ($set_type) {
            $sets[] = "network_type = '$type'";
            $changes[] = "type $type";
        }
        if ($set_location) {
            // A site is per client - only networks of the site's client move to it
            $location_id = intval($location) ? assetClientOwnedId('location', $location, $row['network_client_id']) : 0;
            if (intval($location) && !$location_id) {
                $skipped++;
            } else {
                $sets[] = "network_location_id = $location_id";
                $changes[] = $location_id ? 'site ' . getFieldById('locations', $location_id, 'location_name') : 'no site';
            }
        }
        if (!$sets) {
            continue;
        }
        mysqli_query($mysqli, "UPDATE networks SET " . implode(', ', $sets) . " WHERE network_id = $network_id");
        logAudit("Network", "Edit", "$session_name set " . escapeSql(implode(', ', $changes)) . " on network " . escapeSql($row['network_name']), intval($row['network_client_id']), $network_id);
        $count++;
    }

    flashAlert("Updated <strong>$count</strong> network" . ($count === 1 ? '' : 's') . ($skipped ? " - the site is not at the client of $skipped of them, so they kept theirs" : ''), $skipped ? 'warning' : 'success');

    redirect();

}

if (isset($_POST['bulk_favorite_networks']) || isset($_POST['bulk_unfavorite_networks'])) {

    validateCSRFToken();

    $favorite = isset($_POST['bulk_favorite_networks']) ? 1 : 0;
    $count = 0;
    foreach (networksForBulk(2) as $network_id => $row) {
        mysqli_query($mysqli, "UPDATE networks SET network_favorite = $favorite WHERE network_id = $network_id");
        $count++;
    }

    flashAlert(($favorite ? 'Starred' : 'Unstarred') . " <strong>$count</strong> network" . ($count === 1 ? '' : 's'));

    redirect();

}

if (isset($_POST['bulk_archive_networks'])) {

    validateCSRFToken();

    $count = 0;
    foreach (networksForBulk(2) as $row) {
        if (!$row['network_archived_at']) {
            networkArchive($row);
            $count++;
        }
    }

    flashAlert("Archived <strong>$count</strong> network" . ($count === 1 ? '' : 's'), 'error');

    redirect();

}

if (isset($_POST['bulk_restore_networks'])) {

    validateCSRFToken();

    $count = 0;
    foreach (networksForBulk(2) as $row) {
        if ($row['network_archived_at']) {
            networkRestore($row);
            $count++;
        }
    }

    flashAlert("Restored <strong>$count</strong> network" . ($count === 1 ? '' : 's'));

    redirect();

}

if (isset($_POST['bulk_delete_networks'])) {

    validateCSRFToken();

    $count = 0;
    foreach (networksForBulk(3) as $row) {
        networkDelete($row);
        $count++;
    }

    flashAlert("Deleted <strong>$count</strong> network" . ($count === 1 ? '' : 's'), 'error');

    redirect();

}

if (isExportRequest('export_networks')) {

    validateCSRFToken();

    // Exports are reads - see CONTRIBUTING.md
    enforceUserPermission('module_support');

    $format = resolveExportFormat($_POST['export_networks']);

    // Filters inherited from the networks page - mirrors agent/networks.php
    $filter_summary = [];

    $archived = (isset($_POST['archived']) && $_POST['archived'] == 1);
    if ($archived) {
        $filter_summary['Archived'] = 'Archived only';
    }

    if (!empty($_POST['client_id'])) {
        $client_id = intval($_POST['client_id']);
        enforceClientAccess();
        $client_query = "AND network_client_id = $client_id";
        $client_name = getFieldById('clients', $client_id, 'client_name');
        $file_name_prepend = "$client_name-";
        $filter_summary['Client'] = $client_name;
        $archive_query = $archived ? "network_archived_at IS NOT NULL" : "network_archived_at IS NULL";
    } else {
        $client_query = '';
        $client_id = 0; // for Logging
        $file_name_prepend = "$session_company_name-";
        if (!empty($_POST['client'])) {
            $filter_client_id = intval($_POST['client']);
            $client_query = "AND network_client_id = $filter_client_id";
            $filter_summary['Client'] = getFieldById('clients', $filter_client_id, 'client_name');
        }
        $archive_query = $archived ? "(client_archived_at IS NOT NULL OR network_archived_at IS NOT NULL)" : "(client_archived_at IS NULL AND network_archived_at IS NULL)";
    }

    $location_query = '';
    if (!empty($_POST['location'])) {
        $filter_location_id = intval($_POST['location']);
        $location_query = "AND network_location_id = $filter_location_id";
        $filter_summary['Site'] = getFieldById('locations', $filter_location_id, 'location_name');
    }

    $type_query = '';
    if (isset(NETWORK_TYPES[$_POST['type'] ?? ''])) {
        $type_query = "AND network_type = '" . $_POST['type'] . "'";
        $filter_summary['Type'] = $_POST['type'];
    }

    $tag_query = tagExportFilterSql('network', $_POST['tags'] ?? [], $filter_summary, 'networks.network_id');

    $q = escapeSql($_POST['q'] ?? '');
    if ($q !== '') {
        $filter_summary['Search'] = $_POST['q'];
    }

    $sql = mysqli_query(
        $mysqli,
        "SELECT networks.*, client_name, location_name, vendor_name, " . tagNamesSql('network', 'networks.network_id') . " AS network_tags FROM networks
        LEFT JOIN clients ON client_id = network_client_id
        LEFT JOIN locations ON location_id = network_location_id AND network_location_id > 0
        LEFT JOIN vendors ON vendor_id = network_vendor_id AND network_vendor_id > 0
        WHERE $archive_query
        AND (network_name LIKE '%$q%' OR network_description LIKE '%$q%' OR network_type LIKE '%$q%' OR network_vlan LIKE '%$q%' OR network LIKE '%$q%' OR network_gateway LIKE '%$q%' OR network_primary_dns LIKE '%$q%' OR network_secondary_dns LIKE '%$q%' OR client_name LIKE '%$q%' OR location_name LIKE '%$q%' OR vendor_name LIKE '%$q%'
            " . tagSearchSql('network', $q, 'networks.network_id') . ")
        " . clientScopeSql('network_client_id') . "
        $location_query
        $type_query
        $tag_query
        $client_query
        ORDER BY client_name ASC, network_name ASC"
    );

    $num_rows = mysqli_num_rows($sql);

    if ($num_rows > 0) {

        guardExportPdfRowCount($format, $num_rows);

        $export = beginExport('networks', $format, $file_name_prepend . 'Networks', 'Networks', summarizeExportFilters($filter_summary));

        while ($row = mysqli_fetch_assoc($sql)) {
            $row['network_tags'] = (string) $row['network_tags'];
            addExportRow($export, $row);
        }

        finishExport($export);
    }

    logAudit("Network", "Export", "$session_name exported $num_rows network(s) to a " . strtoupper($format) . " file", $client_id);

    exit;

}

// The import's columns - an export opens straight back in, Type last and optional
const NETWORK_CSV_COLUMNS = ['Name', 'Description', 'VLAN', 'Network (CIDR)', 'Gateway', 'IP Range', 'Primary DNS', 'Secondary DNS', 'Type'];

if (isset($_GET['download_networks_csv_template'])) {

    enforceUserPermission('module_support');

    $f = fopen('php://memory', 'w');
    fputcsv($f, NETWORK_CSV_COLUMNS, ',', '"', '\\');
    // Example rows, so the expected formatting is plain
    fputcsv($f, ['Office LAN', 'Workstations and printers', '10', '192.168.10.0/24', '192.168.10.1', '192.168.10.100 - 192.168.10.199', '192.168.10.5', '1.1.1.1', 'LAN'], ',', '"', '\\');
    fputcsv($f, ['Guest Wireless', 'Internet only', '40', '192.168.40.0/24', '192.168.40.1', '192.168.40.50 - 192.168.40.250', '1.1.1.1', '8.8.8.8', 'Guest'], ',', '"', '\\');
    fseek($f, 0);

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="Networks-Template.csv";');

    fpassthru($f);
    exit;

}

if (isset($_POST['import_networks_csv'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $client_id = intval($_POST['client_id'] ?? 0);

    enforceClientAccess();

    if (empty($_FILES['file']['tmp_name'])) {
        flashAlert("Please select a file to upload.", 'error');
        redirect();
    }
    if (strtolower(pathinfo((string) $_FILES['file']['name'], PATHINFO_EXTENSION)) !== 'csv') {
        flashAlert("Bad file extension - only .csv files are accepted.", 'error');
        redirect();
    }
    if ($_FILES['file']['size'] < 1) {
        flashAlert("Bad file size (empty file?).", 'error');
        redirect();
    }

    $file = fopen($_FILES['file']['tmp_name'], 'r');
    $header = fgetcsv($file, 1000, ',');
    if (!is_array($header) || !in_array(count($header), [8, 9], true)) {
        fclose($file);
        flashAlert("Bad column count - use the template: " . implode(', ', NETWORK_CSV_COLUMNS) . " (Type may be left off).", 'error');
        redirect();
    }

    $row_count = 0;
    $duplicate_count = 0;
    $problems = [];
    $line = 1;
    while (($column = fgetcsv($file, 1000, ',')) !== false) {
        $line++;
        // Each row starts blank - a short row never inherits the row before it
        $column = array_pad($column, 9, '');
        $name = trim((string) $column[0]);
        if ($name === '') {
            continue;
        }

        $post = [
            'name' => $name, 'description' => $column[1], 'vlan' => trim((string) $column[2]), 'network' => $column[3], 'gateway' => $column[4],
            'dhcp_range' => $column[5], 'primary_dns' => $column[6], 'secondary_dns' => $column[7], 'type' => trim((string) $column[8]),
        ];
        // A type in any case ("lan") is that type; none, or one that is not a type, is guessed from the name
        foreach (array_keys(NETWORK_TYPES) as $type) {
            if (strcasecmp($type, $post['type']) === 0) {
                $post['type'] = $type;
            }
        }

        $error = $note = '';
        $set = networkFormSet($post, $client_id, $error, $note);
        if ($set === '') {
            $problems[] = "line $line ($name): " . strip_tags($error);
            continue;
        }

        $info = networkSubnetInfo($post['network']);
        $name_sql = escapeSql(mb_substr($name, 0, 200));
        if (mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM networks WHERE network_name = '$name_sql' AND network = '" . escapeSql($info['cidr']) . "' AND network_client_id = $client_id AND network_archived_at IS NULL LIMIT 1"))) {
            $duplicate_count++;
            continue;
        }

        mysqli_query($mysqli, "INSERT INTO networks SET $set, network_client_id = $client_id");
        $row_count++;
    }
    fclose($file);

    logAudit("Network", "Import", "$session_name imported $row_count network(s). $duplicate_count duplicate(s) and " . count($problems) . " row(s) with problems not imported", $client_id);

    $message = "<strong>$row_count</strong> network" . ($row_count === 1 ? '' : 's') . " imported";
    if ($duplicate_count) {
        $message .= ", <strong>$duplicate_count</strong> already there";
    }
    if ($problems) {
        $message .= ". Not imported - " . implode('; ', array_slice($problems, 0, 3)) . (count($problems) > 3 ? '; and ' . (count($problems) - 3) . ' more' : '');
    }
    flashAlert($message, $problems ? 'warning' : 'success');

    redirect();

}
