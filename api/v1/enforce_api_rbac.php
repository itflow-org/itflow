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
 * Reads may also pass client_id as an optional filter (e.g. every asset belonging to
 * client 5). It is applied inside apiClientScopeSql() on top of the user's scope, so it
 * can only narrow the result set, never widen it.
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

// Why the key's user was refused a module at a level - what it needs, and what their role has.
// Plain text, for apiDeny().
function apiPermissionText($module, $level) {
    global $api_user;
    $label = roleModuleInfo()[$module]['label'] ?? ucfirst(str_replace('module_', '', (string) $module));
    $level = max(1, min(3, intval($level)));
    $has = intval(lookupUserPermission($module));
    $role = permissionRoleName();
    return "This needs " . ROLE_LEVELS[$level] . " on $label - the key's user, " . ($api_user['user_name'] ?? 'its user')
        . ($role !== '' ? " ($role)" : '') . ", has " . ($has ? ROLE_LEVELS[min(3, $has)] : 'no access') . ". An admin can change their role in Admin > Roles.";
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

// Client-scope SQL fragment for a read query: the user's allow / deny lists, plus the
// optional client_id filter the caller asked for.
//
// The scope half is a thin wrapper over clientScopeSql() in functions/auth.php so the API
// and the UI share one implementation. Kept under the api* name because every endpoint
// already calls it.
//
// The filter half is what lets a key that can see every client ask for just one of them -
// GET assets/read.php?api_key=...&client_id=5. It is appended AFTER the scope fragment, so
// it can only narrow: a user who cannot see client 5 still gets nothing back.
//
// Reads only. Writes take client_id as the target client they act on and validate it
// against the user's access further down, so filtering a write query by it would be wrong.
function apiClientScopeSql($column) {
    global $client_id, $client_id_supplied, $is_write;

    $sql = clientScopeSql($column);

    // Tested on $client_id_supplied, not on the value: client_id = 0 is a real request
    // (records with no client), and require_get_method.php turns an absent client_id
    // into "%", which would silently compare as 0 against an integer column.
    if (!empty($client_id_supplied) && empty($is_write)) {
        $sql .= " AND $column = " . intval($client_id);
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
    'network_ips'   => 'module_support',
    'asset_interfaces' => 'module_support',
    'asset_notes'   => 'module_support',
    'racks'         => 'module_support',
    'rack_units'    => 'module_support',
    'services'      => 'module_support',
    'software'      => 'module_support',
    'software_keys' => 'module_support',
    'tickets'       => 'module_support',
    'files'         => 'module_support',
    'folders'       => 'module_support',
    'ticket_replies' => 'module_support',
    'technicians'   => 'module_support',
    'projects'      => 'module_support',
    'tasks'         => 'module_support',
    'task_approvals' => 'module_support',
    'recurring_tickets' => 'module_support',
    'clients'       => 'module_client',
    'contacts'      => 'module_client',
    'contact_notes' => 'module_client',
    'locations'     => 'module_client',
    'portal_invites' => 'module_client',
    'vendors'       => 'module_client',
    'invoices'      => 'module_sales',
    'recurring_invoices' => 'module_sales',
    'invoice_items' => 'module_sales',
    'quotes'        => 'module_sales',
    'quote_items'   => 'module_sales',
    'recurring_invoice_items' => 'module_sales',
    'products'      => 'module_sales',
    'contracts'     => 'module_sales',
    'contract_hours' => 'module_sales',
    'taxes'         => 'module_sales',
    'expenses'      => 'module_financial',
    // Payments are accounting work - Financial, and api/v1/payments/ also needs Sales at Read to see the invoices (billingMoneyCan())
    'payments'      => 'module_financial',
    'accounts'      => 'module_financial',
    'revenues'      => 'module_financial',
    'transfers'     => 'module_financial',
    'recurring_expenses' => 'module_financial',
    'credentials'   => 'module_credential',
    // Each kind of tag is its record's module - api/v1/tags/ checks the kind
    // asked about (tagApiKind()), so there is no one module to check here
    'tags'          => '',
    // Likewise each kind of category - api/v1/categories/ checks the kind (categoryApiKind())
    'categories'    => '',
    // Roles: an admin key reads them all, any other its own user's - api/v1/roles/read.php decides
    'roles'         => '',
    // Calendar events: one with no client is anyone's, a client's follows Clients (calendarEventCan()) - api/v1/events/ checks
    'events'        => '',
    // The calendars, to put events on - anyone reads them (api/v1/calendars/read.php)
    'calendars'     => '',
    // Trips: anyone logs and changes their own, someone else's needs Financial (tripCanEdit()) - api/v1/trips/ checks through tripSave()
    'trips'         => '',
];

// Operation -> required permission level (read = 1, create/update = 2, delete = 3).
$operation_level = [
    'read.php'   => 1,
    'create.php' => 2,
    'update.php' => 2,
    'delete.php' => 3,
    // technicians/time.php only reads
    'time.php'   => 1,
    // files/download.php only reads
    'download.php' => 1,
    // Changing a record's state is an edit, as on its page
    'archive.php'   => 2,
    'unarchive.php' => 2,
    'close.php'     => 2,
    'resolve.php'   => 2,
    'reopen.php'    => 2,
    'send.php'      => 2,
    'mark_sent.php' => 2,
    'cancel.php'    => 2,
    'accept.php'    => 2,
    'decline.php'   => 2,
    'invoice.php'   => 2,
    'status.php'    => 2,
    'complete.php'  => 2,
    'approve.php'   => 2,
    'raise.php'     => 2,
    'apply_template.php' => 2,
    'seats.php'     => 2,
    'renew.php'     => 2,
    'skip.php'      => 2,
    'record.php'    => 2,
    'move_on.php'   => 2,
    'activate.php'  => 2,
    'withdraw.php'  => 2,
    'draw.php'      => 2,
    'put_back.php'  => 2,
    'recurring.php' => 2,
    'unlink.php'    => 2,
];

if (!isset($resource_module[$resource])) {
    // Fail closed: any endpoint that reaches the enforcer must map to a module. When
    // you add a new API resource, add it to $resource_module above - that deliberate
    // step is what brings it under RBAC, so nothing can slip through ungated.
    apiDeny("This API resource is not mapped to a permission module.");
}

$required_level = $operation_level[$operation_file] ?? 2;
if ($resource_module[$resource] !== '' && lookupUserPermission($resource_module[$resource]) < $required_level) {
    apiDeny(apiPermissionText($resource_module[$resource], $required_level));
}

// --- 3) Target client for writes: taken from the request, validated against the user ---
// Create/update/delete act on a single client the caller names (client_id in the
// body/query); it must be within the user's access. Callers that omit it get
// $client_id = 0 (creates that require a client fail their own !empty($client_id) guard,
// which is the intended "must name a client"). On a read the same parameter is not a
// permission at all, just an optional filter applied inside apiClientScopeSql().
$client_id = intval($_POST['client_id'] ?? $_GET['client_id'] ?? 0);
$client_id_supplied = isset($_POST['client_id']) || isset($_GET['client_id']);
// Everything but a read is a write - archive, unarchive, resolve and close act on one
// client's record just as update does, so they name a client the user can reach too.
// Listing the reads, not the writes, keeps a new kind of write from slipping past.
$is_write = !in_array($operation_file, ['read.php', 'time.php', 'download.php'], true);
if ($is_write && !apiUserCanAccessClient($client_id)) {
    // Writes act on a single client the caller names (client_id 0 = a global record).
    // The user must be able to access it - this also blocks a restricted user from
    // writing global (client_id 0) records, which their access does not include.
    apiDeny("The user linked to this API key does not have access to the target client for this write.");
}
// Reads never treat $client_id as a permission - apiClientScopeSql() enforces the user's
// scope first and only then narrows to $client_id if the caller supplied one.
