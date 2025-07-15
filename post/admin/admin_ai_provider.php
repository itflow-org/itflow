<?php

/*
 * ITFlow - GET/POST request handler for AI Providers ('ai_provider')
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_ai_provider'])) {

    validateCSRFToken($_POST['csrf_token']);

    $provider = sanitizeInput($_POST['provider']);
    $url = sanitizeInput($_POST['url']);
    $model = sanitizeInput($_POST['model']);
    $api_key = sanitizeInput($_POST['api_key']);


    mysqli_query($mysqli,"INSERT INTO ai_providers SET ai_provider_name = '$provider', ai_provider_api_url = '$url', ai_provider_api_key = '$api_key'");

    $ai_provider_id = mysqli_insert_id($mysqli);

    // Logging
    logAction("AI Provider", "Create", "$session_name created AI Provider $provider");

    $_SESSION['alert_message'] = "AI Model <strong>$provider</strong> created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_ai_provider'])) {

    validateCSRFToken($_POST['csrf_token']);

    $provider_id = intval($_POST['provider_id']);
    $provider = sanitizeInput($_POST['provider']);
    $url = sanitizeInput($_POST['url']);
    $api_key = sanitizeInput($_POST['api_key']);

    mysqli_query($mysqli,"UPDATE ai_providers SET ai_provider_name = '$provider', ai_provider_api_url = '$url', ai_provider_api_key = '$api_key' WHERE ai_provider_id = $provider_id");

    // Logging
    logAction("AI Provider", "Edit", "$session_name edited AI Provider $provider");

    $_SESSION['alert_message'] = "AI Model <strong>$provider</strong> edited";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['delete_ai_provider'])) {

    validateCSRFToken($_GET['csrf_token']);
    
    $provider_id = intval($_GET['delete_ai_provider']);

    $sql = mysqli_query($mysqli,"SELECT ai_provider_name FROM ai_providers WHERE ai_provider_id = $provider_id");
    $row = mysqli_fetch_array($sql);
    $provider_name = sanitizeInput($row['ai_provider_name']);

    mysqli_query($mysqli,"DELETE FROM ai_providers WHERE ai_provider_id = $provider_id");

    // Logging
    logAction("AI Provider", "Delete", "$session_name deleted AI Provider $provider_name");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "AI Provider <strong>$provider_name</strong> deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}
