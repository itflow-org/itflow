<?php

/*
 * ITFlow - GET/POST request handler for showing custom links on navbars
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_custom_link'])) {

    $name = sanitizeInput($_POST['name']);
    $uri = sanitizeInput($_POST['uri']);
    $new_tab = intval($_POST['new_tab'] ?? 0);
    $icon = preg_replace("/[^0-9a-zA-Z-]/", "", sanitizeInput($_POST['icon']));
    $order = intval($_POST['order'] ?? 0);
    $location = intval($_POST['location']);

    mysqli_query($mysqli,"INSERT INTO custom_links SET custom_link_name = '$name', custom_link_uri = '$uri', custom_link_new_tab = $new_tab, custom_link_icon = '$icon', custom_link_order = $order, custom_link_location = $location");

    $custom_link_id = mysqli_insert_id($mysqli);

    logAction("Custom Link", "Create", "$session_name created custom link $name -> $uri", 0, $custom_link_id);

    flash_alert("Custom link <strong>$name</strong> created");

    redirect();

}

if (isset($_POST['edit_custom_link'])) {

    $custom_link_id = intval($_POST['custom_link_id']);
    $name = sanitizeInput($_POST['name']);
    $uri = sanitizeInput($_POST['uri']);
    $new_tab = intval($_POST['new_tab'] ?? 0);
    $icon = preg_replace("/[^0-9a-zA-Z-]/", "", sanitizeInput($_POST['icon']));
    $order = intval($_POST['order'] ?? 0);
    $location = intval($_POST['location']);

    mysqli_query($mysqli,"UPDATE custom_links SET custom_link_name = '$name', custom_link_uri = '$uri', custom_link_new_tab = $new_tab, custom_link_icon = '$icon', custom_link_order = $order, custom_link_location = $location WHERE custom_link_id = $custom_link_id");

    logAction("Custom Link", "Edit", "$session_name edited custom link $name -> $uri", 0, $custom_link_id);

    flash_alert("Custom Link <strong>$name</strong> edited");

    redirect();

}

if (isset($_GET['delete_custom_link'])) {
    
    $custom_link_id = intval($_GET['delete_custom_link']);

    // Get Custom Link name and uri for logging
    $sql = mysqli_query($mysqli,"SELECT custom_link_name, custom_link_uri FROM custom_links WHERE custom_link_id = $custom_link_id");
    $row = mysqli_fetch_array($sql);
    $custom_link_name = sanitizeInput($row['custom_link_name']);
    $custom_link_uri = sanitizeInput($row['custom_link_uri']);

    mysqli_query($mysqli,"DELETE FROM custom_links WHERE custom_link_id = $custom_link_id");

    logAction("Custom Link", "Delete", "$session_name deleted custom link $custom_link_name -> $custom_link_uri");

    flash_alert("Custom Link <strong>$name</strong> deleted", 'error');

    redirect();

}
