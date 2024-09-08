<?php

/*
 * ITFlow - GET/POST request handler for custom fields
 */

if(isset($_POST['create_custom_field'])){

    require_once 'post/custom_field_model.php';

    $table = sanitizeInput($_POST['table']);

    mysqli_query($mysqli,"INSERT INTO custom_fields SET custom_field_table = '$table', custom_field_label = '$label', custom_field_type = '$type'");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Custom Field', log_action = 'Create', log_description = '$label', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Custom field created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_POST['edit_custom_field'])){

    require_once 'post/custom_field_model.php';

    $custom_field_id = intval($_POST['custom_field_id']);

    mysqli_query($mysqli,"UPDATE custom_fields SET custom_field_label = '$label', custom_field_type = '$type' WHERE custom_field_id = $custom_field_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Custom Field', log_action = 'Edit', log_description = '$label', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "You edited the custom field";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if(isset($_GET['delete_custom_field'])){
    $custom_field_id = intval($_GET['delete_custom_field']);

    mysqli_query($mysqli,"DELETE FROM custom_fields WHERE custom_field_id = $custom_field_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Custom Fields', log_action = 'Delete', log_description = '$custom_field_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "You deleted custom field";
    $_SESSION['alert_type'] = "error";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}
