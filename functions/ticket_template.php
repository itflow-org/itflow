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
 * in brackets, [Employee Name] say, stays for whoever picks it up.
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

/** A template's tasks, in order - [['id', 'name', 'estimate'], ...]. */
function ticketTemplateTasks($ticket_template_id) {
    global $mysqli;
    $tasks = [];
    $sql = mysqli_query($mysqli, "SELECT task_template_id, task_template_name, task_template_completion_estimate FROM task_templates
        WHERE task_template_ticket_template_id = " . intval($ticket_template_id) . " ORDER BY task_template_order ASC, task_template_id ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $tasks[] = ['id' => intval($row['task_template_id']), 'name' => (string) $row['task_template_name'], 'estimate' => intval($row['task_template_completion_estimate'])];
    }
    return $tasks;
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

/** Square-bracket words in some text - [Client], [Employee Name] - each once, as written. */
function ticketTemplatePlaceholdersIn($text) {
    preg_match_all('/\[([A-Za-z][A-Za-z0-9 _\-\/]{0,40})\]/', strip_tags((string) $text), $matches);
    $found = [];
    foreach ($matches[1] as $name) {
        $found[strtolower(trim($name))] = $found[strtolower(trim($name))] ?? trim($name);
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

/** A copy of a template - "Copy of" its name, its tasks and tags - not archived. The new one's id, or 0. */
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
            task_template_completion_estimate = " . intval($task['estimate']) . ", task_template_ticket_template_id = $new_id");
    }
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
 */
function ticketTemplatePickerData(array $row, array $tasks, array $tag_ids) {
    $due = ticketTemplateDueAt($row['ticket_template_due_hours']);
    return [
        'subject' => (string) $row['ticket_template_subject'],
        'details' => (string) $row['ticket_template_details'],
        'tasks' => json_encode(array_map(function ($task) {
            return ['name' => $task['name'], 'estimate' => $task['estimate']];
        }, $tasks)),
        'priority' => (string) ($row['ticket_template_priority'] ?? ''),
        'category' => intval($row['ticket_template_category_id']) ? (string) intval($row['ticket_template_category_id']) : '',
        'billable' => $row['ticket_template_billable'] === null ? '' : (string) intval($row['ticket_template_billable']),
        'assigned' => intval($row['ticket_template_assigned_to']) ? (string) intval($row['ticket_template_assigned_to']) : '',
        'due' => $due ? date('Y-m-d\TH:i', strtotime($due)) : '',
        'tags' => $tag_ids ? implode(',', $tag_ids) : '',
    ];
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
 * $text with the placeholders filled in from $values (ticketPlaceholderValues()),
 * whatever their case. One the ticket has nothing for stays as it is. $html:
 * $text is HTML, so the values go in escaped.
 */
function ticketPlaceholderFill($text, array $values, $html = false) {
    $names = implode('|', array_map('strtolower', array_keys(TICKET_PLACEHOLDERS)));
    return preg_replace_callback('/\[(' . $names . ')\]/i', function ($match) use ($values, $html) {
        $value = $values[strtolower($match[1])] ?? '';
        if ($value === '') {
            return $match[0];
        }
        return $html ? escapeHtml($value) : $value;
    }, (string) $text);
}

/** Fill in the placeholders in a ticket's subject, details and task names. How many of them changed. */
function ticketPlaceholdersFill($ticket_id) {
    global $mysqli;
    $ticket_id = intval($ticket_id);
    $values = ticketPlaceholderValues($ticket_id);
    $row = $values ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ticket_subject, ticket_details FROM tickets WHERE ticket_id = $ticket_id")) : null;
    if (!$row) {
        return 0;
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
 * its history) and fill in its placeholders. $defaults also gives it the
 * template's tags and due time when it has none - for the paths that have no
 * fields of their own for those (the New Tickets windows, project templates).
 * $tasks copies the template's tasks too. The template, or null.
 */
function ticketTemplateFinish($ticket_id, $ticket_template_id, $defaults = false, $tasks = false) {
    global $mysqli, $session_name;
    $ticket_id = intval($ticket_id);
    $template = ticketTemplateRow($ticket_template_id);
    if (!$ticket_id || !$template) {
        return null;
    }
    $ticket_template_id = intval($template['ticket_template_id']);
    mysqli_query($mysqli, "UPDATE tickets SET ticket_template_id = $ticket_template_id WHERE ticket_id = $ticket_id");
    if ($tasks) {
        addTasksFromTicketTemplate($ticket_id, $ticket_template_id);
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
