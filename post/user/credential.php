<?php

/*
 * ITFlow - GET/POST request handler for client credentials (formerly logins)
 */

if (isset($_POST['add_login'])) {

    enforceUserPermission('module_credential', 2);

    require_once 'post/user/credential_model.php';

    mysqli_query($mysqli,"INSERT INTO logins SET login_name = '$name', login_description = '$description', login_uri = '$uri', login_uri_2 = '$uri_2', login_username = '$username', login_password = '$password', login_otp_secret = '$otp_secret', login_note = '$note', login_important = $important, login_contact_id = $contact_id, login_vendor_id = $vendor_id, login_asset_id = $asset_id, login_software_id = $software_id, login_client_id = $client_id");

    $login_id = mysqli_insert_id($mysqli);

     // Add Tags
    if (isset($_POST['tags'])) {
        foreach($_POST['tags'] as $tag) {
            $tag = intval($tag);
            mysqli_query($mysqli, "INSERT INTO login_tags SET login_id = $login_id, tag_id = $tag");
        }
    }

    // Logging
    logAction("Credential", "Create", "$session_name created credential $name", $client_id, $login_id);

    $_SESSION['alert_message'] = "Credential <strong>$name</strong> created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_login'])) {

    enforceUserPermission('module_credential', 2);

    require_once 'post/user/credential_model.php';

    $login_id = intval($_POST['login_id']);

    // Determine if the password has actually changed (salt is rotated on all updates, so have to dencrypt both and compare)
    $current_password = decryptLoginEntry(mysqli_fetch_row(mysqli_query($mysqli, "SELECT login_password FROM logins WHERE login_id = $login_id"))[0]); // Get current login password
    $new_password = decryptLoginEntry($password); // Get the new password being set (already encrypted by the login model)
    if ($current_password !== $new_password) {
        // The password has been changed - update the DB to track
        mysqli_query($mysqli, "UPDATE logins SET login_password_changed_at = NOW() WHERE login_id = $login_id");
    }

    // Update the login entry with the new details
    mysqli_query($mysqli,"UPDATE logins SET login_name = '$name', login_description = '$description', login_uri = '$uri', login_uri_2 = '$uri_2', login_username = '$username', login_password = '$password', login_otp_secret = '$otp_secret', login_note = '$note', login_important = $important, login_contact_id = $contact_id, login_vendor_id = $vendor_id, login_asset_id = $asset_id, login_software_id = $software_id WHERE login_id = $login_id");

    // Tags
    // Delete existing tags
    mysqli_query($mysqli, "DELETE FROM login_tags WHERE login_id = $login_id");

    // Add new tags
    if(isset($_POST['tags'])) {
        foreach($_POST['tags'] as $tag) {
            $tag = intval($tag);
            mysqli_query($mysqli, "INSERT INTO login_tags SET login_id = $login_id, tag_id = $tag");
        }
    }

    // Logging
    logAction("Credential", "Edit", "$session_name edited credential $name", $client_id, $login_id);

    $_SESSION['alert_message'] = "Login <strong>$name</strong> edited";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['archive_login'])){

    enforceUserPermission('module_credential', 2);

    $login_id = intval($_GET['archive_login']);

    // Get Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT login_name, login_client_id FROM logins WHERE login_id = $login_id");
    $row = mysqli_fetch_array($sql);
    $login_name = sanitizeInput($row['login_name']);
    $client_id = intval($row['login_client_id']);

    mysqli_query($mysqli,"UPDATE logins SET login_archived_at = NOW() WHERE login_id = $login_id");

    //logging
    logAction("Credential", "Archive", "$session_name archived credential $login_name", $client_id, $login_id);


    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Credential <strong>$login_name</strong> archived";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['unarchive_login'])){

    enforceUserPermission('module_credential', 2);

    $login_id = intval($_GET['unarchive_login']);

    // Get Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT login_name, login_client_id FROM logins WHERE login_id = $login_id");
    $row = mysqli_fetch_array($sql);
    $login_name = sanitizeInput($row['login_name']);
    $client_id = intval($row['login_client_id']);

    mysqli_query($mysqli,"UPDATE logins SET login_archived_at = NULL WHERE login_id = $login_id");

    //Logging
    logAction("Credential", "Unarchive", "$session_name unarchived credential $login_name", $client_id, $login_id);

    $_SESSION['alert_message'] = "Credential <strong>$login_name</strong> restored";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_GET['delete_login'])) {

    enforceUserPermission('module_credential', 3);

    $login_id = intval($_GET['delete_login']);

    // Get Login Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT login_name, login_client_id FROM logins WHERE login_id = $login_id");
    $row = mysqli_fetch_array($sql);
    $login_name = sanitizeInput($row['login_name']);
    $client_id = intval($row['login_client_id']);

    mysqli_query($mysqli,"DELETE FROM logins WHERE login_id = $login_id");

    // Remove Relations
    mysqli_query($mysqli,"DELETE FROM contact_logins WHERE login_id = $login_id");
    mysqli_query($mysqli,"DELETE FROM service_logins WHERE login_id = $login_id");
    mysqli_query($mysqli,"DELETE FROM software_logins WHERE login_id = $login_id");
    mysqli_query($mysqli,"DELETE FROM vendor_logins WHERE login_id = $login_id");


    // Logging
    logAction("Credential", "Delete", "$session_name deleted credential $login_name", $client_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Credential <strong>$login_name</strong> deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['bulk_assign_login_tags'])) {

    enforceUserPermission('module_credential', 2);

    // Assign tags to Selected Credentials
    if (isset($_POST['login_ids'])) {

        // Get Selected Credential Count
        $count = count($_POST['login_ids']);

        foreach($_POST['login_ids'] as $login_id) {
            $login_id = intval($login_id);

            // Get Contact Details for Logging
            $sql = mysqli_query($mysqli,"SELECT login_name, login_client_id FROM logins WHERE login_id = $login_id");
            $row = mysqli_fetch_array($sql);
            $login_name = sanitizeInput($row['login_name']);
            $client_id = intval($row['login_client_id']);

            if($_POST['bulk_remove_tags']) {
                // Delete tags if chosed to do so
                mysqli_query($mysqli, "DELETE FROM login_tags WHERE login_id = $login_id");
            }

            // Add new tags
            if (isset($_POST['bulk_tags'])) {
                foreach($_POST['bulk_tags'] as $tag) {
                    $tag = intval($tag);

                    $sql = mysqli_query($mysqli,"SELECT * FROM login_tags WHERE login_id = $login_id AND tag_id = $tag");
                    if (mysqli_num_rows($sql) == 0) {
                        mysqli_query($mysqli, "INSERT INTO login_tags SET login_id = $login_id, tag_id = $tag");
                    }
                }
            }

            // Logging
            logAction("Credential", "Edit", "$session_name added tags to $login_name", $client_id, $login_id);

            $_SESSION['alert_message'] = "Assigned tags for <strong>$count</strong> credentials";

        } // End Assign Loop

        // Logging
        logAction("Credential", "Bulk Edit", "$session_name added tags to $count credentials", $client_id);
    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['bulk_archive_logins'])) {

    enforceUserPermission('module_credential', 2);
    validateCSRFToken($_POST['csrf_token']);

    if (isset($_POST['login_ids'])) {

        // Get Selected Credential Count
        $count = count($_POST['login_ids']);

        // Cycle through array and archive each record
        foreach ($_POST['login_ids'] as $login_id) {

            $login_id = intval($login_id);

            // Get Name and Client ID for logging and alert message
            $sql = mysqli_query($mysqli,"SELECT login_name, login_client_id FROM logins WHERE login_id = $login_id");
            $row = mysqli_fetch_array($sql);
            $login_name = sanitizeInput($row['login_name']);
            $client_id = intval($row['login_client_id']);

            mysqli_query($mysqli,"UPDATE logins SET login_archived_at = NOW() WHERE login_id = $login_id");

            // Individual Contact logging
            logAction("Credential", "Archive", "$session_name archived credential $login_name", $client_id, $login_id);
        }

        // Bulk Logging
        logAction("Credential", "Bulk Archive", "$session_name archived $count credentials", $client_id);

        $_SESSION['alert_type'] = "error";
        $_SESSION['alert_message'] = "Archived <strong>$count</strong> credential(s)";

    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['bulk_unarchive_logins'])) {

    enforceUserPermission('module_credential', 2);

    validateCSRFToken($_POST['csrf_token']);

    if (isset($_POST['login_ids'])) {

        // Get Selected Credential Count
        $count = count($_POST['login_ids']);

        // Cycle through array and unarchive
        foreach ($_POST['login_ids'] as $login_id) {

            $login_id = intval($login_id);

            // Get Name and Client ID for logging and alert message
            $sql = mysqli_query($mysqli,"SELECT login_name, login_client_id FROM logins WHERE login_id = $login_id");
            $row = mysqli_fetch_array($sql);
            $login_name = sanitizeInput($row['login_name']);
            $client_id = intval($row['login_client_id']);

            mysqli_query($mysqli,"UPDATE logins SET login_archived_at = NULL WHERE login_id = $login_id");

            // Individual logging
            logAction("Credential", "Unarchive", "$session_name unarchived credential $login_name", $client_id, $login_id);

        }

        // Bulk Logging
        logAction("Credential", "Bulk Unarchive", "$session_name unarchived $count credential(s)", $client_id);

        $_SESSION['alert_message'] = "Unarchived <strong>$count</strong> credential(s)";

    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['bulk_delete_logins'])) {

    enforceUserPermission('module_credential', 3);

    validateCSRFToken($_POST['csrf_token']);

    if (isset($_POST['login_ids'])) {

        // Get Selected Credential Count
        $count = count($_POST['login_ids']);

        // Cycle through array and delete each record
        foreach ($_POST['login_ids'] as $login_id) {

            $login_id = intval($login_id);

            // Get Name and Client ID for logging and alert message
            $sql = mysqli_query($mysqli,"SELECT login_name, login_client_id FROM logins WHERE login_id = $login_id");
            $row = mysqli_fetch_array($sql);
            $login_name = sanitizeInput($row['login_name']);
            $client_id = intval($row['login_client_id']);

            mysqli_query($mysqli, "DELETE FROM logins WHERE login_id = $login_id AND login_client_id = $client_id");

            // Remove Relations
            mysqli_query($mysqli,"DELETE FROM contact_logins WHERE login_id = $login_id");
            mysqli_query($mysqli,"DELETE FROM service_logins WHERE login_id = $login_id");
            mysqli_query($mysqli,"DELETE FROM software_logins WHERE login_id = $login_id");
            mysqli_query($mysqli,"DELETE FROM vendor_logins WHERE login_id = $login_id");

            // Logging
            logAction("Credential", "Delete", "$session_name deleted credential $login_name", $client_id);

        }

        // Bulk Logging
        logAction("Credential", "Bulk Delete", "$session_name deleted $count credential(s)", $client_id);

        $_SESSION['alert_type'] = "error"; 
        $_SESSION['alert_message'] = "Deleted <strong>$count</strong> credential(s)";

    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['export_client_logins_csv'])) {

    enforceUserPermission('module_credential');

    $client_id = intval($_POST['client_id']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT * FROM logins LEFT JOIN clients ON client_id = login_client_id WHERE login_client_id = $client_id ORDER BY login_name ASC");
    $row = mysqli_fetch_array($sql);

    $client_name = $row['client_name'];

    $num_rows = mysqli_num_rows($sql);

    if ($num_rows > 0) {
        $delimiter = ",";
        $filename = strtoAZaz09($client_name) . "-Credentials-" . date('Y-m-d') . ".csv";

        //create a file pointer
        $f = fopen('php://memory', 'w');

        //set column headers
        $fields = array('Name', 'Description', 'Username', 'Password', 'URI');
        fputcsv($f, $fields, $delimiter);

        //output each row of the data, format line as csv and write to file pointer
        while($row = $sql->fetch_assoc()) {
            $login_username = decryptLoginEntry($row['login_username']);
            $login_password = decryptLoginEntry($row['login_password']);
            $lineData = array($row['login_name'], $row['login_description'], $login_username, $login_password, $row['login_uri']);
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
    logAction("Credential", "Export", "$session_name exported $num_rows credential(s) to a CSV file", $client_id);

    exit;

}

if (isset($_POST["import_client_logins_csv"])) {

    enforceUserPermission('module_credential', 2);

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
    if (in_array($file_extension,$allowed_file_extensions) === false){
        $error = true;
        $_SESSION['alert_message'] = "Bad file extension";
    }

    //Check file isn't empty
    elseif ($_FILES["file"]["size"] < 1){
        $error = true;
        $_SESSION['alert_message'] = "Bad file size (empty?)";
    }

    //(Else)Check column count
    $f = fopen($file_name, "r");
    $f_columns = fgetcsv($f, 1000, ",");
    if (!$error & count($f_columns) != 5) {
        $error = true;
        $_SESSION['alert_message'] = "Bad column count.";
    }

    //Else, parse the file
    if (!$error){
        $file = fopen($file_name, "r");
        fgetcsv($file, 1000, ","); // Skip first line
        $row_count = 0;
        $duplicate_count = 0;
        while(($column = fgetcsv($file, 1000, ",")) !== false){
            $duplicate_detect = 0;
            if (isset($column[0])) {
                $name = sanitizeInput($column[0]);
                if (mysqli_num_rows(mysqli_query($mysqli,"SELECT * FROM logins WHERE login_name = '$name' AND login_client_id = $client_id")) > 0){
                    $duplicate_detect = 1;
                }
            }
            if (isset($column[1])) {
                $description = sanitizeInput($column[1]);
            }
            if (isset($column[2])) {
                $username = sanitizeInput(encryptLoginEntry($column[2]));
            }
            if (isset($column[3])) {
                $password = sanitizeInput(encryptLoginEntry($column[3]));
            }
            if (isset($column[4])) {
                $uri = sanitizeInput($column[4]);
            }

            // Check if duplicate was detected
            if ($duplicate_detect == 0){
                //Add
                mysqli_query($mysqli,"INSERT INTO logins SET login_name = '$name', login_description = '$description', login_uri = '$uri', login_username = '$username', login_password = '$password', login_client_id = $client_id");
                $row_count = $row_count + 1;
            }else{
                $duplicate_count = $duplicate_count + 1;
            }
        }
        fclose($file);

        // Logging
        logAction("Credential", "Import", "$session_name imported $row_count credential(s) via CSV file. $duplicate_count duplicate(s) found and not imported", $client_id);

        $_SESSION['alert_message'] = "$row_count credential(s) imported, $duplicate_count duplicate(s) detected and not imported";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    }
    //Check for any errors, if there are notify user and redirect
    if ($error) {
        $_SESSION['alert_type'] = "warning";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
    }
}

if (isset($_GET['download_client_logins_csv_template'])) {
    $client_id = intval($_GET['download_client_logins_csv_template']);

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT client_name FROM clients WHERE client_id = $client_id");
    $row = mysqli_fetch_array($sql);

    $client_name = $row['client_name'];

    $delimiter = ",";
    $filename = strtoAZaz09($client_name) . "-Logins-Template.csv";

    //create a file pointer
    $f = fopen('php://memory', 'w');

    //set column headers
    $fields = array('Name', 'Description', 'Username', 'Password', 'URI');
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
