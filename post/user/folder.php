<?php

/*
 * ITFlow - GET/POST request handler for folders
 */

if (isset($_POST['create_folder'])) {

    validateTechRole();

    $client_id = intval($_POST['client_id']);
    $folder_location = intval($_POST['folder_location']);
    $folder_name = sanitizeInput($_POST['folder_name']);
    $parent_folder = intval($_POST['parent_folder']);

    // Document folder add query
    $add_folder = mysqli_query($mysqli,"INSERT INTO folders SET folder_name = '$folder_name', parent_folder = $parent_folder, folder_location = $folder_location, folder_client_id = $client_id");
    $folder_id = mysqli_insert_id($mysqli);

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Folder', log_action = 'Create', log_description = '$session_name created folder $folder_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $folder_id");

    $_SESSION['alert_message'] = "Folder <strong>$folder_name</strong> created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['rename_folder'])) {

    validateTechRole();

    $folder_id = intval($_POST['folder_id']);
    $client_id = intval($_POST['client_id']);
    $folder_name = sanitizeInput($_POST['folder_name']);

    // Folder edit query
    mysqli_query($mysqli,"UPDATE folders SET folder_name = '$folder_name' WHERE folder_id = $folder_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Folder', log_action = 'Modify', log_description = '$session_name renamed folder to $folder_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_client_id = $client_id, log_user_id = $session_user_id, log_entity_id = $folder_id");

    $_SESSION['alert_message'] = "Folder <strong>$folder_name</strong> renamed";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['delete_folder'])) {

    validateAdminRole();

    $folder_id = intval($_GET['delete_folder']);

    mysqli_query($mysqli,"DELETE FROM folders WHERE folder_id = $folder_id");

    // Move files in deleted folder back to the root folder /
    $sql_documents = mysqli_query($mysqli,"SELECT * FROM documents WHERE document_folder_id = $folder_id");
    while($row = mysqli_fetch_array($sql_documents)) {
        $document_id = intval($row['document_id']);

        mysqli_query($mysqli,"UPDATE documents SET document_folder_id = 0 WHERE document_id = $document_id");
    }

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Folder', log_action = 'Delete', log_description = '$folder_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Folder deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}