<?php

/*
 * ITFlow - GET/POST request handler for roles
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_role'])) {

    validateCSRFToken($_POST['csrf_token']);

    $name = sanitizeInput($_POST['role_name']);
    $description = sanitizeInput($_POST['role_description']);
    $admin = intval($_POST['role_is_admin']);

    mysqli_query($mysqli, "INSERT INTO user_roles SET role_name = '$name', role_description = '$description', role_is_admin = $admin");

    $role_id = mysqli_insert_id($mysqli);

    // Logging
    logAction("User Role", "Create", "$session_name created user role $name", 0, $role_id);

    $_SESSION['alert_message'] = "User Role <strong$name</strong> created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_role'])) {

    validateCSRFToken($_POST['csrf_token']);

    // Update role metadata
    $role_id = sanitizeInput($_POST['role_id']);
    $name = sanitizeInput($_POST['role_name']);
    $description = sanitizeInput($_POST['role_description']);
    $admin = intval($_POST['role_is_admin']);
    
    mysqli_query($mysqli, "UPDATE user_roles SET role_name = '$name', role_description = '$description', role_is_admin = $admin WHERE role_id = $role_id");

    // Update role access levels
    mysqli_query($mysqli, "DELETE FROM user_role_permissions WHERE user_role_id = $role_id");
    foreach ($_POST as $key => $value) {
        if (str_contains($key, '##module_')){
            $module_id = intval(explode('##', $key)[0]);
            $access_level = intval($value);

            if ($access_level > 0) {
                mysqli_query($mysqli, "INSERT INTO user_role_permissions SET user_role_id = $role_id, module_id = $module_id, user_role_permission_level = $access_level");
            }
        }

    }

    // Logging
    logAction("User Role", "Edit", "$session_name edited user role $name", 0, $role_id);

    $_SESSION['alert_message'] = "User Role <strong>$name</strong> edited";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_GET['archive_role'])) {

    validateCSRFToken($_GET['csrf_token']);

    $role_id = intval($_GET['archive_role']);

    // Check role isn't in use
    $sql_role_user_count = mysqli_query($mysqli, "SELECT COUNT(user_id) FROM users WHERE user_role_id = $role_id AND user_archived_at IS NULL");
    $role_user_count = mysqli_fetch_row($sql_role_user_count)[0];
    if ($role_user_count != 0) {
        $_SESSION['alert_type'] = "error";
        $_SESSION['alert_message'] = "Role must not in use to archive it";
        header("Location: " . $_SERVER["HTTP_REFERER"]);
        exit();
    }

    mysqli_query($mysqli, "UPDATE user_roles SET role_archived_at = NOW() WHERE role_id = $role_id");

    // Logging
    $role_details = mysqli_fetch_array(mysqli_query($mysqli, "SELECT role_name FROM user_roles WHERE role_id = $role_id LIMIT 1"));
    $role_name = sanitizeInput($role_details['role_name']);
    logAction("User Role", "Archive", "$session_name archived user role $role_name", 0, $role_id);

    $_SESSION['alert_message'] = "User Role archived";
    header("Location: " . $_SERVER["HTTP_REFERER"]);
}