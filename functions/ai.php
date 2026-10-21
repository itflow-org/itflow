<?php

/*
 * ITFlow - AI
 *
 * The AI providers set up in Admin > AI Providers - their kind (OpenAI, Azure,
 * a model run on your own network...) decides how each is called - the models
 * on them, each for a use case with General the fallback, and every request
 * made to one: who may make it (the role's Use AI permission), the passwords
 * and keys taken out of it first and put back in the answer (aiRedact()), and
 * what it cost (ai_usage - Admin > AI Usage).
 *
 * The features themselves - Reword, the ticket summary, a document template
 * draft - are the endpoints in agent/ajax.php, each naming itself
 * (aiFeatures()) so its requests can be told apart.
 */

// A provider that never answers would otherwise hold a PHP worker open until max_execution_time
DEFINE("AI_REQUEST_TIMEOUT", 60);

/*
 * The kinds of provider, ready made - name, chat completions address, whether
 * it needs a key, how the key is sent (bearer, or Azure's api-key header),
 * whether and how it lists its models, whether it runs on your own network (so
 * nothing leaves it), where its keys are made and a model to start with.
 * 'custom' is any other OpenAI-compatible endpoint.
 */
function aiProviderKinds() {
    return [
        'openai' => ['name' => 'OpenAI', 'url' => 'https://api.openai.com/v1/chat/completions', 'key' => true, 'auth' => 'bearer', 'models' => 'openai', 'local' => false,
            'keys_url' => 'https://platform.openai.com/api-keys', 'example' => 'gpt-5-mini'],
        'anthropic' => ['name' => 'Anthropic (Claude)', 'url' => 'https://api.anthropic.com/v1/chat/completions', 'key' => true, 'auth' => 'bearer', 'models' => 'anthropic', 'local' => false,
            'keys_url' => 'https://console.anthropic.com/settings/keys', 'example' => 'claude-sonnet-5-5'],
        'azure' => ['name' => 'Azure OpenAI', 'url' => 'https://YOUR-RESOURCE.openai.azure.com/openai/v1/chat/completions', 'key' => true, 'auth' => 'api-key', 'models' => '', 'local' => false,
            'keys_url' => 'https://portal.azure.com', 'example' => 'your deployment name'],
        'gemini' => ['name' => 'Google Gemini', 'url' => 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions', 'key' => true, 'auth' => 'bearer', 'models' => 'openai', 'local' => false,
            'keys_url' => 'https://aistudio.google.com/apikey', 'example' => 'gemini-2.5-flash'],
        'mistral' => ['name' => 'Mistral', 'url' => 'https://api.mistral.ai/v1/chat/completions', 'key' => true, 'auth' => 'bearer', 'models' => 'openai', 'local' => false,
            'keys_url' => 'https://console.mistral.ai/api-keys', 'example' => 'mistral-small-latest'],
        'groq' => ['name' => 'Groq', 'url' => 'https://api.groq.com/openai/v1/chat/completions', 'key' => true, 'auth' => 'bearer', 'models' => 'openai', 'local' => false,
            'keys_url' => 'https://console.groq.com/keys', 'example' => 'llama-3.3-70b-versatile'],
        'openrouter' => ['name' => 'OpenRouter', 'url' => 'https://openrouter.ai/api/v1/chat/completions', 'key' => true, 'auth' => 'bearer', 'models' => 'openai', 'local' => false,
            'keys_url' => 'https://openrouter.ai/keys', 'example' => 'openai/gpt-5-mini'],
        'ollama' => ['name' => 'Ollama', 'url' => 'http://localhost:11434/v1/chat/completions', 'key' => false, 'auth' => 'bearer', 'models' => 'openai', 'local' => true,
            'keys_url' => '', 'example' => 'llama3.1'],
        'lmstudio' => ['name' => 'LM Studio', 'url' => 'http://localhost:1234/v1/chat/completions', 'key' => false, 'auth' => 'bearer', 'models' => 'openai', 'local' => true,
            'keys_url' => '', 'example' => 'the model loaded in LM Studio'],
        'custom' => ['name' => 'Other (OpenAI-compatible)', 'url' => '', 'key' => false, 'auth' => 'bearer', 'models' => 'openai', 'local' => false,
            'keys_url' => '', 'example' => 'its model name'],
    ];
}

/* A provider's kind, as aiProviderKinds() describes it - custom for one it doesn't know. */
function aiProviderKind($kind) {
    $kinds = aiProviderKinds();
    return $kinds[(string) $kind] ?? $kinds['custom'];
}

/* What a model can be for, and the features each covers. A use case with no model of its own uses General. */
function aiUseCases() {
    return [
        'General' => 'Everything without a model of its own - Reword and translate in any editor, and client briefings.',
        'Tickets' => 'Drafting replies, triage, summaries, and Reword and translate in the ticket reply editor.',
        'Documentation' => 'Drafting and reviewing documents, writing a ticket up as a document, and drafting document templates.',
    ];
}

/* Each AI feature - its name as Admin > AI Usage shows it. */
function aiFeatures() {
    return [
        'reword' => 'Reword',
        'reword_ticket' => 'Reword a ticket reply',
        'translate' => 'Translate',
        'ticket_draft' => 'Draft a reply',
        'ticket_triage' => 'Triage',
        'ticket_summary' => 'Ticket summary',
        'document_template' => 'Document template draft',
        'ticket_document' => 'Ticket written up as a document',
        'document_draft' => 'Document draft',
        'document_review' => 'Document review',
        'client_briefing' => 'Client briefing',
        'test' => 'Test',
    ];
}

/* Whether the signed-in agent may use AI - their role's Use AI permission (admins always). */
function aiCan() {
    return (bool) lookupUserPermission('module_ai');
}

/*
 * The model configured for a use case - one of aiUseCases(). A feature-specific
 * model wins, a General model is the fallback, so an install with a single
 * General model works everywhere. Null when nothing usable is configured;
 * callers report that rather than posting to an empty URL.
 */
function getAiModel($use_case = 'General') {
    global $mysqli;
    static $models = [];
    $use_case = isset(aiUseCases()[$use_case]) ? $use_case : 'General';
    if (array_key_exists($use_case, $models)) {
        return $models[$use_case];
    }

    // Feature-specific first, then General - FIELD() keeps that preference in SQL so one query answers both
    $preference = ($use_case === 'General') ? "'General'" : "'$use_case', 'General'";

    // Before the 3.3.8 update, every provider is called as an OpenAI-compatible one, secrets taken out
    $provider_columns = aiUsageTableExists() ? 'ai_provider_kind, ai_provider_redact' : "'custom' AS ai_provider_kind, 1 AS ai_provider_redact";

    $model = mysqli_fetch_assoc(mysqli_query($mysqli,
        "SELECT ai_model_id, ai_model_name, ai_model_prompt, ai_model_use_case, ai_model_temperature,
                ai_provider_id, ai_provider_name, $provider_columns, ai_provider_api_url, ai_provider_api_key
        FROM ai_models
        JOIN ai_providers ON ai_model_ai_provider_id = ai_provider_id
        WHERE ai_model_use_case IN ($preference)
        ORDER BY FIELD(ai_model_use_case, $preference), ai_model_id ASC
        LIMIT 1"));

    // A model with no name, or a provider with no address, can't be called
    if (!$model || trim((string) $model['ai_model_name']) === '' || trim((string) $model['ai_provider_api_url']) === '') {
        $model = null;
    }
    return $models[$use_case] = $model;
}

/* Whether AI is there for this agent, for a use case - set up, and their role may use it. */
function aiReady($use_case = 'General') {
    return aiCan() && getAiModel($use_case) !== null;
}

/*
 * What the editors' script needs to know (js/app.js reads window.itflowAi) -
 * whether Reword is there in a ticket reply and elsewhere, and where to ask.
 * False when it is in neither - then the editors have no Reword button.
 */
function aiClientConfig() {
    if (empty($_SESSION['logged']) || !aiCan()) {
        return false;
    }
    $general = getAiModel('General') !== null;
    $tickets = getAiModel('Tickets') !== null;
    if (!$general && !$tickets) {
        return false;
    }
    // Draft is on the ticket reply editor only - its textarea says which ticket
    return ['reword' => $general, 'rewordTicket' => $tickets, 'draft' => $tickets, 'endpoint' => '/agent/ajax.php',
        'tones' => aiRewordTones(), 'languages' => aiLanguages()];
}

/* What Reword can do to text - tone => what the editor's menu calls it. Reword is the button itself; Translate asks which language. */
function aiRewordTones() {
    return [
        'reword' => 'Reword - clearer and more professional',
        'shorter' => 'Make it shorter',
        'formal' => 'More formal',
        'friendly' => 'Friendlier',
        'plain' => 'Plain English, for someone non-technical',
        'structure' => 'Organize it - headings and lists',
        'proofread' => 'Just fix spelling and grammar',
        'translate' => 'Translate...',
    ];
}

/* The languages Translate offers. */
function aiLanguages() {
    return ['English', 'Spanish', 'French', 'German', 'Portuguese', 'Italian', 'Dutch', 'Polish', 'Swedish', 'Danish', 'Norwegian', 'Finnish', 'Czech', 'Romanian',
        'Hungarian', 'Greek', 'Turkish', 'Russian', 'Ukrainian', 'Arabic', 'Hebrew', 'Hindi', 'Vietnamese', 'Thai', 'Indonesian', 'Filipino', 'Chinese (Simplified)',
        'Chinese (Traditional)', 'Japanese', 'Korean'];
}

/*
 * What Reword asks of the model, for a tone (aiRewordTones()) - in a ticket
 * reply ($use_case 'Tickets') or anywhere else - and the language for
 * Translate. Every tone keeps the facts, names, numbers, links, commands and
 * placeholders, and the HTML formatting.
 */
function aiRewordInstructions($use_case, $tone = 'reword', $language = '') {
    $what = $use_case === 'Tickets' ? 'the replies an IT services company sends on support tickets' : 'what an IT services company writes';
    $asks = [
        'reword' => $use_case === 'Tickets'
            ? 'Rewrite the reply you are given so it reads clearly, professionally and kindly - fix spelling and grammar, and make it easy for someone who isn\'t technical to follow.'
            : 'Rewrite the text you are given so it reads clearly and professionally - fix spelling and grammar, and tighten it.',
        'shorter' => 'Rewrite the text you are given shorter - about half as long - keeping everything that matters and dropping repetition and filler.',
        'formal' => 'Rewrite the text you are given in a more formal, businesslike tone, still clear and plain.',
        'friendly' => 'Rewrite the text you are given in a warmer, friendlier tone, still professional - no exclamation marks every sentence.',
        'plain' => 'Rewrite the text you are given in plain English for someone who isn\'t technical: short sentences, everyday words, and a few words to explain any technical term that has to stay.',
        'structure' => 'Organize the text you are given: put it under short headings, turn steps into numbered lists and other runs of items into bullet lists, and split long paragraphs - changing as few words as you can.',
        'proofread' => 'Fix only the spelling, grammar and punctuation of the text you are given. Change nothing else - not the wording, the tone or the order.',
        'translate' => 'Translate the text you are given into ' . ($language !== '' ? $language : 'English') . ', as a native speaker in IT support would write it. Leave product names, commands, file paths, error messages quoted from software, and placeholders in square brackets as they are.',
    ];
    $instructions = "You edit $what. " . ($asks[$tone] ?? $asks['reword']);
    $instructions .= ' Keep its meaning and every fact, name, number, date, link, command and placeholder in square brackets exactly as they are.'
        . ($tone === 'translate' ? '' : ' Write in the language it is written in.')
        . ' Don\'t add anything it doesn\'t say. Keep its HTML formatting - paragraphs, line breaks, lists, bold and links' . ($tone === 'structure' ? ', adding the headings and lists asked for' : '')
        . ' - and answer with the HTML only: no introduction, no notes, no code fences.';
    return $instructions;
}

/*
 * What to say when nothing is configured for a use case. Logged as well as shown,
 * because "no model" and "model rejected the request" look identical from the UI.
 */
function aiModelMissingError($use_case) {
    logApp('AI', 'warning', "No AI model configured for use case '$use_case' and no General model to fall back on");
    return "No AI model is set up for $use_case. Add one in Admin > AI Providers.";
}

/* ------------------------------------------------------------- Keeping secrets out */

/*
 * Passwords, keys and the like taken out of text before it goes to a provider:
 * [the text with each replaced by [secret-1], [secret-2]..., the placeholders =>
 * what they stand for]. The same secret twice gets the same placeholder. What
 * comes back is put right with aiRestore(), so the provider never sees them and
 * the answer still has them. Works on plain text and on an editor's HTML.
 */
function aiRedact($text, array &$secrets = []) {
    $text = (string) $text;
    $keep = function ($secret) use (&$secrets) {
        $found = array_search($secret, $secrets, true);
        if ($found !== false) {
            return $found;
        }
        $placeholder = '[secret-' . (count($secrets) + 1) . ']';
        $secrets[$placeholder] = $secret;
        return $placeholder;
    };
    $whole = function ($m) use ($keep) {
        return $keep($m[0]);
    };

    // Things that are secret whole - private keys, 2FA setup links, the keys services hand out
    foreach ([
        '/-----BEGIN [A-Z ]*PRIVATE KEY-----.*?-----END [A-Z ]*PRIVATE KEY-----/s',
        '#otpauth://[^\s<>"\']+#i',
        '/\b(?:sk|rk|pk)_live_[A-Za-z0-9]{10,}\b/',
        '/\b(?:sk|rk)_test_[A-Za-z0-9]{10,}\b/',
        '/\bsk-(?:proj-|ant-[a-z0-9]*-?|or-v1-)?[A-Za-z0-9_-]{20,}/',
        '/\b(?:AKIA|ASIA)[0-9A-Z]{16}\b/',
        '/\b(?:gh[pousr]_[A-Za-z0-9]{30,}|github_pat_[A-Za-z0-9_]{30,})\b/',
        '/\bglpat-[A-Za-z0-9_-]{20,}/',
        '/\bxox[abprs]-[A-Za-z0-9-]{10,}/',
        '/\bAIza[0-9A-Za-z_-]{35}\b/',
        '/\beyJ[A-Za-z0-9_-]{10,}\.eyJ[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}/',
    ] as $pattern) {
        $text = preg_replace_callback($pattern, $whole, $text);
    }

    // What follows "Password:", "PIN =", "API key:" and the like - the label stays.
    // An editor's tags may sit around the colon (<strong>Password:</strong> ...).
    $gap = '(?:\s|&nbsp;|<\/?[a-z][^>]*>)*';
    $value = '((?:&(?!nbsp;)|[^\s<>"\'&\x{201C}\x{201D}]){3,})';
    $tidy = function ($m) use ($keep) {
        $secret = $m[2];
        if (strpos($secret, '[secret-') === 0) {
            return $m[0];
        }
        // A full stop or comma after it ends the sentence, not the password
        $after = '';
        if (preg_match('/^(.{3,}?)([.,;)]+)$/su', $secret, $split)) {
            [$secret, $after] = [$split[1], $split[2]];
        }
        return $m[1] . $keep($secret) . $after;
    };
    $labels = 'password|passwd|pwd|passphrase|passcode|pin|secret|client[ _-]?secret|api[ _-]?key|access[ _-]?key|secret[ _-]?key|token|otp|(?:2fa|mfa|totp) code|recovery codes?|wi-?fi key|psk';
    // In quotes, spaces and all - Password: "correct horse battery"
    $text = preg_replace_callback("/(\\b(?:$labels)\\b{$gap}[:=]{$gap})([\"'\\x{201C}])([^\"'\\x{201D}<>\\n]{1,200})([\"'\\x{201D}])/iu", function ($m) use ($keep) {
        return $m[1] . $m[2] . $keep($m[3]) . $m[4];
    }, $text);
    $text = preg_replace_callback("/(\\b(?:$labels)\\b{$gap}[:=]{$gap}[\"'\\x{201C}]?)$value/iu", $tidy, $text);
    // "The password is Summer2024!" - only when it looks like one, so "the password is wrong" stays
    $text = preg_replace_callback("/(\\b(?:password|passcode|passphrase|pin)\\s+(?:is|was|will be)\\s+{$gap}[\"'\\x{201C}]?)(?=[^\\s<>\"']*[0-9!@#\$%^*_+=?~-])$value/iu", $tidy, $text);

    // Card numbers - 13 to 19 digits that pass the Luhn check
    $text = preg_replace_callback('/\b\d(?:[ -]?\d){12,18}\b/', function ($m) use ($keep) {
        $digits = preg_replace('/\D/', '', $m[0]);
        $sum = 0;
        foreach (array_reverse(str_split($digits)) as $i => $digit) {
            $digit = intval($digit);
            if ($i % 2) {
                $digit *= 2;
                $digit = $digit > 9 ? $digit - 9 : $digit;
            }
            $sum += $digit;
        }
        return $sum % 10 === 0 ? $keep($m[0]) : $m[0];
    }, $text);

    return [$text, $secrets];
}

/* An answer with the secrets aiRedact() took out put back where it left them. */
function aiRestore($text, array $secrets) {
    return $secrets ? strtr((string) $text, $secrets) : (string) $text;
}

/* ------------------------------------------------------------- Calling a provider */

/* The headers a provider is sent - its key, as its kind takes it. */
function aiProviderHeaders($kind, $api_key, $json = true) {
    $headers = $json ? ['Content-Type: application/json'] : [];
    $api_key = trim((string) $api_key);
    if ($api_key !== '') {
        $headers[] = aiProviderKind($kind)['auth'] === 'api-key' ? "api-key: $api_key" : "Authorization: Bearer $api_key";
    }
    return $headers;
}

/*
 * Posts a chat-completion request for a feature (aiFeatures() - $context
 * 'feature', and 'client_id' when it is about a client's things). Returns:
 *
 *     ['ok' => true,  'content' => '...']
 *     ['ok' => false, 'error'   => 'short message safe to show the user']
 *
 * The first message is the feature's own instructions (a system message). The
 * model's instructions from Admin > AI Providers follow them. Passwords and keys
 * are taken out first when the provider is set to, and put back in the answer -
 * or, with $context 'secrets' => 'remove' (for what is kept, like a document),
 * left out as [removed]. Every request is recorded in ai_usage. Provider detail on a failure - status,
 * error type, code and message - goes to the app log so a misconfiguration is
 * diagnosable. The API key and the message bodies never do.
 */
function callAiApi($model, $messages, array $context = []) {
    $messages = array_values($messages);
    if (($messages[0]['role'] ?? '') !== 'system') {
        array_unshift($messages, ['role' => 'system', 'content' => 'You are a helpful assistant for an IT services company.']);
    }

    $secrets = [];
    if (!empty($model['ai_provider_redact'])) {
        foreach ($messages as $i => $message) {
            if ($message['role'] !== 'system') {
                [$messages[$i]['content']] = aiRedact($message['content'], $secrets);
            }
        }
        if ($secrets) {
            $messages[0]['content'] .= "\n\nSome passwords and keys have been replaced with placeholders like [secret-1]. Keep each placeholder exactly as it is wherever it belongs in your answer, and don't remark on them.";
        }
    }

    // The company's own instructions for the model - house style, things to always or never say
    $instructions = trim((string) ($model['ai_model_prompt'] ?? ''));
    if ($instructions !== '') {
        array_splice($messages, 1, 0, [['role' => 'system', 'content' => "Also follow these instructions from the company:\n$instructions"]]);
    }

    $data = ['model' => $model['ai_model_name'], 'messages' => $messages];

    // Only send a temperature when the model has one configured. Newer models
    // accept nothing but their own default and 400 on anything else.
    if (isset($model['ai_model_temperature']) && $model['ai_model_temperature'] !== '') {
        $data['temperature'] = floatval($model['ai_model_temperature']);
    }

    $started = microtime(true);
    $ch = curl_init($model['ai_provider_api_url']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_TIMEOUT, AI_REQUEST_TIMEOUT);
    curl_setopt($ch, CURLOPT_HTTPHEADER, aiProviderHeaders($model['ai_provider_kind'] ?? 'custom', $model['ai_provider_api_key']));

    $response = curl_exec($ch);
    $status = intval(curl_getinfo($ch, CURLINFO_RESPONSE_CODE));
    $transport_error = curl_error($ch);
    curl_close($ch);

    // Enough to tell one provider/model pairing from another in the log
    $label = $model['ai_provider_name'] . ' / ' . $model['ai_model_name'];
    $usage = [
        'feature' => (string) ($context['feature'] ?? 'other'),
        'model' => $model,
        'client_id' => intval($context['client_id'] ?? 0),
        'ms' => intval(round((microtime(true) - $started) * 1000)),
        'redacted' => count($secrets),
    ];

    if ($response === false) {
        logApp('AI', 'error', "$label - could not reach the provider: $transport_error");
        aiUsageRecord($usage + ['ok' => false, 'error' => 'Could not reach the provider']);
        return ['ok' => false, 'error' => 'Could not reach the AI provider.'];
    }

    $decoded = json_decode($response, true);

    if ($status < 200 || $status > 299) {
        $provider_error = $decoded['error'] ?? [];
        $provider_error = is_array($provider_error) ? $provider_error : ['message' => (string) $provider_error];
        $detail = "$label - HTTP $status";
        foreach (['type', 'code', 'param'] as $field) {
            if (!empty($provider_error[$field]) && is_scalar($provider_error[$field])) {
                $detail .= " $field=" . $provider_error[$field];
            }
        }
        if (!empty($provider_error['message']) && is_scalar($provider_error['message'])) {
            $detail .= ' - ' . $provider_error['message'];
        }
        logApp('AI', 'error', $detail);
        aiUsageRecord($usage + ['ok' => false, 'error' => "HTTP $status" . (!empty($provider_error['message']) && is_scalar($provider_error['message']) ? ' - ' . $provider_error['message'] : '')]);
        return ['ok' => false, 'error' => "The AI provider turned the request away (HTTP $status) - see Admin > App Logs."];
    }

    $content = $decoded['choices'][0]['message']['content'] ?? null;
    if (!is_string($content)) {
        logApp('AI', 'error', "$label - HTTP $status but the response carried no choices[0].message.content");
        aiUsageRecord($usage + ['ok' => false, 'error' => 'No answer in the response']);
        return ['ok' => false, 'error' => 'The AI provider returned an unexpected response - see Admin > App Logs.'];
    }

    aiUsageRecord($usage + ['ok' => true, 'tokens_in' => intval($decoded['usage']['prompt_tokens'] ?? 0), 'tokens_out' => intval($decoded['usage']['completion_tokens'] ?? 0)]);

    // Put back - or, for what is kept (a document), left out for good
    if (($context['secrets'] ?? 'restore') === 'remove') {
        return ['ok' => true, 'content' => aiRestore($content, array_fill_keys(array_keys($secrets), '[removed]')), 'removed' => count($secrets)];
    }
    return ['ok' => true, 'content' => aiRestore($content, $secrets)];
}

/*
 * An answer that should be HTML, as safe HTML - without the code fences or a
 * leading "html" models like to add, and through the purifier the document
 * pages use, so nothing in a ticket can make it carry a script.
 */
function aiCleanHtml($content) {
    $content = trim((string) $content);
    $content = preg_replace('/^```[a-z]*\s*/i', '', $content);
    $content = preg_replace('/\s*```$/', '', $content);
    $content = preg_replace('/^html\b\s*/i', '', $content);
    return trim(documentHtmlPurify(str_replace('```', '', $content)));
}

/* ------------------------------------------------------------- Usage */

/*
 * Whether the 3.3.8 update has run - the providers' kind and redact columns,
 * and ai_usage. Until it has (new files, database not yet updated), AI works
 * as before and nothing is recorded. The database version says, where it is
 * known; otherwise the table and column are looked for.
 */
function aiUsageTableExists() {
    global $mysqli;
    static $exists = null;
    if ($exists === null) {
        if (defined('CURRENT_DATABASE_VERSION') && version_compare(CURRENT_DATABASE_VERSION, '3.3.8', '>=')) {
            $exists = true;
        } else {
            // Both what it adds - the table, and the providers' kind
            $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
                AND ((TABLE_NAME = 'ai_usage' AND COLUMN_NAME = 'ai_usage_id') OR (TABLE_NAME = 'ai_providers' AND COLUMN_NAME = 'ai_provider_kind'))"));
            $exists = intval($row[0] ?? 0) === 2;
        }
    }
    return $exists;
}

/* Record one request - its feature, model (the row callAiApi() was given), client, tokens, time, how many secrets were taken out, and whether it worked. */
function aiUsageRecord(array $usage) {
    global $mysqli, $session_user_id;
    if (!aiUsageTableExists()) {
        return;
    }
    $model = $usage['model'] ?? [];
    $text = function ($value, $length) {
        return "'" . mysqli_real_escape_string($GLOBALS['mysqli'], mb_substr((string) $value, 0, $length)) . "'";
    };
    mysqli_query($mysqli, "INSERT INTO ai_usage SET
        ai_usage_feature = " . $text($usage['feature'] ?? 'other', 50) . ",
        ai_usage_use_case = " . $text($model['ai_model_use_case'] ?? '', 50) . ",
        ai_usage_provider_id = " . intval($model['ai_provider_id'] ?? 0) . ",
        ai_usage_provider_name = " . $text($model['ai_provider_name'] ?? '', 200) . ",
        ai_usage_model_id = " . intval($model['ai_model_id'] ?? 0) . ",
        ai_usage_model = " . $text($model['ai_model_name'] ?? '', 200) . ",
        ai_usage_user_id = " . intval($session_user_id ?? 0) . ",
        ai_usage_client_id = " . intval($usage['client_id'] ?? 0) . ",
        ai_usage_tokens_in = " . intval($usage['tokens_in'] ?? 0) . ",
        ai_usage_tokens_out = " . intval($usage['tokens_out'] ?? 0) . ",
        ai_usage_ms = " . intval($usage['ms'] ?? 0) . ",
        ai_usage_redacted = " . intval($usage['redacted'] ?? 0) . ",
        ai_usage_ok = " . (!empty($usage['ok']) ? 1 : 0) . ",
        ai_usage_error = " . (!empty($usage['error']) ? $text($usage['error'], 250) : 'NULL'));
}

/*
 * The last $days by provider or model ($by 'provider' or 'model') - id =>
 * requests, failed, tokens in and out, and when it was last used. Tests aren't counted.
 */
function aiUsageTotals($by = 'provider', $days = 30) {
    global $mysqli;
    if (!aiUsageTableExists()) {
        return [];
    }
    $column = $by === 'model' ? 'ai_usage_model_id' : 'ai_usage_provider_id';
    $totals = [];
    $sql = mysqli_query($mysqli, "SELECT $column, COUNT(*), SUM(ai_usage_ok = 0), SUM(ai_usage_tokens_in), SUM(ai_usage_tokens_out), MAX(ai_usage_created_at)
        FROM ai_usage WHERE ai_usage_created_at >= NOW() - INTERVAL " . intval($days) . " DAY AND ai_usage_feature <> 'test' GROUP BY $column");
    while ($row = mysqli_fetch_row($sql)) {
        $totals[intval($row[0])] = ['requests' => intval($row[1]), 'failed' => intval($row[2]), 'tokens_in' => intval($row[3]), 'tokens_out' => intval($row[4]), 'last' => $row[5]];
    }
    return $totals;
}

/* A number of tokens as it reads - 950, 12.4k, 3.1M. */
function aiTokensText($tokens) {
    $tokens = intval($tokens);
    foreach ([1000000 => 'M', 1000 => 'k'] as $size => $suffix) {
        if ($tokens >= $size) {
            return rtrim(rtrim(number_format($tokens / $size, 1, '.', ''), '0'), '.') . $suffix;
        }
    }
    return (string) $tokens;
}

/* ------------------------------------------------------------- Testing a provider */

/* Where a provider lists its models - worked out from its chat completions address. '' when it doesn't list them. */
function aiModelsUrl($kind, $url) {
    $listing = aiProviderKind($kind)['models'];
    if ($listing === 'anthropic') {
        return 'https://api.anthropic.com/v1/models?limit=1000';
    }
    $path = strtok((string) $url, '?');
    if ($listing === '' || !preg_match('#/chat/completions/?$#', (string) $path)) {
        return '';
    }
    return preg_replace('#/chat/completions/?$#', '/models', $path);
}

/*
 * Ask a provider which models it has - which says whether its address and key
 * work. ['ok' => bool, 'models' => their names, 'error' => what went wrong in
 * words, 'listed' => false when it doesn't list them (Azure) - then it isn't asked].
 */
function aiProviderTest($kind, $url, $api_key) {
    $models_url = aiModelsUrl($kind, $url);
    if ($models_url === '') {
        return ['ok' => true, 'models' => [], 'error' => '', 'listed' => false];
    }
    $headers = aiProviderHeaders($kind, $api_key, false);
    if (aiProviderKind($kind)['models'] === 'anthropic') {
        $headers = ['x-api-key: ' . trim((string) $api_key), 'anthropic-version: 2023-06-01'];
    }
    $ch = curl_init($models_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    $status = intval(curl_getinfo($ch, CURLINFO_RESPONSE_CODE));
    $transport_error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['ok' => false, 'models' => [], 'error' => "It couldn't be reached - $transport_error.", 'listed' => true];
    }
    $decoded = json_decode($response, true);
    if ($status === 401 || $status === 403) {
        return ['ok' => false, 'models' => [], 'error' => "It turned the key away (HTTP $status) - check it is right and still active.", 'listed' => true];
    }
    if ($status < 200 || $status > 299 || !is_array($decoded)) {
        $message = is_array($decoded['error'] ?? null) ? (string) ($decoded['error']['message'] ?? '') : (is_string($decoded['error'] ?? null) ? $decoded['error'] : '');
        return ['ok' => false, 'models' => [], 'error' => "It answered HTTP $status" . ($message !== '' ? " - " . mb_substr($message, 0, 200) : '') . ' - check the address.', 'listed' => true];
    }
    $models = [];
    foreach ((array) ($decoded['data'] ?? $decoded['models'] ?? []) as $item) {
        $id = is_array($item) ? (string) ($item['id'] ?? $item['name'] ?? '') : (string) $item;
        if ($id !== '') {
            $models[] = preg_replace('#^models/#', '', $id);
        }
    }
    $models = array_values(array_unique($models));
    sort($models, SORT_NATURAL | SORT_FLAG_CASE);
    return ['ok' => true, 'models' => $models, 'error' => '', 'listed' => true];
}

/* ------------------------------------------------------------- Admin > AI Providers */

/* A provider - with how many models it has - or null. */
function aiProviderRow($provider_id) {
    global $mysqli;
    $provider_id = intval($provider_id);
    if ($provider_id <= 0) {
        return null;
    }
    return mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ai_providers.*, (SELECT COUNT(*) FROM ai_models WHERE ai_model_ai_provider_id = ai_provider_id) AS model_count
        FROM ai_providers WHERE ai_provider_id = $provider_id")) ?: null;
}

/* A model, with its provider's name and kind - or null. */
function aiModelRow($model_id) {
    global $mysqli;
    $model_id = intval($model_id);
    if ($model_id <= 0) {
        return null;
    }
    return mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ai_models.*, ai_provider_name, ai_provider_kind FROM ai_models
        LEFT JOIN ai_providers ON ai_provider_id = ai_model_ai_provider_id WHERE ai_model_id = $model_id")) ?: null;
}

/*
 * A provider's address as it is called - its chat completions address. One
 * pasted as the base (https://api.example.com/v1) gets /chat/completions put
 * on, as the OpenAI libraries would; an Azure resource on its own gets the
 * rest of the path too.
 */
function aiProviderUrl($kind, $url) {
    $url = trim((string) $url);
    if ($url === '' || !preg_match('#^https?://#i', $url)) {
        return $url;
    }
    $path = (string) parse_url($url, PHP_URL_PATH);
    if ($kind === 'azure' && trim($path, '/') === '') {
        return rtrim($url, '/') . '/openai/v1/chat/completions';
    }
    if (preg_match('#/v\d+(?:beta\d*)?(?:/openai)?/?$#i', $path)) {
        return rtrim($url, '/') . '/chat/completions';
    }
    return $url;
}

/*
 * A provider as sent from its window, checked - [the row to save (raw values),
 * field => what is wrong]. The key isn't put back into the page, so left empty
 * on an existing provider it stays; 'clear_key' takes it away.
 */
function aiProviderCheck(array $post, $before = null) {
    $kinds = aiProviderKinds();
    $errors = [];
    $kind = (string) ($post['kind'] ?? ($before['ai_provider_kind'] ?? 'custom'));
    if (!isset($kinds[$kind])) {
        $errors['kind'] = 'Pick what kind of provider it is.';
        $kind = 'custom';
    }
    $info = $kinds[$kind];

    $name = trim(preg_replace('/\s+/', ' ', (string) ($post['name'] ?? '')));
    if ($name === '') {
        $errors['name'] = 'Give it a name - ' . $info['name'] . ', or what you call it.';
    } elseif (mb_strlen($name) > 200) {
        $errors['name'] = 'That name is longer than 200 characters.';
    } else {
        global $mysqli;
        $taken = mysqli_fetch_row(mysqli_query($mysqli, "SELECT ai_provider_id FROM ai_providers WHERE ai_provider_name = '" . mysqli_real_escape_string($mysqli, $name) . "'
            AND ai_provider_id <> " . intval($before['ai_provider_id'] ?? 0) . " LIMIT 1"));
        if ($taken) {
            $errors['name'] = 'There is a provider called that already - give this one a name of its own.';
        }
    }

    $url = aiProviderUrl($kind, $post['url'] ?? '');
    if ($url === '') {
        $errors['url'] = 'Its address is needed - where ITFlow sends requests.';
    } elseif (mb_strlen($url) > 500) {
        $errors['url'] = 'That address is longer than 500 characters.';
    } elseif (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url)) {
        $errors['url'] = "That isn't a web address - it starts https:// (or http:// for one on your own network).";
    } elseif (stripos($url, 'YOUR-RESOURCE') !== false) {
        $errors['url'] = 'Put your Azure resource name in place of YOUR-RESOURCE - it is in the Azure portal, under Keys and Endpoint.';
    }

    $key = (string) ($post['api_key'] ?? '');
    $key = trim($key);
    $saved_key = (string) ($before['ai_provider_api_key'] ?? '');
    if (!empty($post['clear_key'])) {
        $key_after = '';
    } else {
        $key_after = $key !== '' ? $key : $saved_key;
    }
    if (mb_strlen($key_after) > 500) {
        $errors['api_key'] = 'That key is longer than 500 characters.';
    } elseif ($key !== '' && preg_match('/\s/', $key)) {
        $errors['api_key'] = "A key has no spaces in it - check it was copied whole.";
    } elseif ($info['key'] && $key_after === '') {
        $errors['api_key'] = $info['name'] . ' needs an API key' . ($info['keys_url'] !== '' ? ' - make one in its console.' : '.');
    }

    $after = [
        'ai_provider_name' => $name,
        'ai_provider_kind' => $kind,
        'ai_provider_api_url' => $url,
        'ai_provider_api_key' => $key_after,
        'ai_provider_redact' => !empty($post['redact']) ? 1 : 0,
    ];
    return [$after, $errors];
}

/* Whether what a provider is called through - kind, address, key - is new or changed, so worth asking it. */
function aiProviderConnectionChanged(array $after, $before) {
    if (!$before) {
        return true;
    }
    return $after['ai_provider_kind'] !== (string) $before['ai_provider_kind'] || $after['ai_provider_api_url'] !== (string) $before['ai_provider_api_url']
        || $after['ai_provider_api_key'] !== (string) $before['ai_provider_api_key'];
}

/* Save a provider - added when there is no $provider_id. Returns its id. */
function aiProviderWrite(array $after, $provider_id = 0) {
    global $mysqli;
    $set = [];
    foreach ($after as $column => $value) {
        $set[] = is_int($value) ? "$column = $value" : "$column = " . ($column === 'ai_provider_api_key' && $value === '' ? 'NULL' : "'" . mysqli_real_escape_string($mysqli, $value) . "'");
    }
    $provider_id = intval($provider_id);
    if ($provider_id) {
        mysqli_query($mysqli, "UPDATE ai_providers SET " . implode(', ', $set) . " WHERE ai_provider_id = $provider_id");
        return $provider_id;
    }
    mysqli_query($mysqli, "INSERT INTO ai_providers SET " . implode(', ', $set));
    return intval(mysqli_insert_id($mysqli));
}

/* What changed on a provider, a line each, from what to what - the key only as changed. Plain text. */
function aiProviderChangeLines(array $before, array $after) {
    $lines = [];
    if ((string) $before['ai_provider_name'] !== $after['ai_provider_name']) {
        $lines[] = "Name {$before['ai_provider_name']} → {$after['ai_provider_name']}";
    }
    if ((string) $before['ai_provider_kind'] !== $after['ai_provider_kind']) {
        $lines[] = 'Kind ' . aiProviderKind($before['ai_provider_kind'])['name'] . ' → ' . aiProviderKind($after['ai_provider_kind'])['name'];
    }
    if ((string) $before['ai_provider_api_url'] !== $after['ai_provider_api_url']) {
        $lines[] = "Address {$before['ai_provider_api_url']} → {$after['ai_provider_api_url']}";
    }
    $was_key = (string) $before['ai_provider_api_key'];
    if ($was_key !== $after['ai_provider_api_key']) {
        $lines[] = $was_key === '' ? 'API key added' : ($after['ai_provider_api_key'] === '' ? 'API key removed' : 'API key changed');
    }
    if (intval($before['ai_provider_redact']) !== $after['ai_provider_redact']) {
        $lines[] = $after['ai_provider_redact'] ? 'Passwords and keys taken out of what is sent' : 'Passwords and keys sent as they are';
    }
    return $lines;
}

/* A model as sent from its window, checked - [the row to save (raw values), field => what is wrong]. */
function aiModelCheck(array $post) {
    $errors = [];
    $provider = aiProviderRow($post['provider'] ?? 0);
    if (!$provider) {
        $errors['provider'] = 'Pick the provider it is on.';
    }
    $name = trim((string) ($post['model'] ?? ''));
    if ($name === '') {
        $errors['model'] = "Its name is needed, as the provider knows it" . ($provider ? ' - like ' . aiProviderKind($provider['ai_provider_kind'])['example'] : '') . '.';
    } elseif (mb_strlen($name) > 200) {
        $errors['model'] = 'That name is longer than 200 characters.';
    } elseif (preg_match('/\s/', $name) && !in_array($provider['ai_provider_kind'] ?? '', ['azure', 'custom'], true)) {
        $errors['model'] = "A model's name has no spaces - " . aiProviderKind($provider['ai_provider_kind'] ?? 'custom')['example'] . ', say.';
    }
    $use_case = (string) ($post['use_case'] ?? '');
    if (!isset(aiUseCases()[$use_case])) {
        $errors['use_case'] = 'Pick what it is for.';
    }
    $temperature = trim((string) ($post['temperature'] ?? ''));
    if ($temperature !== '' && (!is_numeric($temperature) || floatval($temperature) < 0 || floatval($temperature) > 2)) {
        $errors['temperature'] = 'A temperature is from 0 to 2 - or leave it empty for the model\'s own.';
    }
    $prompt = trim(str_replace("\r\n", "\n", (string) ($post['prompt'] ?? '')));
    if (mb_strlen($prompt) > 4000) {
        $errors['prompt'] = 'Keep the instructions under 4,000 characters - they go with every request.';
    }
    $after = [
        'ai_model_ai_provider_id' => intval($provider['ai_provider_id'] ?? 0),
        'ai_model_name' => $name,
        'ai_model_use_case' => $use_case,
        'ai_model_temperature' => $temperature === '' ? null : round(floatval($temperature), 2),
        'ai_model_prompt' => $prompt,
    ];
    return [$after, $errors, $provider];
}

/* Save a model - added when there is no $model_id. Returns its id. */
function aiModelWrite(array $after, $model_id = 0) {
    global $mysqli;
    $set = "ai_model_ai_provider_id = " . intval($after['ai_model_ai_provider_id']) . ",
        ai_model_name = '" . mysqli_real_escape_string($mysqli, $after['ai_model_name']) . "',
        ai_model_use_case = '" . mysqli_real_escape_string($mysqli, $after['ai_model_use_case']) . "',
        ai_model_temperature = " . ($after['ai_model_temperature'] === null ? 'NULL' : floatval($after['ai_model_temperature'])) . ",
        ai_model_prompt = " . ($after['ai_model_prompt'] === '' ? 'NULL' : "'" . mysqli_real_escape_string($mysqli, $after['ai_model_prompt']) . "'");
    $model_id = intval($model_id);
    if ($model_id) {
        mysqli_query($mysqli, "UPDATE ai_models SET $set WHERE ai_model_id = $model_id");
        return $model_id;
    }
    mysqli_query($mysqli, "INSERT INTO ai_models SET $set");
    return intval(mysqli_insert_id($mysqli));
}

/* A temperature as it reads - "the model's own" when there is none. */
function aiTemperatureText($temperature) {
    return $temperature === null || $temperature === '' ? "the model's own" : rtrim(rtrim(number_format(floatval($temperature), 2, '.', ''), '0'), '.');
}

/* What changed on a model, a line each, from what to what - the instructions only as changed. Plain text. */
function aiModelChangeLines(array $before, array $after) {
    $lines = [];
    if (intval($before['ai_model_ai_provider_id']) !== $after['ai_model_ai_provider_id']) {
        $lines[] = 'Provider ' . ($before['ai_provider_name'] ?? '#' . $before['ai_model_ai_provider_id']) . ' → ' . (aiProviderRow($after['ai_model_ai_provider_id'])['ai_provider_name'] ?? '');
    }
    if ((string) $before['ai_model_name'] !== $after['ai_model_name']) {
        $lines[] = "Model {$before['ai_model_name']} → {$after['ai_model_name']}";
    }
    if ((string) $before['ai_model_use_case'] !== $after['ai_model_use_case']) {
        $lines[] = "For {$before['ai_model_use_case']} → {$after['ai_model_use_case']}";
    }
    $was = $before['ai_model_temperature'] === null ? null : round(floatval($before['ai_model_temperature']), 2);
    if ($was !== $after['ai_model_temperature']) {
        $lines[] = 'Temperature ' . aiTemperatureText($was) . ' → ' . aiTemperatureText($after['ai_model_temperature']);
    }
    if (trim((string) $before['ai_model_prompt']) !== $after['ai_model_prompt']) {
        $lines[] = $after['ai_model_prompt'] === '' ? 'Instructions removed' : (trim((string) $before['ai_model_prompt']) === '' ? 'Instructions added' : 'Instructions changed');
    }
    return $lines;
}

/*
 * Which model each use case lands on - use case => ['model' => the model row
 * it uses (or null), 'own' => whether that is its own or General's]. And
 * 'unused' - the models nothing lands on, model id => the row used instead: a
 * second model for a use case is never used, the first one added is. $without
 * leaves out a provider's models, or a model ('provider' / 'model' => id) - what
 * it would be once they are deleted.
 */
function aiUseCaseMap(array $without = []) {
    global $mysqli;
    $first = [];
    $unused = [];
    $sql = mysqli_query($mysqli, "SELECT ai_model_id, ai_model_name, ai_model_use_case, ai_model_ai_provider_id, ai_provider_name FROM ai_models
        JOIN ai_providers ON ai_provider_id = ai_model_ai_provider_id
        WHERE ai_model_ai_provider_id <> " . intval($without['provider'] ?? 0) . " AND ai_model_id <> " . intval($without['model'] ?? 0) . "
        ORDER BY ai_model_id ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $use_case = (string) $row['ai_model_use_case'];
        if (!isset(aiUseCases()[$use_case])) {
            $unused[intval($row['ai_model_id'])] = null;
        } elseif (isset($first[$use_case])) {
            $unused[intval($row['ai_model_id'])] = $first[$use_case];
        } else {
            $first[$use_case] = $row;
        }
    }
    $map = [];
    foreach (aiUseCases() as $use_case => $about) {
        $own = $first[$use_case] ?? null;
        $map[$use_case] = ['model' => $own ?? ($first['General'] ?? null), 'own' => $own !== null];
    }
    return ['map' => $map, 'unused' => $unused];
}

/* What would stop if a provider or model went ($without as aiUseCaseMap()) - the use cases that have a model now and wouldn't. */
function aiUseCasesStopping(array $without) {
    $now = aiUseCaseMap()['map'];
    $then = aiUseCaseMap($without)['map'];
    $stopping = [];
    foreach ($now as $use_case => $lands) {
        if ($lands['model'] && !$then[$use_case]['model']) {
            $stopping[] = $use_case;
        }
    }
    return $stopping;
}

/* A provider kind's icon - Font Awesome classes. */
function aiProviderIcon($kind) {
    $icons = ['azure' => 'fab fa-microsoft', 'gemini' => 'fab fa-google', 'ollama' => 'fas fa-server', 'lmstudio' => 'fas fa-server'];
    return $icons[(string) $kind] ?? 'fas fa-robot';
}

/*
 * Which model each use case lands on, a pill each - for the top of AI
 * Providers and AI Models, so it is plain what Reword, the ticket summary and
 * document drafts will use.
 */
function aiUseCaseStripHtml() {
    $html = '<div class="d-flex flex-wrap gap-2" data-ai-use-cases>';
    foreach (aiUseCaseMap()['map'] as $use_case => $lands) {
        $model = $lands['model'];
        if (!$model) {
            $says = '<span class="text-warning-emphasis">nothing set up</span>';
            $tone = 'border-warning-subtle bg-warning-subtle';
        } elseif ($lands['own']) {
            $says = '<span class="fw-semibold">' . escapeHtml($model['ai_model_name']) . '</span> <span class="text-body-secondary">on ' . escapeHtml($model['ai_provider_name']) . '</span>';
            $tone = 'bg-body';
        } else {
            $says = '<span class="text-body-secondary">General\'s - ' . escapeHtml($model['ai_model_name']) . '</span>';
            $tone = 'bg-body';
        }
        $html .= '<div class="border rounded-pill px-3 py-1 small text-nowrap ' . $tone . '" data-ai-use-case="' . escapeHtml($use_case) . '" title="' . escapeHtml(aiUseCases()[$use_case]) . '">'
            . '<i class="fas fa-fw ' . ['General' => 'fa-magic', 'Tickets' => 'fa-life-ring', 'Documentation' => 'fa-file-alt'][$use_case] . ' text-secondary me-1"></i>'
            . escapeHtml($use_case) . ' <i class="fas fa-long-arrow-alt-right text-secondary mx-1"></i>' . $says . '</div>';
    }
    return $html . '</div>';
}

/*
 * What Admin > Settings says needs attention about AI - requests failing in
 * the last day while the last one failed too (so it is still failing). Items
 * as the overview takes them: [level, icon, title, text, url, button], plain text.
 */
function aiProblems() {
    global $mysqli;
    if (!aiUsageTableExists()) {
        return [];
    }
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM ai_usage WHERE ai_usage_feature <> 'test' AND ai_usage_ok = 0 AND ai_usage_created_at >= NOW() - INTERVAL 1 DAY"));
    $failed = intval($row[0] ?? 0);
    if (!$failed) {
        return [];
    }
    $last = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ai_usage_ok, ai_usage_provider_name, ai_usage_error FROM ai_usage WHERE ai_usage_feature <> 'test' ORDER BY ai_usage_id DESC LIMIT 1"));
    if (!$last || intval($last['ai_usage_ok']) === 1) {
        return [];
    }
    return [['warning', 'fa-robot', $failed . ' AI request' . ($failed === 1 ? '' : 's') . ' failed today',
        'The last, to ' . $last['ai_usage_provider_name'] . ', too' . ($last['ai_usage_error'] ? ' - ' . $last['ai_usage_error'] : '') . '. Reword and summaries fail for agents until it is put right.',
        'ai_usage.php?view=failed', 'See why']];
}
