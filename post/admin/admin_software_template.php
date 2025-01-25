<?php

// Software/License Templates

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

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

    $software_id = mysqli_insert_id($mysqli);

    // Logging
    logAction("Software Template", "Create", "$session_name created software template $name", 0, $software_id);

    $_SESSION['alert_message'] = "Software template <strong>$name</strong> created";

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

    // Logging
    logAction("Software Template", "Edit", "$session_name edited software template $name", 0, $software_id);

    $_SESSION['alert_message'] = "Software template <strong>$name</strong> edited";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}
