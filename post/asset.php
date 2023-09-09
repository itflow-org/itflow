<?php

/*
 * ITFlow - GET/POST request handler for client assets
 */

if (isset($_POST['add_asset'])) {

    validateTechRole();

    $client_id = intval($_POST['client_id']);
    $name = sanitizeInput($_POST['name']);
    $description = sanitizeInput($_POST['description']);
    $type = sanitizeInput($_POST['type']);
    $make = sanitizeInput($_POST['make']);
    $model = sanitizeInput($_POST['model']);
    $serial = sanitizeInput($_POST['serial']);
    $os = sanitizeInput($_POST['os']);
    $ip = sanitizeInput($_POST['ip']);
    $mac = sanitizeInput($_POST['mac']);
    $status = sanitizeInput($_POST['status']);
    $location = intval($_POST['location']);
    $vendor = intval($_POST['vendor']);
    $contact = intval($_POST['contact']);
    $network = intval($_POST['network']);
    $purchase_date = sanitizeInput($_POST['purchase_date']);
    if (empty($purchase_date)) {
        $purchase_date = "NULL";
    } else {
        $purchase_date = "'" . $purchase_date . "'";
    }
    $warranty_expire = sanitizeInput($_POST['warranty_expire']);
    if (empty($warranty_expire)) {
        $warranty_expire = "NULL";
    } else {
        $warranty_expire = "'" . $warranty_expire . "'";
    }
    $install_date = sanitizeInput($_POST['install_date']);
    if (empty($install_date)) {
        $install_date = "NULL";
    } else {
        $install_date = "'" . $install_date . "'";
    }
    $notes = sanitizeInput($_POST['notes']);

    $alert_extended = "";

    mysqli_query($mysqli,"INSERT INTO assets SET asset_name = '$name', asset_description = '$description', asset_type = '$type', asset_make = '$make', asset_model = '$model', asset_serial = '$serial', asset_os = '$os', asset_ip = '$ip', asset_mac = '$mac', asset_location_id = $location, asset_vendor_id = $vendor, asset_contact_id = $contact, asset_status = '$status', asset_purchase_date = $purchase_date, asset_warranty_expire = $warranty_expire, asset_install_date = $install_date, asset_notes = '$notes', asset_network_id = $network, asset_client_id = $client_id");

    $asset_id = mysqli_insert_id($mysqli);

    if (!empty($_POST['username'])) {
        $username = trim(mysqli_real_escape_string($mysqli, encryptLoginEntry($_POST['username'])));
        $password = trim(mysqli_real_escape_string($mysqli, encryptLoginEntry($_POST['password'])));

        mysqli_query($mysqli,"INSERT INTO logins SET login_name = '$name', login_username = '$username', login_password = '$password', login_asset_id = $asset_id, login_client_id = $client_id");

        $login_id = mysqli_insert_id($mysqli);

        //Logging
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Login', log_action = 'Create', log_description = '$session_name created login credentials for asset $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $login_id");

        $alert_extended = " along with login credentials";

    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Asset', log_action = 'Create', log_description = '$session_name created asset $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $asset_id");

    $_SESSION['alert_message'] = "Asset <strong>$name</strong> created $alert_extended";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_asset'])) {

    validateTechRole();

    $asset_id = intval($_POST['asset_id']);
    $login_id = intval($_POST['login_id']);
    $client_id = intval($_POST['client_id']);
    $name = sanitizeInput($_POST['name']);
    $description = sanitizeInput($_POST['description']);
    $type = sanitizeInput($_POST['type']);
    $make = sanitizeInput($_POST['make']);
    $model = sanitizeInput($_POST['model']);
    $serial = sanitizeInput($_POST['serial']);
    $os = sanitizeInput($_POST['os']);
    $ip = sanitizeInput($_POST['ip']);
    $mac = sanitizeInput($_POST['mac']);
    $status = sanitizeInput($_POST['status']);
    $location = intval($_POST['location']);
    $vendor = intval($_POST['vendor']);
    $contact = intval($_POST['contact']);
    $network = intval($_POST['network']);
    $purchase_date = sanitizeInput($_POST['purchase_date']);
    if (empty($purchase_date)) {
        $purchase_date = "NULL";
    } else {
        $purchase_date = "'" . $purchase_date . "'";
    }
    $warranty_expire = sanitizeInput($_POST['warranty_expire']);
    if (empty($warranty_expire)) {
        $warranty_expire = "NULL";
    } else {
        $warranty_expire = "'" . $warranty_expire . "'";
    }
    $install_date = sanitizeInput($_POST['install_date']);
    if (empty($install_date)) {
        $install_date = "NULL";
    } else {
        $install_date = "'" . $install_date . "'";
    }
    $notes = sanitizeInput($_POST['notes']);
    $username = trim(mysqli_real_escape_string($mysqli, encryptLoginEntry($_POST['username'])));
    $password = trim(mysqli_real_escape_string($mysqli, encryptLoginEntry($_POST['password'])));

    $alert_extended = "";

    mysqli_query($mysqli,"UPDATE assets SET asset_name = '$name', asset_description = '$description', asset_type = '$type', asset_make = '$make', asset_model = '$model', asset_serial = '$serial', asset_os = '$os', asset_ip = '$ip', asset_mac = '$mac', asset_location_id = $location, asset_vendor_id = $vendor, asset_contact_id = $contact, asset_status = '$status', asset_purchase_date = $purchase_date, asset_warranty_expire = $warranty_expire, asset_install_date = $install_date, asset_notes = '$notes', asset_network_id = $network WHERE asset_id = $asset_id");

    //If login exists then update the login
    if ($login_id > 0 && !empty($_POST['username'])) {
        mysqli_query($mysqli,"UPDATE logins SET login_name = '$name', login_username = '$username', login_password = '$password' WHERE login_id = $login_id");

        //Logging
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Login', log_action = 'Modify', log_description = '$session_name updated login credentials for asset $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $login_id");

        $alert_extended = " along with updating login credentials";
    }else{
        //If Username is filled in then add a login
        if (!empty($_POST['username'])) {

            mysqli_query($mysqli,"INSERT INTO logins SET login_name = '$name', login_username = '$username', login_password = '$password', login_asset_id = $asset_id, login_client_id = $client_id");

            $login_id = mysqli_insert_id($mysqli);

            //Logging
            mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Login', log_action = 'Create', log_description = '$session_name created login credentials for asset $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $login_id");

            $alert_extended = " along with creating login credentials";

        } else {
            mysqli_query($mysqli,"DELETE FROM logins WHERE login_id = $login_id");

            //Logging
            mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Login', log_action = 'Delete', log_description = '$session_name deleted login credential for asset $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $login_id");

            $alert_extended = " along with deleting login credentials";
        }

    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Asset', log_action = 'Modify', log_description = '$session_name modified asset $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $asset_id");

    $_SESSION['alert_message'] = "Asset <strong>$name</strong> updated $alert_extended";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['archive_asset'])) {

    validateTechRole();

    $asset_id = intval($_GET['archive_asset']);

    // Get Asset Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT asset_name, asset_client_id FROM assets WHERE asset_id = $asset_id");
    $row = mysqli_fetch_array($sql);
    $asset_name = sanitizeInput($row['asset_name']);
    $client_id = intval($row['asset_client_id']);

    mysqli_query($mysqli,"UPDATE assets SET asset_archived_at = NOW() WHERE asset_id = $asset_id");

    //logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Asset', log_action = 'Archive', log_description = '$session_name archived asset $asset_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $asset_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Asset <strong>$asset_name</strong> archived";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['delete_asset'])) {

    validateAdminRole();

    $asset_id = intval($_GET['delete_asset']);

    // Get Asset Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT asset_name, asset_client_id FROM assets WHERE asset_id = $asset_id");
    $row = mysqli_fetch_array($sql);
    $asset_name = sanitizeInput($row['asset_name']);
    $client_id = intval($row['asset_client_id']);

    mysqli_query($mysqli,"DELETE FROM assets WHERE asset_id = $asset_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Asset', log_action = 'Delete', log_description = '$session_name deleted asset $asset_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $asset_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Asset <strong>$asset_name</strong> deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST["import_client_assets_csv"])) {

    validateTechRole();

    $client_id = intval($_POST['client_id']);
    $file_name = $_FILES["file"]["tmp_name"];
    $error = false;

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
                $row_count = $row_count + 1;
            } else {
                $duplicate_count = $duplicate_count + 1;
            }
        }
        fclose($file);

        //Logging
        mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Asset', log_action = 'Import', log_description = '$session_name imported $row_count asset(s) via CSV file', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id");

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

    validateTechRole();

    $client_id = intval($_POST['client_id']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT * FROM assets LEFT JOIN contacts ON asset_contact_id = contact_id LEFT JOIN locations ON asset_location_id = location_id LEFT JOIN clients ON asset_client_id = client_id WHERE asset_client_id = $client_id AND asset_archived_at IS NULL ORDER BY asset_name ASC");
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
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Asset', log_action = 'Export', log_description = '$session_name exported $num_rows asset(s) to a CSV file', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id");

    exit;

}
