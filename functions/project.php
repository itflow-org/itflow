<?php

/*
 * Projects - work bigger than one ticket: onboarding a client, a server
 * refresh, an office move, a laptop rollout. A project holds tickets (each
 * with its tasks and its time); its progress is how many of those are done
 * and its time is the time logged on them, against the hours sold for it
 * when there is a budget. A project with no client is internal work.
 *
 * Open means neither completed nor archived. A completed project can be
 * reopened, an archived one restored.
 */

/** "Due soon" - due within this many days. */
const PROJECT_SOON_DAYS = 14;

/** "Stalled" - an open project whose tickets have not moved for this many days. */
const PROJECT_STALLED_DAYS = 14;

/** SQL: neither completed nor archived. On projects. */
const PROJECT_OPEN_SQL = "(projects.project_completed_at IS NULL AND projects.project_archived_at IS NULL)";

/** May this agent do this with projects? They go with tickets: 1 is to see them, 2 to work them, 3 to delete them. */
function projectCan($level) {
    return lookupUserPermission('module_support') >= $level;
}

/** One project by id, with its client, manager and client contact - raw, or null. */
function projectRow($project_id) {
    global $mysqli;
    $project_id = intval($project_id);
    if (!$project_id) {
        return null;
    }
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT projects.*, client_name, user_name, user_avatar,
            contact_name, contact_title, contact_email, contact_phone, contact_phone_country_code, contact_extension,
            contact_mobile, contact_mobile_country_code, contact_photo, contact_archived_at
        FROM projects
        LEFT JOIN clients ON client_id = project_client_id
        LEFT JOIN users ON user_id = project_manager
        LEFT JOIN contacts ON contact_id = project_contact_id AND project_contact_id > 0
        WHERE project_id = $project_id LIMIT 1"));
    return $row ?: null;
}

/**
 * For post handlers: the project a request names, after the module and
 * client checks (a project with no client is internal work any agent may
 * reach) - or back with an error if it is gone. Sets the global $client_id.
 */
function projectForRequest($project_id, $level) {
    global $client_id;
    enforceUserPermission('module_support', $level);
    $row = projectRow($project_id);
    if (!$row) {
        flashAlert('That project no longer exists.', 'error');
        redirect();
    }
    $client_id = intval($row['project_client_id']);
    if ($client_id) {
        enforceClientAccess();
    }
    return $row;
}

/**
 * For modals: the project a modal is opened for, after the same checks - or
 * the modal's error, and nothing more. $open_only refuses one that is
 * completed or archived. Sets the global $client_id.
 */
function projectForModal($project_id, $level = 2, $open_only = false) {
    global $client_id;
    enforceUserPermission('module_support', $level);
    $row = projectRow($project_id);
    if (!$row) {
        echo json_encode(['error' => 'That project no longer exists.']);
        exit;
    }
    $client_id = intval($row['project_client_id']);
    if ($client_id) {
        enforceClientAccess();
    }
    if ($open_only && !projectIsOpen($row)) {
        echo json_encode(['error' => 'Project ' . projectReference($row) . ' is ' . (!empty($row['project_archived_at']) ? 'archived' : 'completed') . ' - reopen it first.']);
        exit;
    }
    return $row;
}

/** For post handlers: the projects a bulk action names (project_ids[]) that this agent may act on - [id => row]. */
function projectsForBulk($level) {
    global $client_id;
    enforceUserPermission('module_support', $level);
    $rows = [];
    foreach (array_unique(array_map('intval', (array) ($_POST['project_ids'] ?? []))) as $project_id) {
        $row = projectRow($project_id);
        if (!$row) {
            continue;
        }
        $client_id = intval($row['project_client_id']);
        if ($client_id) {
            enforceClientAccess();
        }
        $rows[$project_id] = $row;
    }
    return $rows;
}

/** Its reference - PRJ-12. */
function projectReference($row) {
    return ($row['project_prefix'] ?? '') . intval($row['project_number'] ?? 0);
}

/** Still being worked - neither completed nor archived? */
function projectIsOpen($row) {
    return empty($row['project_completed_at']) && empty($row['project_archived_at']);
}

/** Where the project page is - inside its client when it has one. */
function projectUrl($project_id, $client_id = 0) {
    $client_id = intval($client_id);
    return 'project.php?' . ($client_id ? "client_id=$client_id&" : '') . 'project_id=' . intval($project_id);
}

/** Where it stands: archived, completed, upcoming (starts after today) or active. */
function projectState($row) {
    if (!empty($row['project_archived_at'])) {
        return 'archived';
    }
    if (!empty($row['project_completed_at'])) {
        return 'completed';
    }
    if (!empty($row['project_start']) && $row['project_start'] > date('Y-m-d')) {
        return 'upcoming';
    }
    return 'active';
}

function projectStatePill($row) {
    switch (projectState($row)) {
        case 'archived':
            return '<span class="badge rounded-pill text-bg-secondary fw-normal">Archived</span>';
        case 'completed':
            return '<span class="badge rounded-pill text-bg-success fw-normal"><i class="fas fa-check me-1"></i>Completed</span>';
        case 'upcoming':
            return '<span class="badge rounded-pill text-bg-info fw-normal" title="Starts ' . escapeHtml(date('M j, Y', strtotime($row['project_start']))) . '"><i class="far fa-calendar me-1"></i>Not started</span>';
    }
    return '<span class="badge rounded-pill text-bg-primary fw-normal">Active</span>';
}

/** Whole days from today to a date: negative is past. */
function projectDaysFromToday($date) {
    $today = new DateTime(date('Y-m-d'));
    $then = new DateTime(substr((string) $date, 0, 10));
    return intval($today->diff($then)->format('%r%a'));
}

/** "today", "tomorrow", "in 3 days", "yesterday", "3 days ago". */
function projectDaysText($days) {
    $days = intval($days);
    if ($days === 0) {
        return 'today';
    }
    if ($days === 1) {
        return 'tomorrow';
    }
    if ($days === -1) {
        return 'yesterday';
    }
    return $days > 0 ? "in $days days" : abs($days) . ' days ago';
}

/**
 * The due date for the list and the page: ['key' => overdue | soon | later
 * | done | none, 'days', 'date', 'late' (completed after its due date)].
 */
function projectDueState($row) {
    $due = $row['project_due'] ?? null;
    if (!empty($row['project_completed_at'])) {
        $late = $due && substr($row['project_completed_at'], 0, 10) > $due;
        return ['key' => 'done', 'date' => $row['project_completed_at'], 'late' => $late,
            'days_late' => $late ? -projectDaysFromToday($due) + projectDaysFromToday($row['project_completed_at']) : 0];
    }
    if (!$due) {
        return ['key' => 'none'];
    }
    $days = projectDaysFromToday($due);
    if ($days < 0) {
        return ['key' => 'overdue', 'days' => $days, 'date' => $due];
    }
    return ['key' => $days <= PROJECT_SOON_DAYS ? 'soon' : 'later', 'days' => $days, 'date' => $due];
}

/** The due date as a pill - '' when there is none. */
function projectDuePill($row) {
    $due = projectDueState($row);
    switch ($due['key']) {
        case 'overdue':
            $late = abs($due['days']);
            return '<span class="badge rounded-pill text-bg-danger" title="Due ' . escapeHtml(date('M j, Y', strtotime($due['date']))) . '"><i class="far fa-calendar-times me-1"></i>' . $late . ' day' . ($late === 1 ? '' : 's') . ' overdue</span>';
        case 'soon':
            return '<span class="badge rounded-pill text-bg-warning" title="Due ' . escapeHtml(date('M j, Y', strtotime($due['date']))) . '"><i class="far fa-calendar me-1"></i>Due ' . projectDaysText($due['days']) . '</span>';
        case 'later':
            return '<span class="badge rounded-pill bg-body-secondary text-body-secondary fw-normal" title="Due ' . projectDaysText($due['days']) . '"><i class="far fa-calendar me-1"></i>Due ' . escapeHtml(date('M j', strtotime($due['date']))) . '</span>';
        case 'done':
            $when = escapeHtml(date('M j, Y', strtotime($due['date'])));
            return $due['late']
                ? '<span class="badge rounded-pill bg-warning-subtle text-warning-emphasis fw-normal" title="Completed ' . $when . ', ' . $due['days_late'] . ' day' . ($due['days_late'] === 1 ? '' : 's') . ' after its due date"><i class="fas fa-check me-1"></i>Done ' . $when . ' (late)</span>'
                : '<span class="badge rounded-pill bg-success-subtle text-success-emphasis fw-normal" title="Completed ' . $when . '"><i class="fas fa-check me-1"></i>Done ' . $when . '</span>';
    }
    return '';
}

/** Hours for people: "45m", "3h", "12.5h". */
function projectHoursText($seconds) {
    $seconds = max(0, intval($seconds));
    if ($seconds < 3600) {
        return round($seconds / 60) . 'm';
    }
    return rtrim(rtrim(number_format($seconds / 3600, 1, '.', ''), '0'), '.') . 'h';
}

/** A budget in hours as posted - a positive number up to 999999, or null for none. */
function projectBudgetHours($input) {
    $input = str_replace(',', '.', trim((string) $input));
    if ($input === '' || !is_numeric($input) || floatval($input) <= 0) {
        return null;
    }
    return min(999999.0, round(floatval($input), 2));
}

/** A date as posted (YYYY-MM-DD) as SQL - quoted, or NULL. */
function projectDateSql($input) {
    $input = trim((string) $input);
    $date = DateTime::createFromFormat('!Y-m-d', $input);
    return ($date && $date->format('Y-m-d') === $input) ? "'$input'" : 'NULL';
}

/*
 * ---------- progress, time and activity ----------
 */

/** SQL: when its tickets last moved - the latest ticket update, or NULL with none. Correlated on projects. */
function projectLastActivitySql() {
    return "(SELECT MAX(COALESCE(pt.ticket_updated_at, pt.ticket_created_at)) FROM tickets pt WHERE pt.ticket_project_id = projects.project_id AND pt.ticket_archived_at IS NULL)";
}

/** SQL: seconds logged on its tickets. Correlated on projects. */
function projectSecondsSql() {
    return "(SELECT COALESCE(SUM(TIME_TO_SEC(pr.ticket_reply_time_worked)), 0) FROM ticket_replies pr JOIN tickets pt ON pt.ticket_id = pr.ticket_reply_ticket_id
        WHERE pt.ticket_project_id = projects.project_id AND pr.ticket_reply_archived_at IS NULL)";
}

/**
 * The list's views - [key => label, icon, title, where, alarm]. Over
 * projects; 'mine' needs the agent. Over budget only shows when a budget
 * is set on any project.
 */
function projectViews($with_budget = true) {
    global $session_user_id;
    $open = PROJECT_OPEN_SQL;
    $started = "(projects.project_start IS NULL OR projects.project_start <= CURDATE())";
    $has_tickets = "EXISTS (SELECT 1 FROM tickets rt WHERE rt.ticket_project_id = projects.project_id AND rt.ticket_archived_at IS NULL)";
    $has_open = "EXISTS (SELECT 1 FROM tickets rt WHERE rt.ticket_project_id = projects.project_id AND rt.ticket_archived_at IS NULL AND rt.ticket_resolved_at IS NULL AND rt.ticket_closed_at IS NULL)";
    $views = [
        '' => ['label' => 'Active', 'icon' => 'fa-project-diagram', 'where' => $open, 'title' => 'Not completed or archived'],
        'mine' => ['label' => 'Mine', 'icon' => 'fa-user-tie', 'where' => "$open AND projects.project_manager = " . intval($session_user_id), 'title' => 'Active projects you manage'],
        'overdue' => ['label' => 'Overdue', 'icon' => 'fa-calendar-times', 'where' => "$open AND projects.project_due < CURDATE()",
            'title' => 'Past their due date', 'alarm' => 'danger'],
        'soon' => ['label' => 'Due soon', 'icon' => 'fa-calendar-day', 'where' => "$open AND projects.project_due BETWEEN CURDATE() AND CURDATE() + INTERVAL " . PROJECT_SOON_DAYS . " DAY",
            'title' => 'Due in the next ' . PROJECT_SOON_DAYS . ' days', 'alarm' => 'warning'],
        'stalled' => ['label' => 'Stalled', 'icon' => 'fa-hourglass-half', 'where' => "$open AND $started AND (NOT $has_tickets OR $has_open)
            AND COALESCE(" . projectLastActivitySql() . ", projects.project_created_at) < NOW() - INTERVAL " . PROJECT_STALLED_DAYS . " DAY",
            'title' => 'Started, and none of their tickets has moved in ' . PROJECT_STALLED_DAYS . ' days', 'alarm' => 'warning'],
        'over_budget' => ['label' => 'Over budget', 'icon' => 'fa-hourglass-end', 'where' => "$open AND projects.project_budget_hours > 0 AND " . projectSecondsSql() . " > projects.project_budget_hours * 3600",
            'title' => 'More time logged than the hours budgeted', 'alarm' => 'danger'],
        'ready' => ['label' => 'Ready to close', 'icon' => 'fa-flag-checkered', 'where' => "$open AND $has_tickets AND NOT $has_open",
            'title' => 'Every ticket resolved or closed - mark them complete', 'alarm' => 'success'],
        'completed' => ['label' => 'Completed', 'icon' => 'fa-check', 'where' => 'projects.project_completed_at IS NOT NULL AND projects.project_archived_at IS NULL'],
        'archived' => ['label' => 'Archived', 'icon' => 'fa-archive', 'where' => 'projects.project_archived_at IS NOT NULL'],
        'all' => ['label' => 'All', 'where' => ''],
    ];
    if (!$with_budget) {
        unset($views['over_budget']);
    }
    return $views;
}

/**
 * The numbers for each of these projects, in four queries - [project id =>
 * tickets, open, done, overdue (open tickets past their due date), tasks,
 * tasks_done, seconds, billable_seconds, unbilled_seconds, last_activity].
 * Over all its tickets, whoever's they are - it is the project's progress.
 */
function projectStats(array $project_ids) {
    global $mysqli;
    $ids = implode(',', array_filter(array_map('intval', $project_ids)));
    $stats = [];
    foreach (array_filter(array_map('intval', $project_ids)) as $id) {
        $stats[$id] = ['tickets' => 0, 'open' => 0, 'done' => 0, 'overdue' => 0, 'tasks' => 0, 'tasks_done' => 0,
            'seconds' => 0, 'billable_seconds' => 0, 'unbilled_seconds' => 0, 'last_activity' => null];
    }
    if ($ids === '') {
        return $stats;
    }

    $sql = mysqli_query($mysqli, "SELECT ticket_project_id, COUNT(*) AS ticket_count,
            SUM(ticket_resolved_at IS NULL AND ticket_closed_at IS NULL) AS open_count,
            SUM(ticket_resolved_at IS NULL AND ticket_closed_at IS NULL AND ticket_due_at IS NOT NULL AND ticket_due_at < NOW()) AS overdue_count,
            MAX(COALESCE(ticket_updated_at, ticket_created_at)) AS last_activity
        FROM tickets WHERE ticket_project_id IN ($ids) AND ticket_archived_at IS NULL GROUP BY ticket_project_id");
    while ($row = mysqli_fetch_assoc($sql)) {
        $id = intval($row['ticket_project_id']);
        $stats[$id]['tickets'] = intval($row['ticket_count']);
        $stats[$id]['open'] = intval($row['open_count']);
        $stats[$id]['done'] = intval($row['ticket_count']) - intval($row['open_count']);
        $stats[$id]['overdue'] = intval($row['overdue_count']);
        $stats[$id]['last_activity'] = $row['last_activity'];
    }

    $sql = mysqli_query($mysqli, "SELECT ticket_project_id, COUNT(task_id) AS task_count, SUM(task_completed_at IS NOT NULL) AS done_count
        FROM tasks JOIN tickets ON ticket_id = task_ticket_id
        WHERE ticket_project_id IN ($ids) AND ticket_archived_at IS NULL GROUP BY ticket_project_id");
    while ($row = mysqli_fetch_assoc($sql)) {
        $id = intval($row['ticket_project_id']);
        $stats[$id]['tasks'] = intval($row['task_count']);
        $stats[$id]['tasks_done'] = intval($row['done_count']);
    }

    $sql = mysqli_query($mysqli, "SELECT ticket_project_id, SUM(TIME_TO_SEC(ticket_reply_time_worked)) AS seconds,
            SUM(IF(ticket_billable = 1, TIME_TO_SEC(ticket_reply_time_worked), 0)) AS billable_seconds,
            SUM(IF(ticket_billable = 1 AND ticket_invoice_id = 0, TIME_TO_SEC(ticket_reply_time_worked), 0)) AS unbilled_seconds
        FROM ticket_replies JOIN tickets ON ticket_id = ticket_reply_ticket_id
        WHERE ticket_project_id IN ($ids) AND ticket_reply_archived_at IS NULL GROUP BY ticket_project_id");
    while ($row = mysqli_fetch_assoc($sql)) {
        $id = intval($row['ticket_project_id']);
        $stats[$id]['seconds'] = intval($row['seconds']);
        $stats[$id]['billable_seconds'] = intval($row['billable_seconds']);
        $stats[$id]['unbilled_seconds'] = intval($row['unbilled_seconds']);
    }

    return $stats;
}

/**
 * What wants a look, worst first: overdue, over budget, ready to close
 * (every ticket done), stalled, due soon. Each [key, tone, text].
 */
function projectFlags($row, $stats) {
    if (!projectIsOpen($row)) {
        return [];
    }
    $flags = [];
    $due = projectDueState($row);
    if ($due['key'] === 'overdue') {
        $late = abs($due['days']);
        $flags[] = ['key' => 'overdue', 'tone' => 'danger', 'text' => "$late day" . ($late === 1 ? '' : 's') . ' past its due date'];
    }
    $budget = floatval($row['project_budget_hours'] ?? 0);
    if ($budget > 0 && $stats['seconds'] > $budget * 3600) {
        $flags[] = ['key' => 'over_budget', 'tone' => 'danger', 'text' => projectHoursText($stats['seconds'] - $budget * 3600) . ' over its ' . projectHoursText($budget * 3600) . ' budget'];
    }
    if ($stats['tickets'] && !$stats['open']) {
        $flags[] = ['key' => 'ready', 'tone' => 'success', 'text' => 'Every ticket is resolved or closed - ready to mark complete'];
    }
    $started = empty($row['project_start']) || $row['project_start'] <= date('Y-m-d');
    $last = $stats['last_activity'] ?: $row['project_created_at'];
    if ($started && $stats['open'] && strtotime($last) < time() - PROJECT_STALLED_DAYS * 86400) {
        $flags[] = ['key' => 'stalled', 'tone' => 'warning', 'text' => 'No ticket has moved since ' . date('M j', strtotime($last))];
    } elseif ($started && !$stats['tickets'] && strtotime($row['project_created_at']) < time() - PROJECT_STALLED_DAYS * 86400) {
        $flags[] = ['key' => 'stalled', 'tone' => 'warning', 'text' => 'No tickets yet, ' . PROJECT_STALLED_DAYS . '+ days in'];
    }
    if ($due['key'] === 'soon') {
        $flags[] = ['key' => 'soon', 'tone' => 'warning', 'text' => 'Due ' . projectDaysText($due['days'])];
    }
    return $flags;
}

/** How far along it is - a bar of tickets done, and tasks under it. */
function projectProgressHtml($stats, $compact = false) {
    if (!$stats['tickets']) {
        return '<span class="small text-body-secondary">No tickets yet</span>';
    }
    $percent = round($stats['done'] / $stats['tickets'] * 100);
    $tone = $stats['done'] === $stats['tickets'] ? 'bg-success' : 'bg-primary';
    $html = '<div class="itflow-project-progress" title="' . "{$stats['done']} of {$stats['tickets']} tickets resolved or closed" . '">'
        . '<div class="progress" role="progressbar" aria-valuenow="' . $percent . '" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar ' . $tone . '" style="width: ' . $percent . '%"></div></div>'
        . '<span class="small text-nowrap">' . $stats['done'] . '/' . $stats['tickets'] . ($compact ? '' : ' <span class="text-body-secondary">tickets</span>') . '</span></div>';
    if ($stats['tasks']) {
        $html .= '<div class="small text-body-secondary text-nowrap mt-1" title="Tasks done on its tickets"><i class="fas fa-tasks me-1"></i>' . $stats['tasks_done'] . '/' . $stats['tasks'] . ' tasks</div>';
    }
    return $html;
}

/** Time logged, against the budget when there is one. */
function projectTimeHtml($row, $stats) {
    $budget = floatval($row['project_budget_hours'] ?? 0);
    $worked = projectHoursText($stats['seconds']);
    if ($budget <= 0) {
        return $stats['seconds'] ? '<span class="text-nowrap">' . $worked . '</span>' : '<span class="text-body-secondary">-</span>';
    }
    $used = $stats['seconds'] / ($budget * 3600);
    $percent = min(100, round($used * 100));
    $tone = $used > 1 ? 'bg-danger' : ($used >= .8 ? 'bg-warning' : 'bg-info');
    return '<div class="itflow-project-progress" title="' . $worked . ' logged of ' . projectHoursText($budget * 3600) . ' budgeted (' . round($used * 100) . '%)">'
        . '<div class="progress" role="progressbar" aria-valuenow="' . $percent . '" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar ' . $tone . '" style="width: ' . $percent . '%"></div></div>'
        . '<span class="small text-nowrap ' . ($used > 1 ? 'text-danger-emphasis fw-semibold' : '') . '">' . $worked . ' <span class="text-body-secondary">/ ' . projectHoursText($budget * 3600) . '</span></span></div>';
}

/**
 * Who has worked on it: the agents who logged time or wrote on its tickets,
 * and those with its open tickets - [user id => user_name, user_avatar,
 * seconds, open (tickets of its they have open)]. Most time first.
 */
function projectTeam($project_id) {
    global $mysqli;
    $project_id = intval($project_id);
    $team = [];
    $sql = mysqli_query($mysqli, "SELECT user_id, user_name, user_avatar, SUM(TIME_TO_SEC(ticket_reply_time_worked)) AS seconds
        FROM ticket_replies
        JOIN tickets ON ticket_id = ticket_reply_ticket_id
        JOIN users ON user_id = ticket_reply_by AND ticket_reply_type <> 'Client'
        WHERE ticket_project_id = $project_id AND ticket_reply_archived_at IS NULL AND user_type = 1
        GROUP BY user_id");
    while ($row = mysqli_fetch_assoc($sql)) {
        $team[intval($row['user_id'])] = ['user_name' => $row['user_name'], 'user_avatar' => $row['user_avatar'], 'seconds' => intval($row['seconds']), 'open' => 0];
    }
    $sql = mysqli_query($mysqli, "SELECT user_id, user_name, user_avatar, COUNT(*) AS open_count
        FROM tickets JOIN users ON user_id = ticket_assigned_to
        WHERE ticket_project_id = $project_id AND ticket_archived_at IS NULL AND ticket_resolved_at IS NULL AND ticket_closed_at IS NULL
        GROUP BY user_id");
    while ($row = mysqli_fetch_assoc($sql)) {
        $id = intval($row['user_id']);
        $team[$id] = ($team[$id] ?? ['user_name' => $row['user_name'], 'user_avatar' => $row['user_avatar'], 'seconds' => 0, 'open' => 0]);
        $team[$id]['open'] = intval($row['open_count']);
    }
    uasort($team, function ($a, $b) {
        return [$b['seconds'], $b['open']] <=> [$a['seconds'], $a['open']];
    });
    return $team;
}

/**
 * The latest replies and notes across its tickets - the ones this agent may
 * reach - newest first, each with its ticket and who wrote it.
 */
function projectActivity($project_id, $limit = 20) {
    global $mysqli;
    $project_id = intval($project_id);
    $sql = mysqli_query($mysqli, "SELECT ticket_reply_id, ticket_reply, ticket_reply_type, ticket_reply_time_worked, ticket_reply_created_at,
            ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_client_id, ticket_resolved_at, ticket_closed_at,
            user_name, user_avatar, contact_name
        FROM ticket_replies
        JOIN tickets ON ticket_id = ticket_reply_ticket_id
        LEFT JOIN users ON ticket_reply_type <> 'Client' AND user_id = ticket_reply_by
        LEFT JOIN contacts ON ticket_reply_type = 'Client' AND contact_id = ticket_reply_by
        WHERE ticket_project_id = $project_id AND ticket_reply_archived_at IS NULL AND ticket_archived_at IS NULL " . clientScopeSql('ticket_client_id') . "
        ORDER BY ticket_reply_id DESC LIMIT " . intval($limit));
    $rows = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[] = $row;
    }
    return $rows;
}

/** The first words of a reply, as plain text. */
function projectReplySnippet($html, $length = 180) {
    $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</div>'], ' ', (string) $html)), ENT_QUOTES, 'UTF-8')));
    return mb_strlen($text) > $length ? mb_substr($text, 0, $length - 1) . '…' : $text;
}

/*
 * ---------- changing projects ----------
 */

/** The next project number, taken atomically. */
function projectNextNumber() {
    global $mysqli;
    mysqli_query($mysqli, "UPDATE settings SET config_project_next_number = LAST_INSERT_ID(config_project_next_number), config_project_next_number = config_project_next_number + 1 WHERE company_id = 1");
    return intval(mysqli_insert_id($mysqli));
}

/** A client contact a project of this client may name, else 0. Internal projects have none. */
function projectContactId($contact_id, $client_id) {
    return intval($client_id) ? assetClientOwnedId('contact', $contact_id, $client_id) : 0;
}

/**
 * Raise the tickets of a project template on a project, in the template's
 * order, each set up as its ticket template says (category, priority,
 * billing, agent, due time, tags) with the template's tasks. They go to the
 * project's client contact, and to its manager when $assign_to is given (an
 * agent id) - else to the template's agent. The client is not emailed - a
 * project's tickets are planned work, not a request. [Client], [Project] and
 * the like are filled in (ticketTemplateFinish()). How many tickets were raised.
 */
function projectApplyTemplate($project, $project_template_id, $assign_to = 0) {
    global $mysqli, $session_user_id, $session_name, $config_ticket_prefix, $config_ticket_default_billable;
    $project_template_id = intval($project_template_id);
    $project_id = intval($project['project_id']);
    $client_id = intval($project['project_client_id']);
    $contact_id = projectContactId($project['project_contact_id'] ?? 0, $client_id);
    $assign_to = ticketAgentId($assign_to);
    $prefix = escapeSql($config_ticket_prefix);
    $template_name = escapeSql((string) getFieldById('project_templates', $project_template_id, 'project_template_name'));

    $sql = mysqli_query($mysqli, "SELECT project_template_ticket_templates.ticket_template_id
        FROM project_template_ticket_templates
        JOIN ticket_templates ON ticket_templates.ticket_template_id = project_template_ticket_templates.ticket_template_id
        WHERE project_template_ticket_templates.project_template_id = $project_template_id
        ORDER BY ticket_template_order ASC, ticket_template_name ASC");
    $raised = 0;
    while ($link = mysqli_fetch_row($sql)) {
        $row = ticketTemplateRow($link[0]);
        $subject = trim((string) $row['ticket_template_subject']) !== '' ? $row['ticket_template_subject'] : $row['ticket_template_name'];
        $subject = escapeSql(mb_substr($subject, 0, 500));
        $details = mysqli_real_escape_string($mysqli, (string) $row['ticket_template_details']);
        $priority = escapeSql(ticketPriority($row['ticket_template_priority'] ?? ''));
        $category_id = ticketCategoryId($row['ticket_template_category_id']);
        $billable = $row['ticket_template_billable'] !== null ? intval($row['ticket_template_billable']) : (intval($config_ticket_default_billable) === 1 ? 1 : 0);
        $agent = $assign_to ?: ticketAgentId($row['ticket_template_assigned_to']);
        $status = $agent ? TICKET_STATUS_OPEN : TICKET_STATUS_NEW;
        mysqli_query($mysqli, "UPDATE settings SET config_ticket_next_number = LAST_INSERT_ID(config_ticket_next_number), config_ticket_next_number = config_ticket_next_number + 1 WHERE company_id = 1");
        $ticket_number = intval(mysqli_insert_id($mysqli));
        $url_key = randomString(32);
        mysqli_query($mysqli, "INSERT INTO tickets SET ticket_prefix = '$prefix', ticket_number = $ticket_number, ticket_source = 'Project', ticket_category = $category_id,
            ticket_subject = '$subject', ticket_details = '$details', ticket_priority = '$priority', ticket_billable = $billable, ticket_status = $status,
            ticket_created_by = " . intval($session_user_id) . ", ticket_assigned_to = $agent, ticket_contact_id = $contact_id, ticket_url_key = '$url_key',
            ticket_client_id = $client_id, ticket_invoice_id = 0, ticket_project_id = $project_id");
        $ticket_id = mysqli_insert_id($mysqli);
        applyTicketSla($ticket_id);
        ticketTemplateFinish($ticket_id, $row['ticket_template_id'], true, true);
        logTicketHistory($ticket_id, "$session_name raised the ticket from project template $template_name");
        triggerCustomAction('ticket_create', $ticket_id);
        $raised++;
    }
    return $raised;
}

/**
 * Put a ticket on a project, when it may go there: the agent may reach it,
 * and it is the project's client's (an internal project takes any). '' when
 * done (or it was on it already), else why not.
 */
function projectAddTicket($project, $ticket) {
    global $mysqli, $session_name;
    $ticket_id = intval($ticket['ticket_id']);
    $ticket_client_id = intval($ticket['ticket_client_id']);
    $project_client_id = intval($project['project_client_id']);
    if ($ticket_client_id && !hasClientAccess($ticket_client_id)) {
        return 'not yours to reach';
    }
    if ($project_client_id && $ticket_client_id !== $project_client_id) {
        return "another client's";
    }
    if (intval($ticket['ticket_project_id']) === intval($project['project_id'])) {
        return '';
    }
    mysqli_query($mysqli, "UPDATE tickets SET ticket_project_id = " . intval($project['project_id']) . ", ticket_updated_at = ticket_updated_at WHERE ticket_id = $ticket_id");
    logTicketHistory($ticket_id, "$session_name added the ticket to project " . escapeSql(projectReference($project) . ' - ' . $project['project_name']));
    return '';
}

/** Take a ticket off its project. */
function projectRemoveTicket($ticket) {
    global $mysqli, $session_name;
    $ticket_id = intval($ticket['ticket_id']);
    mysqli_query($mysqli, "UPDATE tickets SET ticket_project_id = 0, ticket_updated_at = ticket_updated_at WHERE ticket_id = $ticket_id");
    logTicketHistory($ticket_id, "$session_name took the ticket out of its project");
}

/** Mark it complete - only once none of its tickets is open. '' when done, else why not. */
function projectComplete($row) {
    global $mysqli, $session_name;
    $project_id = intval($row['project_id']);
    if (!projectIsOpen($row)) {
        return 'it is not open';
    }
    $open = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM tickets WHERE ticket_project_id = $project_id AND ticket_archived_at IS NULL AND ticket_resolved_at IS NULL AND ticket_closed_at IS NULL"))[0]);
    if ($open) {
        return "$open of its tickets " . ($open === 1 ? 'is' : 'are') . ' still open';
    }
    mysqli_query($mysqli, "UPDATE projects SET project_completed_at = NOW() WHERE project_id = $project_id");
    logAudit("Project", "Close", "$session_name completed project " . escapeSql(projectReference($row) . ' - ' . $row['project_name']), intval($row['project_client_id']), $project_id);
    return '';
}

/** Bring a completed (not archived) project back to active. */
function projectReopen($row) {
    global $mysqli, $session_name;
    $project_id = intval($row['project_id']);
    mysqli_query($mysqli, "UPDATE projects SET project_completed_at = NULL WHERE project_id = $project_id");
    logAudit("Project", "Reopen", "$session_name reopened project " . escapeSql(projectReference($row) . ' - ' . $row['project_name']), intval($row['project_client_id']), $project_id);
}

function projectArchive($row) {
    global $mysqli, $session_name;
    $project_id = intval($row['project_id']);
    mysqli_query($mysqli, "UPDATE projects SET project_archived_at = NOW() WHERE project_id = $project_id");
    logAudit("Project", "Archive", "$session_name archived project " . escapeSql(projectReference($row) . ' - ' . $row['project_name']), intval($row['project_client_id']), $project_id);
}

function projectRestore($row) {
    global $mysqli, $session_name;
    $project_id = intval($row['project_id']);
    mysqli_query($mysqli, "UPDATE projects SET project_archived_at = NULL WHERE project_id = $project_id");
    logAudit("Project", "Restore", "$session_name restored project " . escapeSql(projectReference($row) . ' - ' . $row['project_name']), intval($row['project_client_id']), $project_id);
}

/** Delete it for good. Its tickets stay - just no longer part of a project. */
function projectDelete($row) {
    global $mysqli, $session_name;
    $project_id = intval($row['project_id']);
    $name = escapeSql(projectReference($row) . ' - ' . $row['project_name']);
    $sql = mysqli_query($mysqli, "SELECT ticket_id FROM tickets WHERE ticket_project_id = $project_id");
    while ($ticket = mysqli_fetch_row($sql)) {
        logTicketHistory(intval($ticket[0]), "$session_name deleted project $name, which the ticket was part of");
    }
    mysqli_query($mysqli, "UPDATE tickets SET ticket_project_id = 0, ticket_updated_at = ticket_updated_at WHERE ticket_project_id = $project_id");
    mysqli_query($mysqli, "DELETE FROM projects WHERE project_id = $project_id");
    logAudit("Project", "Delete", "$session_name deleted project $name", intval($row['project_client_id']), $project_id);
}

/** Tell the new manager - an app notification linking to the project. Not themselves, not nobody. */
function projectNotifyManager($row, $manager_id) {
    global $mysqli, $session_user_id, $session_name;
    $manager_id = intval($manager_id);
    if (!$manager_id || $manager_id === intval($session_user_id)) {
        return;
    }
    $client_id = intval($row['project_client_id']);
    $text = escapeSql("$session_name made you the manager of project " . projectReference($row) . ' - ' . $row['project_name']);
    $action = escapeSql('/agent/' . projectUrl($row['project_id'], $client_id));
    mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = 'Project', notification = '$text', notification_action = '$action', notification_client_id = $client_id, notification_user_id = $manager_id");
}
