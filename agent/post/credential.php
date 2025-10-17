<?php

/*
 * ITFlow - GET/POST request handler for client credentials
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_credential'])) {

    enforceUserPermission('module_credential', 2);

    require_once 'credential_model.php';

    mysqli_query($mysqli,"INSERT INTO credentials SET credential_name = '$name', credential_description = '$description', credential_uri = '$uri', credential_uri_2 = '$uri_2', credential_username = '$username', credential_password = '$password', credential_otp_secret = '$otp_secret', credential_note = '$note', credential_important = $important, credential_contact_id = $contact_id, credential_asset_id = $asset_id, credential_client_id = $client_id");

    $credential_id = mysqli_insert_id($mysqli);

     // Add Tags
    if (isset($_POST['tags'])) {
        foreach($_POST['tags'] as $tag) {
            $tag = intval($tag);
            mysqli_query($mysqli, "INSERT INTO credential_tags SET credential_id = $credential_id, tag_id = $tag");
        }
    }

    logAction("Credential", "Create", "$session_name created credential $name", $client_id, $credential_id);

    flash_alert("Credential <strong>$name</strong> created");

    redirect();

}

if (isset($_POST['edit_credential'])) {

    enforceUserPermission('module_credential', 2);

    require_once 'credential_model.php';

    $credential_id = intval($_POST['credential_id']);

    // Determine if the password has actually changed (salt is rotated on all updates, so have to dencrypt both and compare)
    $current_password = decryptCredentialEntry(mysqli_fetch_row(mysqli_query($mysqli, "SELECT credential_password FROM credentials WHERE credential_id = $credential_id"))[0]); // Get current credential password
    $new_password = decryptCredentialEntry($password); // Get the new password being set (already encrypted by the credential model)
    if ($current_password !== $new_password) {
        // The password has been changed - update the DB to track
        mysqli_query($mysqli, "UPDATE credentials SET credential_password_changed_at = NOW() WHERE credential_id = $credential_id");
    }

    // Update the credential entry with the new details
    mysqli_query($mysqli,"UPDATE credentials SET credential_name = '$name', credential_description = '$description', credential_uri = '$uri', credential_uri_2 = '$uri_2', credential_username = '$username', credential_password = '$password', credential_otp_secret = '$otp_secret', credential_note = '$note', credential_important = $important, credential_contact_id = $contact_id, credential_asset_id = $asset_id WHERE credential_id = $credential_id");

    // Tags
    // Delete existing tags
    mysqli_query($mysqli, "DELETE FROM credential_tags WHERE credential_id = $credential_id");

    // Add new tags
    if(isset($_POST['tags'])) {
        foreach($_POST['tags'] as $tag) {
            $tag = intval($tag);
            mysqli_query($mysqli, "INSERT INTO credential_tags SET credential_id = $credential_id, tag_id = $tag");
        }
    }

    logAction("Credential", "Edit", "$session_name edited credential $name", $client_id, $credential_id);

    flash_alert("Credential <strong>$name</strong> edited");

    redirect();

}

if(isset($_GET['archive_credential'])){

    enforceUserPermission('module_credential', 2);

    $credential_id = intval($_GET['archive_credential']);

    // Get Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT credential_name, credential_client_id FROM credentials WHERE credential_id = $credential_id");
    $row = mysqli_fetch_array($sql);
    $credential_name = sanitizeInput($row['credential_name']);
    $client_id = intval($row['credential_client_id']);

    mysqli_query($mysqli,"UPDATE credentials SET credential_archived_at = NOW() WHERE credential_id = $credential_id");

    logAction("Credential", "Archive", "$session_name archived credential $credential_name", $client_id, $credential_id);

    flash_alert("Credential <strong>$credential_name</strong> archived", 'error');

    redirect();

}

if(isset($_GET['unarchive_credential'])){

    enforceUserPermission('module_credential', 2);

    $credential_id = intval($_GET['unarchive_credential']);

    // Get Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT credential_name, credential_client_id FROM credentials WHERE credential_id = $credential_id");
    $row = mysqli_fetch_array($sql);
    $credential_name = sanitizeInput($row['credential_name']);
    $client_id = intval($row['credential_client_id']);

    mysqli_query($mysqli,"UPDATE credentials SET credential_archived_at = NULL WHERE credential_id = $credential_id");

    logAction("Credential", "Unarchive", "$session_name unarchived credential $credential_name", $client_id, $credential_id);

    flash_alert("Credential <strong>$credential_name</strong> restored");

    redirect();

}

if (isset($_GET['delete_credential'])) {

    enforceUserPermission('module_credential', 3);

    $credential_id = intval($_GET['delete_credential']);

    // Get Credential Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT credential_name, credential_client_id FROM credentials WHERE credential_id = $credential_id");
    $row = mysqli_fetch_array($sql);
    $credential_name = sanitizeInput($row['credential_name']);
    $client_id = intval($row['credential_client_id']);

    mysqli_query($mysqli,"DELETE FROM credentials WHERE credential_id = $credential_id");

    logAction("Credential", "Delete", "$session_name deleted credential $credential_name", $client_id);

    flash_alert("Credential <strong>$credential_name</strong> deleted", 'error');

    redirect();

}

if (isset($_POST['bulk_assign_credential_tags'])) {

    enforceUserPermission('module_credential', 2);

    // Assign tags to Selected Credentials
    if (isset($_POST['credential_ids'])) {

        // Get Selected Credential Count
        $count = count($_POST['credential_ids']);

        foreach($_POST['credential_ids'] as $credential_id) {
            $credential_id = intval($credential_id);

            // Get Contact Details for Logging
            $sql = mysqli_query($mysqli,"SELECT credential_name, credential_client_id FROM credentials WHERE credential_id = $credential_id");
            $row = mysqli_fetch_array($sql);
            $credential_name = sanitizeInput($row['credential_name']);
            $client_id = intval($row['credential_client_id']);

            if($_POST['bulk_remove_tags']) {
                // Delete tags if chosed to do so
                mysqli_query($mysqli, "DELETE FROM credential_tags WHERE credential_id = $credential_id");
            }

            // Add new tags
            if (isset($_POST['bulk_tags'])) {
                foreach($_POST['bulk_tags'] as $tag) {
                    $tag = intval($tag);

                    $sql = mysqli_query($mysqli,"SELECT * FROM credential_tags WHERE credential_id = $credential_id AND tag_id = $tag");
                    if (mysqli_num_rows($sql) == 0) {
                        mysqli_query($mysqli, "INSERT INTO credential_tags SET credential_id = $credential_id, tag_id = $tag");
                    }
                }
            }

            logAction("Credential", "Edit", "$session_name added tags to $credential_name", $client_id, $credential_id);

            flash_alert("Assigned tags for <strong>$count</strong> credentials");

        } // End Assign Loop

        logAction("Credential", "Bulk Edit", "$session_name added tags to $count credentials", $client_id);
    
    }

    redirect();

}

if (isset($_POST['bulk_archive_credentials'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_credential', 2);

    if (isset($_POST['credential_ids'])) {

        // Get Selected Credential Count
        $count = count($_POST['credential_ids']);

        // Cycle through array and archive each record
        foreach ($_POST['credential_ids'] as $credential_id) {

            $credential_id = intval($credential_id);

            // Get Name and Client ID for logging and alert message
            $sql = mysqli_query($mysqli,"SELECT credential_name, credential_client_id FROM credentials WHERE credential_id = $credential_id");
            $row = mysqli_fetch_array($sql);
            $credential_name = sanitizeInput($row['credential_name']);
            $client_id = intval($row['credential_client_id']);

            mysqli_query($mysqli,"UPDATE credentials SET credential_archived_at = NOW() WHERE credential_id = $credential_id");

            logAction("Credential", "Archive", "$session_name archived credential $credential_name", $client_id, $credential_id);
        }

        logAction("Credential", "Bulk Archive", "$session_name archived $count credentials", $client_id);

        flash_alert("Archived <strong>$count</strong> credential(s)", 'error');

    }

    redirect();

}

if (isset($_POST['bulk_unarchive_credentials'])) {

    validateCSRFToken($_POST['csrf_token']);    

    enforceUserPermission('module_credential', 2);

    if (isset($_POST['credential_ids'])) {

        // Get Selected Credential Count
        $count = count($_POST['credential_ids']);

        // Cycle through array and unarchive
        foreach ($_POST['credential_ids'] as $credential_id) {

            $credential_id = intval($credential_id);

            // Get Name and Client ID for logging and alert message
            $sql = mysqli_query($mysqli,"SELECT credential_name, credential_client_id FROM credentials WHERE credential_id = $credential_id");
            $row = mysqli_fetch_array($sql);
            $credential_name = sanitizeInput($row['credential_name']);
            $client_id = intval($row['credential_client_id']);

            mysqli_query($mysqli,"UPDATE credentials SET credential_archived_at = NULL WHERE credential_id = $credential_id");

            logAction("Credential", "Unarchive", "$session_name unarchived credential $credential_name", $client_id, $credential_id);

        }

        logAction("Credential", "Bulk Unarchive", "$session_name unarchived $count credential(s)", $client_id);

        flash_alert("Unarchived <strong>$count</strong> credential(s)");

    }

    redirect();

}

if (isset($_POST['bulk_delete_credentials'])) {

    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_credential', 3);

    if (isset($_POST['credential_ids'])) {

        // Get Selected Credential Count
        $count = count($_POST['credential_ids']);

        // Cycle through array and delete each record
        foreach ($_POST['credential_ids'] as $credential_id) {

            $credential_id = intval($credential_id);

            // Get Name and Client ID for logging and alert message
            $sql = mysqli_query($mysqli,"SELECT credential_name, credential_client_id FROM credentials WHERE credential_id = $credential_id");
            $row = mysqli_fetch_array($sql);
            $credential_name = sanitizeInput($row['credential_name']);
            $client_id = intval($row['credential_client_id']);

            mysqli_query($mysqli, "DELETE FROM credentials WHERE credential_id = $credential_id AND credential_client_id = $client_id");

            logAction("Credential", "Delete", "$session_name deleted credential $credential_name", $client_id);

        }

        logAction("Credential", "Bulk Delete", "$session_name deleted $count credential(s)", $client_id);

        flash_alert("Deleted <strong>$count</strong> credential(s)", 'error');

    }

    redirect();

}

if (isset($_POST['export_credentials_csv'])) {

    enforceUserPermission('module_credential');

    if (isset($_POST['client_id'])) {
        $client_id = intval($_POST['client_id']);
        $client_query = "AND credential_client_id = $client_id";
        $client_name = getFieldById('clients', $client_id, 'client_name');
        $file_name_prepend = "$client_name-";
    } else {
        $client_query = '';
        $client_id = 0;
        $file_name_prepend = "$session_company_name-";
    }

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT * FROM credentials LEFT JOIN clients ON client_id = credential_client_id WHERE credential_archived_at IS NULL $client_query ORDER BY credential_name ASC");
    
    $num_rows = mysqli_num_rows($sql);

    if ($num_rows > 0) {
        $delimiter = ",";
        $enclosure = '"';
        $escape    = '\\';   // backslash
        $filename = sanitize_filename($file_name_prepend . "Credentials-" . date('Y-m-d_H-i-s') . ".csv");

        //create a file pointer
        $f = fopen('php://memory', 'w');

        //set column headers
        $fields = array('Name', 'Description', 'Username', 'Password', 'TOTP', 'URI', 'URI_2', 'Note', 'Important');
        fputcsv($f, $fields, $delimiter, $enclosure, $escape);

        //output each row of the data, format line as csv and write to file pointer
        while($row = mysqli_fetch_assoc($sql)){
            $credential_username = decryptCredentialEntry($row['credential_username']);
            $credential_password = decryptCredentialEntry($row['credential_password']);
            $lineData = array($row['credential_name'], $row['credential_description'], $credential_username, $credential_password, $row['credential_otp_secret'], $row['credential_uri'], $row['credential_uri_2'], $row['credential_note'], $row['credential_important']);
            fputcsv($f, $lineData, $delimiter, $enclosure, $escape);
        }

        //move back to beginning of file
        fseek($f, 0);

        //set headers to download file rather than displayed
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '";');

        //output all remaining data on a file pointer
        fpassthru($f);
    }

    logAction("Credential", "Export", "$session_name exported $num_rows credential(s) to a CSV file", $client_id);

    exit;

}

if (isset($_POST["import_credentials_csv"])) {

    enforceUserPermission('module_credential', 2);

    $client_id = intval($_POST['client_id']);
    $error = false;

    if (!empty($_FILES["file"]["tmp_name"])) {
        $file_name = $_FILES["file"]["tmp_name"];
    } else {
        flash_alert("Please select a file to upload.", 'error');
        redirect();
    }

    //Check file is CSV
    $file_extension = strtolower(end(explode('.',$_FILES['file']['name'])));
    $allowed_file_extensions = array('csv');
    if (in_array($file_extension,$allowed_file_extensions) === false){
        $error = true;
        flash_alert("Bad file extension", 'error');
    }

    //Check file isn't empty
    elseif ($_FILES["file"]["size"] < 1){
        $error = true;
        flash_alert("Bad file size (empty?)", 'error');
    }

    //(Else)Check column count
    $f = fopen($file_name, "r");
    $f_columns = fgetcsv($f, 1000, ",");
    if (!$error & count($f_columns) != 9) {
        $error = true;
        flash_alert("Bad column count.", 'error');
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
                if (mysqli_num_rows(mysqli_query($mysqli,"SELECT * FROM credentials WHERE credential_name = '$name' AND credential_client_id = $client_id")) > 0){
                    $duplicate_detect = 1;
                }
            }
            if (isset($column[1])) {
                $description = sanitizeInput($column[1]);
            }
            if (isset($column[2])) {
                $username = sanitizeInput(encryptCredentialEntry($column[2]));
            }
            if (isset($column[3])) {
                $password = sanitizeInput(encryptCredentialEntry($column[3]));
            }
            if (isset($column[4])) {
                $otp_secret = sanitizeInput($column[4]);
            }
            if (isset($column[5])) {
                $uri = sanitizeInput($column[5]);
            }
            if (isset($column[6])) {
                $uri_2 = sanitizeInput($column[6]);
            }
            if (isset($column[7])) {
                $note = sanitizeInput($column[7]);
            }
            if (isset($column[8])) {
                $important = sanitizeInput($column[8]);
            }

            // Check if duplicate was detected
            if ($duplicate_detect == 0){
                //Add
                mysqli_query($mysqli,"INSERT INTO credentials SET credential_name = '$name', credential_description = '$description', credential_uri = '$uri', credential_uri_2 = '$uri_2', credential_username = '$username', credential_password = '$password', credential_otp_secret = '$otp_secret', credential_note = '$note', credential_important = $important, credential_client_id = $client_id");
                $row_count = $row_count + 1;
            } else {
                $duplicate_count = $duplicate_count + 1;
            }
        }
        fclose($file);

        logAction("Credential", "Import", "$session_name imported $row_count credential(s) via CSV file. $duplicate_count duplicate(s) found and not imported", $client_id);

        flash_alert("<strong>$row_count</strong> credential(s) imported, <strong>$duplicate_count</strong> duplicate(s) detected and not imported", 'warning');
        
        redirect();
    }
    //Check for any errors, if there are notify user and redirect
    if ($error) {
        redirect();
    }
    
}

if (isset($_GET['download_credentials_csv_template'])) {

    $delimiter = ",";
    $filename = "Credentials-Template.csv";

    //create a file pointer
    $f = fopen('php://memory', 'w');

    //set column headers
    $fields = array('Name', 'Description', 'Username', 'Password', 'TOTP', 'URI', 'URI_2', 'Note', 'Important');
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
