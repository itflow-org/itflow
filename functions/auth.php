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

// Client-scope SQL fragment for a list query, built from the signed-in user's allow / deny lists.
// Admin and unrestricted users get no restriction. This is the list-level counterpart to
// enforceClientAccess(), which gates a single record.
//
// Column-aware on purpose: it scopes on the resource's OWN client column rather than a joined
// clients.client_id, so a row with no client (column = 0) is judged on its real value instead of
// becoming NULL through a LEFT JOIN and silently dropping out of the result set.
//
// Returns " AND ..." or "" - append it after a WHERE clause (add "WHERE 1=1" if there isn't one).
function clientScopeSql($column) {
    global $session_is_admin, $client_access_array, $client_deny_array;

    if ($session_is_admin) {
        return '';
    }

    if (empty($client_access_array) && empty($client_deny_array)) {
        return ''; // Unrestricted user - all clients
    }

    $sql = '';

    // 0 is included deliberately: a record with no client isn't any client's data, so a
    // restricted user keeps seeing it. This also matches the deny branch below, where 0
    // already passes NOT IN, and the old hand-rolled ticket override that did IN (0,...).
    if (!empty($client_access_array)) {
        $sql .= " AND $column IN (0," . implode(',', array_map('intval', $client_access_array)) . ")";
    }

    if (!empty($client_deny_array)) {
        $sql .= " AND $column NOT IN (" . implode(',', array_map('intval', $client_deny_array)) . ")";
    }

    return $sql;
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

    // Deny list wins: an explicit deny blocks access regardless of any allow rule
    $deny_sql = "SELECT client_id
                 FROM user_client_permissions
                 WHERE user_id = $session_user_id
                 AND client_id = $client_id
                 AND permission_type = 'deny'
                 LIMIT 1";
    $deny_result = mysqli_query($mysqli, $deny_sql);
    if ($deny_result && mysqli_num_rows($deny_result) > 0) {
        logAudit('Client', 'Access', "$session_name was denied permission from accessing client", $client_id, $client_id);
        flashAlert('Access Denied - You do not have permission to access that client!', 'error');
        redirect('clients.php');
    }

    // Check if this user has any client permissions set
    $permissions_sql = "SELECT client_id
                        FROM user_client_permissions
                        WHERE user_id = $session_user_id
                        AND permission_type = 'allow'
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
                   AND permission_type = 'allow'
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
