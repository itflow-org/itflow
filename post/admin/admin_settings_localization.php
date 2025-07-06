<?php

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['edit_localization'])) {

    validateCSRFToken($_POST['csrf_token']);

    $locale = sanitizeInput($_POST['locale']);
    $currency_code = sanitizeInput($_POST['currency_code']);
    $timezone = sanitizeInput($_POST['timezone']);

    mysqli_query($mysqli,"UPDATE companies SET company_locale = '$locale', company_currency = '$currency_code' WHERE company_id = 1");

    mysqli_query($mysqli,"UPDATE settings SET config_timezone = '$timezone' WHERE company_id = 1");

    // Logging
    logAction("Settings", "Edit", "$session_name edited localization settings");

    $_SESSION['alert_message'] = "Company localization updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}
