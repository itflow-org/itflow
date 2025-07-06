<?php

/*
 * ITFlow - GET/POST request handler for AI Providers ('ai_providers')
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_ai_provider'])) {

    validateCSRFToken($_GET['csrf_token']);

    $provider = sanitizeInput($_POST['provider']);
    $url = sanitizeInput($_POST['url']);
    $model = sanitizeInput($_POST['model']);
    $api_key = sanitizeInput($_POST['api_key']);


    mysqli_query($mysqli,"INSERT INTO ai_providers SET ai_provider_name = '$name', ai_provider_url = '$url', ai_provider_api_key = '$api_key'");

    $ai_provider_id = mysqli_insert_id($mysqli);

    if ($model) {
        mysqli_query($mysqli,"INSERT INTO ai_models SET ai_model_name = '$model'");
    }

    // Logging
    logAction("AI Provider", "Create", "$session_name created AI Provider $provider");

    $_SESSION['alert_message'] = "AI Model <strong>$provider</strong> created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_ai_provider'])) {

    validateCSRFToken($_GET['csrf_token']);

    $provider_id intval($_POST['provider_id'])
    $provider = sanitizeInput($_POST['provider']);
    $url = sanitizeInput($_POST['url']);
    $api_key = sanitizeInput($_POST['api_key']);

    mysqli_query($mysqli,"UPDATE ai_providers SET ai_provider_name = '$name', ai_provider_url = '$url', ai_provider_api_key = '$api_key' WHERE ai_provider_id = $provider_id");

    // Logging
    logAction("AI Provider", "Edit", "$session_name edited AI Provider $provider");

    $_SESSION['alert_message'] = "AI Model <strong>$provider</strong> edited";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['delete_ai_provider'])) {
    
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

if (isset($_POST['add_ai_model'])) {

    validateCSRFToken($_GET['csrf_token']);

    $provider_id = intval($_POST['provider_id']);
    $model = sanitizeInput($_POST['model']);
    $prompt = sanitizeInput($_POST['prompt']);
    $use_case = sanitizeInput($_POST['use_case']);

    mysqli_query($mysqli,"INSERT INTO ai_models SET ai_model_name = '$model', ai_model_prompt = '$prompt', ai_model_use_case = '$use_case', ai_model_ai_provider_id = $provider_id");

    $ai_model_id = mysqli_insert_id($mysqli);

    // Logging
    logAction("AI Model", "Create", "$session_name created AI Model $model");

    $_SESSION['alert_message'] = "AI Model <strong>$model</strong> created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_ai_model'])) {

    validateCSRFToken($_GET['csrf_token']);

    $model_id = intval($_POST['model_id']);
    $model = sanitizeInput($_POST['model']);
    $prompt = sanitizeInput($_POST['prompt']);
    $use_case = sanitizeInput($_POST['use_case']);

    mysqli_query($mysqli,"UPDATE ai_models SET ai_model_name = '$model', ai_model_prompt = '$prompt', ai_model_use_case = '$use_case' WHERE ai_model_id = $model_id");

    // Logging
    logAction("AI Model", "Edit", "$session_name edited AI Model $model");

    $_SESSION['alert_message'] = "AI Model <strong>$model</strong> edited";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['delete_ai_model'])) {
    
    $model_id = intval($_GET['delete_ai_model']);

    $sql = mysqli_query($mysqli,"SELECT ai_model_name FROM ai_models WHERE ai_model_id = $model_id");
    $row = mysqli_fetch_array($sql);
    $model_name = sanitizeInput($row['ai_model_name']);

    mysqli_query($mysqli,"DELETE FROM ai_models WHERE ai_model_id = $model_id");

    // Logging
    logAction("AI Model", "Delete", "$session_name deleted AI Model $model_name");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "AI Model <strong>$model_name</strong> deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

