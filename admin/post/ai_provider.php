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

    logAction("AI Provider", "Create", "$session_name created AI Provider $provider");

    flash_alert("AI Model <strong>$provider</strong> created");

    redirect();

}

if (isset($_POST['edit_ai_provider'])) {

    validateCSRFToken($_POST['csrf_token']);

    $provider_id = intval($_POST['provider_id']);
    $provider = sanitizeInput($_POST['provider']);
    $url = sanitizeInput($_POST['url']);
    $api_key = sanitizeInput($_POST['api_key']);

    mysqli_query($mysqli,"UPDATE ai_providers SET ai_provider_name = '$provider', ai_provider_api_url = '$url', ai_provider_api_key = '$api_key' WHERE ai_provider_id = $provider_id");

    logAction("AI Provider", "Edit", "$session_name edited AI Provider $provider");

    flash_alert("AI Model <strong>$provider</strong> edited");

    redirect();

}

if (isset($_GET['delete_ai_provider'])) {

    validateCSRFToken($_GET['csrf_token']);
    
    $provider_id = intval($_GET['delete_ai_provider']);

    $provider_name = sanitizeInput(getFieldById('ai_providers', $provider_id, 'ai_provider_name'));

    mysqli_query($mysqli,"DELETE FROM ai_providers WHERE ai_provider_id = $provider_id");

    logAction("AI Provider", "Delete", "$session_name deleted AI Provider $provider_name", 'error');

    flash_alert("AI Provider <strong>$provider_name</strong> deleted", 'error');

    redirect();

}
