<?php

// Role and permission enforcement
// Split from the former monolithic functions.php

// When provided a module name (e.g. module_support), returns the associated permission level (false=none, 1=read, 2=write, 3=full).
// The role's levels are read once a request - a page asks dozens of times.
function lookupUserPermission($module) {
    global $mysqli, $session_is_admin, $session_user_role;
    static $levels = [];

    if (isset($session_is_admin) && $session_is_admin === true) {
        return 3;
    }

    $role_id = intval($session_user_role ?? 0);
    if (!isset($levels[$role_id])) {
        $levels[$role_id] = [];
        $sql = mysqli_query($mysqli, "SELECT module_name, MAX(user_role_permission_level) FROM modules
            JOIN user_role_permissions ON modules.module_id = user_role_permissions.module_id
            WHERE user_role_permissions.user_role_id = $role_id GROUP BY module_name");
        while ($row = mysqli_fetch_row($sql)) {
            $levels[$role_id][$row[0]] = intval($row[1]);
        }
    }

    // Default return for no module permission
    return !empty($levels[$role_id][$module]) ? $levels[$role_id][$module] : false;
}

// Enforce admin portal access - single canonical admin gate ($session_is_admin)
function enforceAdminPermission() {
    global $session_is_admin;
    if (!isset($session_is_admin) || !$session_is_admin) {
        $role = permissionRoleName();
        permissionRefuse("Only an admin can do that - your role" . ($role !== '' ? ", $role," : '') . " isn't an admin role.");
    }
    return true;
}

// Ensures a user has access to a module (e.g. module_support) with at least the required permission level provided (defaults to read)
function enforceUserPermission($module, $check_access_level = 1) {
    $permitted_access_level = lookupUserPermission($module);

    if (!$permitted_access_level || $permitted_access_level < $check_access_level) {
        permissionRefuse(permissionRefusedText($module, $check_access_level, intval($permitted_access_level)));
    }
}

/** The signed-in agent's role's name, raw - '' when they have none. */
function permissionRoleName() {
    global $mysqli, $session_user_role;
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT role_name FROM user_roles WHERE role_id = " . intval($session_user_role ?? 0)));
    return $row ? (string) $row[0] : '';
}

/*
 * Why a role was turned away, in words - "Your role, Accountant, can only
 * view Support, not change it." Plain text.
 */
function permissionRefusedText($module, $level, $has = 0) {
    $label = roleModuleInfo()[$module]['label'] ?? ucfirst(str_replace('module_', '', (string) $module));
    $role = permissionRoleName();
    $who = 'Your role' . ($role !== '' ? ", $role," : '');
    $level = max(1, min(3, intval($level)));
    if ($has <= 0) {
        $why = "$who can't open $label.";
    } elseif ($level === 2) {
        $why = "$who can only view $label, not change it.";
    } else {
        $why = "$who can't delete in $label - that takes Full access.";
    }
    return "$why An admin can change what your role allows in Admin > Roles.";
}

/*
 * Turn the agent away, in the way that fits what they asked for: a window
 * says why in the window; a form or link goes back to its page, which says
 * why; a page already being drawn says why in its place; ajax.php answers
 * JSON with a 403. $message is plain text. Doesn't return.
 */
function permissionRefuse($message) {
    $html = escapeHtml($message);

    // A window - includes/modal_header.php
    if (defined('ITFLOW_MODAL')) {
        while (ob_get_level()) {
            ob_end_clean();
        }
        echo json_encode(['content' => '<div class="modal-header bg-dark"><h5 class="modal-title"><i class="fas fa-fw fa-lock me-2"></i>Not for your role</h5>'
            . '<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>'
            . '<div class="modal-body"><p class="mb-0">' . $html . '</p></div>'
            . '<div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Close</button></div>']);
        exit;
    }

    if (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'ajax.php') {
        if (!headers_sent()) {
            http_response_code(403);
            header('Content-Type: application/json');
        }
        echo json_encode(['ok' => false, 'error' => $message]);
        exit;
    }

    // A handler, or a page that checks before drawing anything - back where they came from, which says why
    if (defined('FROM_POST_HANDLER') || !headers_sent()) {
        flashAlert($html, 'error');
        redirect(permissionBackUrl());
    }

    // A page already under way - why, where the page would be, then the rest of the layout so its menus work
    echo '<div class="card my-3 mx-auto itflow-refused" style="max-width: 36rem;"><div class="card-body text-center py-5">'
        . '<span class="itflow-asset-icon itflow-asset-icon-lg itflow-asset-tone-other mb-3" aria-hidden="true"><i class="fas fa-lock"></i></span>'
        . '<h2 class="h5">Not for your role</h2><p class="text-body-secondary">' . $html . '</p>'
        . '<a class="btn btn-outline-secondary" href="' . escapeHtml(permissionBackUrl()) . '"><i class="fas fa-arrow-left me-2"></i>Back</a>'
        . '</div></div>';
    extract($GLOBALS, EXTR_SKIP);
    require $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php';
    exit;
}

/* Where to send someone turned away - the page they came from on this site, unless it is this one; the dashboard otherwise. */
function permissionBackUrl() {
    $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    $from = parse_url($referer);
    $here = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''));
    $host = parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '')), PHP_URL_HOST);
    if ($referer !== '' && ($from['host'] ?? '') === $host && ($from['path'] ?? '') !== ($here['path'] ?? '')) {
        return $referer;
    }
    return '/agent/dashboard.php';
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

/*
 * Can this user reach this client? Answers yes or no instead of redirecting, for places
 * that need to decide whether to render something - enforceClientAccess() below is the
 * gate that protects the page itself and still has to be called there.
 *
 * Reads the permission arrays that includes/load_user_session.php already loaded, so this
 * costs no query and can be called from the sidebar on every page. The rules match
 * clientScopeSql() above: a deny wins, an allow list restricts to its members, and no
 * rules at all means unrestricted.
 */
function hasClientAccess($client_id) {
    global $session_is_admin, $client_access_array, $client_deny_array;

    $client_id = intval($client_id);

    if ($session_is_admin) {
        return true;
    }

    if (in_array($client_id, array_map('intval', $client_deny_array))) {
        return false;
    }

    if (empty($client_access_array)) {
        return true;
    }

    return in_array($client_id, array_map('intval', $client_access_array));
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
        logAudit('Client', 'Denied', "$session_name was denied permission from accessing client", $client_id, $client_id);
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
        'Denied',
        "$session_name was denied permission from accessing client",
        $client_id,
        $client_id
    );

    flashAlert('Access Denied - You do not have permission to access that client!', 'error');
    redirect('clients.php');
}
