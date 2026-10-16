<?php

/*
 * ITFlow - trips (functions/trips.php does the work).
 *
 * New, Edit, Copy and Return Trip all post through tripSave(). Anyone logs
 * and changes their own trips; someone else's needs write access to
 * Financial, deleting them full access. Bulk Set Driver and Set Client need
 * write access, bulk delete full access.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_trip'])) {

    validateCSRFToken();

    $trip_id = tripSave($_POST, 0, $error);
    if (!$trip_id) {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }

    $row = tripRow($trip_id, false);
    logAudit("Trip", "Create", "$session_name logged the trip " . escapeSql(tripLabel($row)), intval($row['trip_client_id']), $trip_id);

    flashAlert("Trip to <strong>" . escapeHtml($row['trip_destination']) . "</strong> logged - " . escapeHtml(tripMilesText($row['trip_miles'])));

    redirect();

}

if (isset($_POST['edit_trip'])) {

    validateCSRFToken();

    $trip_id = intval($_POST['trip_id'] ?? 0);
    if (!tripSave($_POST, $trip_id, $error)) {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }

    $row = tripRow($trip_id, false);
    logAudit("Trip", "Edit", "$session_name edited the trip " . escapeSql(tripLabel($row)), intval($row['trip_client_id']), $trip_id);

    flashAlert("Trip to <strong>" . escapeHtml($row['trip_destination']) . "</strong> saved");

    redirect();

}

if (isset($_GET['delete_trip'])) {

    validateCSRFToken();

    $row = tripDelete(intval($_GET['delete_trip']), $error);
    if (!$row) {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }

    logAudit("Trip", "Delete", "$session_name deleted the trip " . escapeSql(tripLabel($row)), intval($row['trip_client_id']));

    flashAlert("Trip to <strong>" . escapeHtml($row['trip_destination']) . "</strong> deleted", 'error');

    redirect();

}

/* Set the driver (an agent) or client (0 for none - the site and ticket go with the old one) of the ticked trips. */
if (isset($_POST['bulk_edit_trips'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 2);

    $field = (string) ($_POST['field'] ?? '');
    $value = intval($_POST['value'] ?? 0);
    $set = '';
    $label = '';
    if ($field === 'driver') {
        $drivers = tripDrivers();
        if (isset($drivers[$value])) {
            $set = "trip_user_id = $value";
            $label = 'the driver to ' . $drivers[$value]['user_name'];
        }
    } elseif ($field === 'client') {
        if (!$value) {
            $set = 'trip_client_id = 0, trip_location_id = 0, trip_ticket_id = 0';
            $label = 'no client';
        } elseif (mysqli_num_rows(mysqli_query($mysqli, "SELECT client_id FROM clients WHERE client_id = $value " . clientScopeSql('client_id')))) {
            // The site and ticket first - the client after, as MySQL sets them in order
            $set = "trip_location_id = IF(trip_client_id = $value, trip_location_id, 0), trip_ticket_id = IF(trip_client_id = $value, trip_ticket_id, 0), trip_client_id = $value";
            $label = 'the client to ' . getFieldById('clients', $value, 'client_name');
        }
    }
    $trip_ids = array_unique(array_filter(array_map('intval', (array) ($_POST['trip_ids'] ?? []))));
    if ($set === '' || !$trip_ids) {
        flashAlert($trip_ids ? 'Pick what to set them to' : 'Tick the trips to change first', 'error');
        redirect();
    }

    $count = 0;
    foreach ($trip_ids as $trip_id) {
        $row = tripRow($trip_id);
        if (!$row) {
            continue;
        }
        mysqli_query($mysqli, "UPDATE trips SET $set WHERE trip_id = $trip_id");
        logAudit("Trip", "Edit", "$session_name set " . escapeSql($label) . " on the trip " . escapeSql(tripLabel($row)), intval($row['trip_client_id']), $trip_id);
        $count++;
    }
    logAudit("Trip", "Bulk Edit", "$session_name set " . escapeSql($label) . " on $count trip" . ($count === 1 ? '' : 's'));

    flashAlert("Set " . escapeHtml($label) . " on <strong>$count</strong> trip" . ($count === 1 ? '' : 's'));

    redirect();

}

if (isset($_POST['bulk_delete_trips'])) {

    validateCSRFToken();

    enforceUserPermission('module_financial', 3);

    $count = 0;
    foreach (array_unique(array_filter(array_map('intval', (array) ($_POST['trip_ids'] ?? [])))) as $trip_id) {
        $row = tripDelete($trip_id);
        if (!$row) {
            continue;
        }
        logAudit("Trip", "Delete", "$session_name deleted the trip " . escapeSql(tripLabel($row)), intval($row['trip_client_id']));
        $count++;
    }
    logAudit("Trip", "Bulk Delete", "$session_name deleted $count trip" . ($count === 1 ? '' : 's'));

    flashAlert("<strong>$count</strong> trip" . ($count === 1 ? '' : 's') . " deleted", 'error');

    redirect();

}

if (isExportRequest('export_trips')) {

    validateCSRFToken();

    $format = resolveExportFormat($_POST['export_trips']);

    // The same filters as the trips list, over the trips this agent may see
    $src = $_POST;
    if (!empty($_POST['client_id'])) {
        $src['client'] = intval($_POST['client_id']);
    }
    $where = tripFiltersSql($src, $filter_summary);
    $client_id = intval($src['client'] ?? 0);
    if ($client_id) {
        enforceClientAccess();
    }

    $sql = mysqli_query($mysqli, "SELECT trips.*, client_name, driver.user_name AS driver_name, location_name, ticket_prefix, ticket_number, ticket_subject
        FROM trips " . tripJoinsSql() . "
        WHERE $where
        ORDER BY trips.trip_date ASC, trips.trip_id ASC");

    $num_rows = mysqli_num_rows($sql);

    if ($num_rows > 0) {

        guardExportPdfRowCount($format, $num_rows);

        $export = beginExport('trips', $format, ($client_id ? getFieldById('clients', $client_id, 'client_name') : $session_company_name) . '-Trips', 'Trips', summarizeExportFilters($filter_summary));

        while ($row = mysqli_fetch_assoc($sql)) {
            $row['user_name'] = $row['driver_name'];
            $row['round_trip_display'] = intval($row['round_trip']) ? 'Yes' : 'No';
            $row['trip_one_way'] = number_format(tripOneWay($row), 1, '.', '');
            $row['ticket_display'] = $row['ticket_number'] !== null ? ticketReference($row) . ' - ' . $row['ticket_subject'] : '';
            addExportRow($export, $row);
        }

        finishExport($export);
    }

    logAudit("Trip", "Export", "$session_name exported $num_rows trip(s) to a " . strtoupper($format) . " file", $client_id);

    exit;

}
