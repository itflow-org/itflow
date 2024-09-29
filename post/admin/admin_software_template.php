<?php

// Software/License Templates

// Import shared code from software-side tickets as we reuse functions
require_once 'post/user/software.php';


if (isset($_POST['add_software_template'])) {

    $name = sanitizeInput($_POST['name']);
    $version = sanitizeInput($_POST['version']);
    $description = sanitizeInput($_POST['description']);
    $type = sanitizeInput($_POST['type']);
    $license_type = sanitizeInput($_POST['license_type']);
    $notes = sanitizeInput($_POST['notes']);

    mysqli_query($mysqli,"INSERT INTO software SET software_name = '$name', software_version = '$version', software_description = '$description', software_type = '$type', software_license_type = '$license_type', software_notes = '$notes', software_template = 1, software_client_id = 0");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Software Template', log_action = 'Create', log_description = '$session_user_name created software template $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Software template created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_software_template'])) {

    $software_id = intval($_POST['software_id']);
    $name = sanitizeInput($_POST['name']);
    $version = sanitizeInput($_POST['version']);
    $description = sanitizeInput($_POST['description']);
    $type = sanitizeInput($_POST['type']);
    $license_type = sanitizeInput($_POST['license_type']);
    $notes = sanitizeInput($_POST['notes']);

    mysqli_query($mysqli,"UPDATE software SET software_name = '$name', software_version = '$version', software_description = '$description', software_type = '$type', software_license_type = '$license_type', software_notes = '$notes' WHERE software_id = $software_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Software Teplate', log_action = 'Modify', log_description = '$session_name modified software template $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Software template updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}
