<?php

/*
 * ITFlow - GET/POST request handler for vendor contacts
 */

if (isset($_POST['add_vendor_contact'])) {

    enforceUserPermission('module_client', 2);

    require_once 'post/user/vendor_contact_model.php';

    mysqli_query($mysqli,"INSERT INTO vendor_contacts SET vendor_contact_name = '$name', vendor_contact_title = '$title', vendor_contact_phone = '$phone', vendor_contact_extension = '$extension', vendor_contact_mobile = '$mobile', vendor_contact_email = '$email', vendor_contact_notes = '$notes', vendor_contact_department = '$department', vendor_contact_vendor_id = $vendor_id");

    $vendor_contact_id = mysqli_insert_id($mysqli);

    // Logging
    logAction("Vendor Contact", "Create", "$session_name created vendor contact $name", $client_id, $vendor_contact_id);

    customAction('vendor_contact_create', $vendor_contact_id);

    $_SESSION['alert_message'] = "Vendor Contact <strong>$name</strong> created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_vendor_contact'])) {

    enforceUserPermission('module_client', 2);

    require_once 'post/user/vendor_contact_model.php';

    $vendor_contact_id = intval($_POST['vendor_contact_id']);

    mysqli_query($mysqli,"UPDATE vendor_contacts SET vendor_contact_name = '$name', vendor_contact_title = '$title', vendor_contact_phone = '$phone', vendor_contact_extension = '$extension', vendor_contact_mobile = '$mobile', vendor_contact_email = '$email', contact_pin = '$pin', vendor_contact_notes = '$notes', vendor_contact_department = '$department' WHERE vendor_contact_id = $vendor_contact_id");

    //Logging
    logAction("Vendor Contact", "Edit", "$session_name edited vendor contact $name", $client_id, $vendor_contact_id);

    customAction('vendor_contact_update', $vendor_contact_id);

    $_SESSION['alert_message'] = "Vendor Contact <strong>$name</strong> updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['bulk_archive_vendor_contacts'])) {

    enforceUserPermission('module_client', 2);

    //validateCSRFToken($_POST['csrf_token']);

    if (isset($_POST['vendor_contact_ids'])) {

        $count = 0; // Default 0

        // Cycle through array and archive each contact
        foreach ($_POST['vendor_contact_ids'] as $vendor_contact_id) {

            $vendor_contact_id = intval($vendor_contact_id);

            // Get Contact Name and Client ID for logging and alert message
            $sql = mysqli_query($mysqli,"SELECT vendor_contact_name, vendor_contact_client_id FROM vendor_contacts WHERE vendor_contact_id = $vendor_contact_id");
            $row = mysqli_fetch_array($sql);
            $vendor_contact_name = sanitizeInput($row['vendor_contact_name']);
            $client_id = intval($row['contact_client_id']);

        }

        // Bulk Logging
        logAction("Vendor Contact", "Bulk Archive", "$session_name archived $count vendor contacts", $client_id);

        $_SESSION['alert_type'] = "error";
        $_SESSION['alert_message'] = "Archived <strong>$count</strong> vendor contact(s)";

    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['bulk_unarchive_vendor_contacts'])) {

    enforceUserPermission('module_client', 2);
    //validateCSRFToken($_POST['csrf_token']);

    if (isset($_POST['contact_ids'])) {

        // Get Selected Contacts Count
        $count = count($_POST['contact_ids']);

        // Cycle through array and unarchive each contact
        foreach ($_POST['contact_ids'] as $contact_id) {

            $contact_id = intval($contact_id);

            // Get Contact Name and Client ID for logging and alert message
            $sql = mysqli_query($mysqli,"SELECT contact_name, contact_client_id, contact_user_id FROM contacts WHERE contact_id = $contact_id");
            $row = mysqli_fetch_array($sql);
            $contact_name = sanitizeInput($row['contact_name']);
            $client_id = intval($row['contact_client_id']);
            $contact_user_id = intval($row['contact_user_id']);

            // unArchive Contact User
            if ($contact_user_id > 0) {
                mysqli_query($mysqli,"UPDATE users SET user_archived_at = NULL WHERE user_id = $contact_user_id");
            }

            mysqli_query($mysqli,"UPDATE contacts SET contact_archived_at = NULL WHERE contact_id = $contact_id");

            // Individual Contact logging
            logAction("Contact", "Unarchive", "$session_name unarchived $contact_name", $client_id, $contact_id);

        }

        // Bulk Logging
        logAction("Contact", "Bulk Unarchive", "$session_name Unarchived $count contacts", $client_id);

        $_SESSION['alert_message'] = "You unarchived <strong>$count</strong> contact(s)";

    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['bulk_delete_vendor_contacts'])) {

    enforceUserPermission('module_client', 3);
    validateCSRFToken($_POST['csrf_token']);

    if (isset($_POST['contact_ids'])) {

        // Get Selected Contacts Count
        $count = count($_POST['contact_ids']);

        // Cycle through array and delete each record
        foreach ($_POST['contact_ids'] as $contact_id) {

            $contact_id = intval($contact_id);

            // Get Name and Client ID for logging and alert message
            $sql = mysqli_query($mysqli,"SELECT contact_name, contact_client_id, contact_user_id FROM contacts WHERE contact_id = $contact_id");
            $row = mysqli_fetch_array($sql);
            $contact_name = sanitizeInput($row['contact_name']);
            $client_id = intval($row['contact_client_id']);
            $contact_user_id = intval($row['contact_user_id']);

            // Delete Contact User
            if ($contact_user_id > 0) {
                mysqli_query($mysqli,"DELETE FROM users WHERE user_id = $contact_user_id");
            }

            mysqli_query($mysqli, "DELETE FROM contacts WHERE contact_id = $contact_id AND contact_client_id = $client_id");

            // Remove Relations
            mysqli_query($mysqli, "DELETE FROM contact_tags WHERE contact_id = $contact_id");
            mysqli_query($mysqli, "DELETE FROM contact_assets WHERE contact_id = $contact_id");
            mysqli_query($mysqli, "DELETE FROM contact_documents WHERE contact_id = $contact_id");
            mysqli_query($mysqli, "DELETE FROM contact_files WHERE contact_id = $contact_id");
            mysqli_query($mysqli, "DELETE FROM contact_logins WHERE contact_id = $contact_id");
            mysqli_query($mysqli, "DELETE FROM contact_notes WHERE contact_note_contact_id = $contact_id");

            // Individual Logging
            logAction("Contact", "Delete", "$session_name deleted $contact_name", $client_id);

        }

        // Bulk Logging
         logAction("Contact", "Bulk Delete", "$session_name deleted $count contacts", $client_id);

        $_SESSION['alert_message'] = "You deleted <strong>$count</strong> contact(s)";

    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}


if (isset($_GET['archive_vendor_contact'])) {

    enforceUserPermission('module_client', 2);

    $contact_id = intval($_GET['archive_contact']);

    // Get Contact Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT contact_name, contact_client_id, contact_user_id FROM contacts WHERE contact_id = $contact_id");
    $row = mysqli_fetch_array($sql);
    $contact_name = sanitizeInput($row['contact_name']);
    $client_id = intval($row['contact_client_id']);
    $contact_user_id = intval($row['contact_user_id']);

    // Archive Contact User
    if ($contact_user_id > 0) {
        mysqli_query($mysqli,"UPDATE users SET user_archived_at = NOW() WHERE user_id = $contact_user_id");
    }

    mysqli_query($mysqli,"UPDATE contacts SET contact_important = 0, contact_billing = 0, contact_technical = 0, contact_archived_at = NOW() WHERE contact_id = $contact_id");
    
    // Logging
    logAction("Contact", "Archive", "$session_name archived contact $contact_name", $client_id, $contact_id);


    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Contact <strong>$contact_name</strong> has been archived";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['unarchive_vendor_contact'])) {

    validateAdminRole();

    $contact_id = intval($_GET['unarchive_contact']);

    // Get Contact Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT contact_name, contact_client_id, contact_user_id FROM contacts WHERE contact_id = $contact_id");
    $row = mysqli_fetch_array($sql);
    $contact_name = sanitizeInput($row['contact_name']);
    $client_id = intval($row['contact_client_id']);
    $contact_user_id = intval($row['contact_user_id']);

    // unArchive Contact User
    if ($contact_user_id > 0) {
        mysqli_query($mysqli,"UPDATE users SET user_archived_at = NULL WHERE user_id = $contact_user_id");
    }

    mysqli_query($mysqli,"UPDATE contacts SET contact_archived_at = NULL WHERE contact_id = $contact_id");

    // logging
    logAction("Contact", "Unarchive", "$session_name unarchived contact $contact_name", $client_id, $contact_id);

    $_SESSION['alert_message'] = "Contact <strong>$contact_name</strong> has been Unarchived";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['delete_vendor_contact'])) {

    enforceUserPermission('module_client', 3);

    $contact_id = intval($_GET['delete_contact']);

    // Get Contact Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT contact_name, contact_client_id FROM contacts WHERE contact_id = $contact_id");
    $row = mysqli_fetch_array($sql);
    $contact_name = sanitizeInput($row['contact_name']);
    $client_id = intval($row['contact_client_id']);
    $contact_user_id = intval($row['contact_user_id']);

    // Delete User
    if ($contact_user_id > 0) {
        mysqli_query($mysqli,"DELETE FROM users WHERE user_id = $contact_user_id");
    }

    mysqli_query($mysqli,"DELETE FROM contacts WHERE contact_id = $contact_id");

    // Remove Relations
    mysqli_query($mysqli, "DELETE FROM contact_tags WHERE contact_id = $contact_id");
    mysqli_query($mysqli, "DELETE FROM contact_assets WHERE contact_id = $contact_id");
    mysqli_query($mysqli, "DELETE FROM contact_documents WHERE contact_id = $contact_id");
    mysqli_query($mysqli, "DELETE FROM contact_files WHERE contact_id = $contact_id");
    mysqli_query($mysqli, "DELETE FROM contact_logins WHERE contact_id = $contact_id");
    mysqli_query($mysqli, "DELETE FROM contact_notes WHERE contact_note_contact_id = $contact_id");

    //Logging
    logAction("Contact", "Delete", "$session_name deleted contact $contact_name", $client_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Contact <strong>$contact_name</strong> has been deleted.";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}


if (isset($_POST['export_vendor_contacts_csv'])) {

    enforceUserPermission('module_client');

    $client_id = intval($_POST['client_id']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT client_name FROM clients WHERE client_id = $client_id");
    $row = mysqli_fetch_array($sql);

    $client_name = $row['client_name'];

    //Contacts
    $sql = mysqli_query($mysqli,"SELECT * FROM contacts LEFT JOIN locations ON location_id = contact_location_id WHERE contact_client_id = $client_id AND contact_archived_at IS NULL ORDER BY contact_name ASC");
    $num_rows = mysqli_num_rows($sql);

    if ($num_rows > 0) {
        $delimiter = ",";
        $filename = strtoAZaz09($client_name) . "-Contacts-" . date('Y-m-d') . ".csv";

        //create a file pointer
        $f = fopen('php://memory', 'w');

        //set column headers
        $fields = array('Name', 'Title', 'Department', 'Email', 'Phone', 'Ext', 'Mobile', 'Location');
        fputcsv($f, $fields, $delimiter);

        //output each row of the data, format line as csv and write to file pointer
        while($row = $sql->fetch_assoc()) {
            $lineData = array($row['contact_name'], $row['contact_title'], $row['contact_department'], $row['contact_email'], formatPhoneNumber($row['contact_phone']), $row['contact_extension'], formatPhoneNumber($row['contact_mobile']), $row['location_name']);
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
    logAction("Contact", "Export", "$session_name exported $num_rows contact(s) to a CSV file", $client_id);

    exit;

}

if (isset($_POST["import_vendor_contacts_csv"])) {

    enforceUserPermission('module_client', 2);

    $client_id = intval($_POST['client_id']);
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

    //(Else)Check column count
    $f = fopen($file_name, "r");
    $f_columns = fgetcsv($f, 1000, ",");
    if (!$error & count($f_columns) != 8) {
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
            $duplicate_detect = 0;
            if (isset($column[0])) {
                $name = sanitizeInput($column[0]);
                if (mysqli_num_rows(mysqli_query($mysqli,"SELECT * FROM contacts WHERE contact_name = '$name' AND contact_client_id = $client_id")) > 0) {
                    $duplicate_detect = 1;
                }
            }
            if (isset($column[1])) {
                $title = sanitizeInput($column[1]);
            }
            if (isset($column[2])) {
                $department = sanitizeInput($column[2]);
            }
            if (isset($column[3])) {
                $email = sanitizeInput($column[3]);
            }
            if (isset($column[4])) {
                $phone = preg_replace("/[^0-9]/", '',$column[4]);
            }
            if (isset($column[5])) {
                $ext = preg_replace("/[^0-9]/", '',$column[5]);
            }
            if (isset($column[6])) {
                $mobile = preg_replace("/[^0-9]/", '',$column[6]);
            }
            if (isset($column[7])) {
                $location = sanitizeInput($column[7]);
                $sql_location = mysqli_query($mysqli,"SELECT * FROM locations WHERE location_name = '$location' AND location_client_id = $client_id");
                $row = mysqli_fetch_assoc($sql_location);
                $location_id = intval($row['location_id']);
            }
            // Potentially import the rest in the future?


            // Check if duplicate was detected
            if ($duplicate_detect == 0) {
                //Add
                mysqli_query($mysqli,"INSERT INTO contacts SET contact_name = '$name', contact_title = '$title', contact_department = '$department', contact_email = '$email', contact_phone = '$phone', contact_extension = '$ext', contact_mobile = '$mobile', contact_location_id = $location_id, contact_client_id = $client_id");
                $row_count = $row_count + 1;
            }else{
                $duplicate_count = $duplicate_count + 1;
            }
        }
        fclose($file);

        //Logging
        logAction("Contact", "Import", "$session_name imported $row_count contact(s) via CSV file", $client_id);

        $_SESSION['alert_message'] = "$row_count Contact(s) added, $duplicate_count duplicate(s) detected";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    }
    //Check for any errors, if there are notify user and redirect
    if ($error) {
        $_SESSION['alert_type'] = "warning";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    }
}

if (isset($_GET['download_vendor_contacts_csv_template'])) {
    $client_id = intval($_GET['download_client_contacts_csv_template']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT client_name FROM clients WHERE client_id = $client_id");
    $row = mysqli_fetch_array($sql);

    $client_name = $row['client_name'];

    $delimiter = ",";
    $filename = strtoAZaz09($client_name) . "-Contacts-Template.csv";

    //create a file pointer
    $f = fopen('php://memory', 'w');

    //set column headers
    $fields = array(
        'Full Name           ',
        'Job Title           ',
        'Department Name     ',
        'Email Address       ',
        'Office Phone        ',
        'Office Extension    ',
        'Mobile Phone        ',
        'Office Location     '
    );
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
