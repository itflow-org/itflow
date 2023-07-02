<?php

/*
 * ITFlow - GET/POST request handler for tagging
 */

if (isset($_POST['add_tag'])) {

    require_once('post/tag_model.php');

    mysqli_query($mysqli,"INSERT INTO tags SET tag_name = '$name', tag_type = $type, tag_color = '$color', tag_icon = '$icon'");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Tag', log_action = 'Create', log_description = '$name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Tag added";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_tag'])) {

    require_once('post/tag_model.php');

    $tag_id = intval($_POST['tag_id']);

    mysqli_query($mysqli,"UPDATE tags SET tag_name = '$name', tag_type = $type, tag_color = '$color', tag_icon = '$icon' WHERE tag_id = $tag_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Tag', log_action = 'Modify', log_description = '$name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Tag modified";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['delete_tag'])) {
    $tag_id = intval($_GET['delete_tag']);

    mysqli_query($mysqli,"DELETE FROM tags WHERE tag_id = $tag_id");
    mysqli_query($mysqli,"DELETE FROM client_tags WHERE client_tag_tag_id = $tag_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Tag', log_action = 'Delete', log_description = '$tag_id', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Tag deleted";
    $_SESSION['alert_type'] = "error";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}
