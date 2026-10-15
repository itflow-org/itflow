<?php

/*
 * ITFlow - GET/POST request handler for client locations (sites)
 *
 * The location itself, which one is the client's primary, moving people,
 * assets, networks and racks to it, and import / export. Every handler checks
 * the location's own client, and anything a form names - the on-site
 * contact, the tags, the records being moved - must be that client's.
 * Documents and files are linked to a location through post/link.php.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

/** The location a request names, after the client access check - or back with an error if it is gone. */
function locationForRequest($location_id) {
    global $client_id;
    $row = locationRow($location_id);
    if (!$row) {
        flashAlert('That location no longer exists.', 'error');
        redirect();
    }
    $client_id = intval($row['location_client_id']);
    enforceClientAccess();
    return $row;
}

/** Deleting a location for good - its photo goes, and what pointed at it points at nothing. */
function locationDeleteForGood($row) {
    global $mysqli;
    $location_id = intval($row['location_id']);
    $client_id = intval($row['location_client_id']);
    locationClearReferences($location_id);
    $photo = (string) $row['location_photo'];
    if ($photo !== '' && basename($photo) === $photo && is_file("../uploads/clients/$client_id/$photo")) {
        unlink("../uploads/clients/$client_id/$photo");
    }
    mysqli_query($mysqli, "DELETE FROM locations WHERE location_id = $location_id");
}

if (isset($_POST['add_location'])) {

    validateCSRFToken();

    enforceUserPermission('module_client', 2);

    $client_id = intval($_POST['client_id'] ?? 0);

    enforceClientAccess();

    $set = locationFormSet($_POST, $client_id);
    if ($set === null) {
        flashAlert('A location needs a name.', 'error');
        redirect();
    }

    mysqli_query($mysqli, "INSERT INTO locations SET $set, location_client_id = $client_id");
    $location_id = mysqli_insert_id($mysqli);

    locationSetTags($location_id, $_POST['tags'] ?? []);

    // Ticked as primary - or the client has no primary location yet, so this is it
    $has_primary = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM locations WHERE location_client_id = $client_id AND location_primary = 1 AND location_archived_at IS NULL AND location_id != $location_id"))[0]);
    if (!empty($_POST['location_primary']) || !$has_primary) {
        locationMakePrimary($location_id, $client_id);
    }

    locationSavePhoto($location_id, $client_id, $_FILES['file'] ?? []);

    $name = trim($_POST['name']);
    logAudit("Location", "Create", "$session_name created location " . escapeSql($name), $client_id, $location_id);

    flashAlert("Location <strong>$name</strong> created");

    redirect();

}

if (isset($_POST['edit_location'])) {

    validateCSRFToken();

    enforceUserPermission('module_client', 2);

    $row = locationForRequest($_POST['location_id'] ?? 0);
    $location_id = intval($row['location_id']);

    $set = locationFormSet($_POST, $client_id);
    if ($set === null) {
        flashAlert('A location needs a name.', 'error');
        redirect();
    }

    mysqli_query($mysqli, "UPDATE locations SET $set WHERE location_id = $location_id");

    // Only a form that showed the tags changes them
    if (isset($_POST['tags_form'])) {
        locationSetTags($location_id, $_POST['tags'] ?? []);
    }

    if (!empty($_POST['location_primary'])) {
        locationMakePrimary($location_id, $client_id);
    }

    if (!empty($_POST['remove_photo']) && $row['location_photo']) {
        $photo = (string) $row['location_photo'];
        if (basename($photo) === $photo && is_file("../uploads/clients/$client_id/$photo")) {
            unlink("../uploads/clients/$client_id/$photo");
        }
        mysqli_query($mysqli, "UPDATE locations SET location_photo = NULL WHERE location_id = $location_id");
    }
    locationSavePhoto($location_id, $client_id, $_FILES['file'] ?? []);

    $name = trim($_POST['name']);
    logAudit("Location", "Edit", "$session_name edited location " . escapeSql($name), $client_id, $location_id);

    flashAlert("Location <strong>$name</strong> updated");

    redirect();

}

if (isset($_GET['make_location_primary'])) {

    validateCSRFToken();

    enforceUserPermission('module_client', 2);

    $row = locationForRequest($_GET['make_location_primary']);
    $location_id = intval($row['location_id']);

    if ($row['location_archived_at']) {
        flashAlert('Restore the location before making it primary.', 'error');
        redirect();
    }

    locationMakePrimary($location_id, $client_id);

    logAudit("Location", "Edit", "$session_name made " . escapeSql($row['location_name']) . " the primary location", $client_id, $location_id);

    flashAlert("<strong>{$row['location_name']}</strong> is now the primary location");

    redirect();

}

if (isset($_POST['set_location_records'])) {

    validateCSRFToken();

    $row = locationForRequest($_POST['location_id'] ?? 0);
    $kind = (string) ($_POST['kind'] ?? '');
    if (!isset(LOCATION_KINDS[$kind])) {
        flashAlert('Nothing to move.', 'error');
        redirect();
    }

    enforceUserPermission(LOCATION_KINDS[$kind]['module'], 2);

    if ($row['location_archived_at']) {
        flashAlert('Restore the location before moving anything to it.', 'error');
        redirect();
    }

    $moved = locationAssign($row, $kind, $_POST['ids'] ?? []);
    $k = LOCATION_KINDS[$kind];

    if (!$moved) {
        flashAlert("Nothing new to move to <strong>{$row['location_name']}</strong>.", 'warning');
    } elseif (count($moved) === 1) {
        flashAlert(ucfirst($k['label']) . " <strong>{$moved[0]}</strong> moved to <strong>{$row['location_name']}</strong>");
    } else {
        flashAlert("Moved <strong>" . count($moved) . "</strong> " . strtolower($k['plural']) . " to <strong>{$row['location_name']}</strong>");
    }

    redirect();

}

if (isset($_GET['unset_location_record'])) {

    validateCSRFToken();

    $row = locationForRequest($_GET['location_id'] ?? 0);
    $kind = (string) ($_GET['kind'] ?? '');
    if (!isset(LOCATION_KINDS[$kind])) {
        redirect();
    }

    enforceUserPermission(LOCATION_KINDS[$kind]['module'], 2);

    $name = locationUnassign($row, $kind, intval($_GET['unset_location_record']));
    if ($name !== '') {
        flashAlert(ucfirst(LOCATION_KINDS[$kind]['label']) . " <strong>$name</strong> is no longer at <strong>{$row['location_name']}</strong>", 'error');
    }

    redirect();

}

if (isset($_GET['archive_location'])) {

    validateCSRFToken();

    enforceUserPermission('module_client', 2);

    $row = locationForRequest($_GET['archive_location']);
    $location_id = intval($row['location_id']);

    // The client header and overview show the primary location - another one has to take over first
    if (intval($row['location_primary'])) {
        flashAlert("<strong>{$row['location_name']}</strong> is the primary location - make another location primary first.", 'error');
        redirect();
    }

    mysqli_query($mysqli, "UPDATE locations SET location_archived_at = NOW() WHERE location_id = $location_id");

    logAudit("Location", "Archive", "$session_name archived location " . escapeSql($row['location_name']), $client_id, $location_id);

    flashAlert("Location <strong>{$row['location_name']}</strong> archived", 'error');

    redirect();

}

if (isset($_GET['restore_location'])) {

    validateCSRFToken();

    enforceUserPermission('module_client', 2);

    $row = locationForRequest($_GET['restore_location']);
    $location_id = intval($row['location_id']);

    mysqli_query($mysqli, "UPDATE locations SET location_archived_at = NULL WHERE location_id = $location_id");

    logAudit("Location", "Restore", "$session_name restored location " . escapeSql($row['location_name']), $client_id, $location_id);

    flashAlert("Location <strong>{$row['location_name']}</strong> restored");

    redirect();

}

if (isset($_GET['delete_location'])) {

    validateCSRFToken();

    enforceUserPermission('module_client', 3);

    $row = locationForRequest($_GET['delete_location']);

    if (intval($row['location_primary'])) {
        flashAlert("<strong>{$row['location_name']}</strong> is the primary location - make another location primary first.", 'error');
        redirect();
    }

    locationDeleteForGood($row);

    logAudit("Location", "Delete", "$session_name deleted location " . escapeSql($row['location_name']), $client_id);

    flashAlert("Location <strong>{$row['location_name']}</strong> deleted", 'error');

    // From its own page, back to the client's archived locations - that page is gone
    if (strpos($_SERVER['HTTP_REFERER'] ?? '', '/location.php') !== false) {
        redirect("locations.php?client_id=$client_id&archived=1");
    }
    redirect();

}

if (isset($_POST['bulk_archive_locations'])) {

    validateCSRFToken();

    enforceUserPermission('module_client', 2);

    $count = 0;
    $skipped = [];
    foreach (array_unique(array_map('intval', (array) ($_POST['location_ids'] ?? []))) as $location_id) {
        $row = locationRow($location_id);
        if (!$row) {
            continue;
        }
        $client_id = intval($row['location_client_id']);
        enforceClientAccess();
        if (intval($row['location_primary'])) {
            $skipped[] = $row['location_name'];
            continue;
        }
        mysqli_query($mysqli, "UPDATE locations SET location_archived_at = NOW() WHERE location_id = $location_id");
        logAudit("Location", "Archive", "$session_name archived location " . escapeSql($row['location_name']), $client_id, $location_id);
        $count++;
    }

    flashAlert("Archived <strong>$count</strong> location" . ($count === 1 ? '' : 's') . ($skipped ? ' - kept <strong>' . implode(', ', $skipped) . '</strong>, the primary location' : ''), 'error');

    redirect();

}

if (isset($_POST['bulk_restore_locations'])) {

    validateCSRFToken();

    enforceUserPermission('module_client', 2);

    $count = 0;
    foreach (array_unique(array_map('intval', (array) ($_POST['location_ids'] ?? []))) as $location_id) {
        $row = locationRow($location_id);
        if (!$row) {
            continue;
        }
        $client_id = intval($row['location_client_id']);
        enforceClientAccess();
        mysqli_query($mysqli, "UPDATE locations SET location_archived_at = NULL WHERE location_id = $location_id");
        logAudit("Location", "Restore", "$session_name restored location " . escapeSql($row['location_name']), $client_id, $location_id);
        $count++;
    }

    flashAlert("Restored <strong>$count</strong> location" . ($count === 1 ? '' : 's'));

    redirect();

}

if (isset($_POST['bulk_delete_locations'])) {

    validateCSRFToken();

    enforceUserPermission('module_client', 3);

    $count = 0;
    foreach (array_unique(array_map('intval', (array) ($_POST['location_ids'] ?? []))) as $location_id) {
        $row = locationRow($location_id);
        if (!$row || intval($row['location_primary'])) {
            continue;
        }
        $client_id = intval($row['location_client_id']);
        enforceClientAccess();
        locationDeleteForGood($row);
        logAudit("Location", "Delete", "$session_name deleted location " . escapeSql($row['location_name']), $client_id);
        $count++;
    }

    flashAlert("Deleted <strong>$count</strong> location" . ($count === 1 ? '' : 's'), 'error');

    redirect();

}

if (isExportRequest('export_locations')) {

    validateCSRFToken();

    // Exports are reads - see CONTRIBUTING.md
    enforceUserPermission('module_client');

    $format = resolveExportFormat($_POST['export_locations']);

    // Filters inherited from the locations page - mirrors agent/locations.php
    $filter_summary = [];

    // Archived Filter
    $archived = (isset($_POST['archived']) && $_POST['archived'] == 1);
    if ($archived) {
        $filter_summary['Archived'] = 'Archived only';
    }

    if (!empty($_POST['client_id'])) {
        $client_id = intval($_POST['client_id']);
        $client_query = "AND location_client_id = $client_id";
        $client_name = getFieldById('clients', $client_id, 'client_name');
        $file_name_prepend = "$client_name-";
        $filter_summary['Client'] = $client_name;

        enforceClientAccess();

        $archive_query = $archived ? "location_archived_at IS NOT NULL" : "location_archived_at IS NULL";
    } else {
        $client_query = '';
        $client_id = 0; // for Logging
        $file_name_prepend = "$session_company_name-";

        // Client Filter
        if (!empty($_POST['client'])) {
            $filter_client_id = intval($_POST['client']);
            $client_query = "AND (location_client_id = $filter_client_id)";
            $filter_summary['Client'] = getFieldById('clients', $filter_client_id, 'client_name');
        }

        $archive_query = $archived ? "(client_archived_at IS NOT NULL OR location_archived_at IS NOT NULL)" : "(client_archived_at IS NULL AND location_archived_at IS NULL)";
    }

    // Tags Filter - a location with any of them
    $tag_query = '';
    if (isset($_POST['tags']) && is_array($_POST['tags']) && !empty($_POST['tags'])) {
        $tag_filter = implode(",", array_map('intval', $_POST['tags']));
        $tag_query = "AND locations.location_id IN (SELECT location_id FROM location_tags WHERE tag_id IN ($tag_filter))";

        $tag_names = [];
        $sql_tags = mysqli_query($mysqli, "SELECT tag_name FROM tags WHERE tag_id IN ($tag_filter) ORDER BY tag_name ASC");
        while ($tag_row = mysqli_fetch_assoc($sql_tags)) {
            $tag_names[] = $tag_row['tag_name'];
        }
        $filter_summary['Tags'] = implode(', ', $tag_names);
    }

    // Search Filter
    $q = escapeSql($_POST['q'] ?? '');
    if (!empty($q)) {
        $filter_summary['Search'] = $_POST['q'];
    }

    $sql = mysqli_query(
        $mysqli,
        "SELECT location_name, location_description, location_address, location_city, location_state, location_zip, location_country,
            location_phone, location_phone_country_code, location_hours, location_notes, IF(location_primary = 1, 'Yes', '') AS location_primary_text,
            client_name, contact_name,
            (SELECT COUNT(*) FROM assets WHERE asset_location_id = locations.location_id AND asset_archived_at IS NULL) AS asset_count,
            (SELECT COUNT(*) FROM contacts AS people WHERE people.contact_location_id = locations.location_id AND people.contact_archived_at IS NULL) AS contact_count
        FROM locations
        LEFT JOIN clients ON client_id = location_client_id
        LEFT JOIN contacts ON contact_id = location_contact_id
        WHERE $archive_query
        $tag_query
        AND (location_name LIKE '%$q%' OR location_description LIKE '%$q%' OR location_address LIKE '%$q%' OR location_city LIKE '%$q%' OR location_state LIKE '%$q%' OR location_zip LIKE '%$q%' OR location_country LIKE '%$q%' OR location_phone LIKE '%$q%' OR client_name LIKE '%$q%'
            OR locations.location_id IN (SELECT location_id FROM location_tags JOIN tags USING (tag_id) WHERE tag_name LIKE '%$q%'))
        " . clientScopeSql('location_client_id') . "
        $client_query
        ORDER BY location_primary DESC, location_name ASC"
    );

    $num_rows = mysqli_num_rows($sql);

    if ($num_rows > 0) {

        guardExportPdfRowCount($format, $num_rows);

        $export = beginExport('locations', $format, $file_name_prepend . 'Locations', 'Locations', summarizeExportFilters($filter_summary));

        while ($row = mysqli_fetch_assoc($sql)) {
            addExportRow($export, $row);
        }

        finishExport($export);
    }

    logAudit("Location", "Export", "$session_name exported $num_rows location(s) to a " . strtoupper($format) . " file", $client_id);

    exit;

}

if (isset($_POST["import_locations_csv"])) {

    validateCSRFToken();

    enforceUserPermission('module_client', 2);

    $client_id = intval($_POST['client_id']);

    enforceClientAccess();

    if (empty($_FILES["file"]["tmp_name"])) {
        flashAlert("Please select a file to upload.", 'error');
        redirect();
    }

    $file_extension = strtolower(pathinfo((string) $_FILES['file']['name'], PATHINFO_EXTENSION));
    if ($file_extension !== 'csv') {
        flashAlert("Bad file extension", 'error');
        redirect();
    }
    if ($_FILES["file"]["size"] < 1) {
        flashAlert("Bad file size (empty?)", 'error');
        redirect();
    }

    $file = fopen($_FILES["file"]["tmp_name"], "r");
    $header = fgetcsv($file, 1000, ",");
    if (!is_array($header) || count($header) != 8) {
        fclose($file);
        flashAlert("Bad column count - use the template: Name, Description, Address, City, State, Postal Code, Phone, Hours.", 'error');
        redirect();
    }

    $has_primary = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM locations WHERE location_client_id = $client_id AND location_primary = 1 AND location_archived_at IS NULL"))[0]);

    $row_count = 0;
    $duplicate_count = 0;
    while (($column = fgetcsv($file, 1000, ",")) !== false) {
        // Each row starts blank - a short row never inherits the row before it
        $column = array_pad($column, 8, '');
        $name = trim((string) $column[0]);
        if ($name === '') {
            continue;
        }
        $name_sql = escapeSql(mb_substr($name, 0, 200));
        if (mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM locations WHERE location_name = '$name_sql' AND location_client_id = $client_id"))) {
            $duplicate_count++;
            continue;
        }
        $description = escapeSql((string) $column[1]);
        $address = escapeSql(mb_substr((string) $column[2], 0, 200));
        $city = escapeSql(mb_substr((string) $column[3], 0, 200));
        $state = escapeSql(mb_substr((string) $column[4], 0, 200));
        $zip = escapeSql(mb_substr((string) $column[5], 0, 200));
        $phone = substr(preg_replace("/[^0-9]/", '', (string) $column[6]), 0, 200);
        $hours = escapeSql(mb_substr((string) $column[7], 0, 200));

        mysqli_query($mysqli, "INSERT INTO locations SET location_name = '$name_sql', location_description = '$description', location_address = '$address', location_city = '$city', location_state = '$state', location_zip = '$zip', location_phone = '$phone', location_hours = '$hours', location_client_id = $client_id");

        // A client with no locations yet gets its first one as primary
        if (!$has_primary) {
            locationMakePrimary(mysqli_insert_id($mysqli), $client_id);
            $has_primary = 1;
        }
        $row_count++;
    }
    fclose($file);

    logAudit("Location", "Import", "$session_name imported $row_count location(s). $duplicate_count duplicate(s) found and not imported", $client_id);

    flashAlert("$row_count Location(s) imported, $duplicate_count duplicate(s) detected and not imported");

    redirect();

}

if (isset($_GET['download_locations_csv_template'])) {

    $delimiter = ",";
    $enclosure = '"';
    $escape    = '\\';
    $filename = "Locations-Template.csv";

    //create a file pointer
    $f = fopen('php://memory', 'w');

    //set column headers
    $fields = array('Name', 'Description', 'Address', 'City', 'State', 'Postal Code', 'Phone', 'Hours');
    fputcsv($f, $fields, $delimiter, $enclosure, $escape);

    //move back to beginning of file
    fseek($f, 0);

    //set headers to download file rather than displayed
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '";');

    //output all remaining data on a file pointer
    fpassthru($f);
    exit;

}
