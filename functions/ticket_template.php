<?php

/*
 * Ticket templates - a ticket ready to raise: its subject, details and tasks,
 * and how it is set up - category, priority, billing, who it goes to, when it
 * is due, and its tags.
 *
 * Admin > Ticket Templates makes them (admin/ticket_templates.php,
 * admin/ticket_template.php, admin/post/ticket_template.php). They are picked
 * on New Ticket, the New Tickets windows of the assets and clients lists and
 * recurring tickets (agent/includes/inc_ticket_template_select.php, which
 * fills in the form - agent/js/ticket_tasks_modal.js), and a project template
 * raises its ticket templates as they are (projectApplyTemplate()).
 *
 * Words in square brackets - [Client], [Contact], [Asset], [Site], [Agent],
 * [Project], [Ticket], [Date], [Month], [Quarter] and [Year] - are filled in
 * from the ticket when it is raised (ticketPlaceholdersFill()). Anything else
 * in brackets, [Employee Name] say, is a fill-in: asked for when the ticket is
 * raised (as text, a date, a choice from a list, or yes or no - optional
 * unless the template says otherwise), filled in wherever it appears, and
 * kept on the ticket as its summary (ticket_fill_ins). One left blank stays in
 * the text for whoever picks it up, and can be answered on the ticket later.
 * A template's task can be for some answers only - "Order a laptop" when
 * Needs a laptop is Yes.
 *
 * A ticket keeps which template it came from (tickets.ticket_template_id), so
 * a template shows how often it is used and its recent tickets.
 */

/** The placeholders filled in from the ticket - what each becomes. */
const TICKET_PLACEHOLDERS = [
    'Client' => "the client's name",
    'Contact' => "the contact's name",
    'Asset' => "the asset's name",
    'Site' => "the site's name",
    'Agent' => 'the agent it is assigned to, or who raised it',
    'Project' => "the project's name",
    'Ticket' => 'its number - TCK-123',
    'Date' => "today's date",
    'Month' => 'the month - October 2026',
    'Quarter' => 'the quarter - Q4 2026',
    'Year' => 'the year',
];

/** What can go in square brackets - a letter, then up to 40 letters, digits, spaces, _ - and /. A regex fragment. */
const TICKET_PLACEHOLDER_NAME = '[A-Za-z][A-Za-z0-9 _\-\/]{0,40}';

/** One template with its category and the agent it goes to, or null. Archived ones too. */
function ticketTemplateRow($ticket_template_id) {
    global $mysqli;
    $ticket_template_id = intval($ticket_template_id);
    if (!$ticket_template_id) {
        return null;
    }
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ticket_templates.*, category_name, category_color, category_icon, category_type, category_description,
            user_name AS assignee_name, user_avatar AS assignee_avatar
        FROM ticket_templates
        LEFT JOIN categories ON category_id = ticket_template_category_id AND ticket_template_category_id > 0
        LEFT JOIN users ON user_id = ticket_template_assigned_to AND ticket_template_assigned_to > 0
        WHERE ticket_template_id = $ticket_template_id LIMIT 1"));
    return $row ?: null;
}

/*
 * Templates' tasks, in order - [template id => [['id', 'name', 'estimate',
 * 'when' => ['fill_in', 'value'] or null], ...]]. 'when': only for that answer.
 */
function ticketTemplateTasksFor(array $ticket_template_ids) {
    global $mysqli;
    $ids = array_values(array_filter(array_map('intval', $ticket_template_ids)));
    $tasks = array_fill_keys($ids, []);
    if (!$ids) {
        return $tasks;
    }
    $sql = mysqli_query($mysqli, "SELECT task_template_id, task_template_ticket_template_id, task_template_name, task_template_completion_estimate, task_template_when_fill_in, task_template_when_value
        FROM task_templates WHERE task_template_ticket_template_id IN (" . implode(',', $ids) . ") ORDER BY task_template_order ASC, task_template_id ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $when = trim((string) $row['task_template_when_fill_in']) !== '' ? ['fill_in' => (string) $row['task_template_when_fill_in'], 'value' => (string) $row['task_template_when_value']] : null;
        $tasks[intval($row['task_template_ticket_template_id'])][] = ['id' => intval($row['task_template_id']), 'name' => (string) $row['task_template_name'],
            'estimate' => intval($row['task_template_completion_estimate']), 'when' => $when];
    }
    return $tasks;
}

/** One template's tasks, in order (ticketTemplateTasksFor()). */
function ticketTemplateTasks($ticket_template_id) {
    return ticketTemplateTasksFor([$ticket_template_id])[intval($ticket_template_id)] ?? [];
}

/** The ticket tags a template gives its tickets - ids, the archived ones too. */
function ticketTemplateTagIds($ticket_template_id) {
    global $mysqli;
    $ids = [];
    $sql = mysqli_query($mysqli, "SELECT tag_id FROM ticket_template_tags WHERE ticket_template_id = " . intval($ticket_template_id) . " ORDER BY tag_id");
    while ($row = mysqli_fetch_row($sql)) {
        $ids[] = intval($row[0]);
    }
    return $ids;
}

/** "4 hours", "1 day", "3 days" - when a template's tickets fall due; '' for not set. */
function ticketTemplateDueText($hours) {
    $hours = intval($hours);
    if ($hours <= 0) {
        return '';
    }
    if ($hours % 24 === 0) {
        $days = intdiv($hours, 24);
        return $days === 1 ? '1 day' : "$days days";
    }
    return $hours === 1 ? '1 hour' : "$hours hours";
}

/** Tasks' time in words - "45m", "2h 30m". */
function ticketTemplateMinutesText($minutes) {
    $minutes = max(0, intval($minutes));
    return formatDuration(sprintf('%02d:%02d:00', intdiv($minutes, 60), $minutes % 60));
}

/** Square-bracket words in some text - [Client], [Employee Name] - each once, as first written. HTML is read as its text. */
function ticketTemplatePlaceholdersIn($text) {
    $text = str_replace("\u{00A0}", ' ', html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    preg_match_all('/\[(' . TICKET_PLACEHOLDER_NAME . ')\]/', $text, $matches);
    $found = [];
    foreach ($matches[1] as $name) {
        $name = preg_replace('/ {2,}/', ' ', trim($name));
        $found[strtolower($name)] = $found[strtolower($name)] ?? $name;
    }
    return array_values($found);
}

/** When a ticket raised now from a template that is due in $hours is due - 'Y-m-d H:i:s', or null for not set. */
function ticketTemplateDueAt($hours) {
    $hours = intval($hours);
    return $hours > 0 ? date('Y-m-d H:i:s', time() + $hours * 3600) : null;
}

/*
 * A template's form, posted (admin/modals/ticket_template/ticket_template_form.php),
 * checked - its fields raw, or null with $error saying why. The due time is
 * due_amount in due_unit (hours or days).
 */
function ticketTemplateFields(array $post, &$error = '') {
    $name = trim(mb_substr(preg_replace('/\s+/', ' ', strip_tags((string) ($post['name'] ?? ''))), 0, 200));
    if ($name === '') {
        $error = 'A template needs a name.';
        return null;
    }
    $priority = (string) ($post['priority'] ?? '');
    $billable = (string) ($post['billable'] ?? '');
    $amount = max(0, intval($post['due_amount'] ?? 0));
    $hours = ($post['due_unit'] ?? 'days') === 'hours' ? $amount : $amount * 24;
    return [
        'name' => $name,
        'description' => trim(mb_substr(strip_tags((string) ($post['description'] ?? '')), 0, 500)),
        'subject' => trim(mb_substr(strip_tags((string) ($post['subject'] ?? '')), 0, 500)),
        'details' => (string) ($post['details'] ?? ''),
        'category_id' => ticketCategoryId($post['category_id'] ?? 0),
        'priority' => in_array($priority, TICKET_PRIORITIES, true) ? $priority : null,
        'billable' => $billable === '1' ? 1 : ($billable === '0' ? 0 : null),
        'assigned_to' => ticketAgentId($post['assigned_to'] ?? 0),
        'due_hours' => min($hours, 24 * 366),
        'tags' => (array) ($post['tags'] ?? []),
    ];
}

/** Save a template's fields (ticketTemplateFields()) - a new one when $ticket_template_id is 0. Its id. */
function ticketTemplateSave($ticket_template_id, array $f) {
    global $mysqli;
    $ticket_template_id = intval($ticket_template_id);
    $set = "ticket_template_name = '" . escapeSql($f['name']) . "', ticket_template_description = '" . escapeSql($f['description']) . "',
        ticket_template_subject = '" . escapeSql($f['subject']) . "', ticket_template_details = '" . mysqli_real_escape_string($mysqli, $f['details']) . "',
        ticket_template_category_id = " . intval($f['category_id']) . ", ticket_template_priority = " . ($f['priority'] === null ? 'NULL' : "'" . escapeSql($f['priority']) . "'") . ",
        ticket_template_billable = " . ($f['billable'] === null ? 'NULL' : intval($f['billable'])) . ", ticket_template_assigned_to = " . intval($f['assigned_to']) . ",
        ticket_template_due_hours = " . intval($f['due_hours']);
    if ($ticket_template_id) {
        mysqli_query($mysqli, "UPDATE ticket_templates SET $set WHERE ticket_template_id = $ticket_template_id");
    } else {
        mysqli_query($mysqli, "INSERT INTO ticket_templates SET $set");
        $ticket_template_id = intval(mysqli_insert_id($mysqli));
    }
    ticketTemplateSetTags($ticket_template_id, $f['tags']);
    return $ticket_template_id;
}

/* Set a template's tags from a Tags field - ticket tags, as tagSetForRecord() takes them. The ids it has now. */
function ticketTemplateSetTags($ticket_template_id, array $values) {
    global $mysqli;
    $ticket_template_id = intval($ticket_template_id);
    $had = ticketTemplateTagIds($ticket_template_id);
    $keep = tagKeepIds('ticket', tagIdsFromValues('ticket', $values), $had);
    $remove = array_diff($had, $keep);
    if ($remove) {
        mysqli_query($mysqli, "DELETE FROM ticket_template_tags WHERE ticket_template_id = $ticket_template_id AND tag_id IN (" . implode(',', $remove) . ")");
    }
    foreach (array_diff($keep, $had) as $tag_id) {
        mysqli_query($mysqli, "INSERT IGNORE INTO ticket_template_tags SET ticket_template_id = $ticket_template_id, tag_id = " . intval($tag_id));
    }
    return $keep;
}

/** A copy of a template - "Copy of" its name, its tasks, tags and fill-ins - not archived. The new one's id, or 0. */
function ticketTemplateCopy($ticket_template_id) {
    global $mysqli;
    $row = ticketTemplateRow($ticket_template_id);
    if (!$row) {
        return 0;
    }
    $new_id = ticketTemplateSave(0, [
        'name' => mb_substr('Copy of ' . $row['ticket_template_name'], 0, 200), 'description' => (string) $row['ticket_template_description'],
        'subject' => (string) $row['ticket_template_subject'], 'details' => (string) $row['ticket_template_details'],
        'category_id' => intval($row['ticket_template_category_id']), 'priority' => $row['ticket_template_priority'],
        'billable' => $row['ticket_template_billable'] === null ? null : intval($row['ticket_template_billable']),
        'assigned_to' => intval($row['ticket_template_assigned_to']), 'due_hours' => intval($row['ticket_template_due_hours']),
        'tags' => [],
    ]);
    foreach (ticketTemplateTagIds($ticket_template_id) as $tag_id) {
        mysqli_query($mysqli, "INSERT IGNORE INTO ticket_template_tags SET ticket_template_id = $new_id, tag_id = $tag_id");
    }
    foreach (ticketTemplateTasks($ticket_template_id) as $order => $task) {
        mysqli_query($mysqli, "INSERT INTO task_templates SET task_template_name = '" . escapeSql($task['name']) . "', task_template_order = " . ($order + 1) . ",
            task_template_completion_estimate = " . intval($task['estimate']) . ", task_template_ticket_template_id = $new_id,
            task_template_when_fill_in = " . ($task['when'] ? "'" . escapeSql($task['when']['fill_in']) . "'" : 'NULL') . ",
            task_template_when_value = " . ($task['when'] ? "'" . escapeSql($task['when']['value']) . "'" : 'NULL'));
    }
    mysqli_query($mysqli, "INSERT INTO ticket_template_fill_ins (fill_in_ticket_template_id, fill_in_name, fill_in_type, fill_in_options, fill_in_required, fill_in_help, fill_in_order)
        SELECT $new_id, fill_in_name, fill_in_type, fill_in_options, fill_in_required, fill_in_help, fill_in_order FROM ticket_template_fill_ins WHERE fill_in_ticket_template_id = " . intval($ticket_template_id));
    return $new_id;
}

/** How templates are used - [id => ['count' => tickets raised, 'open' => still open, 'last' => when the last was raised]]. */
function ticketTemplateUsage(array $ticket_template_ids) {
    global $mysqli;
    $ids = array_values(array_filter(array_map('intval', $ticket_template_ids)));
    $usage = [];
    if (!$ids) {
        return $usage;
    }
    $sql = mysqli_query($mysqli, "SELECT ticket_template_id, COUNT(*) AS used, SUM(ticket_closed_at IS NULL AND ticket_resolved_at IS NULL) AS still_open, MAX(ticket_created_at) AS last_used
        FROM tickets WHERE ticket_template_id IN (" . implode(',', $ids) . ") AND ticket_archived_at IS NULL GROUP BY ticket_template_id");
    while ($row = mysqli_fetch_assoc($sql)) {
        $usage[intval($row['ticket_template_id'])] = ['count' => intval($row['used']), 'open' => intval($row['still_open']), 'last' => $row['last_used']];
    }
    return $usage;
}

/*
 * How a template sets up its tickets, as little badges - its priority,
 * category, billing, agent and due time. HTML; '' when it sets none of them.
 */
function ticketTemplateDefaultsHtml(array $row) {
    $chips = [];
    if (!empty($row['ticket_template_priority'])) {
        $chips[] = ticketPriorityHtml($row['ticket_template_priority'], 'detail');
    }
    if (intval($row['ticket_template_category_id']) && !empty($row['category_name'])) {
        $chips[] = '<span class="badge rounded-pill text-bg-light border fw-normal">' . categoryChip(['category_name' => $row['category_name'], 'category_type' => 'Ticket', 'category_color' => $row['category_color'] ?? '', 'category_icon' => $row['category_icon'] ?? '']) . '</span>';
    }
    if ($row['ticket_template_billable'] !== null) {
        $chips[] = '<span class="badge rounded-pill text-bg-light border fw-normal"><i class="fas fa-fw fa-' . (intval($row['ticket_template_billable']) ? 'dollar-sign text-success' : 'ban text-secondary') . ' me-1"></i>' . (intval($row['ticket_template_billable']) ? 'Billable' : 'Not billable') . '</span>';
    }
    if (intval($row['ticket_template_assigned_to']) && !empty($row['assignee_name'])) {
        $chips[] = '<span class="badge rounded-pill text-bg-light border fw-normal"><i class="fas fa-fw fa-user-check text-secondary me-1"></i>' . escapeHtml($row['assignee_name']) . '</span>';
    }
    if (intval($row['ticket_template_due_hours'])) {
        $chips[] = '<span class="badge rounded-pill text-bg-light border fw-normal"><i class="fas fa-fw fa-calendar-check text-secondary me-1"></i>Due in ' . ticketTemplateDueText($row['ticket_template_due_hours']) . '</span>';
    }
    return $chips ? '<span class="d-inline-flex flex-wrap gap-1 align-items-center">' . implode('', $chips) . '</span>' : '';
}

/*
 * What picking a template puts into a ticket form - the data attributes of
 * its <option> (agent/includes/inc_ticket_template_select.php), which
 * agent/js/ticket_tasks_modal.js reads. Due is the local date and time it
 * would be due if raised now. '' values leave the form's field alone.
 * Fill-ins: what it asks for (ticketTemplateFillIns()); a task's 'when' is
 * the answer it is for.
 */
function ticketTemplatePickerData(array $row, array $tasks, array $tag_ids, array $fill_ins = []) {
    $due = ticketTemplateDueAt($row['ticket_template_due_hours']);
    return [
        'subject' => (string) $row['ticket_template_subject'],
        'details' => (string) $row['ticket_template_details'],
        'tasks' => json_encode(array_map(function ($task) {
            return ['name' => $task['name'], 'estimate' => $task['estimate'], 'when' => $task['when'] ?? null];
        }, $tasks)),
        'fill-ins' => $fill_ins ? json_encode(array_map(function ($fill_in) {
            return ['name' => $fill_in['name'], 'type' => $fill_in['type'], 'options' => $fill_in['options'], 'required' => (bool) $fill_in['required'], 'help' => $fill_in['help']];
        }, $fill_ins)) : '',
        'priority' => (string) ($row['ticket_template_priority'] ?? ''),
        'category' => intval($row['ticket_template_category_id']) ? (string) intval($row['ticket_template_category_id']) : '',
        'billable' => $row['ticket_template_billable'] === null ? '' : (string) intval($row['ticket_template_billable']),
        'assigned' => intval($row['ticket_template_assigned_to']) ? (string) intval($row['ticket_template_assigned_to']) : '',
        'due' => $due ? date('Y-m-d\TH:i', strtotime($due)) : '',
        'tags' => $tag_ids ? implode(',', $tag_ids) : '',
    ];
}

/* ---------------------------------------------------------------- fill-ins */

/** The kinds of fill-in - what each is called, and its icon. */
const TICKET_FILL_IN_TYPES = [
    'text' => ['label' => 'Text', 'icon' => 'fa-font'],
    'date' => ['label' => 'Date', 'icon' => 'fa-calendar-day'],
    'list' => ['label' => 'List', 'icon' => 'fa-list-ul'],
    'yesno' => ['label' => 'Yes or no', 'icon' => 'fa-toggle-on'],
];

/*
 * A fill-in's name, tidied - as it goes in square brackets, so the same
 * letters, digits, spaces, _ - and / - or '' when it can't be one. The names
 * filled in from the ticket ([Client] and the rest) can't be.
 */
function ticketFillInName($name) {
    $name = trim(preg_replace('/\s+/', ' ', strip_tags((string) $name)), " []");
    if (!preg_match('/^' . TICKET_PLACEHOLDER_NAME . '$/', $name) || isset(array_change_key_case(TICKET_PLACEHOLDERS)[strtolower($name)])) {
        return '';
    }
    return $name;
}

/** A list fill-in's choices from the box they are typed into - one per line, each once, up to 50. */
function ticketFillInOptions($text) {
    $options = [];
    foreach (preg_split('/\R/', (string) $text) as $line) {
        $line = trim(mb_substr(preg_replace('/\s+/', ' ', strip_tags($line)), 0, 100));
        if ($line !== '' && !isset($options[mb_strtolower($line)])) {
            $options[mb_strtolower($line)] = $line;
        }
    }
    return array_slice(array_values($options), 0, 50);
}

/*
 * The fill-ins set up on templates - [template id => [['id', 'name', 'type',
 * 'options' => [..], 'required' => bool, 'help'], ...]], in their order.
 */
function ticketTemplateFillInRowsFor(array $ticket_template_ids) {
    global $mysqli;
    $ids = array_values(array_filter(array_map('intval', $ticket_template_ids)));
    $rows = array_fill_keys($ids, []);
    if (!$ids) {
        return $rows;
    }
    $sql = mysqli_query($mysqli, "SELECT * FROM ticket_template_fill_ins WHERE fill_in_ticket_template_id IN (" . implode(',', $ids) . ") ORDER BY fill_in_order ASC, fill_in_id ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[intval($row['fill_in_ticket_template_id'])][] = [
            'id' => intval($row['fill_in_id']),
            'name' => (string) $row['fill_in_name'],
            'type' => isset(TICKET_FILL_IN_TYPES[$row['fill_in_type']]) ? $row['fill_in_type'] : 'text',
            'options' => ticketFillInOptions($row['fill_in_options']),
            'required' => (bool) intval($row['fill_in_required']),
            'help' => (string) $row['fill_in_help'],
        ];
    }
    return $rows;
}

/*
 * Everything a template asks for, in order - the ones set up (their rows),
 * then the other bracketed words in its subject, details and tasks, as plain
 * optional text. Each ['id' (0 when not set up), 'name', 'type', 'options',
 * 'required', 'help', 'in_text' => whether it is in the text].
 */
function ticketTemplateFillIns(array $template, array $tasks, array $rows) {
    $known = array_map('strtolower', array_keys(TICKET_PLACEHOLDERS));
    $in_text = [];
    foreach (ticketTemplatePlaceholdersIn($template['ticket_template_subject'] . ' ' . $template['ticket_template_details'] . ' ' . implode(' ', array_column($tasks, 'name'))) as $name) {
        if (!in_array(strtolower($name), $known, true)) {
            $in_text[strtolower($name)] = $name;
        }
    }
    $fill_ins = [];
    foreach ($rows as $row) {
        $key = strtolower($row['name']);
        $fill_ins[$key] = $row + ['in_text' => isset($in_text[$key])];
    }
    foreach ($in_text as $key => $name) {
        $fill_ins[$key] = $fill_ins[$key] ?? ['id' => 0, 'name' => $name, 'type' => 'text', 'options' => [], 'required' => false, 'help' => '', 'in_text' => true];
    }
    return array_values($fill_ins);
}

/** Everything one template asks for (ticketTemplateFillIns()) - [] when it is gone. */
function ticketTemplateFillInsFor($ticket_template_id) {
    $template = ticketTemplateRow($ticket_template_id);
    if (!$template) {
        return [];
    }
    $id = intval($template['ticket_template_id']);
    return ticketTemplateFillIns($template, ticketTemplateTasks($id), ticketTemplateFillInRowsFor([$id])[$id]);
}

/*
 * The answers posted for some fill-ins (fill_ins[Name] = value), checked -
 * each fill-in with its 'value' ('' when not given): text tidied, a date as
 * Y-m-d, a list's choice as written there, yes or no as Yes or No. Null with
 * $error saying what is wrong - a required one left blank (unless
 * $required is false), a date that isn't one, a choice that isn't offered.
 */
function ticketFillInAnswers(array $fill_ins, $posted, &$error = '', $required = true) {
    $given = [];
    foreach ((array) $posted as $name => $value) {
        $given[strtolower(trim((string) $name))] = is_array($value) ? '' : trim((string) $value);
    }
    $answers = [];
    foreach ($fill_ins as $fill_in) {
        $raw = $given[strtolower($fill_in['name'])] ?? '';
        $value = '';
        if ($raw !== '') {
            switch ($fill_in['type']) {
                case 'date':
                    $date = DateTime::createFromFormat('!Y-m-d', $raw);
                    if (!$date || $date->format('Y-m-d') !== $raw) {
                        $error = escapeHtml($fill_in['name']) . ' needs a date.';
                        return null;
                    }
                    $value = $raw;
                    break;
                case 'list':
                    foreach ($fill_in['options'] as $option) {
                        if (mb_strtolower($option) === mb_strtolower($raw)) {
                            $value = $option;
                        }
                    }
                    if ($value === '') {
                        $error = 'Pick one of the choices for ' . escapeHtml($fill_in['name']) . '.';
                        return null;
                    }
                    break;
                case 'yesno':
                    $value = in_array(strtolower($raw), ['1', 'yes', 'y', 'true'], true) ? 'Yes' : (in_array(strtolower($raw), ['0', 'no', 'n', 'false'], true) ? 'No' : '');
                    break;
                default:
                    $value = trim(mb_substr(preg_replace('/\s+/', ' ', strip_tags($raw)), 0, 500));
            }
        }
        if ($value === '' && $required && !empty($fill_in['required'])) {
            $error = 'Fill in ' . escapeHtml($fill_in['name']) . ' - it is needed for this template.';
            return null;
        }
        $answers[] = ['name' => $fill_in['name'], 'type' => $fill_in['type'], 'options' => $fill_in['options'], 'required' => !empty($fill_in['required']), 'value' => $value];
    }
    return $answers;
}

/** An answer as it reads - a date as Oct 20, 2026, the rest as given; '' when not given. */
function ticketFillInText(array $answer) {
    $value = (string) ($answer['value'] ?? '');
    if ($value !== '' && ($answer['type'] ?? '') === 'date' && strtotime($value)) {
        return date('M j, Y', strtotime($value));
    }
    return $value;
}

/* The answers a ticket or recurring ticket keeps - which table, and its columns' prefix. */
const TICKET_FILL_IN_STORES = [
    'ticket' => ['table' => 'ticket_fill_ins', 'prefix' => 'ticket_fill_in_', 'owner' => 'ticket_fill_in_ticket_id'],
    'recurring_ticket' => ['table' => 'recurring_ticket_fill_ins', 'prefix' => 'recurring_ticket_fill_in_', 'owner' => 'recurring_ticket_fill_in_recurring_ticket_id'],
];

/** The answers a ticket ($kind 'ticket') or recurring ticket ('recurring_ticket') keeps, in order - as ticketFillInAnswers() gives them. */
function ticketFillInsStored($kind, $id) {
    global $mysqli;
    $store = TICKET_FILL_IN_STORES[$kind];
    $p = $store['prefix'];
    $answers = [];
    $sql = mysqli_query($mysqli, "SELECT * FROM {$store['table']} WHERE {$store['owner']} = " . intval($id) . " ORDER BY {$p}order ASC, {$p}id ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $answers[] = ['id' => intval($row[$p . 'id']), 'name' => (string) $row[$p . 'name'], 'type' => isset(TICKET_FILL_IN_TYPES[$row[$p . 'type']]) ? $row[$p . 'type'] : 'text',
            'options' => ticketFillInOptions($row[$p . 'options']), 'required' => (bool) intval($row[$p . 'required']), 'value' => (string) $row[$p . 'value']];
    }
    return $answers;
}

/** Keep these answers (ticketFillInAnswers()) on a ticket or recurring ticket, in place of any it had. */
function ticketFillInsStore($kind, $id, array $answers) {
    global $mysqli;
    $store = TICKET_FILL_IN_STORES[$kind];
    $p = $store['prefix'];
    $id = intval($id);
    mysqli_query($mysqli, "DELETE FROM {$store['table']} WHERE {$store['owner']} = $id");
    foreach (array_values($answers) as $order => $answer) {
        mysqli_query($mysqli, "INSERT INTO {$store['table']} SET {$store['owner']} = $id, {$p}name = '" . escapeSql($answer['name']) . "', {$p}type = '" . escapeSql($answer['type']) . "',
            {$p}options = " . ($answer['options'] ? "'" . escapeSql(implode("\n", $answer['options'])) . "'" : 'NULL') . ", {$p}required = " . (empty($answer['required']) ? 0 : 1) . ",
            {$p}value = " . ((string) $answer['value'] !== '' ? "'" . escapeSql($answer['value']) . "'" : 'NULL') . ", {$p}order = " . ($order + 1));
    }
}

/*
 * One fill-in as a form field - label, icon and control (fill_ins[Name]),
 * with its help - holding $value. The same fields agent/js/ticket_tasks_modal.js
 * draws when a template is picked. $required: mark and require it.
 */
function ticketFillInFieldHtml(array $fill_in, $value, $required = true) {
    $name = escapeHtml($fill_in['name']);
    $field = 'fill_ins[' . $name . ']';
    $required = $required && !empty($fill_in['required']);
    $icon = TICKET_FILL_IN_TYPES[$fill_in['type']]['icon'] ?? 'fa-font';
    $attributes = 'name="' . $field . '" data-fill-in-name="' . $name . '" aria-label="' . $name . '"' . ($required ? ' required' : '');
    $value = (string) $value;
    if ($fill_in['type'] === 'list' || $fill_in['type'] === 'yesno') {
        $choices = $fill_in['type'] === 'yesno' ? ['Yes', 'No'] : $fill_in['options'];
        $control = '<select class="form-select' . ($fill_in['type'] === 'list' ? ' select2' : '') . '" ' . $attributes . '><option value="">- Choose -</option>';
        foreach ($choices as $choice) {
            $control .= '<option value="' . escapeHtml($choice) . '"' . (mb_strtolower($choice) === mb_strtolower($value) ? ' selected' : '') . '>' . escapeHtml($choice) . '</option>';
        }
        $control .= '</select>';
    } else {
        $control = '<input type="' . ($fill_in['type'] === 'date' ? 'date' : 'text') . '" class="form-control" ' . $attributes . ($fill_in['type'] === 'date' ? '' : ' maxlength="500"') . ' value="' . escapeHtml($value) . '">';
    }
    return '<label class="form-label mb-1">' . $name . ($required ? ' <strong class="text-danger">*</strong>' : '') . '</label>'
        . '<div class="input-group"><span class="input-group-text"><i class="fas fa-fw ' . $icon . '"></i></span>' . $control . '</div>'
        . (!empty($fill_in['help']) ? '<div class="form-text">' . escapeHtml($fill_in['help']) . '</div>' : '');
}

/*
 * Whether a template task is for these answers: always when it has no
 * condition, when its fill-in isn't among them or wasn't answered (better a
 * task to strike off than one missed), or when the answer it is for is no
 * longer one its fill-in offers; otherwise when the answer is the one it is for.
 */
function ticketTemplateTaskApplies(array $task, array $answers) {
    if (empty($task['when'])) {
        return true;
    }
    $want = mb_strtolower($task['when']['value']);
    foreach ($answers as $answer) {
        if (strtolower($answer['name']) === strtolower($task['when']['fill_in'])) {
            $offered = $answer['type'] === 'yesno' ? ['Yes', 'No'] : ($answer['type'] === 'list' ? $answer['options'] : []);
            if ((string) $answer['value'] === '' || !in_array($want, array_map('mb_strtolower', $offered), true)) {
                return true;
            }
            return mb_strtolower($answer['value']) === $want;
        }
    }
    return true;
}

/*
 * The answers a template's task can be for - each choice of its list and
 * yes-or-no fill-ins: [['value' => 'Name|Choice' (as the form posts it),
 * 'fill_in', 'choice'], ...].
 */
function ticketTemplateWhenChoices(array $fill_ins) {
    $choices = [];
    foreach ($fill_ins as $fill_in) {
        if ($fill_in['type'] === 'list' || $fill_in['type'] === 'yesno') {
            foreach ($fill_in['type'] === 'yesno' ? ['Yes', 'No'] : $fill_in['options'] as $choice) {
                $choices[] = ['value' => $fill_in['name'] . '|' . $choice, 'fill_in' => $fill_in['name'], 'choice' => $choice];
            }
        }
    }
    return $choices;
}

/*
 * The answer a task is for, from its form's "Only when" (Name|Choice) - one
 * of ticketTemplateWhenChoices(), or the one it had ($current) - as
 * ['fill_in', 'value']; null for always.
 */
function ticketTemplateWhenFromPost($posted, array $fill_ins, $current = null) {
    $posted = (string) $posted;
    foreach (ticketTemplateWhenChoices($fill_ins) as $choice) {
        if ($choice['value'] === $posted) {
            return ['fill_in' => $choice['fill_in'], 'value' => $choice['choice']];
        }
    }
    if ($current && $posted === $current['fill_in'] . '|' . $current['value']) {
        return $current;
    }
    return null;
}

/** Whether a task's condition is still one of the answers its template offers (ticketTemplateWhenChoices()). */
function ticketTemplateTaskWhenOffered(array $task, array $fill_ins) {
    if (empty($task['when'])) {
        return true;
    }
    foreach (ticketTemplateWhenChoices($fill_ins) as $choice) {
        if (strtolower($choice['fill_in']) === strtolower($task['when']['fill_in']) && mb_strtolower($choice['choice']) === mb_strtolower($task['when']['value'])) {
            return true;
        }
    }
    return false;
}

/** The "Only when" picker for a template's task - Always, or one of the answers it can be for. HTML; '' when the template offers none and the task has none. */
function ticketTemplateWhenSelectHtml(array $fill_ins, $current = null) {
    $choices = ticketTemplateWhenChoices($fill_ins);
    $current_value = $current ? $current['fill_in'] . '|' . $current['value'] : '';
    if (!$choices && !$current) {
        return '';
    }
    $html = '<select class="form-select select2" name="when"><option value="">Always</option>';
    $groups = [];
    foreach ($choices as $choice) {
        $groups[$choice['fill_in']][] = $choice;
    }
    $found = false;
    foreach ($groups as $fill_in => $group) {
        $html .= '<optgroup label="' . escapeHtml($fill_in) . '">';
        foreach ($group as $choice) {
            $selected = $choice['value'] === $current_value;
            $found = $found || $selected;
            $html .= '<option value="' . escapeHtml($choice['value']) . '"' . ($selected ? ' selected' : '') . '>Only when ' . escapeHtml($fill_in) . ' is ' . escapeHtml($choice['choice']) . '</option>';
        }
        $html .= '</optgroup>';
    }
    if ($current && !$found) {
        $html .= '<option value="' . escapeHtml($current_value) . '" selected>Only when ' . escapeHtml($current['fill_in']) . ' is ' . escapeHtml($current['value']) . ' (no longer offered)</option>';
    }
    return $html . '</select>';
}

/** "Only when Needs a laptop is Yes" - a task's condition in words, escaped; '' for none. */
function ticketTemplateTaskWhenText(array $task) {
    if (empty($task['when'])) {
        return '';
    }
    return 'Only when ' . escapeHtml($task['when']['fill_in']) . ' is ' . escapeHtml($task['when']['value']);
}

/** Copy a template's tasks onto a ticket - the ones for these answers (ticketTemplateTaskApplies()). How many. */
function ticketTemplateAddTasks($ticket_id, $ticket_template_id, array $answers = []) {
    global $mysqli;
    $ticket_id = intval($ticket_id);
    $added = 0;
    foreach (ticketTemplateTasks($ticket_template_id) as $task) {
        if (ticketTemplateTaskApplies($task, $answers)) {
            $added++;
            mysqli_query($mysqli, "INSERT INTO tasks SET task_name = '" . escapeSql($task['name']) . "', task_order = $added, task_completion_estimate = " . intval($task['estimate']) . ", task_ticket_id = $ticket_id");
        }
    }
    return $added;
}

/*
 * Rename one of a template's fill-ins - its [Old Name] in the subject,
 * details and tasks, and the tasks that are only for some of its answers,
 * go with it. Its row, if it has one, is the caller's.
 */
function ticketTemplateRenameFillIn($ticket_template_id, $old, $new) {
    global $mysqli;
    $template = ticketTemplateRow($ticket_template_id);
    if (!$template || $old === $new) {
        return;
    }
    $id = intval($template['ticket_template_id']);
    // The new name holds nothing HTML could misread - see ticketFillInName()
    $rename = function ($text) use ($old, $new) {
        return ticketPlaceholderFill($text, [strtolower($old) => "[$new]"]);
    };
    mysqli_query($mysqli, "UPDATE ticket_templates SET ticket_template_subject = '" . escapeSql(mb_substr($rename($template['ticket_template_subject']), 0, 500)) . "',
        ticket_template_details = '" . mysqli_real_escape_string($mysqli, $rename($template['ticket_template_details'])) . "' WHERE ticket_template_id = $id");
    foreach (ticketTemplateTasks($id) as $task) {
        $name = mb_substr($rename($task['name']), 0, 200);
        if ($name !== $task['name']) {
            mysqli_query($mysqli, "UPDATE task_templates SET task_template_name = '" . escapeSql($name) . "' WHERE task_template_id = " . intval($task['id']));
        }
    }
    mysqli_query($mysqli, "UPDATE task_templates SET task_template_when_fill_in = '" . escapeSql($new) . "' WHERE task_template_ticket_template_id = $id AND task_template_when_fill_in = '" . escapeSql($old) . "'");
}

/* ---------------------------------------------------------------- placeholders */

/** What each placeholder becomes on a ticket - name => value; '' for what the ticket doesn't have. Raw. */
function ticketPlaceholderValues($ticket_id) {
    global $mysqli, $session_name;
    $ticket_id = intval($ticket_id);
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ticket_prefix, ticket_number, client_name, contact_name, asset_name, location_name, project_name, assignee.user_name AS assignee_name, creator.user_name AS creator_name
        FROM tickets
        LEFT JOIN clients ON client_id = ticket_client_id AND ticket_client_id > 0
        LEFT JOIN contacts ON contact_id = ticket_contact_id AND ticket_contact_id > 0
        LEFT JOIN assets ON asset_id = ticket_asset_id AND ticket_asset_id > 0
        LEFT JOIN locations ON location_id = ticket_location_id AND ticket_location_id > 0
        LEFT JOIN projects ON project_id = ticket_project_id AND ticket_project_id > 0
        LEFT JOIN users assignee ON assignee.user_id = ticket_assigned_to AND ticket_assigned_to > 0
        LEFT JOIN users creator ON creator.user_id = ticket_created_by AND ticket_created_by > 0
        WHERE ticket_id = $ticket_id"));
    if (!$row) {
        return [];
    }
    $month = intval(date('n'));
    return [
        'client' => (string) ($row['client_name'] ?? ''),
        'contact' => (string) ($row['contact_name'] ?? ''),
        'asset' => (string) ($row['asset_name'] ?? ''),
        'site' => (string) ($row['location_name'] ?? ''),
        'agent' => (string) ($row['assignee_name'] ?: ($row['creator_name'] ?: stripslashes((string) ($session_name ?? '')))),
        'project' => (string) ($row['project_name'] ?? ''),
        'ticket' => ticketReference($row),
        'date' => date('M j, Y'),
        'month' => date('F Y'),
        'quarter' => 'Q' . intval(ceil($month / 3)) . ' ' . date('Y'),
        'year' => date('Y'),
    ];
}

/*
 * $text with the placeholders filled in from $values (lowercase name =>
 * value - ticketPlaceholderValues() and the fill-ins' answers), whatever their
 * case. One with nothing to put in stays as it is. $html: $text is HTML, so
 * the values go in escaped, and a space in a name matches the editor's &nbsp;.
 */
function ticketPlaceholderFill($text, array $values, $html = false) {
    $values = array_filter($values, function ($value) {
        return (string) $value !== '';
    });
    if (!$values) {
        return (string) $text;
    }
    // Words apart by any run of spaces - the editor writes some of them as &nbsp;
    $space = '(?: |&nbsp;|\x{00A0})+';
    $lookup = $patterns = [];
    foreach ($values as $name => $value) {
        $words = preg_split('/ +/', trim(strtolower($name)));
        $lookup[implode(' ', $words)] = (string) $value;
        $patterns[] = implode($space, array_map(function ($word) {
            return preg_quote($word, '/');
        }, $words));
    }
    return preg_replace_callback('/\[(' . implode('|', $patterns) . ')\]/iu', function ($match) use ($lookup, $html, $space) {
        $key = strtolower(preg_replace('/' . $space . '/u', ' ', $match[1]));
        $value = $lookup[$key] ?? '';
        if ($value === '') {
            return $match[0];
        }
        return $html ? escapeHtml($value) : $value;
    }, (string) $text);
}

/*
 * Fill in the placeholders in a ticket's subject, details and task names -
 * the ones from the ticket, and its fill-ins that have been answered. How
 * many of them changed.
 */
function ticketPlaceholdersFill($ticket_id) {
    global $mysqli;
    $ticket_id = intval($ticket_id);
    $values = ticketPlaceholderValues($ticket_id);
    $row = $values ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ticket_subject, ticket_details FROM tickets WHERE ticket_id = $ticket_id")) : null;
    if (!$row) {
        return 0;
    }
    foreach (ticketFillInsStored('ticket', $ticket_id) as $answer) {
        $values[strtolower($answer['name'])] = $values[strtolower($answer['name'])] ?? ticketFillInText($answer);
    }
    $changed = 0;
    $subject = mb_substr(ticketPlaceholderFill($row['ticket_subject'], $values), 0, 500);
    $details = ticketPlaceholderFill($row['ticket_details'], $values, true);
    if ($subject !== $row['ticket_subject'] || $details !== $row['ticket_details']) {
        mysqli_query($mysqli, "UPDATE tickets SET ticket_subject = '" . escapeSql($subject) . "', ticket_details = '" . mysqli_real_escape_string($mysqli, $details) . "' WHERE ticket_id = $ticket_id");
        $changed++;
    }
    $sql = mysqli_query($mysqli, "SELECT task_id, task_name FROM tasks WHERE task_ticket_id = $ticket_id AND task_name LIKE '%[%]%'");
    while ($task = mysqli_fetch_assoc($sql)) {
        $name = mb_substr(ticketPlaceholderFill($task['task_name'], $values), 0, 255);
        if ($name !== $task['task_name']) {
            mysqli_query($mysqli, "UPDATE tasks SET task_name = '" . escapeSql($name) . "' WHERE task_id = " . intval($task['task_id']));
            $changed++;
        }
    }
    return $changed;
}

/*
 * A ticket was just raised from a template: note which (on the ticket and in
 * its history), keep the answers to its fill-ins ($answers, from
 * ticketFillInAnswers() - null when none were asked, so they are kept
 * unanswered, to be answered on the ticket) and fill in its placeholders.
 * $defaults also gives it the template's tags and due time when it has none -
 * for the paths that have no fields of their own for those (the New Tickets
 * windows, project templates). $tasks copies the template's tasks too, the
 * ones for those answers. The template, or null.
 */
function ticketTemplateFinish($ticket_id, $ticket_template_id, $defaults = false, $tasks = false, $answers = null) {
    global $mysqli, $session_name;
    $ticket_id = intval($ticket_id);
    $template = ticketTemplateRow($ticket_template_id);
    if (!$ticket_id || !$template) {
        return null;
    }
    $ticket_template_id = intval($template['ticket_template_id']);
    mysqli_query($mysqli, "UPDATE tickets SET ticket_template_id = $ticket_template_id WHERE ticket_id = $ticket_id");
    if ($answers === null) {
        $answers = ticketFillInAnswers(ticketTemplateFillInsFor($ticket_template_id), [], $error, false) ?? [];
    }
    if ($answers) {
        ticketFillInsStore('ticket', $ticket_id, $answers);
    }
    if ($tasks) {
        ticketTemplateAddTasks($ticket_id, $ticket_template_id, $answers);
    }
    if ($defaults) {
        $tag_ids = ticketTemplateTagIds($ticket_template_id);
        if ($tag_ids && !tagIdsForRecord('ticket', $ticket_id)) {
            tagSetForRecord('ticket', $ticket_id, array_map('strval', $tag_ids));
        }
        $due = ticketTemplateDueAt($template['ticket_template_due_hours']);
        if ($due) {
            mysqli_query($mysqli, "UPDATE tickets SET ticket_due_at = '$due' WHERE ticket_id = $ticket_id AND ticket_due_at IS NULL");
        }
    }
    ticketPlaceholdersFill($ticket_id);
    logTicketHistory($ticket_id, escapeSql(stripslashes((string) ($session_name ?? 'ITFlow')) . ' raised the ticket from template ' . $template['ticket_template_name']));
    return $template;
}
