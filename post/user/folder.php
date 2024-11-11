<?php

/*
 * ITFlow - GET/POST request handler for folders
 */

if (isset($_POST['create_folder'])) {

    enforceUserPermission('module_support', 2);

    $client_id = intval($_POST['client_id']);
    $folder_location = intval($_POST['folder_location']);
    $folder_name = sanitizeInput($_POST['folder_name']);
    $parent_folder = intval($_POST['parent_folder']);

    // Document folder add query
    $add_folder = mysqli_query($mysqli,"INSERT INTO folders SET folder_name = '$folder_name', parent_folder = $parent_folder, folder_location = $folder_location, folder_client_id = $client_id");
    $folder_id = mysqli_insert_id($mysqli);

    // Logging
    logAction("Folder", "Create", "$session_name created folder $folder_name", $client_id, $folder_id);

    $_SESSION['alert_message'] = "Folder <strong>$folder_name</strong> created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['rename_folder'])) {

    enforceUserPermission('module_support', 2);

    $folder_id = intval($_POST['folder_id']);
    $folder_name = sanitizeInput($_POST['folder_name']);

    // Get old Folder Name Client ID for Logging
    $sql = mysqli_query($mysqli,"SELECT folder_name, folder_client_id FROM folders WHERE folder_id = $folder_id");
    $row = mysqli_fetch_array($sql);
    $old_folder_name = sanitizeInput($row['folder_name']);
    $client_id = intval($row['folder_client_id']);

    // Folder edit query
    mysqli_query($mysqli,"UPDATE folders SET folder_name = '$folder_name' WHERE folder_id = $folder_id");

    //Logging
    logAction("Folder", "Rename", "$session_name renamed folder $old_folder_name to $folder_name", $client_id, $folder_id);

    $_SESSION['alert_message'] = "Folder <strong>$old_folder_name</strong> renamed to <strong>$folder_name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['delete_folder'])) {

    enforceUserPermission('module_support', 3);

    $folder_id = intval($_GET['delete_folder']);

    // Get Folder Name Client ID for Logging
    $sql = mysqli_query($mysqli,"SELECT folder_name, folder_client_id FROM folders WHERE folder_id = $folder_id");
    $row = mysqli_fetch_array($sql);
    $folder_name = sanitizeInput($row['folder_name']);
    $client_id = intval($row['folder_client_id']);

    mysqli_query($mysqli,"DELETE FROM folders WHERE folder_id = $folder_id");

    // Move files in deleted folder back to the root folder /
    $sql_documents = mysqli_query($mysqli,"SELECT * FROM documents WHERE document_folder_id = $folder_id");
    while($row = mysqli_fetch_array($sql_documents)) {
        $document_id = intval($row['document_id']);

        mysqli_query($mysqli,"UPDATE documents SET document_folder_id = 0 WHERE document_id = $document_id");
    }

    //Logging
    logAction("Folder", "Delete", "$session_name deleted folder $folder_name", $client_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Folder <strong>$folder_name</strong> deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}
