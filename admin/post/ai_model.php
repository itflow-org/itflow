<?php

/*
 * ITFlow - GET/POST request handler for AI Models ('ai_model')
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_ai_model'])) {

    validateCSRFToken($_POST['csrf_token']);

    $provider_id = intval($_POST['provider']);
    $model = sanitizeInput($_POST['model']);
    $prompt = sanitizeInput($_POST['prompt']);
    $use_case = sanitizeInput($_POST['use_case']);

    mysqli_query($mysqli,"INSERT INTO ai_models SET ai_model_name = '$model', ai_model_prompt = '$prompt', ai_model_use_case = '$use_case', ai_model_ai_provider_id = $provider_id");

    $ai_model_id = mysqli_insert_id($mysqli);

    // Logging
    logAction("AI Model", "Create", "$session_name created AI Model $model");

    flash_alert("AI Model <strong>$model</strong> created");

    redirect();

}

if (isset($_POST['edit_ai_model'])) {

    validateCSRFToken($_POST['csrf_token']);

    $model_id = intval($_POST['model_id']);
    $model = sanitizeInput($_POST['model']);
    $prompt = sanitizeInput($_POST['prompt']);
    $use_case = sanitizeInput($_POST['use_case']);

    mysqli_query($mysqli,"UPDATE ai_models SET ai_model_name = '$model', ai_model_prompt = '$prompt', ai_model_use_case = '$use_case' WHERE ai_model_id = $model_id");

    // Logging
    logAction("AI Model", "Edit", "$session_name edited AI Model $model");

    flash_alert("AI Model <strong>$model</strong> edited");

    redirect();

}

if (isset($_GET['delete_ai_model'])) {

    validateCSRFToken($_GET['csrf_token']);
    
    $model_id = intval($_GET['delete_ai_model']);

    $sql = mysqli_query($mysqli,"SELECT ai_model_name FROM ai_models WHERE ai_model_id = $model_id");
    $row = mysqli_fetch_array($sql);
    $model_name = sanitizeInput($row['ai_model_name']);

    mysqli_query($mysqli,"DELETE FROM ai_models WHERE ai_model_id = $model_id");

    // Logging
    logAction("AI Model", "Delete", "$session_name deleted AI Model $model_name");

    flash_alert("AI Model <strong>$model_name</strong> deleted", 'error');

    redirect();

}
