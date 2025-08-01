<?php

// Software/License Templates

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_software_template'])) {

    $name = sanitizeInput($_POST['name']);
    $version = sanitizeInput($_POST['version']);
    $description = sanitizeInput($_POST['description']);
    $type = sanitizeInput($_POST['type']);
    $license_type = sanitizeInput($_POST['license_type']);
    $notes = sanitizeInput($_POST['notes']);

    mysqli_query($mysqli,"INSERT INTO software_templates SET software_template_name = '$name', software_template_version = '$version', software_template_description = '$description', software_template_type = '$type', software_template_license_type = '$license_type', software_template_notes = '$notes'");

    $software_template_id = mysqli_insert_id($mysqli);

    // Logging
    logAction("Software Template", "Create", "$session_name created software template $name", 0, $software_template_id);

    $_SESSION['alert_message'] = "Software template <strong>$name</strong> created";

    redirect();

}

if (isset($_POST['edit_software_template'])) {

    $software_template_id = intval($_POST['software_template_id']);
    $name = sanitizeInput($_POST['name']);
    $version = sanitizeInput($_POST['version']);
    $description = sanitizeInput($_POST['description']);
    $type = sanitizeInput($_POST['type']);
    $license_type = sanitizeInput($_POST['license_type']);
    $notes = sanitizeInput($_POST['notes']);

    mysqli_query($mysqli,"UPDATE software_templates SET software_template_name = '$name', software_template_version = '$version', software_template_description = '$description', software_template_type = '$type', software_template_license_type = '$license_type', software_template_notes = '$notes' WHERE software_template_id = $software_template_id");

    // Logging
    logAction("Software Template", "Edit", "$session_name edited software template $name", 0, $software_template_id);

    $_SESSION['alert_message'] = "Software template <strong>$name</strong> edited";

    redirect();

}

if (isset($_GET['delete_software_template'])) {

    $software_template_id = intval($_GET['delete_software_template']);

    // Get Software Template Name for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT software_template_name FROM software_templates WHERE software_template_id = $software_template_id");
    $row = mysqli_fetch_array($sql);
    $software_template_name = sanitizeInput($row['software_template_name']);

    mysqli_query($mysqli,"DELETE FROM software_templates WHERE software_template_id = $software_template_id");

    //Logging
    logAction("Software Template", "Delete", "$session_name deleted software template $software_template_name");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Software Template <strong>$software_template_name</strong> deleted";

    redirect();

}
