<?php

/*
 * ITFlow - GET/POST request handler for categories ('category')
 */

if (isset($_POST['add_category'])) {

    require_once 'post/admin/admin_category_model.php';

    mysqli_query($mysqli,"INSERT INTO categories SET category_name = '$name', category_type = '$type', category_color = '$color'");

    $category_id = mysqli_insert_id($mysqli);

    // Logging
    logAction("Category", "Create", "$session_name created category $type $name", 0, $category_id);

    $_SESSION['alert_message'] = "Category $type <strong>$name</strong> created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_category'])) {

    require_once 'post/admin/admin_category_model.php';

    $category_id = intval($_POST['category_id']);

    mysqli_query($mysqli,"UPDATE categories SET category_name = '$name', category_type = '$type', category_color = '$color' WHERE category_id = $category_id");

    // Logging
    logAction("Category", "Edit", "$session_name edited category $type $name", 0, $category_id);

    $_SESSION['alert_message'] = "Category $type <strong>$name</strong> edited";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['archive_category'])) {
    
    $category_id = intval($_GET['archive_category']);

    // Get Category Name and Type for logging
    $sql = mysqli_query($mysqli,"SELECT category_name, category_type FROM categories WHERE category_id = $category_id");
    $row = mysqli_fetch_array($sql);
    $category_name = sanitizeInput($row['category_name']);
    $category_type = sanitizeInput($row['category_type']);

    mysqli_query($mysqli,"UPDATE categories SET category_archived_at = NOW() WHERE category_id = $category_id");

    // Logging
    logAction("Category", "Archive", "$session_name archived category $type $name", 0, $category_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Category $type <strong>$name</strong> archived";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['unarchive_category'])) {
    
    $category_id = intval($_GET['unarchive_category']);

    // Get Category Name and Type for logging
    $sql = mysqli_query($mysqli,"SELECT category_name, category_type FROM categories WHERE category_id = $category_id");
    $row = mysqli_fetch_array($sql);
    $category_name = sanitizeInput($row['category_name']);
    $category_type = sanitizeInput($row['category_type']);

    mysqli_query($mysqli,"UPDATE categories SET category_archived_at = NULL WHERE category_id = $category_id");

    // Logging
    logAction("Category", "Unarchive", "$session_name unarchived category $type $name", 0, $category_id);

    $_SESSION['alert_message'] = "Category $type <strong>$name</strong> unarchived";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['delete_category'])) {
    
    $category_id = intval($_GET['delete_category']);

    // Get Category Name and Type for logging
    $sql = mysqli_query($mysqli,"SELECT category_name, category_type FROM categories WHERE category_id = $category_id");
    $row = mysqli_fetch_array($sql);
    $category_name = sanitizeInput($row['category_name']);
    $category_type = sanitizeInput($row['category_type']);

    mysqli_query($mysqli,"DELETE FROM categories WHERE category_id = $category_id");

    // Logging
    logAction("Category", "Delete", "$session_name deleted category $type $name");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Category $type <strong>$name</strong> deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}
