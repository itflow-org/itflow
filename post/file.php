<?php

/*
 * ITFlow - GET/POST request handler for client files/uploads
 */

if (isset($_POST['upload_files'])) {
    $client_id = intval($_POST['client_id']);
    $folder_id = intval($_POST['folder_id']);
    
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

        if ($file_reference_name = checkFileUpload($single_file, array('jpg', 'jpeg', 'gif', 'png', 'webp', 'pdf', 'txt', 'md', 'doc', 'docx', 'odt', 'csv', 'xls', 'xlsx', 'ods', 'pptx', 'odp', 'zip', 'tar', 'gz', 'xml', 'msg', 'json', 'wav', 'mp3', 'ogg', 'mov', 'mp4', 'av1'))) {
            
            $file_tmp_path = $_FILES['file']['tmp_name'][$i];

            $file_name = sanitizeInput($_FILES['file']['name'][$i]);
            $extarr = explode('.', $_FILES['file']['name'][$i]);
            $file_extension = sanitizeInput(strtolower(end($extarr)));

            // directory in which the uploaded file will be moved
            $upload_file_dir = "uploads/clients/$client_id/";
            $dest_path = $upload_file_dir . $file_reference_name;

            move_uploaded_file($file_tmp_path, $dest_path);

            // Extract .ext from reference file name to be used to store SHA256 hash
            $file_hash = strstr($file_reference_name, '.', true) ?: $file_reference_name;

            mysqli_query($mysqli,"INSERT INTO files SET file_reference_name = '$file_reference_name', file_name = '$file_name', file_ext = '$file_extension', file_hash = '$file_hash', file_folder_id = $folder_id, file_client_id = $client_id");

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

    validateTechRole();

    $file_id = intval($_POST['file_id']);
    $client_id = intval($_POST['client_id']);
    $file_name = sanitizeInput($_POST['file_name']);

    // Folder edit query
    mysqli_query($mysqli,"UPDATE files SET file_name = '$file_name' WHERE file_id = $file_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'File', log_action = 'Rename', log_description = '$session_name renamed file to $file_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $file_id");

    $_SESSION['alert_message'] = "File <strong>$file_name</strong> renamed";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['move_file'])) {

    validateTechRole();

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

if (isset($_POST['delete_file'])) {

    validateAdminRole();
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
