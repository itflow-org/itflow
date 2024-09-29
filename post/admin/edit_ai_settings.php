<?php

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

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Edit', log_description = '$session_name edited AI settings', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "You updated the AI Settings";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}
