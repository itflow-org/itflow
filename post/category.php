<?php

/*
 * ITFlow - GET/POST request handler for categories
 */

if (isset($_POST['add_category'])) {

    require_once('post/category_model.php');

    mysqli_query($mysqli,"INSERT INTO categories SET category_name = '$name', category_type = '$type', category_color = '$color'");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Category', log_action = 'Create', log_description = '$name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Category added";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_category'])) {

    require_once('post/category_model.php');

    $category_id = intval($_POST['category_id']);

    mysqli_query($mysqli,"UPDATE categories SET category_name = '$name', category_type = '$type', category_color = '$color' WHERE category_id = $category_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Category', log_action = 'Modify', log_description = '$name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Category modified";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['archive_category'])) {
    $category_id = intval($_GET['archive_category']);

    mysqli_query($mysqli,"UPDATE categories SET category_archived_at = NOW() WHERE category_id = $category_id");

    //logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Category', log_action = 'Archive', log_description = '$category_id'");

    $_SESSION['alert_message'] = "Category Archived";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['unarchive_category'])) {
    $category_id = intval($_GET['unarchive_category']);

    mysqli_query($mysqli,"UPDATE categories SET category_archived_at = NULL WHERE category_id = $category_id");

    //logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Category', log_action = 'Unarchive', log_description = '$category_id'");

    $_SESSION['alert_message'] = "Category Unarchived";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['delete_category'])) {
    $category_id = intval($_GET['delete_category']);

    mysqli_query($mysqli,"DELETE FROM categories WHERE category_id = $category_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Category', log_action = 'Delete', log_description = '$category_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Category deleted";
    $_SESSION['alert_type'] = "error";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}
