<?php

/*
 * ITFlow - GET/POST request handler for custom fields
 */

if(isset($_POST['create_custom_field'])){

    require_once 'post/admin/admin_custom_field_model.php';

    $table = sanitizeInput($_POST['table']);

    mysqli_query($mysqli,"INSERT INTO custom_fields SET custom_field_table = '$table', custom_field_label = '$label', custom_field_type = '$type'");

    $custom_field_id = mysqli_insert_id($mysqli);

    // Logging
    logAction("Custom Field", "Create", "$session_name created custom field $label", 0, $custom_field_id);

    $_SESSION['alert_message'] = "Custom field <strong>$label</strong> created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_custom_field'])){

    require_once 'post/admin/admin_custom_field_model.php';

    $custom_field_id = intval($_POST['custom_field_id']);

    mysqli_query($mysqli,"UPDATE custom_fields SET custom_field_label = '$label', custom_field_type = '$type' WHERE custom_field_id = $custom_field_id");

    // Logging
    logAction("Custom Field", "Edit", "$session_name edited custom field $label", 0, $custom_field_id);

    $_SESSION['alert_message'] = "Custom field <strong>$label</strong> edited";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_custom_field'])){
    $custom_field_id = intval($_GET['delete_custom_field']);

    // Get Custom Field Label for logging
    $sql = mysqli_query($mysqli,"SELECT custom_field_label FROM custom_fields WHERE custom_field_id = $custom_field_id");
    $row = mysqli_fetch_array($sql);
    $custom_field_label = sanitizeInput($row['custom_field_label']);

    mysqli_query($mysqli,"DELETE FROM custom_fields WHERE custom_field_id = $custom_field_id");

    // Logging
    logAction("Custom Field", "Delete", "$session_name deleted custom field $label");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Custom field <strong>$label</strong> deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}
