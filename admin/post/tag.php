<?php

/*
 * ITFlow - GET/POST request handler for tagging
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_tag'])) {

    validateCSRFToken($_POST['csrf_token']);

    require_once 'tag_model.php';

    mysqli_query($mysqli,"INSERT INTO tags SET tag_name = '$name', tag_type = $type, tag_color = '$color', tag_icon = '$icon'");

    $tag_id = mysqli_insert_id($mysqli);

    logAudit("Tag", "Create", "$session_name created tag $name", 0, $tag_id);

    flash_alert("Tag <strong>$name</strong> created");

    redirect();

}

if (isset($_POST['edit_tag'])) {

    validateCSRFToken($_POST['csrf_token']);

    require_once 'post/tag_model.php';

    $tag_id = intval($_POST['tag_id']);

    mysqli_query($mysqli,"UPDATE tags SET tag_name = '$name', tag_color = '$color', tag_icon = '$icon' WHERE tag_id = $tag_id");

    logAudit("Tag", "Edit", "$session_name edited tag $name", 0, $tag_id);

    flash_alert("Tag <strong>$name</strong> edited");

    redirect();

}

if (isset($_GET['delete_tag'])) {

    validateCSRFToken($_GET['csrf_token']);

    $tag_id = intval($_GET['delete_tag']);

    $tag_name = escapeSql(getFieldById('tags', $tag_id, 'tag_name'));

    mysqli_query($mysqli,"DELETE FROM tags WHERE tag_id = $tag_id");

    logAudit("Tag", "Delete", "$session_name deleted tag $tag_name");

    flash_alert("Tag <strong>$tag_name</strong> deleted", 'error');

    redirect();

}
