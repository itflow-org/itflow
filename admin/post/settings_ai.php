<?php

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['edit_ai_settings'])) {

    validateCSRFToken($_POST['csrf_token']);

    $provider = sanitizeInput($_POST['provider']);
    if($provider){
        $ai_enable = 1;
    } else {
        $ai_enable = 0;
    }
    $model = sanitizeInput($_POST['model']);
    $url = sanitizeInput($_POST['url']);
    $api_key = sanitizeInput($_POST['api_key']);

    mysqli_query($mysqli,"UPDATE settings SET config_ai_enable = $ai_enable, config_ai_provider = '$provider', config_ai_model = '$model', config_ai_url = '$url', config_ai_api_key = '$api_key' WHERE company_id = 1");

    // Logging
    logAction("Settings", "Edit", "$session_name edited AI settings");

    $_SESSION['alert_message'] = "AI Settings updated";

    redirect();

}
