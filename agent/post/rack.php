<?php

/*
 * ITFlow - GET/POST request handler for client racks
 *
 * Racks and the items mounted in them (rack_units). Layout rules - faces,
 * depth, which way the units are numbered - live in functions/rack.php, and
 * every position check here goes through it so the add and edit paths cannot
 * drift apart.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_rack']) || isset($_POST['edit_rack'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $is_edit = isset($_POST['edit_rack']);

    if ($is_edit) {
        $rack_id = intval($_POST['rack_id']);
        $sql = mysqli_query($mysqli, "SELECT rack_client_id, rack_photo, rack_photo_rear FROM racks WHERE rack_id = $rack_id");
        $rack_row = mysqli_fetch_assoc($sql);
        if (!$rack_row) {
            flashAlert("That rack no longer exists", 'error');
            redirect();
        }
        $client_id = intval($rack_row['rack_client_id']);
    } else {
        $rack_id = 0;
        $rack_row = ['rack_photo' => '', 'rack_photo_rear' => ''];
        $client_id = intval($_POST['client_id']);
    }

    enforceClientAccess();

    $name = escapeSql($_POST['name'] ?? '');
    $name_display = strip_tags(trim($_POST['name'] ?? ''));
    $description = escapeSql($_POST['description'] ?? '');
    $type = escapeSql($_POST['type'] ?? '');
    $model = escapeSql($_POST['model'] ?? '');
    $depth = escapeSql($_POST['depth'] ?? '');
    $units = intval($_POST['units'] ?? 0);
    $units_descending = intval($_POST['units_descending'] ?? 0) === 1 ? 1 : 0;
    $power_capacity = intval($_POST['power_capacity'] ?? 0);
    $power_capacity_sql = $power_capacity > 0 ? $power_capacity : "NULL";
    $physical_location = escapeSql($_POST['physical_location'] ?? '');
    $location = intval($_POST['location'] ?? 0);
    $notes = escapeSql($_POST['notes'] ?? '');

    if ($name === '') {
        flashAlert("A rack needs a name", 'error');
        redirect();
    }

    if ($units < 1 || $units > 70) {
        flashAlert("A rack is between 1 and 70 units tall", 'error');
        redirect();
    }

    // A location from another client would show up on this client's rack
    if ($location && !mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM locations WHERE location_id = $location AND location_client_id = $client_id"))) {
        $location = 0;
    }

    if ($is_edit) {
        // Shrinking the rack under an item would leave it hanging off the top
        $sql = mysqli_query($mysqli, "SELECT unit_device, unit_type, unit_end_number, asset_name FROM rack_units
            LEFT JOIN assets ON asset_id = unit_asset_id
            WHERE unit_rack_id = $rack_id AND unit_end_number > $units
            ORDER BY unit_end_number DESC LIMIT 1");
        if ($row = mysqli_fetch_assoc($sql)) {
            $blocking_name = rackUnitName($row['asset_name'], $row['unit_device'], $row['unit_type']);
            flashAlert("Can't shrink the rack to {$units}U - <strong>" . strip_tags($blocking_name) . "</strong> is mounted up to U" . intval($row['unit_end_number']) . ". Move or remove it first.", 'error');
            redirect();
        }

        mysqli_query($mysqli, "UPDATE racks SET rack_name = '$name', rack_description = '$description', rack_type = '$type', rack_model = '$model', rack_depth = '$depth', rack_units = $units, rack_units_descending = $units_descending, rack_power_capacity = $power_capacity_sql, rack_location_id = $location, rack_physical_location = '$physical_location', rack_notes = '$notes' WHERE rack_id = $rack_id");
    } else {
        mysqli_query($mysqli, "INSERT INTO racks SET rack_name = '$name', rack_description = '$description', rack_type = '$type', rack_model = '$model', rack_depth = '$depth', rack_units = $units, rack_units_descending = $units_descending, rack_power_capacity = $power_capacity_sql, rack_location_id = $location, rack_physical_location = '$physical_location', rack_notes = '$notes', rack_client_id = $client_id");
        $rack_id = mysqli_insert_id($mysqli);
    }

    // Photos of the front and the back. A new upload replaces the old file; the
    // remove boxes only matter when nothing new came in for that side.
    foreach (['rack_photo' => ['file', 'remove_photo'], 'rack_photo_rear' => ['file_rear', 'remove_photo_rear']] as $column => [$file_field, $remove_field]) {
        $old_photo = (string) ($rack_row[$column] ?? '');
        $new_photo = isset($_FILES[$file_field]) ? rackStorePhoto($_FILES[$file_field], $client_id, $old_photo) : '';
        if ($new_photo !== '') {
            $new_photo = escapeSql($new_photo);
            mysqli_query($mysqli, "UPDATE racks SET $column = '$new_photo' WHERE rack_id = $rack_id");
        } elseif ($is_edit && !empty($_POST[$remove_field]) && $old_photo !== '') {
            rackDeletePhoto($client_id, $old_photo);
            mysqli_query($mysqli, "UPDATE racks SET $column = NULL WHERE rack_id = $rack_id");
        }
    }

    if ($is_edit) {
        logAudit("Rack", "Edit", "$session_name edited rack $name", $client_id, $rack_id);
        flashAlert("Rack <strong>$name_display</strong> saved");
        redirect();
    }

    logAudit("Rack", "Create", "$session_name created rack $name", $client_id, $rack_id);
    flashAlert("Rack <strong>$name_display</strong> created - now add what's in it");
    redirect("rack.php?client_id=$client_id&rack_id=$rack_id");

}

if (isset($_GET['archive_rack']) || isset($_GET['restore_rack'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $is_archive = isset($_GET['archive_rack']);
    $rack_id = intval($is_archive ? $_GET['archive_rack'] : $_GET['restore_rack']);

    $sql = mysqli_query($mysqli, "SELECT rack_name, rack_client_id FROM racks WHERE rack_id = $rack_id");
    $row = mysqli_fetch_assoc($sql);
    if (!$row) {
        flashAlert("That rack no longer exists", 'error');
        redirect();
    }
    $rack_name = escapeSql($row['rack_name']);
    $rack_name_display = strip_tags($row['rack_name']);
    $client_id = intval($row['rack_client_id']);

    // The client has to be known before access can be checked against it
    enforceClientAccess();

    if ($is_archive) {
        mysqli_query($mysqli, "UPDATE racks SET rack_archived_at = NOW() WHERE rack_id = $rack_id");
        logAudit("Rack", "Archive", "$session_name archived rack $rack_name", $client_id, $rack_id);
        flashAlert("Rack <strong>$rack_name_display</strong> archived", 'error');
    } else {
        mysqli_query($mysqli, "UPDATE racks SET rack_archived_at = NULL WHERE rack_id = $rack_id");
        logAudit("Rack", "Restore", "$session_name restored rack $rack_name", $client_id, $rack_id);
        flashAlert("Rack <strong>$rack_name_display</strong> restored");
    }

    redirect();

}

if (isset($_GET['delete_rack'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 3);

    $rack_id = intval($_GET['delete_rack']);

    $sql = mysqli_query($mysqli, "SELECT rack_name, rack_client_id, rack_photo, rack_photo_rear FROM racks WHERE rack_id = $rack_id");
    $row = mysqli_fetch_assoc($sql);
    if (!$row) {
        flashAlert("That rack no longer exists", 'error');
        redirect();
    }
    $rack_name = escapeSql($row['rack_name']);
    $rack_name_display = strip_tags($row['rack_name']);
    $client_id = intval($row['rack_client_id']);

    enforceClientAccess();

    // rack_units go with it (ON DELETE CASCADE)
    mysqli_query($mysqli, "DELETE FROM racks WHERE rack_id = $rack_id");

    rackDeletePhoto($client_id, $row['rack_photo']);
    rackDeletePhoto($client_id, $row['rack_photo_rear']);

    logAudit("Rack", "Delete", "$session_name deleted rack $rack_name", $client_id);

    flashAlert("Rack <strong>$rack_name_display</strong> deleted", 'error');

    // The rack page this may have come from is gone - land on the list
    redirect("racks.php?client_id=$client_id");

}

if (isset($_POST['add_rack_unit']) || isset($_POST['edit_rack_unit'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $is_edit = isset($_POST['edit_rack_unit']);

    // On an edit the rack comes from the item itself, never from the form - a
    // posted rack_id would let an item be "edited" into another client's rack
    if ($is_edit) {
        $unit_id = intval($_POST['unit_id']);
        $sql = mysqli_query($mysqli, "SELECT unit_rack_id FROM rack_units WHERE unit_id = $unit_id");
        $unit_row = mysqli_fetch_assoc($sql);
        if (!$unit_row) {
            flashAlert("That item is no longer in the rack", 'error');
            redirect();
        }
        $rack_id = intval($unit_row['unit_rack_id']);
    } else {
        $unit_id = 0;
        $rack_id = intval($_POST['rack_id']);
    }

    $sql = mysqli_query($mysqli, "SELECT rack_name, rack_client_id, rack_units FROM racks WHERE rack_id = $rack_id");
    $rack_row = mysqli_fetch_assoc($sql);
    if (!$rack_row) {
        flashAlert("That rack no longer exists", 'error');
        redirect();
    }
    $rack_name = escapeSql($rack_row['rack_name']);
    $rack_units = intval($rack_row['rack_units']);
    $client_id = intval($rack_row['rack_client_id']);

    enforceClientAccess();

    $asset_id = intval($_POST['asset'] ?? 0);
    $label = escapeSql($_POST['name'] ?? '');
    $unit_type = (string) ($_POST['type'] ?? 'Device');
    $unit_type = array_key_exists($unit_type, rackItemTypes()) ? $unit_type : 'Device';
    $start = intval($_POST['unit_start'] ?? 0);
    $height = max(1, intval($_POST['unit_height'] ?? 1));
    $end = $start + $height - 1;
    $face = ($_POST['face'] ?? 'front') === 'rear' ? 'rear' : 'front';
    $full_depth = intval($_POST['full_depth'] ?? 0) === 1 ? 1 : 0;
    $power_watts = intval($_POST['power_watts'] ?? 0);
    $power_watts_sql = $power_watts > 0 ? $power_watts : "NULL";
    $notes = escapeSql($_POST['notes'] ?? '');

    if ($start < 1 || $end > $rack_units) {
        flashAlert("That doesn't fit - this rack runs from U1 to U$rack_units", 'error');
        redirect();
    }

    // The asset has to be this client's, and in one place at a time
    $asset_name = '';
    if ($asset_id) {
        $sql = mysqli_query($mysqli, "SELECT asset_name FROM assets WHERE asset_id = $asset_id AND asset_client_id = $client_id");
        $asset_row = mysqli_fetch_assoc($sql);
        if (!$asset_row) {
            flashAlert("That asset doesn't belong to this client", 'error');
            redirect();
        }
        $asset_name = $asset_row['asset_name'];

        $sql = mysqli_query($mysqli, "SELECT rack_name, unit_start_number, unit_end_number FROM rack_units
            LEFT JOIN racks ON rack_id = unit_rack_id
            WHERE unit_asset_id = $asset_id AND unit_id != $unit_id LIMIT 1");
        if ($row = mysqli_fetch_assoc($sql)) {
            flashAlert("<strong>" . strip_tags($asset_name) . "</strong> is already racked in " . strip_tags($row['rack_name']) . " at " . html_entity_decode(rackPositionLabel($row['unit_start_number'], $row['unit_end_number'])), 'error');
            redirect();
        }
    } elseif ($label === '') {
        flashAlert("Pick an asset or give the item a name", 'error');
        redirect();
    }

    $conflict = rackUnitConflict($rack_id, $start, $end, $face, $full_depth, $unit_id);
    if ($conflict) {
        flashAlert(html_entity_decode(rackPositionLabel($start, $end)) . " on the $face is taken by <strong>" . strip_tags($conflict['name']) . "</strong> (" . html_entity_decode(rackPositionLabel($conflict['start'], $conflict['end'])) . ")", 'error');
        redirect();
    }

    $asset_sql = $asset_id ? $asset_id : "NULL";
    // A linked asset is drawn by its own asset type, so only unlinked items keep one
    $unit_type_sql = $asset_id ? "NULL" : "'" . escapeSql($unit_type) . "'";

    $set = "unit_device = '$label', unit_asset_id = $asset_sql, unit_type = $unit_type_sql,
        unit_start_number = $start, unit_end_number = $end, unit_face = '$face', unit_full_depth = $full_depth,
        unit_power_watts = $power_watts_sql, unit_notes = '$notes'";

    $item_name = rackUnitName($asset_name, $_POST['name'] ?? '', $unit_type);
    $item_name_sql = escapeSql($item_name);
    $item_name_display = strip_tags($item_name);
    $position = html_entity_decode(rackPositionLabel($start, $end));

    if ($is_edit) {
        mysqli_query($mysqli, "UPDATE rack_units SET $set WHERE unit_id = $unit_id");
        logAudit("Rack", "Edit", "$session_name updated $item_name_sql at $position ($face) in rack $rack_name", $client_id, $rack_id);
        flashAlert("<strong>$item_name_display</strong> saved at $position");
    } else {
        mysqli_query($mysqli, "INSERT INTO rack_units SET $set, unit_rack_id = $rack_id");
        logAudit("Rack", "Edit", "$session_name added $item_name_sql at $position ($face) to rack $rack_name", $client_id, $rack_id);
        flashAlert("<strong>$item_name_display</strong> added at $position");
    }

    redirect();

}

if (isset($_GET['remove_rack_unit'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $unit_id = intval($_GET['remove_rack_unit']);

    $sql = mysqli_query($mysqli, "SELECT rack_name, rack_id, rack_client_id, unit_device, unit_type, asset_name FROM rack_units
        LEFT JOIN racks ON rack_id = unit_rack_id
        LEFT JOIN assets ON asset_id = unit_asset_id
        WHERE unit_id = $unit_id");
    $row = mysqli_fetch_assoc($sql);
    if (!$row) {
        flashAlert("That item is no longer in the rack", 'error');
        redirect();
    }
    $rack_name = escapeSql($row['rack_name']);
    $client_id = intval($row['rack_client_id']);
    $rack_id = intval($row['rack_id']);
    $item_name = rackUnitName($row['asset_name'], $row['unit_device'], $row['unit_type']);

    enforceClientAccess();

    mysqli_query($mysqli, "DELETE FROM rack_units WHERE unit_id = $unit_id");

    logAudit("Rack", "Edit", "$session_name removed " . escapeSql($item_name) . " from rack $rack_name", $client_id, $rack_id);

    flashAlert("<strong>" . strip_tags($item_name) . "</strong> removed from the rack", 'error');

    redirect();

}
