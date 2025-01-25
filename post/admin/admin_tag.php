<?php

/*
 * ITFlow - GET/POST request handler for tagging
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_tag'])) {

    require_once 'post/admin/admin_tag_model.php';

    mysqli_query($mysqli,"INSERT INTO tags SET tag_name = '$name', tag_type = $type, tag_color = '$color', tag_icon = '$icon'");

    $tag_id = mysqli_insert_id($mysqli);

    // Logging
    logAction("Tag", "Create", "$session_name created tag $name", 0, $tag_id);

    $_SESSION['alert_message'] = "Tag <strong>$name</strong> created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_tag'])) {

    require_once 'post/admin/admin_tag_model.php';

    $tag_id = intval($_POST['tag_id']);

    mysqli_query($mysqli,"UPDATE tags SET tag_name = '$name', tag_type = $type, tag_color = '$color', tag_icon = '$icon' WHERE tag_id = $tag_id");

    // Logging
    logAction("Tag", "Edit", "$session_name edited tag $name", 0, $tag_id);

    $_SESSION['alert_message'] = "Tag <strong>$name</strong> edited";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['delete_tag'])) {
    $tag_id = intval($_GET['delete_tag']);

    // Get Tag Name for logging
    $sql = mysqli_query($mysqli,"SELECT tag_name FROM tags WHERE tag_id = $tag_id");
    $row = mysqli_fetch_array($sql);
    $tag_name = sanitizeInput($row['tag_name']);

    mysqli_query($mysqli,"DELETE FROM tags WHERE tag_id = $tag_id");
    mysqli_query($mysqli,"DELETE FROM client_tags WHERE tag_id = $tag_id");

    // Logging
    logAction("Tag", "Delete", "$session_name deleted tag $tag_name");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Tag <strong>$tag_name</strong> deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}
