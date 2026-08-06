<?php

/*
 * ITFlow - GET/POST request handler for trips (accounting related)
 */

 // Todo - JQ 2026-03-02 - Possibly need another Perm for trips

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_trip'])) {

    validateCSRFToken();

    require_once 'trip_model.php';

    $client_id = intval($_POST['client_id']);

    if ($client_id) {
        enforceClientAccess();
    }

    mysqli_query($mysqli,"INSERT INTO trips SET trip_date = '$date', trip_source = '$source', trip_destination = '$destination', trip_miles = $miles, round_trip = $roundtrip, trip_purpose = '$purpose', trip_user_id = $user_id, trip_client_id = $client_id");

    $trip_id = mysqli_insert_id($mysqli);

    logAudit("Trip", "Create", "$session_name logged trip from $source to $destination", $client_id , $trip_id);

    flashAlert("Trip from <strong>$source</strong> to <strong>$destination</strong> logged");

    redirect();

}

if (isset($_POST['edit_trip'])) {

    validateCSRFToken();

    require_once 'trip_model.php';

    $trip_id = intval($_POST['trip_id']);

    $client_id = intval($_POST['client_id']);

    if ($client_id) {
        enforceClientAccess();
    }

    mysqli_query($mysqli,"UPDATE trips SET trip_date = '$date', trip_source = '$source', trip_destination = '$destination', trip_miles = $miles, trip_purpose = '$purpose', round_trip = $roundtrip, trip_user_id = $user_id, trip_client_id = $client_id WHERE trip_id = $trip_id");

    logAudit("Trip", "Edit", "$session_name edited trip", $client_id , $trip_id);

    flashAlert("Trip edited");

    redirect();

}

if (isset($_GET['delete_trip'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 3);

    $trip_id = intval($_GET['delete_trip']);

    // Get Trip Info and Client ID for logging
    $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT trip_client_id, trip_destination, trip_source FROM trips WHERE trip_id = $trip_id"));
    $client_id = intval($row['trip_client_id']);
    $trip_source = escapeSql($row['trip_source']);
    $trip_destination = escapeSql($row['trip_destination']);

    if ($client_id) {
        enforceClientAccess();
    }

    mysqli_query($mysqli,"DELETE FROM trips WHERE trip_id = $trip_id");

    logAudit("Trip", "Delete", "$session_name deleted trip ($trip_source - $trip_destination)", $client_id);

    flashAlert("Trip ($trip_source - $trip_destination) deleted", 'error');

    redirect();

}

if (isExportRequest('export_trips')) {

    validateCSRFToken();

    // Exports are reads - see CONTRIBUTING.md
    enforceUserPermission('module_financial');

    $format = resolveExportFormat($_POST['export_trips']);

    // Filters inherited from the trips page - mirrors agent/trips.php
    $filter_summary = [];

    if (!empty($_POST['client_id'])) {
        $client_id = intval($_POST['client_id']);
        $client_query = "AND trip_client_id = $client_id";
        $client_name = getFieldById('clients', $client_id, 'client_name');
        $file_name_prepend = "$client_name-";
        $filter_summary['Client'] = $client_name;

        enforceClientAccess();
    } else {
        $client_query = '';
        $client_id = 0; // for Logging
        $file_name_prepend = "$session_company_name-";
    }

    // Date Filter
    $dtf = escapeSql(!empty($_POST['dtf']) ? $_POST['dtf'] : '1970-01-01');
    $dtt = escapeSql(!empty($_POST['dtt']) ? $_POST['dtt'] : '2099-12-31');
    $date_range = formatExportDateRange($dtf, $dtt);
    if ($date_range) {
        $filter_summary['Dated'] = $date_range;
    }

    // Search Filter
    $q = escapeSql($_POST['q'] ?? '');
    if (!empty($q)) {
        $filter_summary['Search'] = $_POST['q'];
    }

    $sql = mysqli_query(
        $mysqli,
        "SELECT * FROM trips
        LEFT JOIN clients ON trip_client_id = client_id
        LEFT JOIN users ON trip_user_id = user_id
        WHERE (trip_purpose LIKE '%$q%' OR trip_source LIKE '%$q%' OR trip_destination LIKE '%$q%' OR trip_miles LIKE '%$q%' OR client_name LIKE '%$q%' OR user_name LIKE '%$q%')
        AND DATE(trip_date) BETWEEN '$dtf' AND '$dtt'
        AND trip_archived_at IS NULL
        $client_query
        " . clientScopeSql('trip_client_id') . "
        ORDER BY trip_date ASC"
    );

    $num_rows = mysqli_num_rows($sql);

    if ($num_rows > 0) {

        guardExportPdfRowCount($format, $num_rows);

        $export = beginExport('trips', $format, $file_name_prepend . 'Trips', 'Trips', summarizeExportFilters($filter_summary));

        while ($row = mysqli_fetch_assoc($sql)) {
            addExportRow($export, $row);
        }

        finishExport($export);
    }

    logAudit("Trip", "Export", "$session_name exported $num_rows trip(s) to a " . strtoupper($format) . " file", $client_id);

    exit;

}
