<?php

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['edit_theme_settings'])) {

    validateCSRFToken($_POST['csrf_token']);

    $theme = preg_replace("/[^0-9a-zA-Z-]/", "", escapeSql($_POST['edit_theme_settings']));

    mysqli_query($mysqli,"UPDATE settings SET config_theme = '$theme' WHERE company_id = 1");

    logAudit("Settings", "Edit", "$session_name edited theme settings $dark_mode");

    flashAlert("Changed theme to <strong>$theme</strong>");

    redirect();

}

if (isset($_POST['edit_favicon_settings'])) {

    validateCSRFToken($_POST['csrf_token']);

    // Check to see if a file is attached
    if (isset($_FILES['file']['tmp_name'])) {
        if ($new_file_name = checkFileUpload($_FILES['file'], array('ico'))) {
            $file_tmp_path = $_FILES['file']['tmp_name'];

            // Delete old file
            if(file_exists("../uploads/favicon.ico")) {
                unlink("../uploads/favicon.ico");
            }

            // directory in which the uploaded file will be moved
            $upload_file_dir = "../uploads/";
            //Force File Name
            $new_file_name = "favicon.ico";
            $dest_path = $upload_file_dir . $new_file_name;

            move_uploaded_file($file_tmp_path, $dest_path);
        }
    }

    logAudit("Settings", "Edit", "$session_name changed the favicon");

    flashAlert("Favicon Updated");

    redirect();

}

if (isset($_GET['reset_favicon'])) {

    validateCSRFToken($_GET['csrf_token']);

    if (file_exists("../uploads/favicon.ico")) {
        unlink("../uploads/favicon.ico");
    }

    logAudit("Settings", "Edit", "$session_name reset Favicon");

    flashAlert("Favicon reset", 'error');

    redirect();

}
