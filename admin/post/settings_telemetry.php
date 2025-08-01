<?php

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['edit_telemetry_settings'])) {

    validateCSRFToken($_POST['csrf_token']);

    $config_telemetry = intval($_POST['config_telemetry']);

    mysqli_query($mysqli,"UPDATE settings SET config_telemetry = $config_telemetry WHERE company_id = 1");

   // Logging
    logAction("Settings", "Edit", "$session_name edited telemetry settings");

    $_SESSION['alert_message'] = "Telemetry Settings updated";

    redirect();

}
