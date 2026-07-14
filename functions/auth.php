<?php

// Role and permission enforcement
// Split from the former monolithic functions.php

// When provided a module name (e.g. module_support), returns the associated permission level (false=none, 1=read, 2=write, 3=full)
function lookupUserPermission($module) {
    global $mysqli, $session_is_admin, $session_user_role;

    if (isset($session_is_admin) && $session_is_admin === true) {
        return 3;
    }

    $module = escapeSql($module);

    $sql = mysqli_query(
        $mysqli,
        "SELECT
			user_role_permissions.user_role_permission_level
		FROM
			modules
		JOIN
			user_role_permissions
		ON
			modules.module_id = user_role_permissions.module_id
		WHERE
			module_name = '$module' AND user_role_permissions.user_role_id = $session_user_role"
    );

    $row = mysqli_fetch_assoc($sql);

    if (isset($row['user_role_permission_level'])) {
        return intval($row['user_role_permission_level']);
    }

    // Default return for no module permission
    return false;
}

// Enforce admin portal access - single canonical admin gate ($session_is_admin)
function enforceAdminPermission() {
    global $session_is_admin;
    if (!isset($session_is_admin) || !$session_is_admin) {
        exit(WORDING_ROLECHECK_FAILED . "<br>Tell your admin: Your role does not have admin access.");
    }
    return true;
}

// Ensures a user has access to a module (e.g. module_support) with at least the required permission level provided (defaults to read)
function enforceUserPermission($module, $check_access_level = 1) {
    $permitted_access_level = lookupUserPermission($module);

    if (!$permitted_access_level || $permitted_access_level < $check_access_level) {
        $_SESSION['alert_type'] = "danger";
        $_SESSION['alert_message'] = WORDING_ROLECHECK_FAILED;
        $map = [
            "1" => "read",
            "2" => "write",
            "3" => "full"
        ];
        exit(WORDING_ROLECHECK_FAILED . "<br>Tell your admin: $map[$check_access_level] access to $module is not permitted for your role.");
    }
}

function enforceClientAccess($client_id = null) {
    global $mysqli, $session_user_id, $session_is_admin, $session_name;

    // Use global $client_id if none passed
    if ($client_id === null) {
        global $client_id;
    }

    if ($session_is_admin) {
        return true;
    }

    $client_id = (int) $client_id;
    $session_user_id = (int) $session_user_id;

    if (empty($client_id) || empty($session_user_id)) {
        flashAlert('Access Denied.', 'error');
        redirect('clients.php');
    }

    // Check if this user has any client permissions set
    $permissions_sql = "SELECT client_id
                        FROM user_client_permissions
                        WHERE user_id = $session_user_id
                        LIMIT 1";

    $permissions_result = mysqli_query($mysqli, $permissions_sql);

    // If no permission rows exist for this user, allow access by default
    if ($permissions_result && mysqli_num_rows($permissions_result) == 0) {
        return true;
    }

    // If permission rows exist, require this client
    $access_sql = "SELECT client_id
                   FROM user_client_permissions
                   WHERE user_id = $session_user_id
                   AND client_id = $client_id
                   LIMIT 1";

    $access_result = mysqli_query($mysqli, $access_sql);

    if ($access_result && mysqli_num_rows($access_result) > 0) {
        return true;
    }

    logAudit(
        'Client',
        'Access',
        "$session_name was denied permission from accessing client",
        $client_id,
        $client_id
    );

    flashAlert('Access Denied - You do not have permission to access that client!', 'error');
    redirect('clients.php');
}
