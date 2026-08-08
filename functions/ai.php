<?php

/*
 * ITFlow - AI helpers
 *
 * The three AI endpoints in agent/ajax.php each carried their own copy of the model
 * lookup and the cURL boilerplate, which is how they all ended up hardcoding
 * use_case = 'General' and a temperature the provider may not accept. Model
 * selection and the provider call live here instead.
 */

// A provider that never answers would otherwise hold a PHP worker open until
// max_execution_time
DEFINE("AI_REQUEST_TIMEOUT", 60);

/*
 * The model configured for a use case - one of the values the add/edit modals offer:
 * General, Tickets, Documentation.
 *
 * A feature-specific model wins, a General model is the fallback, so an install with
 * a single General model keeps working everywhere. Returns null when nothing usable
 * is configured; callers report that rather than posting to an empty URL.
 */
function getAiModel($use_case = 'General') {

    global $mysqli;

    $use_case = escapeSql($use_case);

    // Feature-specific first, then General - FIELD() keeps that preference in SQL so
    // one query answers both
    $preference = ($use_case === 'General') ? "'General'" : "'$use_case', 'General'";

    $sql = mysqli_query($mysqli,
        "SELECT ai_model_name, ai_model_prompt, ai_model_use_case, ai_model_temperature,
                ai_provider_name, ai_provider_api_url, ai_provider_api_key
        FROM ai_models
        LEFT JOIN ai_providers ON ai_model_ai_provider_id = ai_provider_id
        WHERE ai_model_use_case IN ($preference)
        ORDER BY FIELD(ai_model_use_case, $preference), ai_model_id ASC
        LIMIT 1"
    );

    $model = mysqli_fetch_assoc($sql);

    // A model row with no provider behind it (or no endpoint) can't be called
    if (!$model || empty($model['ai_model_name']) || empty($model['ai_provider_api_url'])) {
        return null;
    }

    return $model;
}

/*
 * Posts a chat-completion request. Returns:
 *
 *     ['ok' => true,  'content' => '...']
 *     ['ok' => false, 'error'   => 'short message safe to show the user']
 *
 * Provider detail - status code, error type, code and message - goes to the app log
 * so a misconfiguration is diagnosable. The API key and the message bodies never do.
 */
function callAiApi($model, $messages) {

    $data = [
        'model'    => $model['ai_model_name'],
        'messages' => $messages,
    ];

    // Only send a temperature when the model has one configured. Newer OpenAI models
    // accept nothing but their own default and 400 on anything else, which is what
    // the old hardcoded 0.5 / 0.3 ran into.
    if (isset($model['ai_model_temperature']) && $model['ai_model_temperature'] !== '') {
        $data['temperature'] = floatval($model['ai_model_temperature']);
    }

    $ch = curl_init($model['ai_provider_api_url']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_TIMEOUT, AI_REQUEST_TIMEOUT);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $model['ai_provider_api_key'],
    ]);

    $response = curl_exec($ch);
    $status = intval(curl_getinfo($ch, CURLINFO_RESPONSE_CODE));
    $transport_error = curl_error($ch);
    curl_close($ch);

    // Enough to tell one provider/model pairing from another in the log
    $context = $model['ai_provider_name'] . ' / ' . $model['ai_model_name'];

    if ($response === false) {
        logApp('AI', 'error', "$context - could not reach the provider: $transport_error");
        return ['ok' => false, 'error' => 'Could not reach the AI provider.'];
    }

    $decoded = json_decode($response, true);

    if ($status < 200 || $status > 299) {
        $provider_error = $decoded['error'] ?? [];
        $detail = "$context - HTTP $status";
        foreach (['type', 'code', 'param'] as $field) {
            if (!empty($provider_error[$field])) {
                $detail .= " $field=" . $provider_error[$field];
            }
        }
        if (!empty($provider_error['message'])) {
            $detail .= ' - ' . $provider_error['message'];
        }
        logApp('AI', 'error', $detail);
        return ['ok' => false, 'error' => 'The AI provider rejected the request - see Admin > App Logs.'];
    }

    if (!isset($decoded['choices'][0]['message']['content'])) {
        logApp('AI', 'error', "$context - HTTP $status but the response carried no choices[0].message.content");
        return ['ok' => false, 'error' => 'The AI provider returned an unexpected response - see Admin > App Logs.'];
    }

    return ['ok' => true, 'content' => $decoded['choices'][0]['message']['content']];
}

/*
 * What to say when nothing is configured for a use case. Logged as well as shown,
 * because "no model" and "model rejected the request" look identical from the UI.
 */
function aiModelMissingError($use_case) {
    logApp('AI', 'warning', "No AI model configured for use case '$use_case' and no General model to fall back on");
    return "No AI model is configured for $use_case. Add one under Admin > AI Models.";
}
