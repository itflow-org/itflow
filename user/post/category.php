<?php

/*
 * ITFlow - GET/POST request handler for categories ('category')
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_category'])) {

    require_once 'category_model.php';

    mysqli_query($mysqli,"INSERT INTO categories SET category_name = '$name', category_type = '$type', category_color = '$color'");

    $category_id = mysqli_insert_id($mysqli);

    // Logging
    logAction("Category", "Create", "$session_name created category $type $name", 0, $category_id);

    $_SESSION['alert_message'] = "Category $type <strong>$name</strong> created";

    redirect();

}
