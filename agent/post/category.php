<?php

/*
 * ITFlow - GET/POST request handler for categories ('category')
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_category'])) {

    validateCSRFToken($_POST['csrf_token']);

    require_once 'category_model.php';

    mysqli_query($mysqli,"INSERT INTO categories SET category_name = '$name', category_type = '$type', category_color = '$color'");

    $category_id = mysqli_insert_id($mysqli);

    logAudit("Category", "Create", "$session_name created category $type $name", 0, $category_id);

    flash_alert("Category $type <strong>$name</strong> created");

    redirect();

}
