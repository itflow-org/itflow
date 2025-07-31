<?php

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['edit_theme_settings'])) {

    validateCSRFToken($_POST['csrf_token']);

    $dark_mode = intval($_POST['dark_mode'] ?? 0);

    $theme = preg_replace("/[^0-9a-zA-Z-]/", "", sanitizeInput($_POST['edit_theme_settings']));

    mysqli_query($mysqli,"UPDATE settings SET config_theme = '$theme', config_theme_dark = $dark_mode WHERE company_id = 1");

    // Logging
    logAction("Settings", "Edit", "$session_name edited theme settings $dark_mode");

    $_SESSION['alert_message'] = "Changed theme to <strong>$theme</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
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

    // Logging
    logAction("Settings", "Edit", "$session_name changed the favicon");

    $_SESSION['alert_message'] = "Favicon Updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}
