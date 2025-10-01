<?php

$session_ip = sanitizeInput(getIP());
$session_user_agent = sanitizeInput($_SERVER['HTTP_USER_AGENT']);
$session_user_id = intval($_SESSION['user_id']);

$sql = mysqli_query(
    $mysqli,
    "SELECT * FROM users
     LEFT JOIN user_settings ON users.user_id = user_settings.user_id
     LEFT JOIN user_roles ON user_role_id = role_id
     WHERE users.user_id = $session_user_id"
);

$row = mysqli_fetch_array($sql);

$session_name = sanitizeInput($row['user_name']);
$session_email = $row['user_email'];
$session_avatar = $row['user_avatar'];
$session_token = $row['user_token'];
$session_user_type = intval($row['user_type']);
$session_user_role = intval($row['user_role_id']);
$session_user_role_display = sanitizeInput($row['role_name']);
$session_is_admin = isset($row['role_is_admin']) && $row['role_is_admin'] == 1;
$session_user_config_force_mfa = intval($row['user_config_force_mfa']);
$user_config_records_per_page = intval($row['user_config_records_per_page']);
$user_config_theme_dark = intval($row['user_config_theme_dark']);

if ($session_user_type !== 1) {
    session_unset();
    session_destroy();
    redirect("/client/login.php");
}

// Load user client permissions
$user_client_access_sql = "SELECT client_id FROM user_client_permissions WHERE user_id = $session_user_id";
$user_client_access_result = mysqli_query($mysqli, $user_client_access_sql);

$client_access_array = [];
while ($row = mysqli_fetch_assoc($user_client_access_result)) {
    $client_access_array[] = $row['client_id'];
}

$client_access_string = implode(',', $client_access_array);
$access_permission_query = "";
if ($client_access_string && !$session_is_admin) {
    $access_permission_query = "AND clients.client_id IN ($client_access_string)";
}
