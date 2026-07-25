<?php

/*
 * API - enforce_api_rbac.php
 *
 * Every API key runs as a user (api_key_user_id). This loads that user's role and
 * client access, then enforces the SAME permissions the UI uses:
 *   - module + operation (read/write/full) via lookupUserPermission()
 *   - client access via the user's allow / deny lists
 *
 * There is no per-key client scope anymore - client access derives entirely from
 * the user. Reads are scoped with apiClientScopeSql(); writes act on a single
 * target client supplied in the request and validated against the user's access.
 *
 * Reuses the existing RBAC machinery so there is no parallel permission model.
 * Included by validate_api_key.php - runs in global scope with $mysqli,
 * $api_key_user_id and $return_arr available.
 */

// --- Helpers (defined first so they exist for the endpoint that includes us) ---

// JSON 403 + stop.
function apiDeny($message) {
    global $return_arr;
    $return_arr['success'] = "False";
    $return_arr['message'] = $message;
    header("HTTP/1.1 403 Forbidden");
    echo json_encode($return_arr);
    exit();
}

// Can the signed-in (key's) user access this single client? Mirrors enforceClientAccess:
// admin -> yes; explicit deny -> no; otherwise allowed if in the allow list or the
// user has no allow list (unrestricted).
function apiUserCanAccessClient($client_id) {
    global $session_is_admin, $client_access_array, $client_deny_array;
    $client_id = intval($client_id);
    if ($session_is_admin) {
        return true;
    }
    if (in_array($client_id, $client_deny_array, true)) {
        return false;
    }
    return empty($client_access_array) || in_array($client_id, $client_access_array, true);
}

// Client-scope SQL fragment for a read query, from the user's allow / deny lists.
// Admin and unrestricted users get no restriction. Column-aware, so it works on any
// resource. Returns " AND ..." or "" (used after a "WHERE 1=1" anchor).
function apiClientScopeSql($column) {
    global $session_is_admin, $client_access_array, $client_deny_array;
    if ($session_is_admin) {
        return '';
    }
    if (empty($client_access_array) && empty($client_deny_array)) {
        return ''; // unrestricted user - all clients
    }
    $sql = '';
    if (!empty($client_access_array)) {
        $sql .= " AND $column IN (" . implode(',', array_map('intval', $client_access_array)) . ")";
    }
    if (!empty($client_deny_array)) {
        $sql .= " AND $column NOT IN (" . implode(',', array_map('intval', $client_deny_array)) . ")";
    }
    return $sql;
}

// --- Every key must be tied to a user (legacy keys were removed in the 2.4.7 migration) ---
if (empty($api_key_user_id)) {
    apiDeny("This API key is not tied to a user and is no longer valid. Please recreate it.");
}

// --- 1) Load the linked user's session context (mirrors load_user_session.php) ---
$sql_api_user = mysqli_query($mysqli,
    "SELECT users.user_id, user_name, user_type, user_status, user_archived_at,
            user_role_id, role_is_admin
     FROM users
     LEFT JOIN user_roles ON user_role_id = role_id
     WHERE users.user_id = $api_key_user_id
     LIMIT 1");

$api_user = $sql_api_user ? mysqli_fetch_assoc($sql_api_user) : null;

// Linked user must exist, be an active agent (user_type 1), and not be archived.
if (!$api_user
    || intval($api_user['user_type']) !== 1
    || intval($api_user['user_status']) !== 1
    || $api_user['user_archived_at'] !== null) {
    apiDeny("The user linked to this API key is inactive, archived, or invalid.");
}

$session_user_id   = intval($api_user['user_id']);
$session_name      = escapeSql($api_user['user_name']);
$session_user_role = intval($api_user['user_role_id']);
$session_is_admin  = isset($api_user['role_is_admin']) && $api_user['role_is_admin'] == 1;

// Load the user's client allow / deny lists.
$client_access_array = [];
$client_deny_array   = [];
$sql_api_perms = mysqli_query($mysqli,
    "SELECT client_id, permission_type FROM user_client_permissions WHERE user_id = $session_user_id");
while ($sql_api_perms && $prow = mysqli_fetch_assoc($sql_api_perms)) {
    if ($prow['permission_type'] === 'deny') {
        $client_deny_array[] = (int) $prow['client_id'];
    } else {
        $client_access_array[] = (int) $prow['client_id'];
    }
}

// --- 2) Enforce module + operation permission for the requested endpoint ---
// SCRIPT_NAME is the file actually executed, so rewrites can't spoof the resource.
$script = $_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '';
$parts  = array_values(array_filter(explode('/', $script), 'strlen'));
$n      = count($parts);
$operation_file = $n >= 1 ? strtolower($parts[$n - 1]) : '';
$resource       = $n >= 2 ? strtolower($parts[$n - 2]) : '';

// API resource -> UI module (matches the mapping used across the app).
$resource_module = [
    'assets'        => 'module_support',
    'certificates'  => 'module_support',
    'documents'     => 'module_support',
    'domains'       => 'module_support',
    'networks'      => 'module_support',
    'software'      => 'module_support',
    'tickets'       => 'module_support',
    'technicians'   => 'module_support',
    'clients'       => 'module_client',
    'contacts'      => 'module_client',
    'locations'     => 'module_client',
    'vendors'       => 'module_client',
    'invoices'      => 'module_sales',
    'invoice_items' => 'module_sales',
    'quotes'        => 'module_sales',
    'products'      => 'module_sales',
    'expenses'      => 'module_financial',
    'credentials'   => 'module_credential',
];

// Operation -> required permission level (read = 1, create/update = 2, delete = 3).
$operation_level = [
    'read.php'   => 1,
    'create.php' => 2,
    'update.php' => 2,
    'delete.php' => 3,
];

if (!isset($resource_module[$resource])) {
    // Fail closed: any endpoint that reaches the enforcer must map to a module. When
    // you add a new API resource, add it to $resource_module above - that deliberate
    // step is what brings it under RBAC, so nothing can slip through ungated.
    apiDeny("This API resource is not mapped to a permission module.");
}

$required_level = $operation_level[$operation_file] ?? 2;
if (lookupUserPermission($resource_module[$resource]) < $required_level) {
    apiDeny("The user linked to this API key does not have permission for this action.");
}

// --- 3) Target client for writes: taken from the request, validated against the user ---
// Reads ignore this and use apiClientScopeSql(). Create/update/delete act on a single
// client the caller names (client_id in the body/query); it must be within the user's
// access. Callers that omit it get $client_id = 0 (creates that require a client fail
// their own !empty($client_id) guard, which is the intended "must name a client").
$client_id = intval($_POST['client_id'] ?? $_GET['client_id'] ?? 0);
$is_write = in_array($operation_file, ['create.php', 'update.php', 'delete.php'], true);
if ($is_write && !apiUserCanAccessClient($client_id)) {
    // Writes act on a single client the caller names (client_id 0 = a global record).
    // The user must be able to access it - this also blocks a restricted user from
    // writing global (client_id 0) records, which their access does not include.
    apiDeny("The user linked to this API key does not have access to the target client for this write.");
}
// Reads ignore $client_id entirely and are scoped by apiClientScopeSql().
