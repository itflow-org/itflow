<?php

/*
 * ITFlow - GET/POST request handler for tagging
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_tag'])) {

    require_once 'tag_model.php';

    mysqli_query($mysqli,"INSERT INTO tags SET tag_name = '$name', tag_type = $type, tag_color = '$color', tag_icon = '$icon'");

    $tag_id = mysqli_insert_id($mysqli);

    // Logging
    logAction("Tag", "Create", "$session_name created tag $name", 0, $tag_id);

    $_SESSION['alert_message'] = "Tag <strong>$name</strong> created";

    redirect();

}
