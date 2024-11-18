<?php

/*
 * ITFlow - GET/POST request handler for client assets
 */

if (isset($_POST['add_asset'])) {

    enforceUserPermission('module_support', 2);

    validateCSRFToken($_POST['csrf_token']);

    require_once 'asset_model.php';

    $alert_extended = "";

    mysqli_query($mysqli,"INSERT INTO assets SET asset_name = '$name', asset_description = '$description', asset_type = '$type', asset_make = '$make', asset_model = '$model', asset_serial = '$serial', asset_os = '$os', asset_uri = '$uri', asset_uri_2 = '$uri_2', asset_location_id = $location, asset_vendor_id = $vendor, asset_contact_id = $contact, asset_status = '$status', asset_purchase_date = $purchase_date, asset_warranty_expire = $warranty_expire, asset_install_date = $install_date, asset_physical_location = '$physical_location', asset_notes = '$notes', asset_client_id = $client_id");

    $asset_id = mysqli_insert_id($mysqli);

    // Add Photo
    if ($new_file_name = checkFileUpload($_FILES['file'], array('jpg', 'jpeg', 'png'))) {

        $file_tmp_path = $_FILES['file']['tmp_name'];

        // directory in which the uploaded file will be moved
        if (!file_exists("uploads/clients/$client_id")) {
            mkdir("uploads/clients/$client_id");
        }
        $upload_file_dir = "uploads/clients/$client_id/";
        $dest_path = $upload_file_dir . $new_file_name;
        move_uploaded_file($file_tmp_path, $dest_path);

        mysqli_query($mysqli,"UPDATE assets SET asset_photo = '$new_file_name' WHERE asset_id = $asset_id");
    }

    // Add Primary Interface
    mysqli_query($mysqli,"INSERT INTO asset_interfaces SET interface_name = 'Primary', interface_mac = '$mac', interface_ip = '$ip', interface_nat_ip = '$nat_ip', interface_ipv6 = '$ipv6', interface_port = 'eth0', interface_primary = 1, interface_network_id = $network, interface_asset_id = $asset_id");


    if (!empty($_POST['username'])) {
        $username = trim(mysqli_real_escape_string($mysqli, encryptLoginEntry($_POST['username'])));
        $password = trim(mysqli_real_escape_string($mysqli, encryptLoginEntry($_POST['password'])));

        mysqli_query($mysqli,"INSERT INTO logins SET login_name = '$name', login_username = '$username', login_password = '$password', login_asset_id = $asset_id, login_client_id = $client_id");

        $login_id = mysqli_insert_id($mysqli);

        //Logging
        logAction("Credential", "Create", "$session_name created login credential for asset $asset_name", $client_id, $login_id);

        $alert_extended = " along with login credentials";

    }

    // Add to History
    mysqli_query($mysqli,"INSERT INTO asset_history SET asset_history_status = '$status', asset_history_description = '$session_name created $name', asset_history_asset_id = $asset_id");

    //Logging
    logAction("Asset", "Create", "$session_name created asset $name", $client_id, $asset_id);

    $_SESSION['alert_message'] = "Asset <strong>$name</strong> created $alert_extended";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_asset'])) {

    enforceUserPermission('module_support', 2);

    validateCSRFToken($_POST['csrf_token']);

    require_once 'asset_model.php';
    $asset_id = intval($_POST['asset_id']);

    // Get Existing Photo
    $sql = mysqli_query($mysqli,"SELECT asset_photo FROM assets WHERE asset_id = $asset_id");
    $row = mysqli_fetch_array($sql);
    $existing_file_name = sanitizeInput($row['asset_photo']);

    mysqli_query($mysqli,"UPDATE assets SET asset_name = '$name', asset_description = '$description', asset_type = '$type', asset_make = '$make', asset_model = '$model', asset_serial = '$serial', asset_os = '$os', asset_uri = '$uri', asset_uri_2 = '$uri_2', asset_location_id = $location, asset_vendor_id = $vendor, asset_contact_id = $contact, asset_status = '$status', asset_purchase_date = $purchase_date, asset_warranty_expire = $warranty_expire, asset_install_date = $install_date, asset_physical_location = '$physical_location', asset_notes = '$notes' WHERE asset_id = $asset_id");

    $sql_interfaces = mysqli_query($mysqli, "SELECT * FROM asset_interfaces WHERE interface_asset_id = $asset_id AND interface_primary = 1");

    if(mysqli_num_rows($sql_interfaces) == 0 ) {
        // Add Primary Interface
        mysqli_query($mysqli,"INSERT INTO asset_interfaces SET interface_name = 'Primary', interface_mac = '$mac', interface_ip = '$ip', interface_nat_ip = '$nat_ip', interface_ipv6 = '$ipv6', interface_port = 'eth0', interface_primary = 1, interface_network_id = $network, interface_asset_id = $asset_id");
    } else {
        // Update Primary Interface
        mysqli_query($mysqli,"UPDATE asset_interfaces SET interface_mac = '$mac', interface_ip = '$ip', interface_nat_ip = '$nat_ip', interface_ipv6 = '$ipv6', interface_network_id = $network WHERE interface_asset_id = $asset_id AND interface_primary = 1");
    }

    // Update Photo
    if ($new_file_name = checkFileUpload($_FILES['file'], array('jpg', 'jpeg', 'gif', 'png'))) {

        // Set directory in which the uploaded file will be moved
        $file_tmp_path = $_FILES['file']['tmp_name'];
        $upload_file_dir = "uploads/clients/$client_id/";
        $dest_path = $upload_file_dir . $new_file_name;

        move_uploaded_file($file_tmp_path, $dest_path);

        //Delete old file
        unlink("uploads/clients/$client_id/$existing_file_name");

        mysqli_query($mysqli,"UPDATE assets SET asset_photo = '$new_file_name' WHERE asset_id = $asset_id");
    }

    //Logging
    logAction("Asset", "Edit", "$session_name edited asset $name", $client_id, $asset_id);

    $_SESSION['alert_message'] = "Asset <strong>$name</strong> edited";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['change_client_asset'])) {

    enforceUserPermission('module_support', 2);

    validateCSRFToken($_POST['csrf_token']);

    $current_asset_id = intval($_POST['current_asset_id']);
    $new_client_id = intval($_POST['new_client_id']);

    // Get Asset details and current client ID/Name for logging
    $row = mysqli_fetch_array(mysqli_query($mysqli,"SELECT asset_name, asset_notes, asset_client_id, client_name FROM assets LEFT JOIN clients ON client_id = asset_client_id WHERE asset_id = $current_asset_id"));
    $asset_name = sanitizeInput($row['asset_name']);
    $asset_notes = sanitizeInput($row['asset_notes']);
    $current_client_id = intval($row['asset_client_id']);
    $current_client_name = sanitizeInput($row['client_name']);

    // Get new client name for logging
    $row = mysqli_fetch_array(mysqli_query($mysqli,"SELECT client_name FROM clients WHERE client_id = $new_client_id"));
    $new_client_name = sanitizeInput($row['client_name']);

    // Create new asset
    mysqli_query($mysqli, "
        INSERT INTO assets (asset_type, asset_name, asset_description, asset_make, asset_model, asset_serial, asset_os, asset_status, asset_purchase_date, asset_warranty_expire, asset_install_date, asset_notes, asset_important)
        SELECT asset_type, asset_name, asset_description, asset_make, asset_model, asset_serial, asset_os, asset_status, asset_purchase_date, asset_warranty_expire, asset_install_date, asset_notes, asset_important
        FROM assets
        WHERE asset_id = $current_asset_id
    ");
    $new_asset_id = mysqli_insert_id($mysqli);
    mysqli_query($mysqli, "UPDATE assets SET asset_client_id = $new_client_id WHERE asset_id = $new_asset_id");

    // Archive/log the current asset
    $notes = $asset_notes . "\r\n\r\n---\r\n* " . date('Y-m-d H:i:s') . ": Transferred asset $asset_name (old asset ID: $current_asset_id) from $current_client_name to $new_client_name (new asset ID: $new_asset_id)";
    mysqli_query($mysqli,"UPDATE assets SET asset_archived_at = NOW() WHERE asset_id = $current_asset_id");
    
    // Log Archive
    logAction("Asset", "Archive", "$session_name archived asset $asset_name (via transfer)", $current_client_id, $current_asset_id);

    // Log Transfer
    logAction("Asset", "Transfer", "$session_name Transferred asset $asset_name (old asset ID: $current_asset_id) from $current_client_name to $new_client_name (new asset ID: $new_asset_id)", $current_client_id, $current_asset_id);
    mysqli_query($mysqli, "UPDATE assets SET asset_notes = '$notes' WHERE asset_id = $current_asset_id");

    // Log the new asset
    $notes = $asset_notes . "\r\n\r\n---\r\n* " . date('Y-m-d H:i:s') . ": Transferred asset $asset_name (old asset ID: $current_asset_id) from $current_client_name to $new_client_name (new asset ID: $new_asset_id)";
    logAction("Asset", "Create", "$session_name created asset $name (via transfer)", $new_client_id, $new_asset_id);

    logAction("Asset", "Transfer", "$session_name Transferred asset $asset_name (old asset ID: $current_asset_id) from $current_client_name to $new_client_name (new asset ID: $new_asset_id)", $new_client_id, $new_asset_id);

    mysqli_query($mysqli, "UPDATE assets SET asset_notes = '$notes' WHERE asset_id = $new_asset_id");

    $_SESSION['alert_message'] = "Asset <strong>$name</strong> transferred";

    header("Location: client_assets.php?client_id=$new_client_id&asset_id=$new_asset_id");

}

if (isset($_GET['archive_asset'])) {

    enforceUserPermission('module_support', 2);

    validateCSRFToken($_GET['csrf_token']);

    $asset_id = intval($_GET['archive_asset']);

    // Get Asset Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT asset_name, asset_client_id FROM assets WHERE asset_id = $asset_id");
    $row = mysqli_fetch_array($sql);
    $asset_name = sanitizeInput($row['asset_name']);
    $client_id = intval($row['asset_client_id']);

    mysqli_query($mysqli,"UPDATE assets SET asset_archived_at = NOW() WHERE asset_id = $asset_id");

    //logging
    logAction("Asset", "Archive", "$session_name archived asset $asset_name", $client_id, $asset_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Asset <strong>$asset_name</strong> archived";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['unarchive_asset'])) {

    enforceUserPermission('module_support', 2);

    validateCSRFToken($_GET['csrf_token']);

    $asset_id = intval($_GET['unarchive_asset']);

    // Get Asset Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT asset_name, asset_client_id FROM assets WHERE asset_id = $asset_id");
    $row = mysqli_fetch_array($sql);
    $asset_name = sanitizeInput($row['asset_name']);
    $client_id = intval($row['asset_client_id']);

    mysqli_query($mysqli,"UPDATE assets SET asset_archived_at = NULL WHERE asset_id = $asset_id");

    // Logging
    logAction("Asset", "Unarchive", "$session_name unarchived asset $asset_name", $client_id, $asset_id);

    $_SESSION['alert_message'] = "Asset <strong>$asset_name</strong> Unarchived";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['delete_asset'])) {

    enforceUserPermission('module_support', 3);

    validateCSRFToken($_GET['csrf_token']);

    $asset_id = intval($_GET['delete_asset']);

    // Get Asset Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT asset_name, asset_client_id FROM assets WHERE asset_id = $asset_id");
    $row = mysqli_fetch_array($sql);
    $asset_name = sanitizeInput($row['asset_name']);
    $client_id = intval($row['asset_client_id']);

    mysqli_query($mysqli,"DELETE FROM assets WHERE asset_id = $asset_id");

    // Delete Interfaces
    mysqli_query($mysqli,"DELETE FROM asset_interfaces WHERE interface_asset_id = $asset_id");

    // Delete History
    mysqli_query($mysqli,"DELETE FROM asset_history WHERE asset_history_asset_id = $asset_id");

    // Logging
    logAction("Asset", "Delete", "$session_name deleted asset $asset_name", $client_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Asset <strong>$asset_name</strong> deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['bulk_assign_asset_location'])) {

    enforceUserPermission('module_support', 2);

    validateCSRFToken($_POST['csrf_token']);

    $location_id = intval($_POST['bulk_location_id']);

    // Get Location name and client id for logging and alert
    $sql = mysqli_query($mysqli,"SELECT location_name, location_client_id FROM locations WHERE location_id = $location_id");
    $row = mysqli_fetch_array($sql);
    $location_name = sanitizeInput($row['location_name']);
    $client_id = intval($row['location_client_id']);
    
    // Assign Location to Selected Contacts
    if (isset($_POST['asset_ids'])) {

        // Get Selected Contacts Count
        $asset_count = count($_POST['asset_ids']);

        foreach($_POST['asset_ids'] as $asset_id) {
            $asset_id = intval($asset_id);

            // Get Asset Details for Logging
            $sql = mysqli_query($mysqli,"SELECT asset_name FROM assets WHERE asset_id = $asset_id");
            $row = mysqli_fetch_array($sql);
            $asset_name = sanitizeInput($row['asset_name']);

            mysqli_query($mysqli,"UPDATE assets SET asset_location_id = $location_id WHERE asset_id = $asset_id");

            //Logging
            logAction("Asset", "Edit", "$session_name assigned asset $asset_name to location $location_name", $client_id, $asset_id);

        } // End Assign Location Loop
        
        // Bulk Logging
        logAction("Asset", "Bulk Edit", "$session_name assigned $asset_count assets to location $location_name", $client_id);
        
        $_SESSION['alert_message'] = "You assigned <strong>$asset_count</strong> assets to location <strong>$location_name</strong>";
    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['bulk_assign_asset_contact'])) {

    enforceUserPermission('module_support', 2);

    validateCSRFToken($_POST['csrf_token']);

    $contact_id = intval($_POST['bulk_contact_id']);

    // Get Contact name and client id for logging and Notification
    $sql = mysqli_query($mysqli,"SELECT contact_name, contact_client_id FROM contacts WHERE contact_id = $contact_id");
    $row = mysqli_fetch_array($sql);
    $contact_name = sanitizeInput($row['contact_name']);
    $client_id = intval($row['contact_client_id']);
    
    // Assign Contact to Selected Assets
    if (isset($_POST['asset_ids'])) {

        // Get Selected Contacts Count
        $asset_count = count($_POST['asset_ids']);

        foreach($_POST['asset_ids'] as $asset_id) {
            $asset_id = intval($asset_id);

            // Get Asset Details for Logging
            $sql = mysqli_query($mysqli,"SELECT asset_name FROM assets WHERE asset_id = $asset_id");
            $row = mysqli_fetch_array($sql);
            $asset_name = sanitizeInput($row['asset_name']);

            mysqli_query($mysqli,"UPDATE assets SET asset_contact_id = $contact_id WHERE asset_id = $asset_id");

            // Logging
            logAction("Asset", "Edit", "$session_name assigned asset $asset_name to contact $contact_name", $client_id, $asset_id);

        } // End Assign Contact Loop

        // Bulk Logging
        logAction("Asset", "Bulk Edit", "$session_name assigned $asset_count assets to contact $contact_name", $client_id);
        
        $_SESSION['alert_message'] = "You assigned <strong>$asset_count</strong> assets to contact <strong>$contact_name</strong>";
    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['bulk_edit_asset_status'])) {

    enforceUserPermission('module_support', 2);

    validateCSRFToken($_POST['csrf_token']);

    $status = sanitizeInput($_POST['bulk_status']);
    
    // Assign Status to Selected Assets
    if (isset($_POST['asset_ids'])) {

        // Get Count
        $asset_count = count($_POST['asset_ids']);

        foreach($_POST['asset_ids'] as $asset_id) {
            $asset_id = intval($asset_id);

            // Get Asset Details for Logging
            $sql = mysqli_query($mysqli,"SELECT asset_name, asset_client_id FROM assets WHERE asset_id = $asset_id");
            $row = mysqli_fetch_array($sql);
            $asset_name = sanitizeInput($row['asset_name']);
            $client_id = intval($row['asset_client_id']);

            mysqli_query($mysqli,"UPDATE assets SET asset_status = '$status' WHERE asset_id = $asset_id");

            //Logging
            logAction("Asset", "Edit", "$session_name set status to $status on $asset_name", $client_id, $asset_id);

        } // End Assign Status Loop

        // Bulk Logging
        logAction("Asset", "Bulk Edit", "$session_name set status to $status on $asset_count assets", $client_id);
        
        $_SESSION['alert_message'] = "You set the status <strong>$status</strong> on <strong>$asset_count</strong> assets.";
    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['bulk_archive_assets'])) {

    enforceUserPermission('module_support', 2);

    validateCSRFToken($_POST['csrf_token']);

    if (isset($_POST['asset_ids'])) {

        // Get Count
        $count = count($_POST['asset_ids']);

        foreach ($_POST['asset_ids'] as $asset_id) {

            $asset_id = intval($asset_id);

            // Get Asset Name and Client ID for logging and alert message
            $sql = mysqli_query($mysqli,"SELECT asset_name, asset_client_id FROM assets WHERE asset_id = $asset_id");
            $row = mysqli_fetch_array($sql);
            $asset_name = sanitizeInput($row['asset_name']);
            $client_id = intval($row['asset_client_id']);

            mysqli_query($mysqli,"UPDATE assets SET asset_archived_at = NOW() WHERE asset_id = $asset_id");

            // Individual Asset logging
            logAction("Asset", "Archive", "$session_name archived asset $asset_name", $client_id, $asset_id);

        }

        // Bulk Logging
        logAction("Asset", "Bulk Archive", "$session_name archived $count assets", $client_id);

        $_SESSION['alert_type'] = "error";
        $_SESSION['alert_message'] = "Archived $count asset(s)";

    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['bulk_unarchive_assets'])) {

    enforceUserPermission('module_support', 2);

    validateCSRFToken($_POST['csrf_token']);

    if (isset($_POST['asset_ids'])) {

        // Get Count
        $count = count($_POST['asset_ids']);

        foreach ($_POST['asset_ids'] as $asset_id) {

            $asset_id = intval($asset_id);

            // Get Asset Name and Client ID for logging and alert message
            $sql = mysqli_query($mysqli,"SELECT asset_name, asset_client_id FROM assets WHERE asset_id = $asset_id");
            $row = mysqli_fetch_array($sql);
            $asset_name = sanitizeInput($row['asset_name']);
            $client_id = intval($row['asset_client_id']);

            mysqli_query($mysqli,"UPDATE assets SET asset_archived_at = NULL WHERE asset_id = $asset_id");

            // Individual Asset logging
            logAction("Asset", "Unarchive", "$session_name unarchived asset $asset_name", $client_id, $asset_id);

        }

        // Bulk Logging
        logAction("Asset", "Bulk Unarchive", "$session_name unarchived $count assets", $client_id);

        $_SESSION['alert_message'] = "Unarchived $count asset(s)";

    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST["import_client_assets_csv"])) {

    enforceUserPermission('module_support', 2);

    validateCSRFToken($_POST['csrf_token']);

    $client_id = intval($_POST['client_id']);
    $file_name = $_FILES["file"]["tmp_name"];

    $error = false;

    if (!empty($_FILES["file"]["tmp_name"])) {
        $file_name = $_FILES["file"]["tmp_name"];
    } else {
        $_SESSION['alert_message'] = "Please select a file to upload.";
        $_SESSION['alert_type'] = "error";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
        exit();
    }

    //Check file is CSV
    $file_extension = strtolower(end(explode('.',$_FILES['file']['name'])));
    $allowed_file_extensions = array('csv');
    if (in_array($file_extension,$allowed_file_extensions) === false) {
        $error = true;
        $_SESSION['alert_message'] = "Bad file extension";
    }

    //Check file isn't empty
    elseif ($_FILES["file"]["size"] < 1) {
        $error = true;
        $_SESSION['alert_message'] = "Bad file size (empty?)";
    }

    //(Else)Check column count (name, desc, type, make, model, serial, os, assigned to, location)
    $f = fopen($file_name, "r");
    $f_columns = fgetcsv($f, 1000, ",");
    if (!$error & count($f_columns) != 9) {
        $error = true;
        $_SESSION['alert_message'] = "Bad column count.";
    }

    //Else, parse the file
    if (!$error) {
        $file = fopen($file_name, "r");
        fgetcsv($file, 1000, ","); // Skip first line
        $row_count = 0;
        $duplicate_count = 0;
        while(($column = fgetcsv($file, 1000, ",")) !== false) {

            // Default variables (if undefined)
            $description = $type = $make = $model = $serial = $os = '';
            $contact_id = $location_id = 0;

            $duplicate_detect = 0;
            if (isset($column[0])) {
                $name = sanitizeInput($column[0]);
                if (mysqli_num_rows(mysqli_query($mysqli,"SELECT * FROM assets WHERE asset_name = '$name' AND asset_client_id = $client_id")) > 0) {
                    $duplicate_detect = 1;
                }
            }
            if (!empty($column[1])) {
                $description = sanitizeInput($column[1]);
            }
            if (!empty($column[2])) {
                $type = sanitizeInput($column[2]);
            }
            if (!empty($column[3])) {
                $make = sanitizeInput($column[3]);
            }
            if (!empty($column[4])) {
                $model = sanitizeInput($column[4]);
            }
            if (!empty($column[5])) {
                $serial = sanitizeInput($column[5]);
            }
            if (!empty($column[6])) {
                $os = sanitizeInput($column[6]);
            }
            if (!empty($column[7])) {
                $contact = sanitizeInput($column[7]);
                if ($contact) {
                    $sql_contact = mysqli_query($mysqli,"SELECT * FROM contacts WHERE contact_name = '$contact' AND contact_client_id = $client_id");
                    $row = mysqli_fetch_assoc($sql_contact);
                    $contact_id = intval($row['contact_id']);
                }
            }
            if (!empty($column[8])) {
                $location = sanitizeInput($column[8]);
                if ($location) {
                    $sql_location = mysqli_query($mysqli,"SELECT * FROM locations WHERE location_name = '$location' AND location_client_id = $client_id");
                    $row = mysqli_fetch_assoc($sql_location);
                    $location_id = intval($row['location_id']);
                }
            }

            // Check if duplicate was detected
            if ($duplicate_detect == 0) {
                //Add
                mysqli_query($mysqli,"INSERT INTO assets SET asset_name = '$name', asset_description = '$description', asset_type = '$type', asset_make = '$make', asset_model = '$model', asset_serial = '$serial', asset_os = '$os', asset_contact_id = $contact_id, asset_location_id = $location_id, asset_client_id = $client_id");

                $asset_id = mysqli_insert_id($mysqli);
                
                // Add Primary Interface
                mysqli_query($mysqli,"INSERT INTO asset_interfaces SET interface_name = 'Primary', interface_port = 'eth0', interface_primary = 1, interface_asset_id = $asset_id");

                $row_count = $row_count + 1;
            } else {
                $duplicate_count = $duplicate_count + 1;
            }
        }
        fclose($file);

        // Logging
        logAction("Asset", "Import", "$session_name imported $row_count asset(s) via CSV file", $client_id);

        $_SESSION['alert_message'] = "$row_count Asset(s) added, $duplicate_count duplicate(s) detected";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    }
    //Check for any errors, if there are notify user and redirect
    if ($error) {
        $_SESSION['alert_type'] = "warning";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    }
}

if (isset($_GET['download_client_assets_csv_template'])) {
    $client_id = intval($_GET['download_client_assets_csv_template']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT client_name FROM clients WHERE client_id = $client_id");
    $row = mysqli_fetch_array($sql);

    $client_name = $row['client_name'];

    $delimiter = ",";
    $filename = strtoAZaz09($client_name) . "-Assets-Template.csv";

    //create a file pointer
    $f = fopen('php://memory', 'w');

    //set column headers
    $fields = array('Name', 'Description', 'Type', 'Make', 'Model', 'Serial', 'OS', 'Assigned To', 'Location');
    fputcsv($f, $fields, $delimiter);

    //move back to beginning of file
    fseek($f, 0);

    //set headers to download file rather than displayed
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '";');

    //output all remaining data on a file pointer
    fpassthru($f);
    exit;

}

if (isset($_POST['export_client_assets_csv'])) {

    enforceUserPermission('module_support');

    validateCSRFToken($_POST['csrf_token']);

    $client_id = intval($_POST['client_id']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT * FROM assets LEFT JOIN contacts ON asset_contact_id = contact_id LEFT JOIN locations ON asset_location_id = location_id LEFT JOIN asset_interfaces ON interface_asset_id = asset_id AND interface_primary = 1 LEFT JOIN clients ON asset_client_id = client_id WHERE asset_client_id = $client_id AND asset_archived_at IS NULL ORDER BY asset_name ASC");
    $row = mysqli_fetch_array($sql);

    $client_name = $row['client_name'];

    $num_rows = mysqli_num_rows($sql);

    if ($num_rows > 0) {
        $delimiter = ",";
        $filename = strtoAZaz09($client_name) . "-Assets-" . date('Y-m-d') . ".csv";

        //create a file pointer
        $f = fopen('php://memory', 'w');

        //set column headers
        $fields = array('Name', 'Description', 'Type', 'Make', 'Model', 'Serial Number', 'Operating System', 'Purchase Date', 'Warranty Expire', 'Install Date', 'Assigned To', 'Location', 'Notes');
        fputcsv($f, $fields, $delimiter);

        //output each row of the data, format line as csv and write to file pointer
        while($row = mysqli_fetch_array($sql)) {
            $lineData = array($row['asset_name'], $row['asset_description'], $row['asset_type'], $row['asset_make'], $row['asset_model'], $row['asset_serial'], $row['asset_os'], $row['asset_purchase_date'], $row['asset_warranty_expire'], $row['asset_install_date'], $row['contact_name'], $row['location_name'], $row['asset_notes']);
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

    // Logging
    logAction("Asset", "Export", "$session_name exported $num_rows asset(s) to a CSV file", $client_id);

    exit;

}

if (isset($_POST['add_asset_interface'])) {

    enforceUserPermission('module_support', 2);

    validateCSRFToken($_POST['csrf_token']);

    // Interface info
    $interface_id = intval($_POST['interface_id']);
    $asset_id = intval($_POST['asset_id']);
    require_once 'asset_interface_model.php';

    // Get Asset Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT asset_name, asset_client_id FROM assets WHERE asset_id = $asset_id");
    $row = mysqli_fetch_array($sql);
    $asset_name = sanitizeInput($row['asset_name']);
    $client_id = intval($row['asset_client_id']);

    mysqli_query($mysqli,"INSERT INTO asset_interfaces SET interface_name = '$name', interface_mac = '$mac', interface_ip = '$ip', interface_ipv6 = '$ipv6', interface_port = '$port', interface_notes = '$notes', interface_network_id = $network, interface_asset_id = $asset_id");

    $interface_id = mysqli_insert_id($mysqli);

    //Logging
    logAction("Asset Interface", "Create", "$session_name created interface $name for asset $asset_name", $client_id, $asset_id);

    $_SESSION['alert_message'] = "Interface <strong>$name</strong> created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_asset_interface'])) {

    enforceUserPermission('module_support', 2);

    validateCSRFToken($_POST['csrf_token']);

    // Interface info
    $interface_id = intval($_POST['interface_id']);
    require_once 'asset_interface_model.php';

    // Get Asset Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT asset_name, asset_client_id, asset_id FROM asset_interfaces LEFT JOIN assets ON asset_id = interface_asset_id WHERE interface_id = $interface_id");
    $row = mysqli_fetch_array($sql);
    $asset_id = intval($row['asset_id']);
    $asset_name = sanitizeInput($row['asset_name']);
    $client_id = intval($row['asset_client_id']);

    mysqli_query($mysqli,"UPDATE asset_interfaces SET interface_name = '$name', interface_mac = '$mac', interface_ip = '$ip', interface_ipv6 = '$ipv6', interface_port = '$port', interface_notes = '$notes', interface_network_id = $network WHERE interface_id = $interface_id");

    //Logging
    logAction("Asset Interface", "Edit", "$session_name edited interface $name for asset $asset_name", $client_id, $asset_id);

    $_SESSION['alert_message'] = "Interface <strong>$name</strong> edited";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['delete_asset_interface'])) {

    enforceUserPermission('module_support', 2);

    validateCSRFToken($_GET['csrf_token']);

    $interface_id = intval($_GET['delete_asset_interface']);

    $sql = mysqli_query($mysqli,"SELECT asset_name, interface_name, asset_client_id, asset_id FROM asset_interfaces LEFT JOIN assets ON asset_id = interface_asset_id WHERE interface_id = $interface_id");
    $row = mysqli_fetch_array($sql);
    $asset_id = intval($row['asset_id']);
    $interface_name = sanitizeInput($row['interface_name']);
    $asset_name = sanitizeInput($row['asset_name']);
    $client_id = intval($row['asset_client_id']);

    mysqli_query($mysqli,"DELETE FROM asset_interfaces WHERE interface_id = $interface_id");

    //Logging
    logAction("Asset Interface", "Delete", "$session_name deleted interface $interface_name from asset $asset_name", $client_id, $asset_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Interface <strong>$interface_name</strong> deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}
