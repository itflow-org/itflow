<?php

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['edit_security_settings'])) {

    validateCSRFToken();

    $config_login_message = escapeSql($_POST['config_login_message']);
    $config_login_key_required = intval($_POST['config_login_key_required'] ?? 0);
    $config_login_key_secret = escapeSql($_POST['config_login_key_secret']);
    $config_login_remember_me_expire = intval($_POST['config_login_remember_me_expire']);
    $config_log_retention = intval($_POST['config_log_retention']);

    // Shared links (functions/share.php) - only the lengths the share window offers
    $config_share_max_hours = intval($_POST['config_share_max_hours'] ?? 720);
    if (!isset(shareMaxOptions()[$config_share_max_hours])) {
        $config_share_max_hours = 720;
    }
    $config_share_require_protection = intval($_POST['config_share_require_protection'] ?? 0) ? 1 : 0;

    // Disallow turning on login key without a secret
    if (empty($config_login_key_secret)) {
        $config_login_key_required = 0;
    }

    mysqli_query($mysqli,"UPDATE settings SET config_login_message = '$config_login_message', config_login_key_required = '$config_login_key_required', config_login_key_secret = '$config_login_key_secret', config_login_remember_me_expire = $config_login_remember_me_expire, config_log_retention = $config_log_retention, config_share_max_hours = $config_share_max_hours, config_share_require_protection = $config_share_require_protection WHERE company_id = 1");

    logAudit("Settings", "Edit", "$session_name edited security settings");

    flashAlert("Security settings updated");

    redirect();

}
