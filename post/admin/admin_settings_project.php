<?php

if (isset($_POST['edit_project_settings'])) {

    validateCSRFToken($_POST['csrf_token']);

    $config_project_prefix = sanitizeInput($_POST['config_project_prefix']);
    $config_project_next_number = intval($_POST['config_project_next_number']);

    mysqli_query($mysqli,"UPDATE settings SET config_project_prefix = '$config_project_prefix', config_project_next_number = $config_project_next_number WHERE company_id = 1");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Modify', log_description = '$session_name modified project settings', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Project Settings updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}
