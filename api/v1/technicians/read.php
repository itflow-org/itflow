<?php

/*
 * API - Technicians - Read
 * GET /api/v1/technicians/read.php
 *
 * The agents - who tickets can be assigned to - with their role. Needs Read
 * on Support. Sign in details (password, MFA, tokens) are never included.
 *
 * Parameters (GET):
 *   api_key        required - Your API key
 *   technician_id  optional - One agent, archived or not
 *   archived       optional - 1 to include archived agents in the list
 *   limit          optional - Max rows to return (default 50)
 *   offset         optional - Offset for pagination (default 0)
 *
 * Each: technician_id, technician_name, technician_email, technician_status
 * (1 active, 0 disabled), role_id, role_name, role_is_admin (1 or 0),
 * technician_created_at and technician_archived_at.
 */

require_once '../validate_api_key.php';

require_once '../require_get_method.php';

$where = 'user_type = 1';
if (isset($_GET['technician_id'])) {
    $where .= ' AND user_id = ' . intval($_GET['technician_id']);
} elseif (intval($_GET['archived'] ?? 0) !== 1) {
    $where .= ' AND user_archived_at IS NULL';
}

$sql = mysqli_query($mysqli, "SELECT user_id AS technician_id, user_name AS technician_name, user_email AS technician_email,
        user_status AS technician_status, user_role_id AS role_id, role_name, IFNULL(role_is_admin, 0) AS role_is_admin,
        user_created_at AS technician_created_at, user_archived_at AS technician_archived_at
    FROM users
    LEFT JOIN user_roles ON role_id = user_role_id
    WHERE $where
    ORDER BY user_name ASC LIMIT $limit OFFSET $offset");

// Output
require_once "../read_output.php";
