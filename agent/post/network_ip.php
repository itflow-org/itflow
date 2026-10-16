<?php

/*
 * ITFlow - GET/POST request handler for documented IP addresses
 *
 * Addresses hang off a network (subnet): fixed addresses, DHCP reservations
 * (with the MAC they are for), addresses held back, and floating / virtual
 * ones. The two safeguards - valid and inside the subnet, and not already
 * documented on that network - live in checkIpForNetwork() in
 * functions/network.php so add, edit, a range and CSV import all apply exactly
 * the same rules. There is a UNIQUE(ip_network_id, ip_address) index behind
 * them as a backstop.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_network_ip'])) {

    validateCSRFToken();

    $network = networkForRequest($_POST['network_id'] ?? 0, 2);
    $network_id = intval($network['network_id']);

    $ip_address = trim((string) ($_POST['ip_address'] ?? ''));
    $range_end = trim((string) ($_POST['ip_range_end'] ?? ''));
    if ($range_end !== '' && ($_POST['type'] ?? '') === 'Reservation') {
        flashAlert('A DHCP reservation is for one device - add them one at a time.', 'error');
        redirect();
    }

    $error = '';
    $fields = networkIpFieldsSet($_POST, $error);
    if ($fields === '') {
        flashAlert($error, 'error');
        redirect();
    }

    // A run of addresses - held back for a printer pool, a block of fixed ones
    if ($range_end !== '') {
        $first = normalizeIpAddress(expandIpSuffix($ip_address, $network['network']));
        $last = normalizeIpAddress(expandIpSuffix($range_end, $network['network']));
        $first_int = $first === false ? null : ipv4ToInt($first);
        $last_int = $last === false ? null : ipv4ToInt($last);
        if ($first_int === null || $last_int === null || $first_int > $last_int) {
            flashAlert('A range is two IPv4 addresses, the lower first.', 'error');
            redirect();
        }
        if ($last_int - $first_int >= 256) {
            flashAlert('A range is up to 256 addresses at a time.', 'error');
            redirect();
        }
        $added = 0;
        $skipped = 0;
        for ($int = $first_int; $int <= $last_int; $int++) {
            $ip = intToIpv4($int);
            if (checkIpForNetwork($ip, $network_id) !== '') {
                $skipped++;
                continue;
            }
            mysqli_query($mysqli, "INSERT INTO network_ips SET ip_address = '" . escapeSql($ip) . "', $fields, ip_network_id = $network_id");
            $added++;
        }
        logAudit("IP Address", "Create", "$session_name documented $added address(es), $first - $last, on network " . escapeSql($network['network_name']), $client_id, $network_id);
        flashAlert("Documented <strong>$added</strong> address" . ($added === 1 ? '' : 'es') . " from $first to $last" . ($skipped ? " - $skipped skipped (outside the subnet or already documented)" : ''), $skipped && !$added ? 'warning' : 'success');
        redirect();
    }

    $ip_error = checkIpForNetwork($ip_address, $network_id);
    if ($ip_error) {
        flashAlert($ip_error, 'error');
        redirect();
    }

    mysqli_query($mysqli, "INSERT INTO network_ips SET ip_address = '" . escapeSql($ip_address) . "', $fields, ip_network_id = $network_id");
    $ip_id = mysqli_insert_id($mysqli);

    logAudit("IP Address", "Create", "$session_name documented IP " . escapeSql($ip_address) . " on network " . escapeSql($network['network_name']), $client_id, $ip_id);

    flashAlert("IP <strong>$ip_address</strong> documented");

    redirect();

}

if (isset($_POST['edit_network_ip'])) {

    validateCSRFToken();

    $row = networkIpForRequest($_POST['ip_id'] ?? 0, 2);
    $ip_id = intval($row['ip_id']);
    $network_id = intval($row['ip_network_id']);

    $error = '';
    $fields = networkIpFieldsSet($_POST, $error);
    if ($fields === '') {
        flashAlert($error, 'error');
        redirect();
    }

    // The row's own id is excluded so saving without changing the address
    // doesn't trip the duplicate check against itself
    $ip_address = trim((string) ($_POST['ip_address'] ?? ''));
    $ip_error = checkIpForNetwork($ip_address, $network_id, $ip_id);
    if ($ip_error) {
        flashAlert($ip_error, 'error');
        redirect();
    }

    mysqli_query($mysqli, "UPDATE network_ips SET ip_address = '" . escapeSql($ip_address) . "', $fields WHERE ip_id = $ip_id");

    logAudit("IP Address", "Edit", "$session_name edited IP " . escapeSql($ip_address) . " on network " . escapeSql($row['network_name']), $client_id, $ip_id);

    flashAlert("IP <strong>$ip_address</strong> saved");

    redirect();

}

if (isset($_GET['delete_network_ip'])) {

    validateCSRFToken();

    $row = networkIpForRequest($_GET['delete_network_ip'], 2);

    mysqli_query($mysqli, "DELETE FROM network_ips WHERE ip_id = " . intval($row['ip_id']));

    logAudit("IP Address", "Delete", "$session_name deleted IP " . escapeSql($row['ip_address']) . " from network " . escapeSql($row['network_name']), $client_id);

    flashAlert("IP <strong>" . $row['ip_address'] . "</strong> deleted", 'error');

    redirect();

}

/** The documented addresses a bulk action names (network_ip_ids[]) that this agent may change - [id => row]. */
function networkIpsForBulk($level) {
    enforceUserPermission('module_support', $level);
    $rows = [];
    foreach (array_unique(array_map('intval', (array) ($_POST['network_ip_ids'] ?? []))) as $ip_id) {
        $row = networkIpRow($ip_id);
        if ($row && hasClientAccess($row['network_client_id'])) {
            $rows[$ip_id] = $row;
        }
    }
    return $rows;
}

// From the address plan's Selected menu - the button pressed is the type
if (isset($_POST['bulk_ip_type'])) {

    validateCSRFToken();

    $type = (string) $_POST['bulk_ip_type'];
    if (!isset(NETWORK_IP_TYPES[$type])) {
        flashAlert('Pick what the addresses are.', 'warning');
        redirect();
    }

    $count = 0;
    foreach (networkIpsForBulk(2) as $ip_id => $row) {
        mysqli_query($mysqli, "UPDATE network_ips SET ip_type = '$type' WHERE ip_id = $ip_id");
        logAudit("IP Address", "Edit", "$session_name set IP " . escapeSql($row['ip_address']) . " to $type", intval($row['network_client_id']), $ip_id);
        $count++;
    }

    flashAlert("Made <strong>$count</strong> address" . ($count === 1 ? '' : 'es') . " <strong>" . strtolower(NETWORK_IP_TYPES[$type][3]) . "</strong>");

    redirect();

}

if (isset($_POST['bulk_delete_network_ips'])) {

    validateCSRFToken();

    $count = 0;
    foreach (networkIpsForBulk(2) as $ip_id => $row) {
        mysqli_query($mysqli, "DELETE FROM network_ips WHERE ip_id = $ip_id");
        logAudit("IP Address", "Delete", "$session_name deleted IP " . escapeSql($row['ip_address']) . " from network " . escapeSql($row['network_name']), intval($row['network_client_id']));
        $count++;
    }

    flashAlert("Deleted <strong>$count</strong> address" . ($count === 1 ? '' : 'es'), 'error');

    redirect();

}

/*
 * Document what the devices on a network have - each interface address in the
 * subnet that is not documented yet becomes a fixed address, named for the
 * asset, with its MAC.
 */
if (isset($_POST['document_network_interfaces'])) {

    validateCSRFToken();

    $network = networkForRequest($_POST['network_id'] ?? 0, 2);
    $network_id = intval($network['network_id']);

    $added = 0;
    $addresses = networkAddresses([$network_id]);
    foreach ($addresses[$network_id]['interfaces'] as $interface) {
        $ip = trim((string) $interface['interface_ip']);
        if ($ip === '' || strtoupper($ip) === 'DHCP' || checkIpForNetwork($ip, $network_id) !== '') {
            continue;
        }
        $mac = normalizeMacAddress($interface['interface_mac']);
        mysqli_query($mysqli, "INSERT INTO network_ips SET ip_address = '" . escapeSql($ip) . "', ip_type = 'Static',
            ip_hostname = '" . escapeSql($interface['asset_name']) . "', ip_mac = " . ($mac ? "'$mac'" : 'NULL') . ",
            ip_description = '" . escapeSql($interface['interface_name'] . ($interface['interface_description'] ? ' - ' . $interface['interface_description'] : '')) . "', ip_network_id = $network_id");
        $added++;
    }

    logAudit("IP Address", "Create", "$session_name documented $added device address(es) on network " . escapeSql($network['network_name']), $client_id, $network_id);

    flashAlert($added ? "Documented <strong>$added</strong> device address" . ($added === 1 ? '' : 'es') : 'Every device address on this network is documented already.', $added ? 'success' : 'info');

    redirect();

}

// The import's columns - the original three, then type and MAC (optional)
const NETWORK_IP_CSV_COLUMNS = ['IP Address', 'Hostname', 'Description', 'Type', 'MAC'];

if (isset($_GET['download_network_ips_csv_template'])) {

    $network = networkForRequest($_GET['download_network_ips_csv_template'], 1);

    $f = fopen('php://memory', 'w');
    fputcsv($f, NETWORK_IP_CSV_COLUMNS, ',', '"', '\\');
    $fixed = ipSubnetFixedOctets($network['network']);
    if ($fixed !== '' && substr_count($fixed, '.') === 3) {
        fputcsv($f, [$fixed . '10', 'DC01', 'Domain controller', 'Static', ''], ',', '"', '\\');
        fputcsv($f, [$fixed . '150', 'PRN-FRONT', 'Front desk printer', 'Reservation', '00:1A:2B:3C:4D:5E'], ',', '"', '\\');
    }
    fseek($f, 0);

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . toAlphanumeric($network['network_name']) . '-IPs-Template.csv";');

    fpassthru($f);
    exit;

}

if (isset($_POST['import_network_ips_csv'])) {

    validateCSRFToken();

    $network = networkForRequest($_POST['network_id'] ?? 0, 2);
    $network_id = intval($network['network_id']);

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
    if (!is_array($header) || !in_array(count($header), [3, 5], true)) {
        fclose($file);
        flashAlert("Bad column count - use the template: " . implode(', ', NETWORK_IP_CSV_COLUMNS) . " (Type and MAC may be left off).", 'error');
        redirect();
    }

    $row_count = 0;
    $skipped_count = 0;
    while (($column = fgetcsv($file, 1000, ',')) !== false) {
        $column = array_pad($column, 5, '');
        $ip_address = trim((string) $column[0]);
        if ($ip_address === '') {
            continue;
        }
        $type = 'Static';
        foreach (array_keys(NETWORK_IP_TYPES) as $known) {
            if (strcasecmp($known, trim((string) $column[3])) === 0) {
                $type = $known;
            }
        }
        // A reservation in a file without its MAC is still worth having
        $error = '';
        $fields = networkIpFieldsSet(['type' => $type, 'hostname' => $column[1], 'description' => $column[2], 'mac' => $column[4], 'allow_no_mac' => 1], $error);

        // Same safeguards as the add form - invalid, out of subnet or already
        // documented all mean skip. Rows inserted earlier in this same run are
        // in the table already, so a file that repeats an address imports it once
        if ($fields === '' || checkIpForNetwork($ip_address, $network_id) !== '') {
            $skipped_count++;
            continue;
        }

        mysqli_query($mysqli, "INSERT INTO network_ips SET ip_address = '" . escapeSql($ip_address) . "', $fields, ip_network_id = $network_id");
        $row_count++;
    }
    fclose($file);

    logAudit("IP Address", "Import", "$session_name imported $row_count IP(s) to network " . escapeSql($network['network_name']) . ". $skipped_count row(s) skipped", $client_id);

    flashAlert("<strong>$row_count</strong> IP(s) imported, <strong>$skipped_count</strong> skipped (invalid, outside the subnet or already documented)", $skipped_count ? 'warning' : 'success');

    redirect();

}

if (isExportRequest('export_network_ips')) {

    validateCSRFToken();

    // Exports are reads - see CONTRIBUTING.md
    $network = networkForRequest($_POST['network_id'] ?? 0, 1);
    $network_id = intval($network['network_id']);
    $network_name = $network['network_name'];

    $format = resolveExportFormat($_POST['export_network_ips']);

    // Scoped to the one network, so no page filters apply
    $sql = mysqli_query(
        $mysqli,
        "SELECT ip_address, ip_type, ip_hostname, ip_mac, ip_description, network_name, client_name FROM network_ips
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
