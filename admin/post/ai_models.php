<?php

/*
 * ITFlow - Admin > AI Models (admin/ai_models.php): adding one, changing it,
 * trying it and deleting it.
 *
 * The window asks first (check_ai_model, JSON) and says what is wrong beside
 * each field; with 'try' it also sends the model a one-line request, as it is
 * in the window - unsaved - and says what came back, how long it took and the
 * tokens it used (recorded in AI Usage as a test). What changed is in the
 * audit log, from what to what - the instructions only as changed.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['check_ai_model']) || isset($_POST['add_ai_model']) || isset($_POST['edit_ai_model'])) {

    validateCSRFToken();

    $checking = isset($_POST['check_ai_model']);
    $model_id = intval($_POST['model_id'] ?? 0);
    $before = $model_id ? aiModelRow($model_id) : null;
    $answer = function ($ok, $errors, $extra = []) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok, 'errors' => (object) $errors] + $extra);
        exit;
    };

    if ($model_id && !$before) {
        if ($checking) {
            $answer(false, ['model' => 'That model no longer exists - close this and look again.']);
        }
        flashAlert('That AI model no longer exists.', 'error');
        redirect('ai_models.php');
    }

    [$after, $errors, $provider] = aiModelCheck($_POST);

    if ($checking) {
        $tried = [];
        if (!$errors && !empty($_POST['try'])) {
            // As it is in the window, on its provider
            $model = $provider + [
                'ai_model_id' => $model_id,
                'ai_model_name' => $after['ai_model_name'],
                'ai_model_use_case' => $after['ai_model_use_case'],
                'ai_model_temperature' => $after['ai_model_temperature'],
                'ai_model_prompt' => $after['ai_model_prompt'],
            ];
            $started = microtime(true);
            $result = callAiApi($model, [
                ['role' => 'system', 'content' => 'You are checking that you are set up and working. Answer in one short, friendly sentence.'],
                ['role' => 'user', 'content' => 'Say hello, and which model you are.'],
            ], ['feature' => 'test']);
            $ms = intval(round((microtime(true) - $started) * 1000));
            if ($result['ok']) {
                $tried = ['reply' => mb_substr(trim(strip_tags($result['content'])), 0, 400), 'ms' => $ms];
            } else {
                $errors['model'] = $result['error'];
            }
        }
        $answer(!$errors, $errors, $tried);
    }

    // Only reached without the window's script - say the first thing wrong
    if ($errors) {
        flashAlert('Not saved - ' . escapeHtml(reset($errors)), 'error');
        redirect();
    }

    $name = $after['ai_model_name'];
    $use_case = $after['ai_model_use_case'];

    if (!$before) {
        // A second one for a use case isn't used - the first one is - so it is said
        $already = getAiModel($use_case);
        $model_id = aiModelWrite($after);
        logAudit("AI Model", "Create", "$session_name added AI model " . escapeSql("$name on {$provider['ai_provider_name']}, for $use_case"), 0, $model_id);
        if ($already && $already['ai_model_use_case'] === $use_case) {
            flashAlert('<strong>' . escapeHtml($name) . '</strong> added - but <strong>' . escapeHtml($already['ai_model_name']) . "</strong> is for $use_case already and is the one used. Delete it, or set it to something else, to use this one", 'warning');
        } else {
            flashAlert('<strong>' . escapeHtml($name) . "</strong> added - it is used for " . ($use_case === 'General' ? 'everything without a model of its own' : $use_case));
        }
        redirect('ai_models.php');
    }

    $lines = aiModelChangeLines($before, $after);
    if (!$lines) {
        flashAlert('Nothing changed on <strong>' . escapeHtml($name) . '</strong>', 'info');
        redirect();
    }
    aiModelWrite($after, $model_id);
    logAudit("AI Model", "Edit", "$session_name changed AI model " . escapeSql("$name: " . implode('; ', $lines)), 0, $model_id);
    flashAlert('<strong>' . escapeHtml($name) . '</strong> saved - ' . escapeHtml(count($lines) === 1 ? $lines[0] : count($lines) . ' changes'));
    redirect();

}

// Delete a model - what it was for falls back on General, or stops when it was General
if (isset($_GET['delete_ai_model'])) {

    validateCSRFToken();

    $row = aiModelRow($_GET['delete_ai_model']);
    if (!$row) {
        flashAlert('That AI model no longer exists.', 'error');
        redirect('ai_models.php');
    }
    $model_id = intval($row['ai_model_id']);
    $name = $row['ai_model_name'];

    mysqli_query($mysqli, "DELETE FROM ai_models WHERE ai_model_id = $model_id");

    logAudit("AI Model", "Delete", "$session_name deleted AI model " . escapeSql("$name on {$row['ai_provider_name']}, for {$row['ai_model_use_case']}"), 0, $model_id);
    flashAlert('<strong>' . escapeHtml($name) . '</strong> deleted', 'error');
    redirect('ai_models.php');

}
