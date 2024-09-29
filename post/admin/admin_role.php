<?php

/*
 * ITFlow - GET/POST request handler for roles
 */

if (isset($_POST['add_role'])) {

    validateCSRFToken($_POST['csrf_token']);

    $name = sanitizeInput($_POST['role_name']);
    $description = sanitizeInput($_POST['role_description']);
    $admin = intval($_POST['role_is_admin']);

    mysqli_query($mysqli, "INSERT INTO user_roles SET user_role_name = '$name', user_role_description = '$description', user_role_is_admin = $admin");

    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Role', log_action = 'Create', log_description = '$session_name created the $name role', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Role $name created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_role'])) {

    validateCSRFToken($_POST['csrf_token']);

    // Update role metadata
    $role_id = sanitizeInput($_POST['role_id']);
    $name = sanitizeInput($_POST['role_name']);
    $description = sanitizeInput($_POST['role_description']);
    $admin = intval($_POST['role_is_admin']);
    mysqli_query($mysqli, "UPDATE user_roles SET user_role_name = '$name', user_role_description = '$description', user_role_is_admin = $admin WHERE user_role_id = $role_id");

    // Update role access levels
    mysqli_query($mysqli, "DELETE FROM user_role_permissions WHERE user_role_id = $role_id");
    foreach ($_POST as $key => $value) {
        if (str_contains($key, '##module_')){
            $module_id = intval(explode('##', $key)[0]);
            $access_level = intval($value);

            if ($access_level > 0) {
                echo $key . ' with id ' . $module_id . " : ". $access_level . "\n";
                mysqli_query($mysqli, "INSERT INTO user_role_permissions SET user_role_id = $role_id, module_id = $module_id, user_role_permission_level = $access_level");
            }
        }

    }

    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Role', log_action = 'Modify', log_description = '$session_name updated the $name role', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Role $name updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}
