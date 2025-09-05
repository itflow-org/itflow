<?php

/*
 * ITFlow - GET/POST request handler for tagging
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_tag'])) {

    require_once 'tag_model.php';

    mysqli_query($mysqli,"INSERT INTO tags SET tag_name = '$name', tag_type = $type, tag_color = '$color', tag_icon = '$icon'");

    $tag_id = mysqli_insert_id($mysqli);

    logAction("Tag", "Create", "$session_name created tag $name", 0, $tag_id);

    flash_alert("Tag <strong>$name</strong> created");

    redirect();

}

if (isset($_POST['edit_tag'])) {

    require_once 'post/tag_model.php';

    $tag_id = intval($_POST['tag_id']);

    mysqli_query($mysqli,"UPDATE tags SET tag_name = '$name', tag_type = $type, tag_color = '$color', tag_icon = '$icon' WHERE tag_id = $tag_id");

    logAction("Tag", "Edit", "$session_name edited tag $name", 0, $tag_id);

    flash_alert("Tag <strong>$name</strong> edited");

    redirect();

}

if (isset($_GET['delete_tag'])) {
    
    $tag_id = intval($_GET['delete_tag']);
    
    $tag_name = sanitizeInput(getFieldById('tags', $tag_id, 'tag_name'));

    mysqli_query($mysqli,"DELETE FROM tags WHERE tag_id = $tag_id");

    logAction("Tag", "Delete", "$session_name deleted tag $tag_name");

    flash_alert("Tag <strong>$tag_name</strong> deleted", 'error');

    redirect();

}
