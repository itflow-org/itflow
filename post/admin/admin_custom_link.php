<?php

/*
 * ITFlow - GET/POST request handler for showing custom links on navbars
 */

if (isset($_POST['add_custom_link'])) {

    $name = sanitizeInput($_POST['name']);
    $uri = sanitizeInput($_POST['uri']);
    $new_tab = intval($_POST['new_tab']);
    $icon = preg_replace("/[^0-9a-zA-Z-]/", "", sanitizeInput($_POST['icon']));
    $order = intval($_POST['order']);
    $location = intval($_POST['location']);

    mysqli_query($mysqli,"INSERT INTO custom_links SET custom_link_name = '$name', custom_link_uri = '$uri', custom_link_new_tab = $new_tab, custom_link_icon = '$icon', custom_link_order = $order, custom_link_location = $location");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Custom Link', log_action = 'Create', log_description = '$session_name created custom link $name --> $uri', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Custom link successfully created!";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_custom_link'])) {

    $custom_link_id = intval($_POST['custom_link_id']);
    $name = sanitizeInput($_POST['name']);
    $uri = sanitizeInput($_POST['uri']);
    $new_tab = intval($_POST['new_tab']);
    $icon = preg_replace("/[^0-9a-zA-Z-]/", "", sanitizeInput($_POST['icon']));
    $order = intval($_POST['order']);
    $location = intval($_POST['location']);

    mysqli_query($mysqli,"UPDATE custom_links SET custom_link_name = '$name', custom_link_uri = '$uri', custom_link_new_tab = $new_tab, custom_link_icon = '$icon', custom_link_order = $order, custom_link_location = $location WHERE custom_link_id = $custom_link_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Custom Link', log_action = 'Modify', log_description = '$session_name edited the custom link $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Custom Link modified";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['delete_custom_link'])) {
    $custom_link_id = intval($_GET['delete_custom_link']);

    mysqli_query($mysqli,"DELETE FROM custom_links WHERE custom_link_id = $custom_link_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Custom Link', log_action = 'Delete', log_description = '$session_name deleted a custom link', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Custom Link deleted!";
    $_SESSION['alert_type'] = "error";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}
