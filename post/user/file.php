<?php

/*
 * ITFlow - GET/POST request handler for client files/uploads
 */

if (isset($_POST['upload_files'])) {
    
    enforceUserPermission('module_support', 2);    

    $client_id = intval($_POST['client_id']);
    $folder_id = intval($_POST['folder_id']);
    $description = sanitizeInput($_POST['description']);

    if (!file_exists("uploads/clients/$client_id")) {
        mkdir("uploads/clients/$client_id");
    }

    for ($i = 0; $i < count($_FILES['file']['name']); $i++) {
        // Extract file details for this iteration
        $single_file = [
            'name' => $_FILES['file']['name'][$i],
            'type' => $_FILES['file']['type'][$i],
            'tmp_name' => $_FILES['file']['tmp_name'][$i],
            'error' => $_FILES['file']['error'][$i],
            'size' => $_FILES['file']['size'][$i]
        ];

        if ($file_reference_name = checkFileUpload($single_file, array('jpg', 'jpeg', 'gif', 'png', 'webp', 'pdf', 'txt', 'md', 'doc', 'docx', 'odt', 'csv', 'xls', 'xlsx', 'ods', 'pptx', 'odp', 'zip', 'tar', 'gz', 'xml', 'msg', 'json', 'wav', 'mp3', 'ogg', 'mov', 'mp4', 'av1', 'ovpn', 'cfg', 'ps1', 'vsdx', 'drawio', 'pfx', 'pages', 'numbers', 'unf'))) {

            $file_tmp_path = $_FILES['file']['tmp_name'][$i];

            $file_name = sanitizeInput($_FILES['file']['name'][$i]);
            $extarr = explode('.', $_FILES['file']['name'][$i]);
            $file_extension = sanitizeInput(strtolower(end($extarr)));

            // Extract the file mime type and size
            $file_mime_type = sanitizeInput($single_file['type']);
            $file_size = intval($single_file['size']);

            // directory in which the uploaded file will be moved
            $upload_file_dir = "uploads/clients/$client_id/";
            $dest_path = $upload_file_dir . $file_reference_name;

            move_uploaded_file($file_tmp_path, $dest_path);

            // Extract .ext from reference file name to be used to store SHA256 hash
            $file_hash = strstr($file_reference_name, '.', true) ?: $file_reference_name;

            mysqli_query($mysqli,"INSERT INTO files SET file_reference_name = '$file_reference_name', file_name = '$file_name', file_description = '$description', file_ext = '$file_extension', file_hash = '$file_hash', file_mime_type = '$file_mime_type', file_size = $file_size, file_created_by = $session_user_id, file_folder_id = $folder_id, file_client_id = $client_id");

            //Logging
            $file_id = intval(mysqli_insert_id($mysqli));
            mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'File', log_action = 'Upload', log_description = '$session_name uploaded $file_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $file_id");
        } else {
            $_SESSION['alert_message'] = 'There was an error moving the file to upload directory. Please make sure the upload directory is writable by web server.';
        }
    }
    // Redirect at the end, after processing all files
    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['rename_file'])) {

    enforceUserPermission('module_support', 2);

    $file_id = intval($_POST['file_id']);
    $client_id = intval($_POST['client_id']);
    $file_name = sanitizeInput($_POST['file_name']);
    $file_description = sanitizeInput($_POST['file_description']);

    // Folder edit query
    mysqli_query($mysqli,"UPDATE files SET file_name = '$file_name' ,file_description = '$file_description' WHERE file_id = $file_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'File', log_action = 'Rename', log_description = '$session_name renamed file to $file_name $file_description', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $file_id");

    $_SESSION['alert_message'] = "File <strong>$file_name</strong> renamed";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['move_file'])) {

    enforceUserPermission('module_support', 2);

    $file_id = intval($_POST['file_id']);
    $client_id = intval($_POST['client_id']);
    $folder_id = intval($_POST['folder_id']);

    // Document edit query
    mysqli_query($mysqli,"UPDATE files SET file_folder_id = $folder_id WHERE file_id = $file_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'File', log_action = 'Move', log_description = '$session_name moved file', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $file_id");


    $_SESSION['alert_message'] = "File moved";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['archive_file'])) {

    enforceUserPermission('module_support', 2);

    $file_id = intval($_GET['archive_file']);

    // Get Contact Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT file_name, file_client_id FROM files WHERE file_id = $file_id");
    $row = mysqli_fetch_array($sql);
    $file_name = sanitizeInput($row['file_name']);
    $client_id = intval($row['file_client_id']);

    mysqli_query($mysqli,"UPDATE files SET file_archived_at = NOW() WHERE file_id = $file_id");

    //logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'File', log_action = 'Archive', log_description = '$session_name archived file $file_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $file_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "File <strong>$file_name</strong> archived";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['delete_file'])) {

    enforceUserPermission('module_support', 3);
    validateCSRFToken($_POST['csrf_token']);

    $file_id = intval($_POST['file_id']);

    $sql_file = mysqli_query($mysqli,"SELECT * FROM files WHERE file_id = $file_id");
    $row = mysqli_fetch_array($sql_file);
    $client_id = intval($row['file_client_id']);
    $file_name = sanitizeInput($row['file_name']);
    $file_reference_name = sanitizeInput($row['file_reference_name']);

    unlink("uploads/clients/$client_id/$file_reference_name");

    mysqli_query($mysqli,"DELETE FROM files WHERE file_id = $file_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'File', log_action = 'Delete', log_description = '$file_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = '$client_id', log_user_id = $session_user_id, log_entity_id = $file_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "File <strong>$file_name</strong> deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['bulk_delete_files'])) {

    enforceUserPermission('module_support', 3);
    validateCSRFToken($_POST['csrf_token']);

    // Get selected file Count
    $file_count = count($_POST['file_ids']);

    // Delete file loop
    if ($_POST['file_ids']) {
        foreach($_POST['file_ids'] as $file_id) {

            $file_id = intval($file_id);

            $sql_file = mysqli_query($mysqli,"SELECT * FROM files WHERE file_id = $file_id");
            $row = mysqli_fetch_array($sql_file);
            $client_id = intval($row['file_client_id']);
            $file_name = sanitizeInput($row['file_name']);
            $file_reference_name = sanitizeInput($row['file_reference_name']);

            unlink("uploads/clients/$client_id/$file_reference_name");

            mysqli_query($mysqli,"DELETE FROM files WHERE file_id = $file_id");

            // Log each invidual file deletion
            mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'File', log_action = 'Delete', log_description = '$file_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = '$client_id', log_user_id = $session_user_id");
        }
    }

    // Log the bulk delete action
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'File', log_action = 'Bulk Delete', log_description = '$session_name deleted $file_count files', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = '$client_id', log_user_id = $session_user_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "You deleted <strong>$file_count</strong> files";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['bulk_move_files'])) {

    enforceUserPermission('module_support', 2);
    validateCSRFToken($_POST['csrf_token']);

    $folder_id = intval($_POST['bulk_folder_id']);

    // Get folder name for logging and Notification
    $sql = mysqli_query($mysqli,"SELECT folder_name, folder_client_id FROM folders WHERE folder_id = $folder_id");
    $row = mysqli_fetch_array($sql);
    $folder_name = sanitizeInput($row['folder_name']);
    $client_id = intval($row['folder_client_id']);

    // Get Selected file Count
    $file_count = count($_POST['file_ids']);

    // Move Documents to Folder Loop
    if (!empty($_POST['file_ids'])) {
        foreach($_POST['file_ids'] as $file_id) {
            $file_id = intval($file_id);
            // Get file name for logging
            $sql = mysqli_query($mysqli,"SELECT file_name FROM files WHERE file_id = $file_id");
            $row = mysqli_fetch_array($sql);
            $file_name = sanitizeInput($row['file_name']);

            // file move query
            mysqli_query($mysqli,"UPDATE files SET file_folder_id = $folder_id WHERE file_id = $file_id");

            //Logging
            mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'File', log_action = 'Move', log_description = '$session_name moved file $file_name to folder $folder_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $file_id");
        }
    }

    $_SESSION['alert_message'] = "You moved <b>$file_count</b> files to the folder <b>$folder_name</b>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['link_asset_to_file'])) {

    enforceUserPermission('module_support', 2);

    $client_id = intval($_POST['client_id']);
    $file_id = intval($_POST['file_id']);
    $asset_id = intval($_POST['asset_id']);

    // Contact add query
    mysqli_query($mysqli,"INSERT INTO asset_files SET asset_id = $asset_id, file_id = $file_id");

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'File', log_action = 'Link', log_description = 'Created File Asset link', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Asset linked with File";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['unlink_asset_from_file'])) {

    enforceUserPermission('module_support', 2);
    
    $asset_id = intval($_GET['asset_id']);
    $file_id = intval($_GET['file_id']);

    mysqli_query($mysqli,"DELETE FROM asset_files WHERE asset_id = $asset_id AND file_id = $file_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'File', log_action = 'unLink', log_description = 'File Asset link removed', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Asset has been unlinked";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}