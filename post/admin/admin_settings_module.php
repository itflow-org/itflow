<?php

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['edit_module_settings'])) {

    $config_module_enable_itdoc = intval($_POST['config_module_enable_itdoc'] ?? 0);
    $config_module_enable_ticketing = intval($_POST['config_module_enable_ticketing'] ?? 0);
    $config_module_enable_accounting = intval($_POST['config_module_enable_accounting'] ?? 0);
    $config_client_portal_enable = intval($_POST['config_client_portal_enable'] ?? 0);
    $config_whitelabel_key = sanitizeInput($_POST['config_whitelabel_key']);

    mysqli_query($mysqli,"UPDATE settings SET config_module_enable_itdoc = $config_module_enable_itdoc, config_module_enable_ticketing = $config_module_enable_ticketing, config_module_enable_accounting = $config_module_enable_accounting, config_client_portal_enable = $config_client_portal_enable WHERE company_id = 1");

    // Validate white label key
    if (!empty($config_whitelabel_key && validateWhitelabelKey($config_whitelabel_key))) {
        mysqli_query($mysqli, "UPDATE settings SET config_whitelabel_enabled = 1, config_whitelabel_key = '$config_whitelabel_key' WHERE company_id = 1");
    } else {
        mysqli_query($mysqli, "UPDATE settings SET config_whitelabel_enabled = 0, config_whitelabel_key = '' WHERE company_id = 1");
    }

    // Logging
    logAction("Settings", "Edit", "$session_name edited module settings");

    $_SESSION['alert_message'] = "Module Settings updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}
