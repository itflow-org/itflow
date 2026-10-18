<?php

/*
 * API - Roles - Read
 * GET /api/v1/roles/read.php
 *
 * The roles agents can have (Admin > Roles) and what each opens. A key whose
 * user is an admin reads every role; any other key reads only its own user's
 * role - so an integration can check what it may do.
 *
 * Parameters (GET):
 *   api_key    required - Your API key
 *   role_id    optional - One role, archived or not
 *   archived   optional - 1 to include archived roles in the list
 *   limit      optional - Max rows to return (default 50)
 *   offset     optional - Offset for pagination (default 0)
 *
 * Each role: role_id, role_name, role_description, role_is_admin (1 or 0 - an
 * admin role opens everything and its levels don't apply), role_levels (each
 * module's level by its name: 0 none, 1 read, 2 modify - add, edit and
 * archive - and 3 full, delete too), role_agents (how many active agents
 * have it), role_created_at, role_updated_at and role_archived_at.
 */

require_once '../validate_api_key.php';

require_once '../require_get_method.php';

$where = '1';
if (!$session_is_admin) {
    // Not an admin - only its own role
    $where = 'role_id = ' . intval($session_user_role);
} elseif (isset($_GET['role_id'])) {
    $where = 'role_id = ' . intval($_GET['role_id']);
}
if (!isset($_GET['role_id']) && $session_is_admin && intval($_GET['archived'] ?? 0) !== 1) {
    $where .= ' AND role_archived_at IS NULL';
}

$sql = mysqli_query($mysqli, "SELECT role_id, role_name, role_description, role_is_admin, role_created_at, role_updated_at, role_archived_at,
        (SELECT COUNT(*) FROM users WHERE user_role_id = role_id AND user_type = 1 AND user_archived_at IS NULL) AS role_agents
    FROM user_roles WHERE role_type = 1 AND $where
    ORDER BY role_is_admin DESC, role_name ASC LIMIT $limit OFFSET $offset");

if ($sql && mysqli_num_rows($sql) > 0) {
    $roles = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $roles[intval($row['role_id'])] = $row;
    }
    $levels = roleLevels(array_keys($roles));
    $rows = [];
    foreach ($roles as $role_id => $row) {
        $role_levels = [];
        foreach (roleModules() as $module_id => $module) {
            $role_levels[$module['module_name']] = min(intval($levels[$role_id][$module_id] ?? 0), $module['max']);
        }
        $rows[] = [
            'role_id' => $role_id,
            'role_name' => $row['role_name'],
            'role_description' => $row['role_description'],
            'role_is_admin' => intval($row['role_is_admin']),
            'role_levels' => $role_levels,
            'role_agents' => intval($row['role_agents']),
            'role_created_at' => $row['role_created_at'],
            'role_updated_at' => $row['role_updated_at'],
            'role_archived_at' => $row['role_archived_at'],
        ];
    }
    echo json_encode(['success' => 'True', 'count' => count($rows), 'data' => $rows]);
    exit();
}

// Output
require_once "../read_output.php";
