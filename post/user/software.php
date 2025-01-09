<?php

/*
 * ITFlow - GET/POST request handler for client software & licenses
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_software_from_template'])) {

    enforceUserPermission('module_support', 2);

    // GET POST Data
    $client_id = intval($_POST['client_id']);
    $software_template_id = intval($_POST['software_template_id']);

    // GET Software Info
    $sql_software = mysqli_query($mysqli,"SELECT * FROM software WHERE software_id = $software_template_id");
    $row = mysqli_fetch_array($sql_software);
    $name = sanitizeInput($row['software_name']);
    $version = sanitizeInput($row['software_version']);
    $description = sanitizeInput($row['software_description']);
    $type = sanitizeInput($row['software_type']);
    $license_type = sanitizeInput($row['software_license_type']);
    $notes = sanitizeInput($row['software_notes']);

    // Software add query
    mysqli_query($mysqli,"INSERT INTO software SET software_name = '$name', software_version = '$version', software_description = '$description', software_type = '$type', software_license_type = '$license_type', software_notes = '$notes', software_client_id = $client_id");

    $software_id = mysqli_insert_id($mysqli);

    // Logging
    logAction("Software", "Create", "$session_name created software $name using template", $client_id, $software_id);

    $_SESSION['alert_message'] = "Software <strong>$name</strong> created from template";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['add_software'])) {

    enforceUserPermission('module_support', 2);

    $client_id = intval($_POST['client_id']);
    $name = sanitizeInput($_POST['name']);
    $version = sanitizeInput($_POST['version']);
    $description = sanitizeInput($_POST['description']);
    $type = sanitizeInput($_POST['type']);
    $license_type = sanitizeInput($_POST['license_type']);
    $notes = sanitizeInput($_POST['notes']);
    $key = sanitizeInput($_POST['key']);
    $seats = intval($_POST['seats']);
    $purchase = sanitizeInput($_POST['purchase']);
    if (empty($purchase)) {
        $purchase = "NULL";
    } else {
        $purchase = "'" . $purchase . "'";
    }
    $expire = sanitizeInput($_POST['expire']);
    if (empty($expire)) {
        $expire = "NULL";
    } else {
        $expire = "'" . $expire . "'";
    }
    $notes = sanitizeInput($_POST['notes']);

    mysqli_query($mysqli,"INSERT INTO software SET software_name = '$name', software_version = '$version', software_description = '$description', software_type = '$type', software_key = '$key', software_license_type = '$license_type', software_seats = $seats, software_purchase = $purchase, software_expire = $expire, software_notes = '$notes', software_client_id = $client_id");

    $software_id = mysqli_insert_id($mysqli);

    $alert_extended = "";

    // Add Asset Licenses
    if (isset($_POST['assets'])) {
        foreach($_POST['assets'] as $asset) {
            $asset_id = intval($asset);
            mysqli_query($mysqli,"INSERT INTO software_assets SET software_id = $software_id, asset_id = $asset_id");
        }
    }

    // Add Contact Licenses
    if (isset($_POST['contacts'])) {
        foreach($_POST['contacts'] as $contact) {
            $contact = intval($contact);
            mysqli_query($mysqli,"INSERT INTO software_contacts SET software_id = $software_id, contact_id = $contact");
        }
    }

    // Logging
    logAction("Software", "Create", "$session_name created software $name", $client_id, $software_id);

    $_SESSION['alert_message'] = "Software <strong>$name</strong> created $alert_extended";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_software'])) {

    enforceUserPermission('module_support', 2);

    $software_id = intval($_POST['software_id']);
    $client_id = intval($_POST['client_id']);
    $name = sanitizeInput($_POST['name']);
    $version = sanitizeInput($_POST['version']);
    $description = sanitizeInput($_POST['description']);
    $type = sanitizeInput($_POST['type']);
    $license_type = sanitizeInput($_POST['license_type']);
    $notes = sanitizeInput($_POST['notes']);
    $key = sanitizeInput($_POST['key']);
    $seats = intval($_POST['seats']);
    $purchase = sanitizeInput($_POST['purchase']);
    if (empty($purchase)) {
        $purchase = "NULL";
    } else {
        $purchase = "'" . $purchase . "'";
    }
    $expire = sanitizeInput($_POST['expire']);
    if (empty($expire)) {
        $expire = "NULL";
    } else {
        $expire = "'" . $expire . "'";
    }
    $notes = sanitizeInput($_POST['notes']);

    mysqli_query($mysqli,"UPDATE software SET software_name = '$name', software_version = '$version', software_description = '$description', software_type = '$type', software_key = '$key', software_license_type = '$license_type', software_seats = $seats, software_purchase = $purchase, software_expire = $expire, software_notes = '$notes' WHERE software_id = $software_id");


    // Update Asset Licenses
    mysqli_query($mysqli,"DELETE FROM software_assets WHERE software_id = $software_id");
    if (isset($_POST['assets'])) {
        foreach($_POST['assets'] as $asset) {
            $asset = intval($asset);
            mysqli_query($mysqli,"INSERT INTO software_assets SET software_id = $software_id, asset_id = $asset");
        }
    }

    // Update Contact Licenses
    mysqli_query($mysqli,"DELETE FROM software_contacts WHERE software_id = $software_id");
    if (isset($_POST['contacts'])) {
        foreach($_POST['contacts'] as $contact) {
            $contact = intval($contact);
            mysqli_query($mysqli,"INSERT INTO software_contacts SET software_id = $software_id, contact_id = $contact");
        }
    }

    // Logging
    logAction("Software", "Edit", "$session_name edited software $name", $client_id, $software_id);

    $_SESSION['alert_message'] = "Software <strong>$name</strong> updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['archive_software'])) {

    enforceUserPermission('module_support', 2);

    $software_id = intval($_GET['archive_software']);

    // Get Software Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT software_name, software_client_id FROM software WHERE software_id = $software_id");
    $row = mysqli_fetch_array($sql);
    $software_name = sanitizeInput($row['software_name']);
    $client_id = intval($row['software_client_id']);

    mysqli_query($mysqli,"UPDATE software SET software_archived_at = NOW() WHERE software_id = $software_id");

    // Remove Software Relations
    mysqli_query($mysqli,"DELETE FROM software_contacts WHERE software_id = $software_id");
    mysqli_query($mysqli,"DELETE FROM software_assets WHERE software_id = $software_id");

    // Logging
    logAction("Software", "Archive", "$session_name archived software $software_name and removed all device/user license associations", $client_id, $software_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Software <strong>$software_name</strong> archived and removed all device/user license associations";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['delete_software'])) {

    enforceUserPermission('module_support', 3);

    $software_id = intval($_GET['delete_software']);

    // Get Software Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT software_name, software_client_id FROM software WHERE software_id = $software_id");
    $row = mysqli_fetch_array($sql);
    $software_name = sanitizeInput($row['software_name']);
    $client_id = intval($row['software_client_id']);

    mysqli_query($mysqli,"DELETE FROM software WHERE software_id = $software_id");

    // Remove Software Relations
    mysqli_query($mysqli,"DELETE FROM software_contacts WHERE software_id = $software_id");
    mysqli_query($mysqli,"DELETE FROM software_assets WHERE software_id = $software_id");

    //Logging
    logAction("Software", "Delete", "$session_name deleted software $software_name and removed all device/user license associations", $client_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Software <strong>$software_name</strong> deleted and removed all device/user license associations";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['export_client_software_csv'])) {

    enforceUserPermission('module_support');

    $client_id = intval($_POST['client_id']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT client_name FROM clients WHERE client_id = $client_id");
    $row = mysqli_fetch_array($sql);

    $client_name = $row['client_name'];

    $sql = mysqli_query($mysqli,"SELECT * FROM software WHERE software_client_id = $client_id ORDER BY software_name ASC");

    $num_rows = mysqli_num_rows($sql);

    if ($num_rows > 0) {
        $delimiter = ",";
        $filename = $client_name . "-Software-" . date('Y-m-d') . ".csv";

        //create a file pointer
        $f = fopen('php://memory', 'w');

        //set column headers
        $fields = array('Name', 'Version', 'Description', 'Type', 'License Type', 'Seats', 'Key', 'Assets', 'Contacts', 'Purchased', 'Expires', 'Notes');
        fputcsv($f, $fields, $delimiter);

        //output each row of the data, format line as csv and write to file pointer
        while($row = $sql->fetch_assoc()) {

            // Generate asset & user license list for this software

            // Asset licenses
            $assigned_to_assets = '';
            $asset_licenses_sql = mysqli_query($mysqli,"SELECT software_assets.asset_id, assets.asset_name 
                                                    FROM software_assets
                                                    LEFT JOIN assets
                                                        ON software_assets.asset_id = assets.asset_id
                                                    WHERE software_id = $row[software_id]");
            while($asset_row = mysqli_fetch_array($asset_licenses_sql)) {
                $assigned_to_assets .= $asset_row['asset_name'] . ", ";
            }

            // Contact Licenses
            $assigned_to_contacts = '';
            $contact_licenses_sql = mysqli_query($mysqli,"SELECT software_contacts.contact_id, contacts.contact_name
                                                      FROM software_contacts
                                                      LEFT JOIN contacts
                                                          ON software_contacts.contact_id = contacts.contact_id
                                                      WHERE software_id = $row[software_id]");
            while($contact_row = mysqli_fetch_array($contact_licenses_sql)) {
                $assigned_to_contacts .= $contact_row['contact_name'] . ", ";
            }

            $lineData = array($row['software_name'], $row['software_version'], $row['software_description'], $row['software_type'], $row['software_license_type'], $row['software_seats'], $row['software_key'], $assigned_to_assets, $assigned_to_contacts, $row['software_purchase'], $row['software_expire'], $row['software_notes']);
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

    //Logging
    logAction("Software", "Export", "$session_name exported $num_rows software(s) $software_name to a CSV file", $client_id);

    exit;

}
