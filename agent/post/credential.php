<?php

/*
 * ITFlow - GET/POST request handler for client credentials
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_credential'])) {

    validateCSRFToken();

    enforceUserPermission('module_credential', 2);

    require_once 'credential_model.php';

    $client_id = intval($_POST['client_id']);

    enforceClientAccess();

    mysqli_query($mysqli,"INSERT INTO credentials SET credential_name = '$name', credential_description = '$description', credential_uri = '$uri', credential_uri_2 = '$uri_2', credential_username = '$username', credential_password = '$password', credential_otp_secret = '$otp_secret', credential_note = '$note', credential_favorite = $favorite, credential_contact_id = $contact_id, credential_asset_id = $asset_id, credential_client_id = $client_id");

    $credential_id = mysqli_insert_id($mysqli);

     // Add Tags
    if (isset($_POST['tags'])) {
        foreach($_POST['tags'] as $tag) {
            $tag = intval($tag);
            mysqli_query($mysqli, "INSERT INTO credential_tags SET credential_id = $credential_id, tag_id = $tag");
        }
    }

    logAudit("Credential", "Create", "$session_name created credential $name", $client_id, $credential_id);

    flashAlert("Credential <strong>$name</strong> created");

    redirect();

}

if (isset($_POST['edit_credential'])) {

    validateCSRFToken();

    enforceUserPermission('module_credential', 2);

    require_once 'credential_model.php';

    $credential_id = intval($_POST['credential_id']);

    $client_id = intval(getFieldById('credentials', $credential_id, 'credential_client_id'));

    enforceClientAccess();

    // Determine if the password has actually changed (salt is rotated on all updates, so have to dencrypt both and compare)
    $current_password = decryptCredentialEntry(mysqli_fetch_row(mysqli_query($mysqli, "SELECT credential_password FROM credentials WHERE credential_id = $credential_id"))[0]); // Get current credential password
    $new_password = decryptCredentialEntry($password); // Get the new password being set (already encrypted by the credential model)
    if ($current_password !== $new_password) {
        // The password has been changed - update the DB to track
        mysqli_query($mysqli, "UPDATE credentials SET credential_password_changed_at = NOW() WHERE credential_id = $credential_id");
    }

    // Update the credential entry with the new details
    mysqli_query($mysqli,"UPDATE credentials SET credential_name = '$name', credential_description = '$description', credential_uri = '$uri', credential_uri_2 = '$uri_2', credential_username = '$username', credential_password = '$password', credential_otp_secret = '$otp_secret', credential_note = '$note', credential_favorite = $favorite, credential_contact_id = $contact_id, credential_asset_id = $asset_id WHERE credential_id = $credential_id");

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

    logAudit("Credential", "Edit", "$session_name edited credential $name", $client_id, $credential_id);

    flashAlert("Credential <strong>$name</strong> edited");

    redirect();

}

if(isset($_GET['archive_credential'])){

    validateCSRFToken();

    enforceUserPermission('module_credential', 2);

    $credential_id = intval($_GET['archive_credential']);

    // Get Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT credential_name, credential_client_id FROM credentials WHERE credential_id = $credential_id");
    $row = mysqli_fetch_assoc($sql);
    $credential_name = escapeSql($row['credential_name']);
    $client_id = intval($row['credential_client_id']);

    enforceClientAccess();

    mysqli_query($mysqli,"UPDATE credentials SET credential_archived_at = NOW() WHERE credential_id = $credential_id");

    logAudit("Credential", "Archive", "$session_name archived credential $credential_name", $client_id, $credential_id);

    flashAlert("Credential <strong>$credential_name</strong> archived", 'error');

    redirect();

}

if(isset($_GET['restore_credential'])){

    validateCSRFToken();

    enforceUserPermission('module_credential', 2);

    $credential_id = intval($_GET['restore_credential']);

    // Get Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT credential_name, credential_client_id FROM credentials WHERE credential_id = $credential_id");
    $row = mysqli_fetch_assoc($sql);
    $credential_name = escapeSql($row['credential_name']);
    $client_id = intval($row['credential_client_id']);

    enforceClientAccess();

    mysqli_query($mysqli,"UPDATE credentials SET credential_archived_at = NULL WHERE credential_id = $credential_id");

    logAudit("Credential", "Restore", "$session_name restored credential $credential_name", $client_id, $credential_id);

    flashAlert("Credential <strong>$credential_name</strong> restored");

    redirect();

}

if (isset($_GET['delete_credential'])) {

    validateCSRFToken();

    enforceUserPermission('module_credential', 3);

    $credential_id = intval($_GET['delete_credential']);

    // Get Credential Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT credential_name, credential_client_id FROM credentials WHERE credential_id = $credential_id");
    $row = mysqli_fetch_assoc($sql);
    $credential_name = escapeSql($row['credential_name']);
    $client_id = intval($row['credential_client_id']);

    enforceClientAccess();

    mysqli_query($mysqli,"DELETE FROM credentials WHERE credential_id = $credential_id");

    logAudit("Credential", "Delete", "$session_name deleted credential $credential_name", $client_id);

    flashAlert("Credential <strong>$credential_name</strong> deleted", 'error');

    redirect();

}

if (isset($_POST['bulk_assign_credential_tags'])) {

    validateCSRFToken();

    enforceUserPermission('module_credential', 2);

    // Assign tags to Selected Credentials
    if (isset($_POST['credential_ids'])) {

        // Get Selected Credential Count
        $count = count($_POST['credential_ids']);

        foreach($_POST['credential_ids'] as $credential_id) {
            $credential_id = intval($credential_id);

            // Get Contact Details for Logging
            $sql = mysqli_query($mysqli,"SELECT credential_name, credential_client_id FROM credentials WHERE credential_id = $credential_id");
            $row = mysqli_fetch_assoc($sql);
            $credential_name = escapeSql($row['credential_name']);
            $client_id = intval($row['credential_client_id']);

            enforceClientAccess();

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

            logAudit("Credential", "Edit", "$session_name added tags to $credential_name", $client_id, $credential_id);

            flashAlert("Assigned tags for <strong>$count</strong> credentials");

        } // End Assign Loop

        logAudit("Credential", "Bulk Edit", "$session_name added tags to $count credentials", $client_id);

    }

    redirect();

}

if (isset($_POST['bulk_favorite_credentials'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    if (isset($_POST['credential_ids'])) {

        $count = count($_POST['credential_ids']);

        foreach ($_POST['credential_ids'] as $credential_id) {

            $credential_id = intval($credential_id);

            // Get Asset Name and Client ID for logging and alert message
            $sql = mysqli_query($mysqli,"SELECT credential_name, credential_client_id FROM credentials WHERE credential_id = $credential_id");
            $row = mysqli_fetch_assoc($sql);
            $credential_name = escapeSql($row['credential_name']);
            $client_id = intval($row['credential_client_id']);

            enforceClientAccess();

            mysqli_query($mysqli,"UPDATE credentials SET credential_favorite = 1 WHERE credential_id = $credential_id");

            logAudit("Credential", "Edit", "$session_name marked credential $credential_name a favorite", $client_id, $credential_id);

        }

        logAudit("Credential", "Bulk Edit", "$session_name favorited $count credentials", $client_id);

        flashAlert("Favorited <strong>$count</strong> credential(s)");

    }

    redirect();

}

if (isset($_POST['bulk_unfavorite_credentials'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    if (isset($_POST['credential_ids'])) {

        $count = count($_POST['credential_ids']);

        foreach ($_POST['credential_ids'] as $credential_id) {

            $credential_id = intval($credential_id);

            // Get Asset Name and Client ID for logging and alert message
            $sql = mysqli_query($mysqli,"SELECT credential_name, credential_client_id FROM credentials WHERE credential_id = $credential_id");
            $row = mysqli_fetch_assoc($sql);
            $credential_name = escapeSql($row['credential_name']);
            $client_id = intval($row['credential_client_id']);

            enforceClientAccess();

            mysqli_query($mysqli,"UPDATE credentials SET credential_favorite = 0 WHERE credential_id = $credential_id");

            logAudit("Credential", "Edit", "$session_name unfavorited credential $credential_name", $client_id, $credential_id);

        }

        logAudit("Crednetial", "Bulk Edit", "$session_name unfavorited $count credentials", $client_id);

        flashAlert("Unfavorited <strong>$count</strong> credential(s)");

    }

    redirect();

}

if (isset($_POST['bulk_archive_credentials'])) {

    validateCSRFToken();

    enforceUserPermission('module_credential', 2);

    if (isset($_POST['credential_ids'])) {

        // Get Selected Credential Count
        $count = count($_POST['credential_ids']);

        // Cycle through array and archive each record
        foreach ($_POST['credential_ids'] as $credential_id) {

            $credential_id = intval($credential_id);

            // Get Name and Client ID for logging and alert message
            $sql = mysqli_query($mysqli,"SELECT credential_name, credential_client_id FROM credentials WHERE credential_id = $credential_id");
            $row = mysqli_fetch_assoc($sql);
            $credential_name = escapeSql($row['credential_name']);
            $client_id = intval($row['credential_client_id']);

            enforceClientAccess();

            mysqli_query($mysqli,"UPDATE credentials SET credential_archived_at = NOW() WHERE credential_id = $credential_id");

            logAudit("Credential", "Archive", "$session_name archived credential $credential_name", $client_id, $credential_id);
        }

        logAudit("Credential", "Bulk Archive", "$session_name archived $count credentials", $client_id);

        flashAlert("Archived <strong>$count</strong> credential(s)", 'error');

    }

    redirect();

}

if (isset($_POST['bulk_restore_credentials'])) {

    validateCSRFToken();

    enforceUserPermission('module_credential', 2);

    if (isset($_POST['credential_ids'])) {

        // Get Selected Credential Count
        $count = count($_POST['credential_ids']);

        // Cycle through array and restore
        foreach ($_POST['credential_ids'] as $credential_id) {

            $credential_id = intval($credential_id);

            // Get Name and Client ID for logging and alert message
            $sql = mysqli_query($mysqli,"SELECT credential_name, credential_client_id FROM credentials WHERE credential_id = $credential_id");
            $row = mysqli_fetch_assoc($sql);
            $credential_name = escapeSql($row['credential_name']);
            $client_id = intval($row['credential_client_id']);

            enforceClientAccess();

            mysqli_query($mysqli,"UPDATE credentials SET credential_archived_at = NULL WHERE credential_id = $credential_id");

            logAudit("Credential", "Restore", "$session_name restored credential $credential_name", $client_id, $credential_id);

        }

        logAudit("Credential", "Bulk Restore", "$session_name restored $count credential(s)", $client_id);

        flashAlert("Restored <strong>$count</strong> credential(s)");

    }

    redirect();

}

if (isset($_POST['bulk_delete_credentials'])) {

    validateCSRFToken();

    enforceUserPermission('module_credential', 3);

    if (isset($_POST['credential_ids'])) {

        // Get Selected Credential Count
        $count = count($_POST['credential_ids']);

        // Cycle through array and delete each record
        foreach ($_POST['credential_ids'] as $credential_id) {

            $credential_id = intval($credential_id);

            // Get Name and Client ID for logging and alert message
            $sql = mysqli_query($mysqli,"SELECT credential_name, credential_client_id FROM credentials WHERE credential_id = $credential_id");
            $row = mysqli_fetch_assoc($sql);
            $credential_name = escapeSql($row['credential_name']);
            $client_id = intval($row['credential_client_id']);

            enforceClientAccess();

            mysqli_query($mysqli, "DELETE FROM credentials WHERE credential_id = $credential_id AND credential_client_id = $client_id");

            logAudit("Credential", "Delete", "$session_name deleted credential $credential_name", $client_id);

        }

        logAudit("Credential", "Bulk Delete", "$session_name deleted $count credential(s)", $client_id);

        flashAlert("Deleted <strong>$count</strong> credential(s)", 'error');

    }

    redirect();

}

if (isset($_POST['export_credentials'])) {

    validateCSRFToken();

    // Exports are reads - see CONTRIBUTING.md
    enforceUserPermission('module_credential');

    $format = resolveExportFormat($_POST['export_credentials']);

    // Filters inherited from the credentials page - mirrors agent/credentials.php
    $filter_summary = [];

    // Archived Filter
    $archived = (isset($_POST['archived']) && $_POST['archived'] == 1);
    if ($archived) {
        $filter_summary['Archived'] = 'Archived only';
    }

    if (!empty($_POST['client_id'])) {
        $client_id = intval($_POST['client_id']);
        $client_query = "AND credential_client_id = $client_id";
        $client_name = getFieldById('clients', $client_id, 'client_name');
        $file_name_prepend = "$client_name-";
        $filter_summary['Client'] = $client_name;

        enforceClientAccess();

        $archive_query = $archived ? "credential_archived_at IS NOT NULL" : "credential_archived_at IS NULL";
    } else {
        $client_query = '';
        $client_id = 0; // for Logging
        $file_name_prepend = "$session_company_name-";

        // Client Filter
        if (!empty($_POST['client'])) {
            $filter_client_id = intval($_POST['client']);
            $client_query = "AND (credential_client_id = $filter_client_id)";
            $filter_summary['Client'] = getFieldById('clients', $filter_client_id, 'client_name');
        }

        $archive_query = $archived ? "(client_archived_at IS NOT NULL OR credential_archived_at IS NOT NULL)" : "(client_archived_at IS NULL AND credential_archived_at IS NULL)";
    }

    // Tags Filter
    if (isset($_POST['tags']) && is_array($_POST['tags']) && !empty($_POST['tags'])) {
        $tag_filter = implode(",", array_map('intval', $_POST['tags']));
        $tag_query = "AND tags.tag_id IN ($tag_filter)";

        $tag_names = [];
        $sql_tags = mysqli_query($mysqli, "SELECT tag_name FROM tags WHERE tag_id IN ($tag_filter) ORDER BY tag_name ASC");
        while ($tag_row = mysqli_fetch_assoc($sql_tags)) {
            $tag_names[] = $tag_row['tag_name'];
        }
        $filter_summary['Tags'] = implode(', ', $tag_names);
    } else {
        // Default - any
        $tag_query = '';
    }

    // Search Filter
    $q = escapeSql($_POST['q'] ?? '');
    if (!empty($q)) {
        $filter_summary['Search'] = $_POST['q'];
    }

    $sql = mysqli_query(
        $mysqli,
        "SELECT c.*, clients.*, contacts.*, assets.*
        FROM credentials c
        LEFT JOIN credential_tags ON credential_tags.credential_id = c.credential_id
        LEFT JOIN tags ON tags.tag_id = credential_tags.tag_id
        LEFT JOIN clients ON client_id = credential_client_id
        LEFT JOIN contacts ON contact_id = credential_contact_id
        LEFT JOIN assets ON asset_id = credential_asset_id
        WHERE $archive_query
        $tag_query
        AND (c.credential_name LIKE '%$q%' OR c.credential_description LIKE '%$q%' OR c.credential_uri LIKE '%$q%' OR tag_name LIKE '%$q%' OR client_name LIKE '%$q%')
        $access_permission_query
        $client_query
        GROUP BY c.credential_id
        ORDER BY c.credential_name ASC"
    );

    $num_rows = mysqli_num_rows($sql);

    if ($num_rows > 0) {

        guardExportPdfRowCount($format, $num_rows);

        $export = beginExport('credentials', $format, $file_name_prepend . 'Credentials', 'Credentials', summarizeExportFilters($filter_summary));

        while ($row = mysqli_fetch_assoc($sql)) {
            $row['credential_username'] = decryptCredentialEntry($row['credential_username']);
            $row['credential_password'] = decryptCredentialEntry($row['credential_password']);
            addExportRow($export, $row);
        }

        finishExport($export);
    }

    logAudit("Credential", "Export", "$session_name exported $num_rows credential(s) to a " . strtoupper($format) . " file", $client_id);

    exit;

}

if (isset($_POST["import_credentials_csv"])) {

    validateCSRFToken();

    enforceUserPermission('module_credential', 2);

    $client_id = intval($_POST['client_id']);

    enforceClientAccess();

    $error = false;

    if (!empty($_FILES["file"]["tmp_name"])) {
        $file_name = $_FILES["file"]["tmp_name"];
    } else {
        flashAlert("Please select a file to upload.", 'error');
        redirect();
    }

    //Check file is CSV
    $file_extension = strtolower(end(explode('.',$_FILES['file']['name'])));
    $allowed_file_extensions = array('csv');
    if (in_array($file_extension,$allowed_file_extensions) === false){
        $error = true;
        flashAlert("Bad file extension", 'error');
    }

    //Check file isn't empty
    elseif ($_FILES["file"]["size"] < 1){
        $error = true;
        flashAlert("Bad file size (empty?)", 'error');
    }

    //(Else)Check column count
    $f = fopen($file_name, "r");
    $f_columns = fgetcsv($f, 1000, ",");
    if (!$error & count($f_columns) != 6) {
        $error = true;
        flashAlert("Bad column count.", 'error');
    }

    //Else, parse the file
    if (!$error){
        $file = fopen($file_name, "r");
        fgetcsv($file, 1000, ","); // Skip first line
        $row_count = 0;
        $duplicate_count = 0;
        $too_long_count = 0;
        while(($column = fgetcsv($file, 1000, ",")) !== false){
            $duplicate_detect = 0;

            // Nothing client-side guards an uploaded file, and an overlong value is a hard
            // MySQL error - skip the row and report it rather than losing the whole import
            if (checkCredentialLengths([
                'name'        => $column[0] ?? null,
                'description' => $column[1] ?? null,
                'username'    => $column[2] ?? null,
                'password'    => $column[3] ?? null,
                'otp_secret'  => $column[4] ?? null,
                'uri'         => $column[5] ?? null,
            ])) {
                $too_long_count = $too_long_count + 1;
                continue;
            }

            // Name
            if (isset($column[0])) {
                $name = escapeSql($column[0]);
                if (mysqli_num_rows(mysqli_query($mysqli,"SELECT * FROM credentials WHERE credential_name = '$name' AND credential_client_id = $client_id")) > 0){
                    $duplicate_detect = 1;
                }
            }
            // Desc
            if (isset($column[1])) {
                $description = escapeSql($column[1]);
            }
            // User
            if (isset($column[2])) {
                $username = escapeSql(encryptCredentialEntry($column[2]));
            }
            // Pass
            if (isset($column[3])) {
                $password = escapeSql(encryptCredentialEntry($column[3]));
            }
            // OTP
            if (isset($column[4])) {
                $totp = escapeSql($column[4]);
            }
            // URL
            if (isset($column[5])) {
                $uri = escapeSql($column[5]);
            }

            // Check if duplicate was detected
            if ($duplicate_detect == 0){
                //Add
                mysqli_query($mysqli,"INSERT INTO credentials SET credential_name = '$name', credential_description = '$description', credential_uri = '$uri', credential_username = '$username', credential_password = '$password', credential_otp_secret = '$totp', credential_client_id = $client_id");
                $row_count = $row_count + 1;
            } else {
                $duplicate_count = $duplicate_count + 1;
            }
        }
        fclose($file);

        logAudit("Credential", "Import", "$session_name imported $row_count credential(s) via CSV file. $duplicate_count duplicate(s) found and not imported, $too_long_count row(s) skipped for over-length fields", $client_id);

        flashAlert("<strong>$row_count</strong> credential(s) imported, <strong>$duplicate_count</strong> duplicate(s) detected and not imported, <strong>$too_long_count</strong> row(s) skipped for over-length fields", 'warning');

        redirect();
    }
    //Check for any errors, if there are notify user and redirect
    if ($error) {
        redirect();
    }

}

if (isset($_GET['download_credentials_csv_template'])) {

    $delimiter = ",";
    $enclosure = '"';
    $escape    = '\\';
    $filename = "Credentials-Template.csv";

    //create a file pointer
    $f = fopen('php://memory', 'w');

    //set column headers
    $fields = array('Name', 'Description', 'Username', 'Password', 'TOTP', 'URI');
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
