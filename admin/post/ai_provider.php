<?php

/*
 * ITFlow - Admin > AI Providers (admin/ai_providers.php): adding one,
 * changing it, testing it and deleting it.
 *
 * Saving checks what was sent (aiProviderCheck(), functions/ai.php) and, when
 * its kind, address or key is new or changed, asks the provider for its models
 * - which says whether they work. The window asks first (check_ai_provider,
 * which answers in JSON) and says what is wrong beside each field; a provider
 * that doesn't answer right now can still be saved once the window has said
 * so (save_anyway). Adding one can add its first model too. What changed is in
 * the audit log, from what to what - the key only as changed.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['check_ai_provider']) || isset($_POST['add_ai_provider']) || isset($_POST['edit_ai_provider'])) {

    validateCSRFToken();

    $checking = isset($_POST['check_ai_provider']);
    $provider_id = intval($_POST['provider_id'] ?? 0);
    $before = $provider_id ? aiProviderRow($provider_id) : null;
    $answer = function ($ok, $errors, $extra = []) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok, 'errors' => (object) $errors] + $extra);
        exit;
    };

    if ($provider_id && !$before) {
        if ($checking) {
            $answer(false, ['name' => 'That provider no longer exists - close this and look again.']);
        }
        flashAlert('That AI provider no longer exists.', 'error');
        redirect('ai_providers.php');
    }

    [$after, $errors] = aiProviderCheck($_POST, $before);

    // Its first model, for everything - adding one while there is nothing for everything yet
    $first_model = !$before && !getAiModel('General') ? trim((string) ($_POST['first_model'] ?? '')) : '';
    if ($first_model !== '' && (mb_strlen($first_model) > 200 || (preg_match('/\s/', $first_model) && !in_array($after['ai_provider_kind'], ['azure', 'custom'], true)))) {
        $errors['first_model'] = "That isn't a model's name - " . aiProviderKind($after['ai_provider_kind'])['example'] . ', say.';
    }

    // New or changed - does it answer? Once it has, not asked again for ten minutes
    $test = ['ok' => true, 'models' => [], 'listed' => false, 'asked' => false];
    $soft = false;
    $save_anyway = !empty($_POST['save_anyway']);
    if (!array_intersect_key($errors, ['kind' => 1, 'url' => 1, 'api_key' => 1]) && (aiProviderConnectionChanged($after, $before) || !empty($_POST['test'])) && !($save_anyway && !$checking)) {
        $asked_key = hash('sha256', $after['ai_provider_kind'] . '|' . $after['ai_provider_api_url'] . '|' . $after['ai_provider_api_key']);
        $asked_at = $_SESSION['ai_provider_asked'][$asked_key] ?? 0;
        if ($checking || time() - $asked_at > 600) {
            $test = aiProviderTest($after['ai_provider_kind'], $after['ai_provider_api_url'], $after['ai_provider_api_key']) + ['asked' => true];
            if ($test['ok']) {
                $_SESSION['ai_provider_asked'] = [$asked_key => time()];
            } else {
                // A key turned away is the key; anything else is the address, or it being down right now
                $errors[strpos($test['error'], 'turned the key away') !== false ? 'api_key' : 'url'] = $test['error'];
                $soft = true;
            }
        }
    }

    if ($checking) {
        $answer(!$errors, $errors, [
            'soft' => $soft && count($errors) === 1,
            'models' => $test['models'],
            'listed' => $test['listed'],
            'asked' => $test['asked'],
            'url' => $after['ai_provider_api_url'],
        ]);
    }

    // Only reached without the window's script - say the first thing wrong
    if ($errors) {
        flashAlert('Not saved - ' . escapeHtml(reset($errors)), 'error');
        redirect();
    }

    $name = $after['ai_provider_name'];
    $kind_name = aiProviderKind($after['ai_provider_kind'])['name'];

    if (!$before) {
        $provider_id = aiProviderWrite($after);
        logAudit("AI Provider", "Create", "$session_name added AI provider " . escapeSql("$name ($kind_name, {$after['ai_provider_api_url']})" . ($after['ai_provider_redact'] ? '' : ' - passwords and keys sent as they are')), 0, $provider_id);
        if ($first_model !== '') {
            $model_id = aiModelWrite(['ai_model_ai_provider_id' => $provider_id, 'ai_model_name' => $first_model, 'ai_model_use_case' => 'General', 'ai_model_temperature' => null, 'ai_model_prompt' => '']);
            logAudit("AI Model", "Create", "$session_name added AI model " . escapeSql("$first_model on $name, for General"), 0, $model_id);
            flashAlert('<strong>' . escapeHtml($name) . '</strong> added, with <strong>' . escapeHtml($first_model) . '</strong> for everything - AI is ready to use');
        } else {
            flashAlert('<strong>' . escapeHtml($name) . '</strong> added - now add the model to use on it');
        }
        redirect('ai_providers.php');
    }

    $lines = aiProviderChangeLines($before, $after);
    if (!$lines) {
        flashAlert('Nothing changed on <strong>' . escapeHtml($name) . '</strong>', 'info');
        redirect();
    }
    aiProviderWrite($after, $provider_id);
    logAudit("AI Provider", "Edit", "$session_name changed AI provider " . escapeSql("$name: " . implode('; ', $lines)), 0, $provider_id);
    flashAlert('<strong>' . escapeHtml($name) . '</strong> saved - ' . escapeHtml(count($lines) === 1 ? $lines[0] : count($lines) . ' changes'));
    redirect();

}

// The models a provider has - for the model window's suggestions. JSON.
if (isset($_POST['ai_provider_models'])) {

    validateCSRFToken();

    header('Content-Type: application/json; charset=utf-8');
    $row = aiProviderRow($_POST['ai_provider_models']);
    if (!$row) {
        echo json_encode(['ok' => false, 'models' => [], 'error' => 'That provider no longer exists.', 'listed' => false, 'example' => '']);
        exit;
    }
    // Asked once in ten minutes per provider - its models don't change from one window to the next
    $cache_key = hash('sha256', $row['ai_provider_kind'] . '|' . $row['ai_provider_api_url'] . '|' . $row['ai_provider_api_key']);
    $cached = $_SESSION['ai_provider_models'][$cache_key] ?? null;
    if ($cached && time() - $cached['at'] < 600) {
        echo json_encode($cached['answer']);
        exit;
    }
    $test = aiProviderTest($row['ai_provider_kind'], $row['ai_provider_api_url'], $row['ai_provider_api_key']);
    $answer = ['ok' => $test['ok'], 'models' => $test['models'], 'error' => $test['error'], 'listed' => $test['listed'], 'example' => aiProviderKind($row['ai_provider_kind'])['example']];
    if ($test['ok']) {
        $_SESSION['ai_provider_models'] = [$cache_key => ['at' => time(), 'answer' => $answer]];
    }
    echo json_encode($answer);
    exit;

}

// Test a provider - from its menu: does it answer, with its key, and has it the models set up on it?
if (isset($_GET['test_ai_provider'])) {

    validateCSRFToken();

    $row = aiProviderRow($_GET['test_ai_provider']);
    if (!$row) {
        flashAlert('That AI provider no longer exists.', 'error');
        redirect('ai_providers.php');
    }
    $name = escapeHtml($row['ai_provider_name']);
    $test = aiProviderTest($row['ai_provider_kind'], $row['ai_provider_api_url'], $row['ai_provider_api_key']);
    if (!$test['ok']) {
        logApp('AI', 'warning', "Test of AI provider {$row['ai_provider_name']} - {$test['error']}");
        flashAlert("<strong>$name</strong> didn't answer as it should - " . escapeHtml($test['error']), 'error');
    } elseif (!$test['listed']) {
        flashAlert("<strong>$name</strong> doesn't list its models, so it can't be tested on its own - use Try it on one of its models", 'info');
    } else {
        $count = count($test['models']);
        $ours = [];
        $sql = mysqli_query($mysqli, "SELECT ai_model_name FROM ai_models WHERE ai_model_ai_provider_id = " . intval($row['ai_provider_id']) . " ORDER BY ai_model_name");
        while ($model = mysqli_fetch_row($sql)) {
            $ours[] = $model[0];
        }
        $missing = $count ? array_values(array_diff($ours, $test['models'])) : [];
        flashAlert("<strong>$name</strong> answered - $count model" . ($count === 1 ? '' : 's') . ' there'
            . ($missing ? '. It has no <strong>' . implode('</strong> or <strong>', array_map('escapeHtml', $missing)) . '</strong> - check the name in AI Models' : ''), $missing ? 'warning' : 'success');
    }
    redirect();

}

// Delete a provider - its models go with it
if (isset($_GET['delete_ai_provider'])) {

    validateCSRFToken();

    $row = aiProviderRow($_GET['delete_ai_provider']);
    if (!$row) {
        flashAlert('That AI provider no longer exists.', 'error');
        redirect('ai_providers.php');
    }
    $provider_id = intval($row['ai_provider_id']);
    $name = $row['ai_provider_name'];
    $models = [];
    $sql = mysqli_query($mysqli, "SELECT ai_model_name FROM ai_models WHERE ai_model_ai_provider_id = $provider_id ORDER BY ai_model_name");
    while ($model = mysqli_fetch_row($sql)) {
        $models[] = $model[0];
    }

    // Its models cascade with it
    mysqli_query($mysqli, "DELETE FROM ai_providers WHERE ai_provider_id = $provider_id");

    logAudit("AI Provider", "Delete", "$session_name deleted AI provider " . escapeSql($name . ($models ? ' - and with it ' . implode(', ', $models) : '')), 0, $provider_id);
    flashAlert('<strong>' . escapeHtml($name) . '</strong> deleted' . ($models ? ', with ' . count($models) . ' model' . (count($models) === 1 ? '' : 's') : ''), 'error');
    redirect('ai_providers.php');

}
