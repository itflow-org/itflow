<?php

$session_ip = escapeSql(getIP());
$session_user_agent = escapeSql($_SERVER['HTTP_USER_AGENT']);
$session_user_id = intval($_SESSION['user_id']);

$sql = mysqli_query(
    $mysqli,
    "SELECT * FROM users
     LEFT JOIN user_settings ON users.user_id = user_settings.user_id
     LEFT JOIN user_roles ON user_role_id = role_id
     WHERE users.user_id = $session_user_id"
);

$row = mysqli_fetch_assoc($sql);

$session_name = escapeSql($row['user_name']);
$session_email = $row['user_email'];
$session_avatar = $row['user_avatar'];
$session_token = $row['user_token'];
$session_user_type = intval($row['user_type']);
$session_user_archived_at = $row['user_archived_at'];
$session_user_status = intval($row['user_status']);
$session_user_role = intval($row['user_role_id']);
$session_user_role_display = escapeSql($row['role_name']);
$session_is_admin = isset($row['role_is_admin']) && $row['role_is_admin'] == 1;
$session_user_config_force_mfa = intval($row['user_config_force_mfa']);
$user_config_records_per_page = intval($row['user_config_records_per_page']);
$user_config_theme_dark = intval($row['user_config_theme_dark']);

// Check user type is agent aka 1
if ($session_user_type !== 1) {
    session_unset();
    session_destroy();
    redirect("/login.php");
}

// Check User is active
if ($session_user_status !== 1) {
    session_unset();
    session_destroy();
    redirect("/login.php");
}

// Check User is archived
if ($session_user_archived_at !== null) {
    session_unset();
    session_destroy();
    redirect("/login.php");
}

// Load user client permissions (allow + deny lists)
$user_client_access_sql = "SELECT client_id, permission_type FROM user_client_permissions WHERE user_id = $session_user_id";
$user_client_access_result = mysqli_query($mysqli, $user_client_access_sql);

$client_access_array = []; // allow
$client_deny_array = [];   // deny
while ($row = mysqli_fetch_assoc($user_client_access_result)) {
    if ($row['permission_type'] === 'deny') {
        $client_deny_array[] = (int) $row['client_id'];
    } else {
        $client_access_array[] = (int) $row['client_id'];
    }
}

// Client scoping for queries is built per-query by clientScopeSql() in functions/auth.php,
// which is column-aware. These strings remain for any caller that needs the raw lists.
$client_access_string = implode(',', $client_access_array);
$client_deny_string = implode(',', $client_deny_array);
