<?php

/*
 * ITFlow - GET/POST request handler for asset interfaces
 *
 * The ports and network cards of an asset (functions/asset_interface.php):
 * checked by interfaceFormSet() - an address inside its network, a MAC that is
 * one, a network of the asset's client - and connected one-to-one by
 * interfaceConnect(). The asset's own form keeps its primary interface in step
 * (agent/post/asset.php).
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

/** What saving an interface says - its name, and anything worth knowing (the address is on another device too). */
function interfaceSavedMessage($verb, $name, $warnings) {
    if ($warnings) {
        flashAlert("Interface <strong>$name</strong> $verb - " . implode('; ', $warnings) . '.', 'warning');
    } else {
        flashAlert("Interface <strong>$name</strong> $verb");
    }
}

if (isset($_POST['add_asset_interface'])) {

    validateCSRFToken();

    $asset = interfaceAssetForRequest($_POST['asset_id'] ?? 0, 2);
    $asset_id = intval($asset['asset_id']);

    $error = '';
    $warnings = [];
    $set = interfaceFormSet($_POST, $asset, 0, $error, $warnings);
    if ($set === '') {
        flashAlert($error, 'error');
        redirect();
    }

    mysqli_query($mysqli, "INSERT INTO asset_interfaces SET $set, interface_asset_id = $asset_id");
    $interface_id = mysqli_insert_id($mysqli);

    // The first one is the primary whether it is ticked or not
    $has_primary = mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM asset_interfaces WHERE interface_asset_id = $asset_id AND interface_primary = 1 AND interface_archived_at IS NULL"));
    if (!empty($_POST['primary_interface']) || !$has_primary) {
        interfaceMakePrimary($interface_id, $asset_id);
    }

    $connect_to = interfaceConnectTarget($_POST['connected_to'] ?? 0, $client_id, $asset_id);
    if ($connect_to) {
        interfaceConnect($interface_id, $connect_to);
    }

    $name = trim($_POST['name']);
    logAudit("Asset Interface", "Create", "$session_name created interface " . escapeSql($name) . " on asset " . escapeSql($asset['asset_name']), $client_id, $asset_id);

    interfaceSavedMessage('created', $name, $warnings);

    redirect();

}

if (isset($_POST['edit_asset_interface'])) {

    validateCSRFToken();

    $row = interfaceForRequest($_POST['interface_id'] ?? 0, 2);
    $interface_id = intval($row['interface_id']);
    $asset_id = intval($row['asset_id']);

    $error = '';
    $warnings = [];
    $set = interfaceFormSet($_POST, $row, $interface_id, $error, $warnings);
    if ($set === '') {
        flashAlert($error, 'error');
        redirect();
    }

    mysqli_query($mysqli, "UPDATE asset_interfaces SET $set WHERE interface_id = $interface_id");

    if (!empty($_POST['primary_interface'])) {
        interfaceMakePrimary($interface_id, $asset_id);
    }

    // What it is plugged into - moved, or let go when emptied
    if (isset($_POST['connected_to'])) {
        $connect_to = interfaceConnectTarget($_POST['connected_to'], $client_id, $asset_id);
        $current = intval($row['link']['interface_id'] ?? 0);
        if ($connect_to !== $current) {
            if ($current) {
                interfaceConnect($interface_id, 0);
            }
            if ($connect_to) {
                interfaceConnect($interface_id, $connect_to);
            }
        }
    }

    $name = trim($_POST['name']);
    logAudit("Asset Interface", "Edit", "$session_name edited interface " . escapeSql($name) . " on asset " . escapeSql($row['asset_name']), $client_id, $asset_id);

    interfaceSavedMessage('saved', $name, $warnings);

    redirect();

}

if (isset($_GET['disconnect_asset_interface'])) {

    validateCSRFToken();

    $row = interfaceForRequest($_GET['disconnect_asset_interface'], 2);

    if ($row['link']) {
        interfaceConnect($row['interface_id'], 0);
        logAudit("Asset Interface", "Edit", "$session_name disconnected interface " . escapeSql($row['interface_name']) . " on asset " . escapeSql($row['asset_name']) . " from " . escapeSql($row['link']['asset_name'] . ' ' . $row['link']['interface_name']), $client_id, intval($row['asset_id']));
    }

    flashAlert("<strong>" . $row['asset_name'] . ' ' . $row['interface_name'] . "</strong> disconnected");

    redirect();

}

if (isset($_GET['primary_asset_interface'])) {

    validateCSRFToken();

    $row = interfaceForRequest($_GET['primary_asset_interface'], 2);

    interfaceMakePrimary($row['interface_id'], $row['asset_id']);

    logAudit("Asset Interface", "Edit", "$session_name made interface " . escapeSql($row['interface_name']) . " the primary of asset " . escapeSql($row['asset_name']), $client_id, intval($row['asset_id']));

    flashAlert("<strong>" . $row['interface_name'] . "</strong> is the primary interface of " . $row['asset_name']);

    redirect();

}

if (isset($_GET['delete_asset_interface'])) {

    validateCSRFToken();

    $row = interfaceForRequest($_GET['delete_asset_interface'], 2);

    // Its connection goes with it (ON DELETE CASCADE)
    mysqli_query($mysqli, "DELETE FROM asset_interfaces WHERE interface_id = " . intval($row['interface_id']));

    logAudit("Asset Interface", "Delete", "$session_name deleted interface " . escapeSql($row['interface_name']) . " from asset " . escapeSql($row['asset_name']), $client_id, intval($row['asset_id']));

    flashAlert("Interface <strong>" . $row['interface_name'] . "</strong> deleted", 'error');

    redirect();

}

// A run of ports - "Gi1/0/" 1 to 24, "eth" 0 to 3
if (isset($_POST['add_asset_multiple_interfaces'])) {

    validateCSRFToken();

    $asset = interfaceAssetForRequest($_POST['asset_id'] ?? 0, 2);
    $asset_id = intval($asset['asset_id']);

    $count = intval($_POST['interfaces'] ?? 0);
    if ($count < 1 || $count > 128) {
        flashAlert('Add from 1 to 128 interfaces at a time.', 'error');
        redirect();
    }
    $names = interfaceSeriesNames(trim((string) ($_POST['name_prefix'] ?? '')), $_POST['interface_start'] ?? 1, $count, $_POST['pad'] ?? 0);

    $type = escapeSql(mb_substr(trim((string) ($_POST['type'] ?? '')), 0, 50));
    $network_id = intval(assetClientOwnedId('network', $_POST['network_id'] ?? 0, $client_id));
    $vlans = escapeSql(interfaceVlansText($_POST['vlans'] ?? ''));
    $notes = escapeSql(trim((string) ($_POST['notes'] ?? '')));

    $existing = [];
    $sql = mysqli_query($mysqli, "SELECT interface_name FROM asset_interfaces WHERE interface_asset_id = $asset_id AND interface_archived_at IS NULL");
    while ($row = mysqli_fetch_row($sql)) {
        $existing[mb_strtolower($row[0])] = true;
    }

    $added = 0;
    $skipped = 0;
    foreach ($names as $name) {
        if (isset($existing[mb_strtolower($name)])) {
            $skipped++;
            continue;
        }
        mysqli_query($mysqli, "INSERT INTO asset_interfaces SET interface_name = '" . escapeSql(mb_substr($name, 0, 200)) . "', interface_type = '$type',
            interface_vlans = '$vlans', interface_notes = '$notes', interface_network_id = $network_id, interface_asset_id = $asset_id");
        $added++;
    }

    logAudit("Asset Interface", "Bulk Create", "$session_name created $added interface(s) " . escapeSql($names[0] . ' - ' . end($names)) . " on asset " . escapeSql($asset['asset_name']), $client_id, $asset_id);

    flashAlert("Added <strong>$added</strong> interface" . ($added === 1 ? '' : 's') . " to <strong>" . $asset['asset_name'] . "</strong>" . ($skipped ? " - $skipped already there" : ''));

    redirect();

}

if (isset($_POST['bulk_edit_asset_interface_type'])) {

    validateCSRFToken();

    $type = mb_substr(trim((string) ($_POST['bulk_type'] ?? '')), 0, 50);

    $count = 0;
    foreach (interfacesForBulk(2) as $interface_id => $row) {
        mysqli_query($mysqli, "UPDATE asset_interfaces SET interface_type = '" . escapeSql($type) . "' WHERE interface_id = $interface_id");
        logAudit("Asset Interface", "Edit", "$session_name set the type of interface " . escapeSql($row['interface_name']) . " on asset " . escapeSql($row['asset_name']) . " to " . escapeSql($type ?: 'none'), intval($row['asset_client_id']), intval($row['asset_id']));
        $count++;
    }

    flashAlert("Type set to <strong>" . ($type ?: 'none') . "</strong> on <strong>$count</strong> interface" . ($count === 1 ? '' : 's'));

    redirect();

}

if (isset($_POST['bulk_edit_asset_interface_network'])) {

    validateCSRFToken();

    $network_id = intval($_POST['bulk_network'] ?? 0);
    $network = $network_id ? networkRow($network_id) : null;

    $count = 0;
    $outside = 0;
    foreach (interfacesForBulk(2) as $interface_id => $row) {
        // The network must be the interface's client's
        if ($network && intval($network['network_client_id']) !== intval($row['asset_client_id'])) {
            continue;
        }
        mysqli_query($mysqli, "UPDATE asset_interfaces SET interface_network_id = $network_id WHERE interface_id = $interface_id");
        $ip = normalizeIpAddress((string) $row['interface_ip']);
        if ($network && $ip !== false && !isIpInSubnet($ip, $network['network'])) {
            $outside++;
        }
        logAudit("Asset Interface", "Edit", "$session_name set the network of interface " . escapeSql($row['interface_name']) . " on asset " . escapeSql($row['asset_name']) . " to " . escapeSql($network['network_name'] ?? 'none'), intval($row['asset_client_id']), intval($row['asset_id']));
        $count++;
    }

    flashAlert("Network set to <strong>" . ($network['network_name'] ?? 'none') . "</strong> on <strong>$count</strong> interface" . ($count === 1 ? '' : 's')
        . ($outside ? " - $outside ha" . ($outside === 1 ? 's an address' : 've addresses') . " outside " . $network['network'] : ''), $outside ? 'warning' : 'success');

    redirect();

}

if (isset($_POST['bulk_edit_asset_interface_ip_dhcp'])) {

    validateCSRFToken();

    $count = 0;
    foreach (interfacesForBulk(2) as $interface_id => $row) {
        mysqli_query($mysqli, "UPDATE asset_interfaces SET interface_ip = 'DHCP' WHERE interface_id = $interface_id");
        logAudit("Asset Interface", "Edit", "$session_name set interface " . escapeSql($row['interface_name']) . " on asset " . escapeSql($row['asset_name']) . " to DHCP", intval($row['asset_client_id']), intval($row['asset_id']));
        $count++;
    }

    flashAlert("Set <strong>$count</strong> interface" . ($count === 1 ? '' : 's') . " to <strong>DHCP</strong>");

    redirect();

}

if (isset($_POST['bulk_edit_asset_interface_vlans'])) {

    validateCSRFToken();

    $vlans = interfaceVlansText($_POST['bulk_vlans'] ?? '');

    $count = 0;
    foreach (interfacesForBulk(2) as $interface_id => $row) {
        mysqli_query($mysqli, "UPDATE asset_interfaces SET interface_vlans = '" . escapeSql($vlans) . "' WHERE interface_id = $interface_id");
        logAudit("Asset Interface", "Edit", "$session_name set the VLANs of interface " . escapeSql($row['interface_name']) . " on asset " . escapeSql($row['asset_name']) . " to " . escapeSql($vlans ?: 'none'), intval($row['asset_client_id']), intval($row['asset_id']));
        $count++;
    }

    flashAlert("VLANs set to <strong>" . ($vlans ?: 'none') . "</strong> on <strong>$count</strong> interface" . ($count === 1 ? '' : 's'));

    redirect();

}

if (isset($_POST['bulk_disconnect_asset_interfaces'])) {

    validateCSRFToken();

    $count = 0;
    foreach (interfacesForBulk(2) as $interface_id => $row) {
        if ($row['link']) {
            interfaceConnect($interface_id, 0);
            logAudit("Asset Interface", "Edit", "$session_name disconnected interface " . escapeSql($row['interface_name']) . " on asset " . escapeSql($row['asset_name']), intval($row['asset_client_id']), intval($row['asset_id']));
            $count++;
        }
    }

    flashAlert("Disconnected <strong>$count</strong> interface" . ($count === 1 ? '' : 's'));

    redirect();

}

if (isset($_POST['bulk_delete_asset_interfaces'])) {

    validateCSRFToken();

    $count = 0;
    foreach (interfacesForBulk(2) as $interface_id => $row) {
        mysqli_query($mysqli, "DELETE FROM asset_interfaces WHERE interface_id = $interface_id");
        logAudit("Asset Interface", "Delete", "$session_name deleted interface " . escapeSql($row['interface_name']) . " from asset " . escapeSql($row['asset_name']), intval($row['asset_client_id']), intval($row['asset_id']));
        $count++;
    }

    flashAlert("Deleted <strong>$count</strong> interface" . ($count === 1 ? '' : 's'), 'error');

    redirect();

}

// The import's columns - an export opens straight back in, VLANs last and optional
const INTERFACE_CSV_COLUMNS = ['Name', 'Description', 'Type', 'MAC', 'IP', 'NAT IP', 'IPv6', 'Network', 'VLANs'];

if (isset($_POST['import_client_asset_interfaces_csv'])) {

    validateCSRFToken();

    $asset = interfaceAssetForRequest($_POST['asset_id'] ?? 0, 2);
    $asset_id = intval($asset['asset_id']);

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
        flashAlert("Bad column count - use the template: " . implode(', ', INTERFACE_CSV_COLUMNS) . " (VLANs may be left off).", 'error');
        redirect();
    }

    // The client's networks, by name and by subnet
    $networks = [];
    $sql = mysqli_query($mysqli, "SELECT network_id, network_name, network FROM networks WHERE network_client_id = $client_id AND network_archived_at IS NULL");
    while ($row = mysqli_fetch_assoc($sql)) {
        $networks[mb_strtolower($row['network_name'])] = intval($row['network_id']);
        $networks[mb_strtolower($row['network'])] = intval($row['network_id']);
    }

    $row_count = 0;
    $duplicate_count = 0;
    $problems = [];
    $line = 1;
    while (($column = fgetcsv($file, 1000, ',')) !== false) {
        $line++;
        $column = array_pad($column, 9, '');
        $name = trim((string) $column[0]);
        if ($name === '') {
            continue;
        }
        $post = [
            'name' => $name, 'description' => $column[1], 'type' => $column[2], 'mac' => $column[3], 'ip' => trim((string) $column[4]),
            'nat_ip' => $column[5], 'ipv6' => $column[6], 'network_id' => $networks[mb_strtolower(trim((string) $column[7]))] ?? 0, 'vlans' => $column[8],
        ];
        $error = '';
        $warnings = [];
        $set = interfaceFormSet($post, $asset, 0, $error, $warnings);
        if ($set === '') {
            if (strpos($error, 'already has an interface') !== false) {
                $duplicate_count++;
            } else {
                $problems[] = "line $line ($name): " . strip_tags($error);
            }
            continue;
        }
        mysqli_query($mysqli, "INSERT INTO asset_interfaces SET $set, interface_asset_id = $asset_id");
        $row_count++;
    }
    fclose($file);

    logAudit("Asset Interface", "Import", "$session_name imported $row_count interface(s) to asset " . escapeSql($asset['asset_name']) . " via CSV file", $client_id, $asset_id);

    $message = "<strong>$row_count</strong> interface" . ($row_count === 1 ? '' : 's') . " added to <strong>" . $asset['asset_name'] . "</strong>";
    if ($duplicate_count) {
        $message .= ", <strong>$duplicate_count</strong> already there";
    }
    if ($problems) {
        $message .= ". Not imported - " . implode('; ', array_slice($problems, 0, 3)) . (count($problems) > 3 ? '; and ' . (count($problems) - 3) . ' more' : '');
    }
    flashAlert($message, $problems ? 'warning' : 'success');

    redirect();

}

if (isset($_GET['download_client_asset_interfaces_csv_template'])) {

    enforceUserPermission('module_support');

    $asset_id = intval($_GET['download_client_asset_interfaces_csv_template']);
    $client_id = intval(getFieldById('assets', $asset_id, 'asset_client_id'));
    enforceClientAccess();
    $asset_name = getFieldById('assets', $asset_id, 'asset_name');

    $f = fopen('php://memory', 'w');
    fputcsv($f, INTERFACE_CSV_COLUMNS, ',', '"', '\\');
    fputcsv($f, ['Gi1/0/1', 'Uplink to firewall', 'Ethernet', '', '', '', '', '', 'Trunk 1, 10, 20'], ',', '"', '\\');
    fputcsv($f, ['eth0', 'Management', 'Ethernet', '00:1A:2B:3C:4D:5E', 'DHCP', '', '', 'Office LAN', ''], ',', '"', '\\');
    fseek($f, 0);

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . toAlphanumeric($asset_name) . '-Asset-Interfaces-Template.csv";');

    fpassthru($f);
    exit;

}

if (isExportRequest('export_asset_interfaces')) {

    validateCSRFToken();

    // Exports are reads - see CONTRIBUTING.md
    enforceUserPermission('module_support');

    $format = resolveExportFormat($_POST['export_asset_interfaces']);

    $asset_id = intval($_POST['asset_id']);
    $client_id = intval(getFieldById('assets', $asset_id, 'asset_client_id'));
    enforceClientAccess();
    $asset_name = getFieldById('assets', $asset_id, 'asset_name');

    // Scoped to the one asset, so no page filters apply
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT asset_interfaces.*, network_name, network FROM asset_interfaces
        LEFT JOIN networks ON network_id = interface_network_id AND interface_network_id > 0
        WHERE interface_asset_id = $asset_id AND interface_archived_at IS NULL");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[intval($row['interface_id'])] = $row;
    }
    $links = interfaceLinks(array_keys($rows));
    interfaceSort($rows);

    $num_rows = count($rows);

    if ($num_rows > 0) {

        guardExportPdfRowCount($format, $num_rows);

        $export = beginExport('asset_interfaces', $format, toAlphanumeric($asset_name) . '-Interfaces', "$asset_name - Interfaces", '');

        foreach ($rows as $row) {
            $link = $links[intval($row['interface_id'])] ?? null;
            $row['connected_to'] = $link ? $link['asset_name'] . ' ' . $link['interface_name'] : '';
            addExportRow($export, $row);
        }

        finishExport($export);
    }

    logAudit("Asset Interface", "Export", "$session_name exported $num_rows interface(s) of asset " . escapeSql($asset_name) . " to a " . strtoupper($format) . " file", $client_id, $asset_id);

    exit;

}
