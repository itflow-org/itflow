<?php

/*
 * ITFlow - AI on documents
 *
 * A new document drafted from a line on what it is for (aiDocumentDraft()),
 * with what ITFlow knows of the client to draw on (aiClientProfile()); and a
 * document reviewed (aiDocumentReview()) - what to fix or check, a one-line
 * summary for it, and the passwords and keys written in it that belong in
 * Credentials. The endpoints are in agent/ajax.php; the windows are New
 * Document (modals/document/document_add.php) and Review with AI
 * (modals/document/document_review.php).
 */

/*
 * What AI writes as a document, split up - [its title (the <h1>), its
 * summary (the <p><em> after it), what it says]. The prompts ask for both at
 * the top; either may be missing.
 */
function aiDocumentSplit($html) {
    $html = trim((string) $html);
    $title = '';
    if (preg_match('#<h1[^>]*>(.*?)</h1>#is', $html, $m)) {
        $title = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8'));
        $html = trim(preg_replace('#<h1[^>]*>.*?</h1>#is', '', $html, 1));
    }
    $description = '';
    if (preg_match('#^<p[^>]*>\s*<em>(.*?)</em>\s*</p>#is', $html, $m)) {
        $description = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8'));
        $html = trim(substr($html, strlen($m[0])));
    }
    return [mb_substr($title, 0, 200), mb_substr($description, 0, 250), $html];
}

/*
 * What ITFlow knows of a client that documentation draws on, in words - who
 * they are, their sites, the people to contact, their servers and network
 * gear, networks, software and domains. Never a password, a license key or a
 * login. For the agent who can see the client's documentation.
 */
function aiClientProfile($client_id) {
    global $mysqli;
    $client_id = intval($client_id);
    $client = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT client_name, client_type, client_website FROM clients WHERE client_id = $client_id"));
    if (!$client) {
        return '';
    }
    $lines = ['Client: ' . $client['client_name'] . ($client['client_type'] ? " ({$client['client_type']})" : '') . ($client['client_website'] ? " - {$client['client_website']}" : '')];

    $list = function ($label, $sql, $format) use ($mysqli, &$lines) {
        $items = [];
        $result = mysqli_query($mysqli, $sql);
        while ($row = mysqli_fetch_assoc($result)) {
            $items[] = '- ' . $format($row);
        }
        if ($items) {
            $lines[] = "$label:\n" . implode("\n", $items);
        }
    };
    $list('Sites', "SELECT location_name, location_city, location_state FROM locations WHERE location_client_id = $client_id AND location_archived_at IS NULL ORDER BY location_primary DESC, location_name LIMIT 10",
        function ($r) { return $r['location_name'] . (trim($r['location_city'] . ' ' . $r['location_state']) !== '' ? ' - ' . trim($r['location_city'] . ', ' . $r['location_state'], ', ') : ''); });
    $list('People', "SELECT contact_name, contact_title, contact_primary, contact_technical, contact_billing FROM contacts
        WHERE contact_client_id = $client_id AND contact_archived_at IS NULL AND (contact_primary = 1 OR contact_technical = 1 OR contact_billing = 1 OR contact_important = 1)
        ORDER BY contact_primary DESC, contact_name LIMIT 8",
        function ($r) {
            $roles = array_keys(array_filter(['primary contact' => $r['contact_primary'], 'technical contact' => $r['contact_technical'], 'billing contact' => $r['contact_billing']]));
            return $r['contact_name'] . ($r['contact_title'] ? ", {$r['contact_title']}" : '') . ($roles ? ' (' . implode(', ', $roles) . ')' : '');
        });
    $counts = [];
    $result = mysqli_query($mysqli, "SELECT asset_type, COUNT(*) FROM assets WHERE asset_client_id = $client_id AND asset_archived_at IS NULL GROUP BY asset_type ORDER BY COUNT(*) DESC");
    while ($row = mysqli_fetch_row($result)) {
        $counts[] = "$row[1] " . ($row[0] ?: 'other');
    }
    if ($counts) {
        $lines[] = 'Assets: ' . implode(', ', $counts);
    }
    $list('Servers and network gear', "SELECT asset_name, asset_type, asset_make, asset_model, asset_os FROM assets
        WHERE asset_client_id = $client_id AND asset_archived_at IS NULL AND asset_type IN ('Server', 'Virtual Machine', 'Firewall/Router', 'Switch', 'Access Point', 'Storage', 'NAS')
        ORDER BY FIELD(asset_type, 'Firewall/Router', 'Server', 'Virtual Machine', 'Switch', 'Access Point'), asset_name LIMIT 25",
        function ($r) { return $r['asset_name'] . ' - ' . $r['asset_type'] . (trim($r['asset_make'] . ' ' . $r['asset_model']) !== '' ? ', ' . trim($r['asset_make'] . ' ' . $r['asset_model']) : '') . ($r['asset_os'] ? ", {$r['asset_os']}" : ''); });
    $list('Networks', "SELECT network_name, network_type, network_vlan, network, network_gateway FROM networks WHERE network_client_id = $client_id AND network_archived_at IS NULL ORDER BY network_name LIMIT 15",
        function ($r) { return $r['network_name'] . ($r['network_type'] ? " ({$r['network_type']})" : '') . ($r['network'] ? " - {$r['network']}" : '') . ($r['network_vlan'] ? ", VLAN {$r['network_vlan']}" : '') . ($r['network_gateway'] ? ", gateway {$r['network_gateway']}" : ''); });
    $list('Software and licenses', "SELECT software_name, software_version, software_type FROM software WHERE software_client_id = $client_id AND software_archived_at IS NULL ORDER BY software_name LIMIT 25",
        function ($r) { return $r['software_name'] . ($r['software_version'] ? " {$r['software_version']}" : '') . ($r['software_type'] ? " ({$r['software_type']})" : ''); });
    $list('Domains', "SELECT domain_name FROM domains WHERE domain_client_id = $client_id AND domain_archived_at IS NULL ORDER BY domain_name LIMIT 10",
        function ($r) { return $r['domain_name']; });
    return implode("\n", $lines);
}

/*
 * A new document drafted - ['ok', 'title', 'description', 'html'] or ['ok' =>
 * false, 'error']. $prompt says what it is for; with $use_client, the
 * client's profile goes with it, and its details are used where they belong.
 * Anything not known is left as a short placeholder in square brackets.
 */
function aiDocumentDraft($client_id, $prompt, $use_client = true) {
    $model = getAiModel('Documentation');
    if (!$model) {
        return ['ok' => false, 'error' => aiModelMissingError('Documentation')];
    }
    $prompt = trim((string) $prompt);
    if ($prompt === '') {
        return ['ok' => false, 'error' => 'Say what the document is for first.'];
    }
    $profile = $use_client ? aiClientProfile($client_id) : '';
    $result = callAiApi($model, [
        ['role' => 'system', 'content' => 'You write IT documentation for a managed service provider - for its engineers, about one of its clients. Write the document asked for: clear, practical and specific, with headings, numbered steps for anything to do, and tables where they help.'
            . ($profile !== '' ? ' Use the client\'s details given where they belong - their sites, people, servers, networks and software - and never invent ones that aren\'t given.' : '')
            . ' Where something only the engineer knows is needed, leave a short placeholder in square brackets, like [Backup retention] or [ISP account number]. Never write a password, key or login.'
            . ' Answer in HTML only, in this shape: <h1>a short title</h1><p><em>one sentence on what it covers</em></p> then the document. No introduction, no code fences, no <html> or <body>.'],
        ['role' => 'user', 'content' => "Write: $prompt" . ($profile !== '' ? "\n\nWhat we know about the client:\n$profile" : '')],
    ], ['feature' => 'document_draft', 'client_id' => intval($client_id), 'secrets' => 'remove']);
    if (!$result['ok']) {
        return $result;
    }
    [$title, $description, $html] = aiDocumentSplit(aiCleanHtml($result['content']));
    if (trim(strip_tags($html)) === '') {
        return ['ok' => false, 'error' => 'The AI provider answered with nothing to keep - try again.'];
    }
    return ['ok' => true, 'title' => $title, 'description' => $description, 'html' => $html];
}

/*
 * A document reviewed - ['ok', 'verdict' => good / needs work / out of date,
 * 'summary' => a one-line summary for it, 'findings' => [severity, text] -
 * high first, 'secrets' => how many passwords and keys are written in it] or
 * ['ok' => false, 'error']. The passwords and keys are found here, not by the
 * model, and never sent - whatever the provider is set to.
 */
function aiDocumentReview($document_id) {
    global $mysqli;
    $model = getAiModel('Documentation');
    if (!$model) {
        return ['ok' => false, 'error' => aiModelMissingError('Documentation')];
    }
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT document_name, document_description, document_content, document_client_id, document_updated_at, client_name
        FROM documents LEFT JOIN clients ON client_id = document_client_id WHERE document_id = " . intval($document_id)));
    if (!$row) {
        return ['ok' => false, 'error' => 'That document no longer exists.'];
    }
    $text = aiHtmlToText($row['document_content'], 30000);
    if ($text === '') {
        return ['ok' => false, 'error' => 'There is nothing in it to review yet.'];
    }
    [, $secrets] = aiRedact($text);

    $model['ai_provider_redact'] = 1;
    $result = callAiApi($model, [
        ['role' => 'system', 'content' => 'You review a managed service provider\'s IT documentation about one of its clients, as a careful senior engineer would. Today is ' . date('F j, Y') . '.'
            . ' List what should be fixed or checked: steps that are unclear, missing or in the wrong order; anything that looks out of date (software or systems at or past end of life, old dates, old names); things that contradict each other; what a document like this usually has that this one lacks (how to restore, who to call, where things are kept); and anything risky to follow as written.'
            . ' Don\'t mention the placeholders like [secret-1] - they stand for passwords taken out before you saw it.'
            . ' Answer with JSON only, in this shape: {"verdict": "good" or "needs work" or "out of date", "summary": "one sentence on what the document covers, for its summary", "findings": [{"severity": "high" or "medium" or "low", "text": "what to fix or check, in one or two sentences"}]}. At most 10 findings, the most important first; none when it is good.'],
        ['role' => 'user', 'content' => 'Document: ' . $row['document_name'] . ($row['client_name'] ? "\nClient: {$row['client_name']}" : '') . "\nLast changed: " . date('F j, Y', strtotime($row['document_updated_at'] ?: 'now')) . "\n\n$text"],
    ], ['feature' => 'document_review', 'client_id' => intval($row['document_client_id'])]);
    if (!$result['ok']) {
        return $result;
    }
    $answer = aiJsonFromText($result['content']);
    if (!$answer) {
        return ['ok' => false, 'error' => "The AI provider's answer couldn't be read - try again."];
    }
    $order = ['high' => 0, 'medium' => 1, 'low' => 2];
    $findings = [];
    foreach (array_slice((array) ($answer['findings'] ?? []), 0, 10) as $finding) {
        $text = is_array($finding) ? trim(strip_tags((string) ($finding['text'] ?? ''))) : trim(strip_tags((string) $finding));
        if ($text === '') {
            continue;
        }
        $severity = strtolower((string) (is_array($finding) ? ($finding['severity'] ?? 'medium') : 'medium'));
        $findings[] = [isset($order[$severity]) ? $severity : 'medium', mb_substr($text, 0, 500)];
    }
    usort($findings, function ($a, $b) use ($order) {
        return $order[$a[0]] <=> $order[$b[0]];
    });
    $verdict = strtolower(trim((string) ($answer['verdict'] ?? '')));
    return [
        'ok' => true,
        'verdict' => in_array($verdict, ['good', 'needs work', 'out of date'], true) ? $verdict : ($findings ? 'needs work' : 'good'),
        'summary' => mb_substr(trim(strip_tags((string) ($answer['summary'] ?? ''))), 0, 250),
        'findings' => $findings,
        'secrets' => count($secrets),
    ];
}
