<?php

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['edit_security_settings'])) {

    validateCSRFToken($_POST['csrf_token']);

    $config_login_message = sanitizeInput($_POST['config_login_message']);
    $config_login_key_required = intval($_POST['config_login_key_required'] ?? 0);
    $config_login_key_secret = sanitizeInput($_POST['config_login_key_secret']);
    $config_login_remember_me_expire = intval($_POST['config_login_remember_me_expire']);
    $config_log_retention = intval($_POST['config_log_retention']);

    // Disallow turning on login key without a secret
    if (empty($config_login_key_secret)) {
        $config_login_key_required = 0;
    }

    mysqli_query($mysqli,"UPDATE settings SET config_login_message = '$config_login_message', config_login_key_required = '$config_login_key_required', config_login_key_secret = '$config_login_key_secret', config_login_remember_me_expire = $config_login_remember_me_expire, config_log_retention = $config_log_retention WHERE company_id = 1");

    // Logging
    logAction("Settings", "Edit", "$session_name edited security settings");

    $_SESSION['alert_message'] = "Security settings updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}
