<?php

if (isset($_POST['edit_telemetry_settings'])) {

    validateCSRFToken($_POST['csrf_token']);

    $config_telemetry = intval($_POST['config_telemetry']);

    mysqli_query($mysqli,"UPDATE settings SET config_telemetry = $config_telemetry WHERE company_id = 1");

    // Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Settings', log_action = 'Modify', log_description = '$session_name modified telemetry settings', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Telemetry Settings updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}
