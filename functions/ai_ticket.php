<?php

/*
 * ITFlow - AI on tickets
 *
 * What the ticket features ask a model (functions/ai.php), and what they do
 * with the answer: the summary for the agents working a ticket, a draft of the
 * next reply, triage of a new ticket (a category, a priority and a line on what
 * the client needs - suggested on the ticket, or filled in as it comes in), and
 * a ticket written up as a document for the client's documentation.
 *
 * Each reads the ticket through aiTicketContext() - its details, the people,
 * the conversation, tasks and time - with the internal notes left out of
 * anything the client will read. The callers have checked the agent may see
 * the ticket; the endpoints are in agent/ajax.php, the handlers in
 * agent/post/ticket.php, the automatic triage in cron/ai_triage.php.
 */

/* Text, from an editor's or an email's HTML - its paragraphs, line breaks and list items kept as lines. At most $limit characters. */
function aiHtmlToText($html, $limit = 0) {
    $html = (string) $html;
    $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $html);
    $html = preg_replace('#<br\s*/?>#i', "\n", $html);
    $html = preg_replace('#<li\b[^>]*>#i', "\n- ", $html);
    $html = preg_replace('#</(p|div|h[1-6]|li|tr|table|blockquote|pre)>#i', "\n", $html);
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = str_replace("\xC2\xA0", ' ', $text);
    $text = preg_replace("/[ \t]+/", ' ', $text);
    $text = preg_replace("/ *\n */", "\n", $text);
    $text = trim(preg_replace("/\n{3,}/", "\n\n", $text));
    if ($limit && mb_strlen($text) > $limit) {
        $text = rtrim(mb_substr($text, 0, $limit)) . ' [...]';
    }
    return $text;
}

/*
 * A ticket as a model reads it - ['text' => it in words, 'row' => the ticket,
 * 'first_name' => its contact's first name, 'client_id']. Its reference,
 * subject, client, contact, status, priority, category, source, agent, assets,
 * tasks and time worked, what was asked, then the conversation in order, each
 * reply saying who it is from and whether the client saw it. Internal notes
 * only with $internal. Long conversations keep the first message and the
 * latest replies, to about 40,000 characters.
 */
function aiTicketContext($ticket_id, $internal = true) {
    global $mysqli;
    $ticket_id = intval($ticket_id);
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT tickets.*, client_name, contact_name, contact_title, ticket_status_name, category_name, user_name,
            asset_name, asset_type
        FROM tickets
        LEFT JOIN clients ON client_id = ticket_client_id
        LEFT JOIN contacts ON contact_id = ticket_contact_id
        LEFT JOIN ticket_statuses ON ticket_status_id = ticket_status
        LEFT JOIN categories ON category_id = ticket_category
        LEFT JOIN users ON user_id = ticket_assigned_to
        LEFT JOIN assets ON asset_id = ticket_asset_id
        WHERE ticket_id = $ticket_id"));
    if (!$row) {
        return null;
    }

    $lines = [
        'Ticket: ' . ticketReference($row) . ' - ' . $row['ticket_subject'],
        'Client: ' . ($row['client_name'] ?: 'none - internal work'),
        'Contact: ' . ($row['contact_name'] ? $row['contact_name'] . ($row['contact_title'] ? ', ' . $row['contact_title'] : '') : 'none'),
        'Status: ' . $row['ticket_status_name'] . ' - Priority: ' . $row['ticket_priority'] . ' - Category: ' . ($row['category_name'] ?: 'none'),
        'Raised: ' . date('M j, Y g:i A', strtotime($row['ticket_created_at'])) . ' from ' . ($row['ticket_source'] ?: 'an agent'),
        'Assigned to: ' . ($row['user_name'] ?: 'nobody yet'),
    ];

    $assets = [];
    if ($row['asset_name']) {
        $assets[] = $row['asset_name'] . ($row['asset_type'] ? " ({$row['asset_type']})" : '');
    }
    $sql = mysqli_query($mysqli, "SELECT asset_name, asset_type FROM ticket_assets JOIN assets USING (asset_id) WHERE ticket_assets.ticket_id = $ticket_id ORDER BY asset_name");
    while ($asset = mysqli_fetch_assoc($sql)) {
        $assets[] = $asset['asset_name'] . ($asset['asset_type'] ? " ({$asset['asset_type']})" : '');
    }
    if ($assets) {
        $lines[] = 'Assets: ' . implode(', ', array_unique($assets));
    }

    $tasks = [];
    $sql = mysqli_query($mysqli, "SELECT task_name, task_completed_at FROM tasks WHERE task_ticket_id = $ticket_id ORDER BY task_order, task_id");
    while ($task = mysqli_fetch_assoc($sql)) {
        $tasks[] = '- [' . ($task['task_completed_at'] ? 'done' : 'open') . '] ' . $task['task_name'];
    }
    if ($tasks) {
        $lines[] = "Tasks:\n" . implode("\n", $tasks);
    }

    $seconds = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COALESCE(SUM(TIME_TO_SEC(ticket_reply_time_worked)), 0) FROM ticket_replies WHERE ticket_reply_ticket_id = $ticket_id AND ticket_reply_archived_at IS NULL"))[0]);
    if ($seconds) {
        $lines[] = 'Time worked so far: ' . floor($seconds / 3600) . 'h ' . floor(($seconds % 3600) / 60) . 'm';
    }

    $lines[] = "\nWhat was asked:\n" . aiHtmlToText($row['ticket_details'], 8000);

    // The conversation - who each reply is from, and whether the client saw it
    $replies = [];
    $sql = mysqli_query($mysqli, "SELECT ticket_reply, ticket_reply_type, ticket_reply_created_at, user_name, contact_name
        FROM ticket_replies
        LEFT JOIN users ON ticket_reply_type <> 'Client' AND user_id = ticket_reply_by
        LEFT JOIN contacts ON ticket_reply_type = 'Client' AND contact_id = ticket_reply_by
        WHERE ticket_reply_ticket_id = $ticket_id AND ticket_reply_archived_at IS NULL" . ($internal ? '' : " AND ticket_reply_type <> 'Internal'") . "
        ORDER BY ticket_reply_id ASC");
    while ($reply = mysqli_fetch_assoc($sql)) {
        $type = $reply['ticket_reply_type'];
        $who = $type === 'Client' ? 'Client - ' . ($reply['contact_name'] ?: 'the client') : ($type === 'Internal' ? 'Internal note - ' : 'Agent reply - ') . ($reply['user_name'] ?: 'ITFlow');
        $replies[] = '[' . date('M j, Y g:i A', strtotime($reply['ticket_reply_created_at'])) . "] $who:\n" . aiHtmlToText($reply['ticket_reply'], 4000);
    }
    // Room for the latest of a long one
    $kept = [];
    $room = 30000;
    foreach (array_reverse($replies) as $reply) {
        if (mb_strlen($reply) > $room) {
            break;
        }
        $room -= mb_strlen($reply);
        array_unshift($kept, $reply);
    }
    if ($kept) {
        $lines[] = "\nThe conversation" . (count($kept) < count($replies) ? ' (the latest ' . count($kept) . ' of ' . count($replies) . ' replies)' : '') . ":\n" . implode("\n\n", $kept);
    } else {
        $lines[] = "\nNo replies yet.";
    }

    $first_name = trim(strtok((string) $row['contact_name'], ' ') ?: '');
    return ['text' => implode("\n", $lines), 'row' => $row, 'first_name' => $first_name, 'client_id' => intval($row['ticket_client_id'])];
}

/*
 * A ticket summed up for the agents working it - ['ok', 'html'] or ['ok' =>
 * false, 'error']. In short, then the problem, what has been done, where it
 * stands and what is next. Internal notes included - it is for the team.
 */
function aiTicketSummary($ticket_id) {
    $model = getAiModel('Tickets');
    if (!$model) {
        return ['ok' => false, 'error' => aiModelMissingError('Tickets')];
    }
    $ticket = aiTicketContext($ticket_id, true);
    if (!$ticket) {
        return ['ok' => false, 'error' => 'That ticket no longer exists.'];
    }
    $result = callAiApi($model, [
        ['role' => 'system', 'content' => 'You summarize IT support tickets for the agents of a managed service provider, so a colleague can pick a ticket up cold. Use only what the ticket says - never guess at causes, steps or dates it doesn\'t give.'
            . ' Answer in HTML only, in exactly this shape: <p><strong>In short:</strong> one sentence on what the client needs and where it stands.</p>'
            . ' <h6>The problem</h6><ul>...</ul> <h6>What\'s been done</h6><ul>...</ul> (who did what, with dates where they help) <h6>Where it stands</h6><ul>...</ul> (the status, who it is waiting on, open tasks)'
            . ' <h6>Next steps</h6><ul>...</ul>. Say when something is urgent or has been escalated, when files or screenshots were sent, and what is unclear and needs asking.'
            . ' Short bullets. No introduction, no code fences, no <html> or <body>.'],
        ['role' => 'user', 'content' => $ticket['text']],
    ], ['feature' => 'ticket_summary', 'client_id' => $ticket['client_id']]);
    if (!$result['ok']) {
        return $result;
    }
    $html = aiCleanHtml($result['content']);
    return $html === '' ? ['ok' => false, 'error' => 'The AI provider answered with nothing - try again.'] : ['ok' => true, 'html' => $html];
}

/*
 * The next reply on a ticket, drafted - ['ok', 'html'] or ['ok' => false,
 * 'error']. $notes is what the agent has typed in the editor so far: the reply
 * says what it says, in full. $type is who reads it: 'internal', 'public' or
 * 'email' - a reply the client reads is drafted without the internal notes, so
 * nothing in them can find its way to the client, and an email has no sign-off
 * (the agent's signature is added when it is sent).
 */
function aiTicketDraft($ticket_id, $notes = '', $type = 'email') {
    $model = getAiModel('Tickets');
    if (!$model) {
        return ['ok' => false, 'error' => aiModelMissingError('Tickets')];
    }
    $type = in_array($type, ['internal', 'public', 'email'], true) ? $type : 'email';
    $ticket = aiTicketContext($ticket_id, $type === 'internal');
    if (!$ticket) {
        return ['ok' => false, 'error' => 'That ticket no longer exists.'];
    }
    $notes = aiHtmlToText($notes, 6000);

    if ($type === 'internal') {
        $instructions = 'You write internal notes on IT support tickets for the agents of a managed service provider - for colleagues, not the client: concise and technical, no greeting and no sign-off.';
    } else {
        $instructions = 'You draft the replies the agents of a managed service provider send to their clients on support tickets.'
            . ' Plain, friendly and professional, in short paragraphs, with no jargon the client wouldn\'t know.'
            . ($ticket['first_name'] !== '' ? " Greet {$ticket['first_name']} by first name." : ' Greet the client briefly.')
            . ($type === 'email' ? ' End with a short friendly closing line but no name or signature - the agent\'s signature is added when it is sent.' : ' End with a short friendly closing line and no signature.');
    }
    $instructions .= $notes !== ''
        ? ' The agent has written notes on what to say: write the reply from them - everything they say, in full sentences, and nothing they don\'t: no extra promises, times, causes or fixes.'
        : ' Write the next sensible reply from what the ticket says: acknowledge what is new, say what is being done, or ask for what is missing.';
    $instructions .= ' Never make up facts, steps already taken, times or commitments. Where only the agent knows something the reply needs, put a short note in square brackets for them to fill in, like [time of the visit].'
        . ' Write in the language the client writes in. Answer with the reply in HTML only (<p>, <ul>, <li>, <strong>) - no introduction, no notes to the agent, no code fences.';

    $result = callAiApi($model, [
        ['role' => 'system', 'content' => $instructions],
        ['role' => 'user', 'content' => $ticket['text'] . "\n\n" . ($notes !== '' ? "The agent's notes on what to say:\n$notes" : 'Draft the next ' . ($type === 'internal' ? 'internal note.' : 'reply to the client.'))],
    ], ['feature' => 'ticket_draft', 'client_id' => $ticket['client_id']]);
    if (!$result['ok']) {
        return $result;
    }
    $html = aiCleanHtml($result['content']);
    return $html === '' ? ['ok' => false, 'error' => 'The AI provider answered with nothing - try again.'] : ['ok' => true, 'html' => $html];
}

/*
 * A ticket written up as a document for its client's documentation - ['ok',
 * 'title', 'description', 'html', 'removed' => how many passwords and keys
 * were left out] or ['ok' => false, 'error']. What to do the next time it
 * happens - specific to the client's systems the ticket names, but not the
 * story of the ticket. Passwords and keys are left out for good, as
 * [removed], since a document is kept and shared. Internal notes are read -
 * they are often where the fix is.
 */
function aiTicketDocument($ticket_id) {
    $model = getAiModel('Documentation');
    if (!$model) {
        return ['ok' => false, 'error' => aiModelMissingError('Documentation')];
    }
    $ticket = aiTicketContext($ticket_id, true);
    if (!$ticket) {
        return ['ok' => false, 'error' => 'That ticket no longer exists.'];
    }
    // Every model is set to take them out for this, whatever its provider says - a document is kept
    $model['ai_provider_redact'] = 1;
    $result = callAiApi($model, [
        ['role' => 'system', 'content' => 'You write knowledge base articles for a managed service provider\'s documentation of a client, from a support ticket that was worked.'
            . ' Write what to do the next time this happens - specific to the client\'s systems, devices and software where the ticket names them, but not the story of the ticket: no dates, no ticket numbers, no who-said-what.'
            . ' Use only what the ticket supports; where a step or detail should be confirmed, add [check].'
            . ' Answer in HTML only, in this shape: <h1>a short title</h1><p><em>one sentence on what it covers</em></p>'
            . ' <h2>Symptoms</h2> (what it looks like - error messages word for word) <h2>Cause</h2> (only when the ticket says) <h2>Resolution</h2> (numbered steps in an <ol>)'
            . ' <h2>If that doesn\'t work</h2> and <h2>Prevention</h2> only when the ticket gives something for them. No introduction, no code fences, no <html> or <body>.'],
        ['role' => 'user', 'content' => $ticket['text']],
    ], ['feature' => 'ticket_document', 'client_id' => $ticket['client_id'], 'secrets' => 'remove']);
    if (!$result['ok']) {
        return $result;
    }
    $html = aiCleanHtml($result['content']);

    // Its title and summary come off the top
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
    if (trim(strip_tags($html)) === '') {
        return ['ok' => false, 'error' => 'The AI provider answered with nothing to keep - try again.'];
    }
    return ['ok' => true, 'title' => mb_substr($title !== '' ? $title : (string) $ticket['row']['ticket_subject'], 0, 200), 'description' => mb_substr($description, 0, 250),
        'html' => $html, 'removed' => intval($result['removed'] ?? 0)];
}

/* The first JSON object in a model's answer - as an array, or null. Code fences and words around it are let be. */
function aiJsonFromText($content) {
    $content = (string) $content;
    $start = strpos($content, '{');
    $end = strrpos($content, '}');
    if ($start === false || $end === false || $end < $start) {
        return null;
    }
    $decoded = json_decode(substr($content, $start, $end - $start + 1), true);
    return is_array($decoded) ? $decoded : null;
}

/* ------------------------------------------------------------- Triage */

/*
 * Whether the 3.3.9 update has run - ai_ticket_triage and the triage setting.
 * The database version says where it is known (not in cron); otherwise the
 * setting's column is looked for.
 */
function aiTriageReady() {
    global $mysqli;
    static $ready = null;
    if ($ready === null) {
        if (defined('CURRENT_DATABASE_VERSION')) {
            $ready = version_compare(CURRENT_DATABASE_VERSION, '3.3.9', '>=');
        } else {
            $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
                AND ((TABLE_NAME = 'settings' AND COLUMN_NAME = 'config_ticket_ai_triage') OR (TABLE_NAME = 'ai_ticket_triage' AND COLUMN_NAME = 'ai_triage_ticket_id'))"));
            $ready = intval($row[0] ?? 0) === 2;
        }
    }
    return $ready;
}

/* How new tickets are triaged - aiTriageModes(): 0 not, 1 suggested on the ticket, 2 filled in. */
function aiTriageMode() {
    global $mysqli;
    if (!aiTriageReady()) {
        return 0;
    }
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT config_ticket_ai_triage FROM settings WHERE company_id = 1"));
    return max(0, min(2, intval($row[0] ?? 0)));
}

/* The ways new tickets can be triaged, for Settings > Tickets. */
function aiTriageModes() {
    return [
        0 => 'Off',
        1 => 'Suggest - shown on the ticket, to apply or dismiss',
        2 => 'Fill in - set as each comes in',
    ];
}

/* Where new tickets triaged as they come in are from - an agent's own picks their category and priority. */
const AI_TRIAGE_SOURCES = ['Email', 'Portal', 'API'];

/* A ticket's triage - with its suggested category's name - or null. */
function aiTriageRow($ticket_id) {
    global $mysqli;
    if (!aiTriageReady()) {
        return null;
    }
    return mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ai_ticket_triage.*, category_name FROM ai_ticket_triage
        LEFT JOIN categories ON category_id = ai_triage_category_id AND category_type = 'Ticket'
        WHERE ai_triage_ticket_id = " . intval($ticket_id))) ?: null;
}

/*
 * Ask a model to triage a ticket, and keep what it suggests - ['ok',
 * 'triage' => the row] or ['ok' => false, 'error']. The ticket's categories to
 * pick from go with it, each with its description; the answer is checked
 * against them and the priorities. A suggestion already there is replaced.
 */
function aiTicketTriage($ticket_id) {
    global $mysqli;
    if (!aiTriageReady()) {
        return ['ok' => false, 'error' => 'The database needs updating first - Maintenance > Update.'];
    }
    $model = getAiModel('Tickets');
    if (!$model) {
        return ['ok' => false, 'error' => aiModelMissingError('Tickets')];
    }
    $ticket = aiTicketContext($ticket_id, false);
    if (!$ticket) {
        return ['ok' => false, 'error' => 'That ticket no longer exists.'];
    }

    $categories = [];
    foreach (categoryPicker('Ticket') as $category_id => $category) {
        $categories[intval($category_id)] = $category;
    }
    $category_lines = [];
    foreach ($categories as $category) {
        $category_lines[] = '- ' . $category['category_name'] . (trim((string) $category['category_description']) !== '' ? ': ' . trim($category['category_description']) : '');
    }

    $result = callAiApi($model, [
        ['role' => 'system', 'content' => 'You triage new IT support tickets for a managed service provider. Read the ticket and answer with JSON only, in this shape:'
            . ' {"category": "...", "priority": "...", "summary": "...", "reason": "..."}'
            . "\n- category: exactly one of these names, or \"\" when none fits:\n" . ($category_lines ? implode("\n", $category_lines) : '(none set up - answer "")')
            . "\n- priority: one of Urgent (a whole site or a business-critical system is down, many people can't work, or a security incident), High (someone can't work, or something important is broken with no way around it), Medium (something is broken but there is a way around it, or it touches one person a little) or Low (a question, a request, or something that can wait)."
            . "\n- summary: one sentence, under 25 words, on what the client needs."
            . "\n- reason: a few words on why that priority."
            . "\nGo by what the ticket says, not by how urgent the sender says it is."],
        ['role' => 'user', 'content' => $ticket['text']],
    ], ['feature' => 'ticket_triage', 'client_id' => $ticket['client_id']]);
    if (!$result['ok']) {
        return $result;
    }

    $answer = aiJsonFromText($result['content']);
    if (!$answer) {
        logApp('AI', 'warning', 'Triage of ticket ' . ticketReference($ticket['row']) . ' - the answer was not JSON');
        return ['ok' => false, 'error' => "The AI provider's answer couldn't be read - try again."];
    }
    $category_id = 0;
    $wanted = mb_strtolower(trim((string) ($answer['category'] ?? '')));
    foreach ($categories as $id => $category) {
        if ($wanted !== '' && mb_strtolower(trim($category['category_name'])) === $wanted) {
            $category_id = $id;
            break;
        }
    }
    $priority = ucfirst(strtolower(trim((string) ($answer['priority'] ?? ''))));
    $priority = in_array($priority, TICKET_PRIORITIES, true) ? $priority : '';
    $summary = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags((string) ($answer['summary'] ?? '')))), 0, 300);
    $reason = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags((string) ($answer['reason'] ?? '')))), 0, 200);
    if (!$category_id && $priority === '') {
        return ['ok' => false, 'error' => 'The AI provider suggested no category or priority it could use - try again.'];
    }

    $esc = function ($value) use ($mysqli) {
        return $value === '' ? 'NULL' : "'" . mysqli_real_escape_string($mysqli, $value) . "'";
    };
    $ticket_id = intval($ticket_id);
    mysqli_query($mysqli, "INSERT INTO ai_ticket_triage SET ai_triage_ticket_id = $ticket_id, ai_triage_category_id = $category_id, ai_triage_priority = {$esc($priority)},
            ai_triage_summary = {$esc($summary)}, ai_triage_reason = {$esc($reason)}, ai_triage_state = 'suggested', ai_triage_by = 0, ai_triage_created_at = NOW()
        ON DUPLICATE KEY UPDATE ai_triage_category_id = VALUES(ai_triage_category_id), ai_triage_priority = VALUES(ai_triage_priority), ai_triage_summary = VALUES(ai_triage_summary),
            ai_triage_reason = VALUES(ai_triage_reason), ai_triage_state = 'suggested', ai_triage_by = 0, ai_triage_created_at = NOW()");
    return ['ok' => true, 'triage' => aiTriageRow($ticket_id)];
}

/*
 * What a triage would change on its ticket - ['category' => [from, to name,
 * to id], 'priority' => [from, to]], for those that differ. $only limits it to
 * 'category' and/or 'priority'.
 */
function aiTriageChanges(array $triage, array $ticket, array $only = ['category', 'priority']) {
    $changes = [];
    if (in_array('category', $only, true) && intval($triage['ai_triage_category_id']) && intval($triage['ai_triage_category_id']) !== intval($ticket['ticket_category'])
        && ticketCategoryId($triage['ai_triage_category_id'])) {
        $changes['category'] = [intval($ticket['ticket_category']) ? (string) getFieldById('categories', intval($ticket['ticket_category']), 'category_name') : 'none', (string) $triage['category_name'], intval($triage['ai_triage_category_id'])];
    }
    if (in_array('priority', $only, true) && (string) $triage['ai_triage_priority'] !== '' && $triage['ai_triage_priority'] !== $ticket['ticket_priority']) {
        $changes['priority'] = [(string) $ticket['ticket_priority'], (string) $triage['ai_triage_priority']];
    }
    return $changes;
}

/*
 * Put a triage on its ticket - its category and priority, those in $only -
 * and say so in the ticket's history and the audit log, with the SLA worked
 * out again for the priority. $who is who did it ('AI triage' when it came in
 * that way). Returns what changed, as aiTriageChanges() says it.
 */
function aiTriageApply($ticket_id, $who, array $only = ['category', 'priority'], $state = 'applied', $by = 0) {
    global $mysqli;
    $ticket_id = intval($ticket_id);
    $triage = aiTriageRow($ticket_id);
    $ticket = ticketRow($ticket_id);
    if (!$triage || !$ticket) {
        return [];
    }
    $changes = aiTriageChanges($triage, $ticket, $only);
    $words = [];
    if (isset($changes['category'])) {
        mysqli_query($mysqli, "UPDATE tickets SET ticket_category = {$changes['category'][2]} WHERE ticket_id = $ticket_id");
        $words[] = "the category from {$changes['category'][0]} to {$changes['category'][1]}";
    }
    if (isset($changes['priority'])) {
        mysqli_query($mysqli, "UPDATE tickets SET ticket_priority = '" . escapeSql($changes['priority'][1]) . "' WHERE ticket_id = $ticket_id");
        applyTicketSla($ticket_id);
        $words[] = "the priority from {$changes['priority'][0]} to {$changes['priority'][1]}";
    }
    mysqli_query($mysqli, "UPDATE ai_ticket_triage SET ai_triage_state = '" . escapeSql($state) . "', ai_triage_by = " . intval($by) . " WHERE ai_triage_ticket_id = $ticket_id");
    if ($words) {
        $said = escapeSql("$who set " . implode(' and ', $words) . ', as AI triage suggested');
        logTicketHistory($ticket_id, $said);
        logAudit("Ticket", "Edit", $said . ' on ticket ' . escapeSql(ticketReference($ticket)), intval($ticket['ticket_client_id']), $ticket_id);
        triggerCustomAction('ticket_update', $ticket_id);
    }
    return $changes;
}

/*
 * Triage the new tickets that have come in - from email, the portal or the
 * API in the last six hours, still open, not triaged yet - up to $limit of
 * them, oldest first. Filled in (mode 2): the category when the ticket has
 * none, and the priority of an emailed ticket (nobody picked it); the rest is
 * left as a suggestion. Returns [triaged, failed].
 */
function aiTriageNewTickets($limit = 5) {
    global $mysqli;
    $mode = aiTriageMode();
    if (!$mode || !getAiModel('Tickets')) {
        return [0, 0];
    }
    $sources = "'" . implode("', '", AI_TRIAGE_SOURCES) . "'";
    $sql = mysqli_query($mysqli, "SELECT ticket_id FROM tickets
        LEFT JOIN ai_ticket_triage ON ai_triage_ticket_id = ticket_id
        WHERE ai_triage_ticket_id IS NULL AND ticket_source IN ($sources) AND ticket_created_at >= NOW() - INTERVAL 6 HOUR
            AND ticket_resolved_at IS NULL AND ticket_closed_at IS NULL
        ORDER BY ticket_id ASC LIMIT " . intval($limit));
    $done = $failed = 0;
    while ($row = mysqli_fetch_row($sql)) {
        $ticket_id = intval($row[0]);
        $result = aiTicketTriage($ticket_id);
        if (!$result['ok']) {
            // Not asked again - triage with AI from the ticket's menu tries it once more
            mysqli_query($mysqli, "INSERT IGNORE INTO ai_ticket_triage SET ai_triage_ticket_id = $ticket_id, ai_triage_state = 'failed', ai_triage_reason = '" . escapeSql(mb_substr($result['error'], 0, 200)) . "'");
            $failed++;
            continue;
        }
        $done++;
        if ($mode === 2) {
            $ticket = ticketRow($ticket_id);
            $only = intval($ticket['ticket_category']) ? [] : ['category'];
            if ($ticket['ticket_source'] === 'Email') {
                $only[] = 'priority';
            }
            aiTriageApply($ticket_id, 'AI triage', $only, aiTriageChanges($result['triage'], $ticket) == aiTriageChanges($result['triage'], $ticket, $only) ? 'applied' : 'suggested');
        }
    }
    return [$done, $failed];
}
