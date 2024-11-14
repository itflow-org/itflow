<?php

if (isset($_POST['edit_quote_settings'])) {

    validateCSRFToken($_POST['csrf_token']);

    $config_quote_prefix = sanitizeInput($_POST['config_quote_prefix']);
    $config_quote_next_number = intval($_POST['config_quote_next_number']);
    $config_quote_footer = sanitizeInput($_POST['config_quote_footer']);
    $config_quote_notification_email = '';
    if (filter_var($_POST['config_quote_notification_email'], FILTER_VALIDATE_EMAIL)) {
        $config_quote_notification_email = sanitizeInput($_POST['config_quote_notification_email']);
    }

    mysqli_query($mysqli,"UPDATE settings SET config_quote_prefix = '$config_quote_prefix', config_quote_next_number = $config_quote_next_number, config_quote_footer = '$config_quote_footer', config_quote_notification_email = '$config_quote_notification_email' WHERE company_id = 1");

    // Logging
    logAction("Settings", "Edit", "$session_name edited Quote settings");

    $_SESSION['alert_message'] = "Quote Settings updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}
