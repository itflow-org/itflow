<?php

/*
 * ITFlow - GET/POST request handler for AI Models ('ai_models')
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_ai_model'])) {

    validateCSRFToken();

    $provider_id = intval($_POST['provider']);
    $model = escapeSql($_POST['model']);
    $prompt = escapeSql($_POST['prompt']);
    $use_case = escapeSql($_POST['use_case']);

    // Blank means "send no temperature at all" - the only setting that works on every
    // provider. Anything else rides as a numeric literal, so no quoting.
    $temperature = ($_POST['temperature'] ?? '') === '' ? 'NULL' : floatval($_POST['temperature']);

    mysqli_query($mysqli,"INSERT INTO ai_models SET ai_model_name = '$model', ai_model_prompt = '$prompt', ai_model_use_case = '$use_case', ai_model_temperature = $temperature, ai_model_ai_provider_id = $provider_id");

    if (!mysqli_affected_rows($mysqli)) {
        logApp('AI', 'error', 'Failed to create AI Model ' . $model . ': ' . mysqli_error($mysqli));
        flashAlert("AI Model <strong>$model</strong> could not be created - see Admin > App Logs", 'error');
        redirect();
    }

    logAudit("AI Model", "Create", "$session_name created AI Model $model");

    flashAlert("AI Model <strong>$model</strong> created");

    redirect();

}

if (isset($_POST['edit_ai_model'])) {

    validateCSRFToken();

    $model_id = intval($_POST['model_id']);
    $model = escapeSql($_POST['model']);
    $prompt = escapeSql($_POST['prompt']);
    $use_case = escapeSql($_POST['use_case']);

    // Blank means "send no temperature at all" - the only setting that works on every
    // provider. Anything else rides as a numeric literal, so no quoting.
    $temperature = ($_POST['temperature'] ?? '') === '' ? 'NULL' : floatval($_POST['temperature']);

    mysqli_query($mysqli,"UPDATE ai_models SET ai_model_name = '$model', ai_model_prompt = '$prompt', ai_model_use_case = '$use_case', ai_model_temperature = $temperature WHERE ai_model_id = $model_id");

    logAudit("AI Model", "Edit", "$session_name edited AI Model $model");

    flashAlert("AI Model <strong>$model</strong> edited");

    redirect();

}

if (isset($_GET['delete_ai_model'])) {

    validateCSRFToken();

    $model_id = intval($_GET['delete_ai_model']);

    $model_name = escapeSql(getFieldById('ai_models', $model_id, 'ai_model_name'));

    mysqli_query($mysqli,"DELETE FROM ai_models WHERE ai_model_id = $model_id");

    logAudit("AI Model", "Delete", "$session_name deleted AI Model $model_name");

    flashAlert("AI Model <strong>$model_name</strong> deleted", 'error');

    redirect();

}
