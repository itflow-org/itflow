<?php

if (isset($_POST['edit_project_settings'])) {

    validateCSRFToken($_POST['csrf_token']);

    $config_project_prefix = sanitizeInput($_POST['config_project_prefix']);
    $config_project_next_number = intval($_POST['config_project_next_number']);

    mysqli_query($mysqli,"UPDATE settings SET config_project_prefix = '$config_project_prefix', config_project_next_number = $config_project_next_number WHERE company_id = 1");

    // Logging
    logAction("Settings", "Edit", "$session_name edited project settings");

    $_SESSION['alert_message'] = "Project Settings updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}
