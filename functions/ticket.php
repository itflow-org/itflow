<?php

/*
 * Tickets - loading one, checking what it may link to, drawing it wherever
 * another page lists tickets, deleting it for good, and the recurring
 * schedules that raise them.
 *
 * A ticket belongs to a client (or to none, for internal work). What it
 * links to - its contact, asset and other assets, site, vendor and project -
 * must be that client's own; ticketClientOwnedId() is the one check, used by
 * the handlers, the API and the cron alike. The change functions for
 * priority, assignee, status and billing are in functions/ticket_fields.php.
 */

/** The built-in statuses (ticket_statuses rows 1-5) - the system sets New and Closed; Resolve sets Resolved. */
const TICKET_STATUS_NEW = 1;
const TICKET_STATUS_OPEN = 2;
const TICKET_STATUS_ON_HOLD = 3;
const TICKET_STATUS_RESOLVED = 4;
const TICKET_STATUS_CLOSED = 5;

/** How often a recurring ticket can run - what is stored, and how far one run is from the next. */
const RECURRING_TICKET_FREQUENCIES = [
    'Three Days' => ['days' => 3],
    'Weekly' => ['days' => 7],
    'Biweekly' => ['days' => 14],
    'Monthly' => ['months' => 1],
    'Quarterly' => ['months' => 3],
    'Biannually' => ['months' => 6],
    'Annually' => ['months' => 12],
];

/** May this agent do this with tickets? 1 is to see them, 2 to work them, 3 to delete them. */
function ticketCan($level) {
    return lookupUserPermission('module_support') >= $level;
}

/** One ticket by id, with its client, contact, status and agent - raw, or null. */
function ticketRow($ticket_id) {
    global $mysqli;
    $ticket_id = intval($ticket_id);
    if (!$ticket_id) {
        return null;
    }
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT tickets.*, client_name, contact_name, contact_email,
            ticket_status_name, ticket_status_color, user_name, user_avatar
        FROM tickets
        LEFT JOIN clients ON client_id = ticket_client_id
        LEFT JOIN contacts ON contact_id = ticket_contact_id
        LEFT JOIN ticket_statuses ON ticket_status_id = ticket_status
        LEFT JOIN users ON user_id = ticket_assigned_to
        WHERE ticket_id = $ticket_id LIMIT 1"));
    return $row ?: null;
}

/**
 * For post handlers: the ticket a request names, after the module and client
 * checks (a ticket with no client is internal work any agent may reach) - or
 * back with an error if it is gone. Sets the global $client_id.
 */
function ticketForRequest($ticket_id, $level) {
    global $client_id;
    enforceUserPermission('module_support', $level);
    $row = ticketRow($ticket_id);
    if (!$row) {
        flashAlert('That ticket no longer exists.', 'error');
        redirect();
    }
    $client_id = intval($row['ticket_client_id']);
    if ($client_id) {
        enforceClientAccess();
    }
    return $row;
}

/**
 * For modals: the ticket a modal is opened for, after the module and client
 * checks - or the modal's error, and nothing more. $open_only refuses a
 * closed ticket. Sets the global $client_id.
 */
function ticketForModal($ticket_id, $level = 2, $open_only = false) {
    global $client_id;
    enforceUserPermission('module_support', $level);
    $row = ticketRow($ticket_id);
    if (!$row) {
        echo json_encode(['error' => 'That ticket no longer exists.']);
        exit;
    }
    $client_id = intval($row['ticket_client_id']);
    if ($client_id) {
        enforceClientAccess();
    }
    if ($open_only && !empty($row['ticket_closed_at'])) {
        echo json_encode(['error' => 'Ticket ' . ticketReference($row) . ' is closed.']);
        exit;
    }
    return $row;
}

/** For post handlers: the tickets a bulk action names (ticket_ids[]) that this agent may act on - [id => row]. */
function ticketsForBulk($level) {
    global $client_id;
    enforceUserPermission('module_support', $level);
    $rows = [];
    foreach (array_unique(array_map('intval', (array) ($_POST['ticket_ids'] ?? []))) as $ticket_id) {
        $row = ticketRow($ticket_id);
        if (!$row) {
            continue;
        }
        $client_id = intval($row['ticket_client_id']);
        if ($client_id) {
            enforceClientAccess();
        }
        $rows[$ticket_id] = $row;
    }
    return $rows;
}

/** Its reference - TCK-1234. */
function ticketReference($row) {
    return ($row['ticket_prefix'] ?? '') . intval($row['ticket_number'] ?? 0);
}

/** Is it still being worked - neither resolved nor closed? */
function ticketIsOpen($row) {
    return empty($row['ticket_resolved_at']) && empty($row['ticket_closed_at']);
}

/** Where the ticket page is - inside its client when it has one. */
function ticketUrl($ticket_id, $client_id = 0) {
    $client_id = intval($client_id);
    return 'ticket.php?' . ($client_id ? "client_id=$client_id&" : '') . 'ticket_id=' . intval($ticket_id);
}

/** A ticket wherever a page mentions one: its reference, then its subject, both opening the ticket. 'subject' => false for the reference alone. */
function ticketLink($row, $options = []) {
    $url = escapeHtml(ticketUrl($row['ticket_id'], $row['ticket_client_id'] ?? 0));
    $reference = escapeHtml(ticketReference($row));
    $html = '<a class="font-monospace text-nowrap" href="' . $url . '">' . $reference . '</a>';
    if (($options['subject'] ?? true) && isset($row['ticket_subject'])) {
        $html .= ' <a class="' . (ticketIsOpen($row) ? 'text-body' : 'text-body-secondary') . '" href="' . $url . '">' . escapeHtml($row['ticket_subject']) . '</a>';
    }
    return $html;
}

/**
 * A record a ticket of this client may link to - its id when it is the
 * client's own, else 0. $kind is contact, asset, location, vendor or
 * project. A project may also be one with no client (internal work); a
 * ticket with no client may only link to our own vendors and projects.
 */
function ticketClientOwnedId($kind, $id, $client_id) {
    global $mysqli;
    $id = intval($id);
    $client_id = intval($client_id);
    if (!$id) {
        return 0;
    }
    if ($kind === 'project') {
        $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT project_id FROM projects WHERE project_id = $id AND project_client_id IN (0, $client_id) LIMIT 1"));
        return $row ? $id : 0;
    }
    if (!$client_id && $kind !== 'vendor') {
        return 0;
    }
    return assetClientOwnedId($kind, $id, $client_id);
}

/** The client's own assets out of a list of ids, without the primary one - for "other assets". */
function ticketClientOwnedAssetIds($ids, $client_id, $primary_asset_id = 0) {
    global $mysqli;
    $ids = array_values(array_diff(array_unique(array_filter(array_map('intval', (array) $ids))), [intval($primary_asset_id)]));
    $client_id = intval($client_id);
    if (!$ids || !$client_id) {
        return [];
    }
    $owned = [];
    $sql = mysqli_query($mysqli, "SELECT asset_id FROM assets WHERE asset_client_id = $client_id AND asset_id IN (" . implode(',', $ids) . ")");
    while ($row = mysqli_fetch_row($sql)) {
        $owned[] = intval($row[0]);
    }
    return $owned;
}

/** Replace a ticket's other assets with these (already checked) ids. */
function ticketSetOtherAssets($ticket_id, array $asset_ids) {
    global $mysqli;
    $ticket_id = intval($ticket_id);
    mysqli_query($mysqli, "DELETE FROM ticket_assets WHERE ticket_id = $ticket_id");
    foreach ($asset_ids as $asset_id) {
        mysqli_query($mysqli, "INSERT IGNORE INTO ticket_assets SET ticket_id = $ticket_id, asset_id = " . intval($asset_id));
    }
}

/** An active agent's id, else 0 (unassigned). */
function ticketAgentId($user_id) {
    global $mysqli;
    $user_id = intval($user_id);
    if (!$user_id) {
        return 0;
    }
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT user_id FROM users WHERE user_id = $user_id AND user_type = 1 AND user_status = 1 AND user_archived_at IS NULL LIMIT 1"));
    return $row ? $user_id : 0;
}

/** A ticket category's id, else 0 (not categorised). */
function ticketCategoryId($category_id) {
    global $mysqli;
    $category_id = intval($category_id);
    if (!$category_id) {
        return 0;
    }
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT category_id FROM categories WHERE category_id = $category_id AND category_type = 'Ticket' LIMIT 1"));
    return $row ? $category_id : 0;
}

/** A priority as posted, or Medium when it isn't one. */
function ticketPriority($priority) {
    return in_array($priority, TICKET_PRIORITIES, true) ? $priority : 'Medium';
}

/** A due date posted from a datetime-local field (with or without seconds) as SQL - quoted, or NULL. */
function ticketDueSql($input) {
    $input = trim((string) $input);
    if ($input === '') {
        return 'NULL';
    }
    foreach (['Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i:s', 'Y-m-d H:i'] as $format) {
        $date = DateTime::createFromFormat("!$format", $input);
        if ($date && $date->format($format) === $input) {
            return "'" . $date->format('Y-m-d H:i:s') . "'";
        }
    }
    return 'NULL';
}

/** The watchers posted - valid email addresses only, each once, lower-cased. */
function ticketWatcherEmails($input) {
    $list = is_array($input) ? $input : preg_split('/[\s,;]+/', (string) $input);
    $emails = [];
    foreach ($list as $email) {
        $email = strtolower(trim((string) $email));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $emails[$email] = true;
        }
    }
    return array_keys($emails);
}

/** Add watchers a ticket doesn't have already - the ones added. */
function ticketAddWatchers($ticket_id, array $emails) {
    global $mysqli;
    $ticket_id = intval($ticket_id);
    $added = [];
    foreach ($emails as $email) {
        $email_sql = escapeSql($email);
        if (mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM ticket_watchers WHERE watcher_ticket_id = $ticket_id AND watcher_email = '$email_sql' LIMIT 1"))) {
            continue;
        }
        mysqli_query($mysqli, "INSERT INTO ticket_watchers SET watcher_email = '$email_sql', watcher_ticket_id = $ticket_id");
        $added[] = $email;
    }
    return $added;
}

/**
 * Delete a ticket and everything that is only its: replies, views, watchers,
 * attachments (and their files), tasks and their approvals, history and SLA
 * history. Its other-asset links go with it (ON DELETE CASCADE).
 */
function ticketDeleteForGood($ticket_id) {
    global $mysqli;
    $ticket_id = intval($ticket_id);
    if (!$ticket_id) {
        return;
    }
    mysqli_query($mysqli, "DELETE FROM task_approvals WHERE approval_task_id IN (SELECT task_id FROM tasks WHERE task_ticket_id = $ticket_id)");
    mysqli_query($mysqli, "DELETE FROM tasks WHERE task_ticket_id = $ticket_id");
    mysqli_query($mysqli, "DELETE FROM ticket_replies WHERE ticket_reply_ticket_id = $ticket_id");
    mysqli_query($mysqli, "DELETE FROM ticket_views WHERE view_ticket_id = $ticket_id");
    mysqli_query($mysqli, "DELETE FROM ticket_watchers WHERE watcher_ticket_id = $ticket_id");
    mysqli_query($mysqli, "DELETE FROM ticket_attachments WHERE ticket_attachment_ticket_id = $ticket_id");
    mysqli_query($mysqli, "DELETE FROM ticket_history WHERE ticket_history_ticket_id = $ticket_id");
    mysqli_query($mysqli, "DELETE FROM sla_history WHERE sla_history_ticket_id = $ticket_id");
    mysqli_query($mysqli, "DELETE FROM tickets WHERE ticket_id = $ticket_id");
    // The driving for it still happened - the trips stay, without the ticket
    mysqli_query($mysqli, "UPDATE trips SET trip_ticket_id = 0 WHERE trip_ticket_id = $ticket_id");
    removeDirectory(__DIR__ . "/../uploads/tickets/$ticket_id");
}

/** The company's name and phone, SQL-escaped, for the foot of a ticket email. */
function ticketMailCompany() {
    global $mysqli;
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT company_name, company_phone, company_phone_country_code FROM companies WHERE company_id = 1"));
    return [escapeSql($row['company_name'] ?? ''), escapeSql(formatPhoneNumber($row['company_phone'] ?? '', $row['company_phone_country_code'] ?? ''))];
}

/**
 * Queue an email about a ticket to its contact (when $to_contact and the
 * address is valid) and to every watcher, who get $watcher_note under it.
 * $subject and $body are already SQL-escaped, as addToMailQueue() takes
 * them. Nothing is queued without a mail provider. How many were queued.
 * $to_watchers false is the contact alone.
 */
function ticketMailContactAndWatchers($ticket_id, $subject, $body, $attachments = [], $to_contact = true, $watcher_note = 'YOU ARE A COLLABORATOR ON THIS TICKET', $to_watchers = true) {
    global $mysqli, $config_smtp_provider, $config_ticket_from_email, $config_ticket_from_name;
    if (empty($config_smtp_provider)) {
        return 0;
    }
    $ticket_id = intval($ticket_id);
    $from_email = escapeSql($config_ticket_from_email);
    $from_name = escapeSql($config_ticket_from_name);
    $data = [];
    $sent_to = [];
    if ($to_contact) {
        $contact = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT contact_name, contact_email FROM tickets JOIN contacts ON contact_id = ticket_contact_id WHERE ticket_id = $ticket_id"));
        if ($contact && filter_var($contact['contact_email'], FILTER_VALIDATE_EMAIL)) {
            $data[] = ['from' => $from_email, 'from_name' => $from_name, 'recipient' => escapeSql($contact['contact_email']), 'recipient_name' => escapeSql($contact['contact_name']),
                'subject' => $subject, 'body' => $body, 'attachments' => $attachments];
            $sent_to[strtolower($contact['contact_email'])] = true;
        }
    }
    $watcher_body = $body . "<br><br>----------------------------------------<br>$watcher_note";
    $sql = mysqli_query($mysqli, "SELECT watcher_email FROM ticket_watchers WHERE watcher_ticket_id = $ticket_id" . ($to_watchers ? '' : ' AND 0'));
    while ($row = mysqli_fetch_assoc($sql)) {
        $email = strtolower(trim($row['watcher_email']));
        if (isset($sent_to[$email]) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            continue;
        }
        $sent_to[$email] = true;
        $data[] = ['from' => $from_email, 'from_name' => $from_name, 'recipient' => escapeSql($email), 'recipient_name' => escapeSql($email),
            'subject' => $subject, 'body' => $watcher_body, 'attachments' => $attachments];
    }
    if ($data) {
        addToMailQueue($data);
    }
    return count($data);
}

/** Time worked as posted - HH:MM:SS, or H:MM - normalised to HH:MM:SS; nothing sensible is 00:00:00. */
function ticketTimeWorked($input) {
    if (preg_match('/^\s*(\d{1,3}):([0-5]?\d)(?::([0-5]?\d))?\s*$/', (string) $input, $match)) {
        return sprintf('%02d:%02d:%02d', intval($match[1]), intval($match[2]), intval($match[3] ?? 0));
    }
    return '00:00:00';
}

/** Statuses a reply may leave the ticket in: the active ones but New and Closed (the system sets those), plus the one it has. */
function ticketReplyStatuses($current_status) {
    global $mysqli;
    $ids = [intval($current_status)];
    $sql = mysqli_query($mysqli, "SELECT ticket_status_id FROM ticket_statuses WHERE ticket_status_id NOT IN (1, 5) AND ticket_status_active = 1");
    while ($row = mysqli_fetch_row($sql)) {
        $ids[] = intval($row[0]);
    }
    return array_values(array_unique($ids));
}

/** How many of a ticket's tasks are not done yet. */
function ticketOpenTaskCount($ticket_id) {
    global $mysqli;
    return intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM tasks WHERE task_ticket_id = " . intval($ticket_id) . " AND task_completed_at IS NULL"))[0]);
}

/**
 * Tickets for another page's list - open ones first, then newest - each with
 * its status, agent and is_open. $where is SQL on tickets; $limit 0 is all.
 */
function ticketRows($where, $limit = 0) {
    global $mysqli;
    $limit = intval($limit);
    $sql = mysqli_query($mysqli, "SELECT tickets.ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_priority, ticket_vendor_ticket_number,
            ticket_created_at, ticket_updated_at, ticket_resolved_at, ticket_closed_at, ticket_assigned_to, ticket_client_id,
            user_name, user_avatar, ticket_status_id, ticket_status_name, ticket_status_color
        FROM tickets
        LEFT JOIN users ON ticket_assigned_to = user_id
        LEFT JOIN ticket_statuses ON ticket_status_id = ticket_status
        WHERE ($where) AND ticket_archived_at IS NULL
        ORDER BY (ticket_resolved_at IS NULL AND ticket_closed_at IS NULL) DESC, ticket_number DESC" . ($limit ? " LIMIT $limit" : ''));
    $rows = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $row['is_open'] = ticketIsOpen($row);
        $rows[] = $row;
    }
    return $rows;
}

/** The SQL for "tickets about this asset" - its primary asset or one of its others. */
function ticketAssetSql($asset_id) {
    $asset_id = intval($asset_id);
    return "tickets.ticket_asset_id = $asset_id OR tickets.ticket_id IN (SELECT ticket_assets.ticket_id FROM ticket_assets WHERE ticket_assets.asset_id = $asset_id)";
}

/** How many tickets match, and how many of those are open - [all, open]. */
function ticketCounts($where) {
    global $mysqli;
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*), COALESCE(SUM(ticket_resolved_at IS NULL AND ticket_closed_at IS NULL), 0) FROM tickets WHERE ($where) AND ticket_archived_at IS NULL"));
    return [intval($row[0]), intval($row[1])];
}

/**
 * Tickets as a table - the one used by the asset, contact, site, vendor and
 * project pages and their quick looks. Options: 'compact' (a quick look's
 * table: no header, no priority), 'vendor_ref' (their reference for it, to
 * copy), 'client' (a Client column, for lists across clients).
 */
function ticketTableHtml(array $rows, $options = []) {
    $compact = !empty($options['compact']);
    $vendor_ref = !empty($options['vendor_ref']);
    $with_client = !empty($options['client']);
    $html = '<div class="table-responsive"><table class="table ' . ($compact ? 'table-sm ' : '') . 'table-hover align-middle mb-0 itflow-ticket-table">';
    if (!$compact) {
        $html .= '<thead class="text-nowrap table-light"><tr><th>Ticket</th><th>Subject</th>' . ($vendor_ref ? '<th>Their reference</th>' : '')
            . ($with_client ? '<th>Client</th>' : '') . '<th>Priority</th><th>Status</th><th>Assigned</th><th>Updated</th></tr></thead>';
    }
    $html .= '<tbody>';
    foreach ($rows as $row) {
        $is_open = $row['is_open'] ?? ticketIsOpen($row);
        $url = escapeHtml(ticketUrl($row['ticket_id'], $row['ticket_client_id'] ?? 0));
        $last = $row['ticket_updated_at'] ?: $row['ticket_created_at'];
        $their_ref = escapeHtml($row['ticket_vendor_ticket_number'] ?? '');
        $html .= '<tr class="' . ($is_open ? '' : 'text-body-secondary') . '">'
            . '<td class="text-nowrap"><a class="font-monospace" href="' . $url . '">' . escapeHtml(ticketReference($row)) . '</a></td>'
            . '<td class="' . ($compact ? 'w-100' : 'itflow-ticket-subject') . '"><a class="' . ($is_open ? 'text-body' . ($compact ? '' : ' fw-semibold') : 'text-body-secondary') . '" href="' . $url . '">' . escapeHtml($row['ticket_subject']) . '</a></td>';
        if ($vendor_ref) {
            $html .= '<td class="text-nowrap small">' . ($their_ref !== ''
                ? '<span class="font-monospace">' . $their_ref . '</span><button type="button" class="itflow-copy clipboardjs" title="Copy their reference" data-clipboard-text="' . $their_ref . '"><i class="far fa-copy"></i></button>'
                : '<span class="text-body-secondary">-</span>') . '</td>';
        }
        if ($with_client) {
            $html .= '<td class="small">' . escapeHtml($row['client_name'] ?? '') . '</td>';
        }
        if (!$compact) {
            $html .= '<td class="text-nowrap">' . ticketPriorityHtml($row['ticket_priority'], 'detail') . '</td>';
        }
        $html .= '<td class="text-nowrap">' . ticketStatusHtml($row['ticket_status_name'] ?? '', $row['ticket_status_color'] ?? '', 'detail') . '</td>'
            . '<td class="text-nowrap">' . formatAssignee($row['ticket_assigned_to'], $row['user_name'] ?? '', $row['user_avatar'] ?? '', $is_open) . '</td>'
            . '<td class="text-nowrap small text-body-secondary" title="' . escapeHtml($last) . '">' . timeAgo($last) . '</td>'
            . '</tr>';
    }
    return $html . '</tbody></table></div>';
}

/**
 * The other open tickets for the same contact or the same asset as this one
 * (or as the contact and asset given, for a ticket not yet created) - so a
 * duplicate or a related problem shows up. Each row says which it shares.
 */
function ticketAlsoOpen($contact_id, $asset_id, $exclude_ticket_id = 0, $limit = 6) {
    global $mysqli;
    $contact_id = intval($contact_id);
    $asset_id = intval($asset_id);
    $exclude_ticket_id = intval($exclude_ticket_id);
    $match = [];
    if ($contact_id) {
        $match[] = "tickets.ticket_contact_id = $contact_id";
    }
    if ($asset_id) {
        $match[] = ticketAssetSql($asset_id);
    }
    if (!$match) {
        return [];
    }
    $rows = ticketRows('(' . implode(' OR ', $match) . ") AND tickets.ticket_id <> $exclude_ticket_id AND ticket_resolved_at IS NULL AND ticket_closed_at IS NULL", $limit);
    foreach ($rows as &$row) {
        $shares = [];
        $ticket_id = intval($row['ticket_id']);
        $full = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ticket_contact_id, ticket_asset_id FROM tickets WHERE ticket_id = $ticket_id"));
        if ($contact_id && intval($full['ticket_contact_id']) === $contact_id) {
            $shares[] = 'contact';
        }
        if ($asset_id && (intval($full['ticket_asset_id']) === $asset_id
            || mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM ticket_assets WHERE ticket_id = $ticket_id AND asset_id = $asset_id LIMIT 1")))) {
            $shares[] = 'asset';
        }
        $row['shares'] = $shares;
    }
    return $rows;
}

/** Mark a ticket resolved - status, SLA, history, audit and the custom action. The caller has checked it may be. */
function ticketResolve($row, $how = '') {
    global $mysqli, $session_name;
    $ticket_id = intval($row['ticket_id']);
    if (empty($row['ticket_first_response_at'])) {
        setTicketFirstResponse($ticket_id);
    }
    mysqli_query($mysqli, "UPDATE tickets SET ticket_status = " . TICKET_STATUS_RESOLVED . ", ticket_resolved_at = NOW() WHERE ticket_id = $ticket_id");
    syncTicketSlaClock($ticket_id);
    setTicketResolutionSlaMet($ticket_id);
    logTicketHistory($ticket_id, "$session_name resolved the ticket$how");
    logAudit("Ticket", "Resolved", "$session_name resolved ticket " . escapeSql(ticketReference($row)) . " - " . escapeSql($row['ticket_subject']), intval($row['ticket_client_id']), $ticket_id);
    triggerCustomAction('ticket_resolve', $ticket_id);
}

/** Bring a resolved (not closed) ticket back into a working status. The caller has checked it may be. */
function ticketReopen($row, $status_id = TICKET_STATUS_OPEN, $how = '') {
    global $mysqli, $session_name;
    $ticket_id = intval($row['ticket_id']);
    $status_id = intval($status_id);
    mysqli_query($mysqli, "UPDATE tickets SET ticket_status = $status_id, ticket_resolved_at = NULL WHERE ticket_id = $ticket_id");
    syncTicketSlaClock($ticket_id);
    resetTicketResolutionSla($ticket_id);
    $status_name = escapeSql(getTicketStatusName($status_id));
    logTicketHistory($ticket_id, "$session_name reopened the ticket" . ($status_id === TICKET_STATUS_OPEN ? '' : " to $status_name") . $how);
    logAudit("Ticket", "Reopened", "$session_name reopened ticket " . escapeSql(ticketReference($row)), intval($row['ticket_client_id']), $ticket_id);
    triggerCustomAction('ticket_update', $ticket_id);
}

/** The "your ticket is resolved, pending closure" email - to its contact and watchers. $note is the agent's words, SQL-escaped, or ''. */
function ticketMailResolved($ticket_id, $note = '') {
    global $config_base_url, $config_ticket_from_email;
    $row = ticketRow($ticket_id);
    if (!$row) {
        return 0;
    }
    [$company_name, $company_phone] = ticketMailCompany();
    $ticket_id = intval($ticket_id);
    $contact_name = escapeSql($row['contact_name']);
    $reference = escapeSql(ticketReference($row));
    $subject = escapeSql($row['ticket_subject']);
    $status = escapeSql($row['ticket_status_name']);
    $url_key = escapeSql($row['ticket_url_key']);
    $base_url = escapeSql($config_base_url);
    $from_email = escapeSql($config_ticket_from_email);
    $note_html = $note !== '' ? "<br><br>--------------------------------<br>$note<br>--------------------------------" : '';
    $body = "<i style=\'color: #808080\'>##- Please type your reply above this line -##</i><br><br>Hello $contact_name,<br><br>Your ticket regarding $subject has been marked as solved and is pending closure.$note_html<br><br>If your request/issue is resolved, you can simply ignore this email. If you need further assistance, please reply or <a href=\'https://$base_url/guest/guest_view_ticket.php?ticket_id=$ticket_id&url_key=$url_key\'>re-open</a> to let us know! <br><br>Ticket: $reference<br>Subject: $subject<br>Status: $status<br>Portal: <a href=\'https://$base_url/guest/guest_view_ticket.php?ticket_id=$ticket_id&url_key=$url_key\'>View ticket</a><br><br>--<br>$company_name - Support<br>$from_email<br>$company_phone";
    return ticketMailContactAndWatchers($ticket_id, "Ticket resolved - [$reference] - $subject | (pending closure)", $body);
}

/*
 * ---------- the ticket list and board ----------
 *
 * Whose move it is. A ticket is waiting on us until we reply where the
 * client can see it; after that it waits on them until they write back.
 * Internal notes change neither. A status that pauses the SLA clock (On
 * Hold, or one of yours like "Waiting on vendor") parks it - waiting, too.
 */

/** SQL: still being worked - neither resolved nor closed. On tickets. */
const TICKET_OPEN_SQL = "(tickets.ticket_resolved_at IS NULL AND tickets.ticket_closed_at IS NULL)";

/**
 * SQL for who spoke last where the client can see it: 'Client', 'Public'
 * (us), or NULL when nobody has replied yet. Correlated on tickets, so it
 * goes after the open-ticket test in a condition - MySQL stops at the first
 * false AND, and it then only runs for open tickets.
 */
function ticketLastSpokeSql() {
    return "(SELECT tr.ticket_reply_type FROM ticket_replies tr WHERE tr.ticket_reply_ticket_id = tickets.ticket_id
        AND tr.ticket_reply_archived_at IS NULL AND tr.ticket_reply_type IN ('Public', 'Client')
        ORDER BY tr.ticket_reply_id DESC LIMIT 1)";
}

/** SQL: open, not parked, and the client spoke last (or nobody has yet). Needs tickets joined to ticket_statuses. */
function ticketNeedsReplySql() {
    return TICKET_OPEN_SQL . " AND COALESCE(ticket_statuses.ticket_status_pauses_sla, 0) = 0 AND COALESCE(" . ticketLastSpokeSql() . ", 'Client') = 'Client'";
}

/** SQL: open, and parked or we spoke last. Needs tickets joined to ticket_statuses. */
function ticketWaitingSql() {
    return TICKET_OPEN_SQL . " AND (COALESCE(ticket_statuses.ticket_status_pauses_sla, 0) = 1 OR " . ticketLastSpokeSql() . " = 'Public')";
}

/** SQL: open and past its due date, or its SLA is breached. */
function ticketOverdueSql() {
    return TICKET_OPEN_SQL . " AND ((tickets.ticket_due_at IS NOT NULL AND tickets.ticket_due_at < NOW())
        OR (tickets.ticket_sla_id > 0 AND (tickets.ticket_response_sla_alert_stage = 2 OR tickets.ticket_resolution_sla_alert_stage = 2)))";
}

/** Whose move: 'us', 'client', 'parked', or '' for a ticket that is done. From a row with ticket_last_spoke and ticket_status_pauses_sla. */
function ticketWhoseMove($row) {
    if (!ticketIsOpen($row)) {
        return '';
    }
    if (intval($row['ticket_status_pauses_sla'] ?? 0)) {
        return 'parked';
    }
    return ($row['ticket_last_spoke'] ?? null) === 'Public' ? 'client' : 'us';
}

/**
 * Where its SLA stands: ['key' => breached | at_risk | paused | running |
 * met | missed | none, 'due' => the clock still running's deadline, 'what'].
 * A breach recorded stands even while paused - it already happened.
 */
function ticketSlaState($row) {
    if (!intval($row['ticket_sla_id'] ?? 0)) {
        return ['key' => 'none'];
    }
    $stage = max(intval($row['ticket_response_sla_alert_stage'] ?? 0), intval($row['ticket_resolution_sla_alert_stage'] ?? 0));
    if (!ticketIsOpen($row)) {
        if ($stage === 2 || ($row['ticket_response_sla_met'] ?? null) === '0' || ($row['ticket_resolution_sla_met'] ?? null) === '0') {
            return ['key' => 'missed'];
        }
        $met = intval($row['ticket_response_sla_met'] ?? 0) && (empty($row['ticket_resolution_due_at']) || intval($row['ticket_resolution_sla_met'] ?? 0));
        return ['key' => $met ? 'met' : 'none'];
    }
    $responded = !empty($row['ticket_first_response_at']);
    $state = [
        'due' => $responded ? ($row['ticket_resolution_due_at'] ?? null) : ($row['ticket_response_due_at'] ?? null),
        'what' => $responded ? 'Resolution due' : 'First response due',
    ];
    if ($stage === 2) {
        return ['key' => 'breached'] + $state;
    }
    if (intval($row['ticket_status_pauses_sla'] ?? 0)) {
        return ['key' => 'paused'] + $state;
    }
    if ($stage === 1) {
        return ['key' => 'at_risk'] + $state;
    }
    return ['key' => empty($state['due']) ? 'none' : 'running'] + $state;
}

/** The SLA as a small pill - '' when there is nothing to say. */
function ticketSlaPill($sla) {
    $due = !empty($sla['due']) ? ' title="' . escapeHtml($sla['what'] . ' ' . $sla['due']) . '"' : '';
    switch ($sla['key']) {
        case 'breached':
            return '<span class="badge rounded-pill text-bg-danger"' . $due . '><i class="fas fa-stopwatch me-1"></i>Breached</span>';
        case 'at_risk':
            return '<span class="badge rounded-pill text-bg-warning"' . $due . '><i class="fas fa-stopwatch me-1"></i>' . (!empty($sla['due']) ? timeAgo($sla['due']) : 'At risk') . '</span>';
        case 'paused':
            return '<span class="badge rounded-pill text-bg-secondary" title="The SLA clock is paused in this status"><i class="fas fa-pause me-1"></i>Paused</span>';
        case 'running':
            return '<span class="badge rounded-pill bg-body-secondary text-body-secondary fw-normal"' . $due . '><i class="fas fa-stopwatch me-1"></i>' . timeAgo($sla['due']) . '</span>';
        case 'met':
            return '<span class="badge rounded-pill bg-success-subtle text-success-emphasis fw-normal"><i class="fas fa-check me-1"></i>SLA met</span>';
        case 'missed':
            return '<span class="badge rounded-pill bg-danger-subtle text-danger-emphasis fw-normal"><i class="fas fa-stopwatch me-1"></i>SLA missed</span>';
    }
    return '';
}

/** Its own due date, for an open ticket: ['key' => overdue | soon (a day or less) | later, 'at'], or null. */
function ticketDueState($row) {
    if (empty($row['ticket_due_at']) || !ticketIsOpen($row)) {
        return null;
    }
    $left = strtotime($row['ticket_due_at']) - time();
    return ['key' => $left < 0 ? 'overdue' : ($left <= 86400 ? 'soon' : 'later'), 'at' => $row['ticket_due_at']];
}

function ticketDuePill($due) {
    if (!$due) {
        return '';
    }
    $title = ' title="Due ' . escapeHtml(date('M j, Y g:i A', strtotime($due['at']))) . '"';
    if ($due['key'] === 'overdue') {
        return '<span class="badge rounded-pill text-bg-danger"' . $title . '><i class="far fa-calendar-times me-1"></i>Due ' . timeAgo($due['at']) . '</span>';
    }
    return '<span class="badge rounded-pill ' . ($due['key'] === 'soon' ? 'text-bg-warning' : 'bg-body-secondary text-body-secondary fw-normal') . '"' . $title . '><i class="far fa-calendar me-1"></i>Due ' . timeAgo($due['at']) . '</span>';
}

/** The edge a row or card gets: danger (late), warning (close to it) or ''. */
function ticketAttention($sla, $due) {
    if ($sla['key'] === 'breached' || ($due['key'] ?? '') === 'overdue') {
        return 'danger';
    }
    if ($sla['key'] === 'at_risk' || ($due['key'] ?? '') === 'soon') {
        return 'warning';
    }
    return '';
}

/** The last reply on each of these tickets, any kind, with who wrote it - [ticket id => row]. */
function ticketLastReplies(array $ticket_ids) {
    global $mysqli;
    $ids = implode(',', array_filter(array_map('intval', $ticket_ids)));
    if ($ids === '') {
        return [];
    }
    $sql = mysqli_query($mysqli, "SELECT ticket_reply_ticket_id, ticket_reply_type, ticket_reply_created_at, ticket_reply_by,
            contact_name, contact_photo, contact_client_id, user_name, user_avatar
        FROM ticket_replies
        LEFT JOIN users ON ticket_reply_type <> 'Client' AND ticket_reply_by = user_id
        LEFT JOIN contacts ON ticket_reply_type = 'Client' AND ticket_reply_by = contact_id
        WHERE ticket_reply_id IN (SELECT MAX(ticket_reply_id) FROM ticket_replies
            WHERE ticket_reply_ticket_id IN ($ids) AND ticket_reply_archived_at IS NULL GROUP BY ticket_reply_ticket_id)");
    $rows = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[intval($row['ticket_reply_ticket_id'])] = $row;
    }
    return $rows;
}

/** Tasks on each of these tickets - [ticket id => [done, all]]. */
function ticketTaskCounts(array $ticket_ids) {
    global $mysqli;
    $ids = implode(',', array_filter(array_map('intval', $ticket_ids)));
    if ($ids === '') {
        return [];
    }
    $sql = mysqli_query($mysqli, "SELECT task_ticket_id, COUNT(task_id) AS task_count, SUM(task_completed_at IS NOT NULL) AS done_count
        FROM tasks WHERE task_ticket_id IN ($ids) GROUP BY task_ticket_id");
    $counts = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $counts[intval($row['task_ticket_id'])] = [intval($row['done_count']), intval($row['task_count'])];
    }
    return $counts;
}

/** A small bar and "2/5" for a ticket's tasks - '' when it has none. */
function ticketTasksHtml($counts) {
    if (!$counts || !$counts[1]) {
        return '';
    }
    [$done, $all] = $counts;
    $percent = round($done / $all * 100);
    return '<span class="itflow-ticket-tasks" title="' . "$done of $all tasks done" . '">'
        . '<span class="progress" role="progressbar" aria-valuenow="' . $percent . '" aria-valuemin="0" aria-valuemax="100"><span class="progress-bar ' . ($done === $all ? 'bg-success' : 'bg-secondary') . '" style="width: ' . $percent . '%"></span></span>'
        . "$done/$all</span>";
}

/**
 * The last reply as one line - when, and who - with an icon for which way it
 * went. The whole thing is what the list's Activity column and the board's
 * cards show.
 */
function ticketActivityHtml($reply, $row) {
    if (!$reply) {
        $created = $row['ticket_created_at'];
        return '<span class="text-body-secondary" title="Opened ' . escapeHtml($created) . '"><i class="far fa-fw fa-clock me-1"></i>No replies</span>';
    }
    $type = $reply['ticket_reply_type'];
    $who = $type === 'Client' ? ($reply['contact_name'] ?? '') : ($reply['user_name'] ?? '');
    $icons = [
        'Client' => ['fa-reply text-warning', 'The client replied'],
        'Public' => ['fa-paper-plane text-body-secondary', 'We replied'],
        'Internal' => ['fa-sticky-note text-body-secondary', 'Internal note'],
    ];
    [$icon, $label] = $icons[$type] ?? $icons['Internal'];
    return '<span title="' . escapeHtml($label . ($who !== '' ? " - $who" : '') . ', ' . $reply['ticket_reply_created_at']) . '">'
        . '<i class="fas fa-fw ' . $icon . ' me-1"></i>' . timeAgo($reply['ticket_reply_created_at']) . '</span>'
        . ($who !== '' ? '<div class="small text-body-secondary text-truncate itflow-ticket-who">' . escapeHtml($who) . '</div>' : '');
}

/**
 * For the client portal: the ticket's status as a badge, and under it
 * "Waiting on your reply" when we wrote last on an open ticket. Classes only -
 * the portal's content security policy turns inline styles off, so the
 * status colours the agents see aren't used here.
 */
function ticketPortalStatusHtml($row) {
    $closed = !empty($row['ticket_closed_at']);
    $resolved = !$closed && !empty($row['ticket_resolved_at']);
    $class = $closed ? 'text-bg-secondary' : ($resolved ? 'text-bg-success' : 'text-bg-primary');
    $html = '<span class="badge rounded-pill ' . $class . '">' . escapeHtml($row['ticket_status_name'] ?? '') . '</span>';
    if (!$closed && !$resolved && ($row['ticket_last_spoke'] ?? null) === 'Public') {
        $html .= '<div class="small text-warning-emphasis mt-1"><i class="fas fa-reply me-1"></i>Waiting on your reply</div>';
    }
    return $html;
}

/** Where it came in from, as an icon - '' for one an agent raised. */
function ticketSourceIcon($source) {
    $icons = ['Email' => ['fa-envelope', 'Came in by email'], 'Portal' => ['fa-globe', 'Raised in the client portal'],
        'Recurring' => ['fa-redo-alt', 'Raised by a recurring ticket'], 'API' => ['fa-plug', 'Raised through the API'],
        'Project' => ['fa-project-diagram', 'From a project template']];
    foreach ($icons as $match => [$icon, $title]) {
        if (stripos((string) $source, $match) !== false) {
            return '<i class="fas fa-fw ' . $icon . '" title="' . $title . '"></i>';
        }
    }
    return '';
}

/*
 * ---------- recurring tickets ----------
 */

/** One recurring ticket by id, with its client - raw, or null. */
function recurringTicketRow($recurring_ticket_id) {
    global $mysqli;
    $recurring_ticket_id = intval($recurring_ticket_id);
    if (!$recurring_ticket_id) {
        return null;
    }
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT recurring_tickets.*, client_name FROM recurring_tickets
        LEFT JOIN clients ON client_id = recurring_ticket_client_id
        WHERE recurring_ticket_id = $recurring_ticket_id LIMIT 1"));
    return $row ?: null;
}

/** A frequency as posted, or '' when it isn't one. */
function recurringTicketFrequency($frequency) {
    foreach (array_keys(RECURRING_TICKET_FREQUENCIES) as $known) {
        if (strcasecmp(trim((string) $frequency), $known) === 0) {
            return $known;
        }
    }
    return '';
}

/**
 * The next run after $after (today by default), stepping from the run that
 * was due. Month-based schedules keep to the day of the month the run that
 * was due fell on - except when that was a shorter month's last day standing
 * in for a later one: one that started ($anchor) on the 31st runs on the last
 * day of shorter months and back on the 31st after, rather than drifting.
 * Missed runs are stepped over, not raised one by one.
 */
function recurringTicketNextRun($due, $frequency, $anchor = '', $after = '') {
    $frequency = recurringTicketFrequency($frequency) ?: 'Monthly';
    $step = RECURRING_TICKET_FREQUENCIES[$frequency];
    $after = $after !== '' ? $after : date('Y-m-d');
    $date = DateTime::createFromFormat('!Y-m-d', substr((string) $due, 0, 10)) ?: new DateTime('today');
    $anchor_date = DateTime::createFromFormat('!Y-m-d', substr((string) $anchor, 0, 10));
    $anchor_day = intval($date->format('j'));
    if ($anchor_date && intval($anchor_date->format('j')) > $anchor_day && $anchor_day === intval($date->format('t'))) {
        $anchor_day = intval($anchor_date->format('j'));
    }
    $guard = 0;
    do {
        if (isset($step['days'])) {
            $date->modify('+' . $step['days'] . ' days');
        } else {
            $year = intval($date->format('Y'));
            $month = intval($date->format('n')) + $step['months'];
            $year += intdiv($month - 1, 12);
            $month = ($month - 1) % 12 + 1;
            $days_in_month = intval(date('t', mktime(0, 0, 0, $month, 1, $year)));
            $date->setDate($year, $month, min($anchor_day, $days_in_month));
        }
    } while ($date->format('Y-m-d') <= $after && ++$guard < 5000);
    return $date->format('Y-m-d');
}

/** When in the week or month it comes round, in words - "on Mondays", "on the 15th", "on the last day of the month". */
function recurringTicketCadenceText($row) {
    $frequency = recurringTicketFrequency($row['recurring_ticket_frequency'] ?? '') ?: 'Monthly';
    $next = DateTime::createFromFormat('!Y-m-d', substr((string) ($row['recurring_ticket_next_run'] ?? ''), 0, 10));
    if (!$next) {
        return '';
    }
    $step = RECURRING_TICKET_FREQUENCIES[$frequency];
    if (isset($step['days'])) {
        return $step['days'] === 7 ? 'on ' . $next->format('l') . 's' : ($step['days'] === 14 ? 'every other ' . $next->format('l') : 'every three days');
    }
    $day = intval($next->format('j'));
    $start = DateTime::createFromFormat('!Y-m-d', substr((string) ($row['recurring_ticket_start_date'] ?? ''), 0, 10));
    if ($start && intval($start->format('j')) > $day && $day === intval($next->format('t'))) {
        $day = intval($start->format('j'));
    }
    if ($day >= 31 || ($day >= 29 && $day === intval($next->format('t')) && (!$start || intval($start->format('j')) >= 31))) {
        return 'on the last day of the month';
    }
    $suffix = ($day % 100 >= 11 && $day % 100 <= 13) ? 'th' : (['th', 'st', 'nd', 'rd'][$day % 10] ?? 'th');
    return "on the $day$suffix";
}

/** Is a recurring ticket paused? */
function recurringTicketPaused($row) {
    return !empty($row['recurring_ticket_paused_at']);
}

/** When it runs next, in words - "Today", "Tomorrow", "In 3 days", "Missed - was due 2 days ago", or "Paused". */
function recurringTicketNextRunText($row) {
    if (recurringTicketPaused($row)) {
        return ['text' => 'Paused', 'tone' => 'secondary', 'icon' => 'fa-pause'];
    }
    $next = DateTime::createFromFormat('!Y-m-d', substr((string) $row['recurring_ticket_next_run'], 0, 10));
    if (!$next) {
        return ['text' => 'Not scheduled', 'tone' => 'secondary', 'icon' => 'fa-question'];
    }
    $days = intval((new DateTime('today'))->diff($next)->format('%r%a'));
    if ($days < 0) {
        return ['text' => 'Missed - was due ' . ($days === -1 ? 'yesterday' : abs($days) . ' days ago'), 'tone' => 'danger', 'icon' => 'fa-exclamation-triangle'];
    }
    if ($days === 0) {
        return ['text' => 'Today', 'tone' => 'warning', 'icon' => 'fa-clock'];
    }
    if ($days === 1) {
        return ['text' => 'Tomorrow', 'tone' => 'info', 'icon' => 'fa-clock'];
    }
    return ['text' => $days <= 14 ? "In $days days" : timeAgo($next->format('Y-m-d')), 'tone' => $days <= 7 ? 'info' : 'success', 'icon' => 'fa-calendar-alt'];
}

/** The next run as a soft pill. */
function recurringTicketNextRunPill($row) {
    $state = recurringTicketNextRunText($row);
    $tone = $state['tone'];
    return '<span class="badge rounded-pill bg-' . $tone . '-subtle text-' . $tone . '-emphasis border border-' . $tone . '-subtle fw-normal" title="' . escapeHtml((string) $row['recurring_ticket_next_run']) . '">'
        . '<i class="fas ' . $state['icon'] . ' me-1"></i>' . escapeHtml($state['text']) . '</span>';
}

/**
 * Raise the ticket a recurring ticket describes, now: its subject, details,
 * priority, category, agent, contact, asset and other assets, billing and
 * tasks, with the SLA applied, the client told (when general notifications
 * are on) and the new-ticket address told. $by is who did it, for the audit
 * log. Does not move the next run - the caller does. The new ticket's id, or 0.
 */
function recurringTicketRaise($recurring_ticket_id, $by = 'Cron') {
    global $mysqli, $config_base_url;

    $row = recurringTicketRow($recurring_ticket_id);
    if (!$row) {
        return 0;
    }

    // The settings, read here: the cron keeps its own copies SQL-escaped, the pages don't
    $settings = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_ticket_prefix, config_ticket_from_email, config_ticket_from_name,
        config_ticket_new_ticket_notification_email, config_ticket_client_general_notifications, config_smtp_provider FROM settings WHERE company_id = 1"));
    $config_ticket_prefix = $settings['config_ticket_prefix'] ?? '';
    $config_ticket_from_email = $settings['config_ticket_from_email'] ?? '';
    $config_ticket_from_name = $settings['config_ticket_from_name'] ?? '';
    $config_ticket_new_ticket_notification_email = $settings['config_ticket_new_ticket_notification_email'] ?? '';
    $config_ticket_client_general_notifications = intval($settings['config_ticket_client_general_notifications'] ?? 0);
    $config_smtp_provider = $settings['config_smtp_provider'] ?? '';
    $recurring_ticket_id = intval($row['recurring_ticket_id']);
    $client_id = intval($row['recurring_ticket_client_id']);
    $subject = escapeSql($row['recurring_ticket_subject']);
    $details = mysqli_real_escape_string($mysqli, (string) $row['recurring_ticket_details']);
    $priority = escapeSql(ticketPriority($row['recurring_ticket_priority']));
    $frequency = strtolower((string) $row['recurring_ticket_frequency']);
    $billable = intval($row['recurring_ticket_billable']);
    $created_by = intval($row['recurring_ticket_created_by']);
    $assigned_to = ticketAgentId($row['recurring_ticket_assigned_to']);
    // What it links to has to still be the client's
    $contact_id = ticketClientOwnedId('contact', $row['recurring_ticket_contact_id'], $client_id);
    $asset_id = ticketClientOwnedId('asset', $row['recurring_ticket_asset_id'], $client_id);
    $category_id = ticketCategoryId($row['recurring_ticket_category']);
    $ticket_status = $assigned_to ? TICKET_STATUS_OPEN : TICKET_STATUS_NEW;
    $url_key = randomString(32);
    $prefix = escapeSql($config_ticket_prefix);

    // Atomically increment and get the new ticket number
    mysqli_query($mysqli, "UPDATE settings SET config_ticket_next_number = LAST_INSERT_ID(config_ticket_next_number), config_ticket_next_number = config_ticket_next_number + 1 WHERE company_id = 1");
    $ticket_number = mysqli_insert_id($mysqli);

    mysqli_query($mysqli, "INSERT INTO tickets SET ticket_prefix = '$prefix', ticket_number = $ticket_number, ticket_source = 'Recurring', ticket_subject = '$subject',
        ticket_details = '$details', ticket_priority = '$priority', ticket_status = $ticket_status, ticket_billable = $billable, ticket_url_key = '$url_key',
        ticket_created_by = $created_by, ticket_assigned_to = $assigned_to, ticket_contact_id = $contact_id, ticket_client_id = $client_id,
        ticket_asset_id = $asset_id, ticket_category = $category_id, ticket_recurring_ticket_id = $recurring_ticket_id");
    $ticket_id = mysqli_insert_id($mysqli);
    if (!$ticket_id) {
        return 0;
    }
    applyTicketSla($ticket_id);

    // Its other assets that are still the client's, and its tasks
    mysqli_query($mysqli, "INSERT IGNORE INTO ticket_assets (ticket_id, asset_id)
        SELECT $ticket_id, recurring_ticket_assets.asset_id FROM recurring_ticket_assets
        JOIN assets ON assets.asset_id = recurring_ticket_assets.asset_id AND asset_client_id = $client_id
        WHERE recurring_ticket_id = $recurring_ticket_id AND recurring_ticket_assets.asset_id <> $asset_id");
    addTasksFromRecurringTicket($ticket_id, $recurring_ticket_id);

    logAudit("Ticket", "Create", escapeSql($by) . " created recurring $frequency ticket $prefix$ticket_number - $subject", $client_id, $ticket_id);
    triggerCustomAction('ticket_create', $ticket_id);

    // Who to tell
    $ticket = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT client_name, contact_name, contact_email FROM tickets
        LEFT JOIN clients ON ticket_client_id = client_id
        LEFT JOIN contacts ON ticket_contact_id = contact_id
        WHERE ticket_id = $ticket_id"));
    $company = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT company_name, company_phone, company_phone_country_code FROM companies WHERE company_id = 1"));
    $company_name = escapeSql($company['company_name'] ?? '');
    $company_phone = escapeSql(formatPhoneNumber($company['company_phone'] ?? '', $company['company_phone_country_code'] ?? ''));
    $contact_name = escapeSql($ticket['contact_name'] ?? '');
    $contact_email = escapeSql($ticket['contact_email'] ?? '');
    $client_name = escapeSql($ticket['client_name'] ?? '');
    $from_email = escapeSql($config_ticket_from_email);
    $from_name = escapeSql($config_ticket_from_name);
    $base_url = escapeSql($config_base_url);
    $data = [];

    if (!empty($config_smtp_provider) && intval($config_ticket_client_general_notifications) === 1 && filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
        $sla_notice = escapeSql(getTicketSlaEmailNotice($ticket_id, $company_phone));
        $status_name = escapeSql(getTicketStatusName($ticket_status));
        $data[] = [
            'from' => $from_email,
            'from_name' => $from_name,
            'recipient' => $contact_email,
            'recipient_name' => $contact_name,
            'subject' => "Ticket created - [$prefix$ticket_number] - $subject (scheduled)",
            'body' => "<i style=\'color: #808080\'>##- Please type your reply above this line -##</i><br><br>Hello $contact_name,<br><br>A ticket regarding \"$subject\" has been automatically created for you.<br><br>--------------------------------<br>$details--------------------------------<br><br>Ticket: $prefix$ticket_number<br>Subject: $subject<br>Status: $status_name<br>Portal: https://$base_url/client/ticket.php?id=$ticket_id$sla_notice<br><br>--<br>$company_name - Support<br>$from_email<br>$company_phone",
        ];
    }
    if (filter_var($config_ticket_new_ticket_notification_email, FILTER_VALIDATE_EMAIL)) {
        $client_uri = $client_id ? "&client_id=$client_id" : '';
        $data[] = [
            'from' => $from_email,
            'from_name' => $from_name,
            'recipient' => escapeSql($config_ticket_new_ticket_notification_email),
            'recipient_name' => $from_name,
            'subject' => "ITFlow - New Recurring Ticket - $client_name: $subject",
            'body' => "Hello, <br><br>This is a notification that a recurring (scheduled) ticket has been raised in ITFlow. <br>Ticket: $prefix$ticket_number<br>Client: $client_name<br>Priority: $priority<br>Link: https://$base_url/agent/ticket.php?ticket_id=$ticket_id$client_uri <br><br>--------------------------------<br><br><b>$subject</b><br>$details",
        ];
    }
    if ($data) {
        addToMailQueue($data);
    }

    return $ticket_id;
}

/**
 * Raise a recurring ticket's ticket now and move its next run on: one step
 * past the run that was due, or past today when that was missed - so forcing
 * an early run takes the place of the next one, and nothing is raised twice.
 * The new ticket's id, or 0.
 */
function recurringTicketRunNow($row, $by) {
    global $mysqli;
    $recurring_ticket_id = intval($row['recurring_ticket_id']);
    $ticket_id = recurringTicketRaise($recurring_ticket_id, $by);
    if ($ticket_id) {
        $due = (string) $row['recurring_ticket_next_run'];
        $next_run = recurringTicketNextRun($due, $row['recurring_ticket_frequency'], $row['recurring_ticket_start_date'], max(date('Y-m-d'), substr($due, 0, 10)));
        mysqli_query($mysqli, "UPDATE recurring_tickets SET recurring_ticket_next_run = '$next_run' WHERE recurring_ticket_id = $recurring_ticket_id");
    }
    return $ticket_id;
}

/**
 * For post handlers: the recurring ticket a request names, after the module
 * and client checks - or back with an error if it is gone. Sets the global
 * $client_id.
 */
function recurringTicketForRequest($recurring_ticket_id, $level) {
    global $client_id;
    enforceUserPermission('module_support', $level);
    $row = recurringTicketRow($recurring_ticket_id);
    if (!$row) {
        flashAlert('That recurring ticket no longer exists.', 'error');
        redirect();
    }
    $client_id = intval($row['recurring_ticket_client_id']);
    if ($client_id) {
        enforceClientAccess();
    }
    return $row;
}

/** For post handlers: the recurring tickets a bulk action names (recurring_ticket_ids[]) - [id => row]. */
function recurringTicketsForBulk($level) {
    global $client_id;
    enforceUserPermission('module_support', $level);
    $rows = [];
    foreach (array_unique(array_map('intval', (array) ($_POST['recurring_ticket_ids'] ?? []))) as $recurring_ticket_id) {
        $row = recurringTicketRow($recurring_ticket_id);
        if (!$row) {
            continue;
        }
        $client_id = intval($row['recurring_ticket_client_id']);
        if ($client_id) {
            enforceClientAccess();
        }
        $rows[$recurring_ticket_id] = $row;
    }
    return $rows;
}

/** A date posted from a date field as Y-m-d, or '' when it isn't one. */
function recurringTicketDate($input) {
    $input = trim((string) $input);
    $date = DateTime::createFromFormat('!Y-m-d', $input);
    return $date && $date->format('Y-m-d') === $input && $input <= '2999-12-31' ? $input : '';
}

/** The next few runs from the next one - for showing what a schedule means. */
function recurringTicketUpcoming($row, $count = 3) {
    $runs = [];
    $date = substr((string) $row['recurring_ticket_next_run'], 0, 10);
    if (!recurringTicketDate($date)) {
        return $runs;
    }
    $runs[] = $date;
    while (count($runs) < $count) {
        $date = recurringTicketNextRun($date, $row['recurring_ticket_frequency'], $row['recurring_ticket_start_date'], $date);
        $runs[] = $date;
    }
    return $runs;
}

/** The last ticket each of these recurring tickets raised, with its status - [recurring id => row]. */
function recurringTicketLastTickets(array $recurring_ticket_ids) {
    global $mysqli;
    $ids = array_values(array_unique(array_filter(array_map('intval', $recurring_ticket_ids))));
    if (!$ids) {
        return [];
    }
    $last = [];
    $sql = mysqli_query($mysqli, "SELECT ticket_recurring_ticket_id, MAX(ticket_id) FROM tickets WHERE ticket_recurring_ticket_id IN (" . implode(',', $ids) . ") GROUP BY ticket_recurring_ticket_id");
    while ($row = mysqli_fetch_row($sql)) {
        $last[intval($row[1])] = intval($row[0]);
    }
    if (!$last) {
        return [];
    }
    $rows = [];
    foreach (ticketRows('tickets.ticket_id IN (' . implode(',', array_keys($last)) . ')') as $row) {
        $rows[$last[intval($row['ticket_id'])]] = $row;
    }
    return $rows;
}

/*
 * ---------- scheduled work ----------
 * Booked from the ticket, or by dragging it on the calendar - both come here.
 */

/** Work out when a datetime-local value is - 'Y-m-d H:i:s', or '' when it isn't a date and time. */
function ticketScheduleValue($input) {
    $sql = ticketDueSql($input);
    return $sql === 'NULL' ? '' : trim($sql, "'");
}

/** The scheduled-visit emails - the agent, the contact and the watchers - with the calendar invite (or its cancellation). */
function ticketMailSchedule($row, $cal_str, $cancelled, $when_text) {
    global $mysqli, $config_smtp_provider, $config_ticket_from_email, $config_ticket_from_name, $config_base_url, $config_ticket_client_general_notifications, $session_company_name;
    if (empty($config_smtp_provider)) {
        return;
    }
    // Built as plain text and HTML, then escaped once for the mail queue
    $sql = function ($value) use ($mysqli) {
        return mysqli_real_escape_string($mysqli, (string) $value);
    };
    $html = function ($value) {
        return escapeHtml((string) $value);
    };
    $ticket_id = intval($row['ticket_id']);
    $client_id = intval($row['ticket_client_id']);
    $reference = ticketReference($row);
    $subject = (string) $row['ticket_subject'];
    $company_name = (string) $session_company_name;
    $from_email = (string) $config_ticket_from_email;
    $agent_link = "https://$config_base_url/agent/ticket.php?ticket_id=$ticket_id" . ($client_id ? "&client_id=$client_id" : '');
    $client_link = "https://$config_base_url/client/ticket.php?id=$ticket_id";
    $what = $cancelled ? 'Scheduled work for the ticket regarding ' . $html($subject) . ' has been cancelled.' : 'The ticket regarding ' . $html($subject) . ' has been scheduled for ' . $html($when_text) . '.';
    $what_yours = $cancelled ? 'Scheduled work for your ticket regarding ' . $html($subject) . ' has been cancelled.' : 'Your ticket regarding ' . $html($subject) . ' has been scheduled for ' . $html($when_text) . '.';
    $mail_subject = $sql(($cancelled ? 'Ticket Schedule Cancelled' : 'Ticket Scheduled') . " - [$reference] - $subject");
    $footer = "<div class='footer'>~<br>" . $html($company_name) . "<br>Support Department<br>" . $html($from_email) . "<br></div><div class='no-reply'>This is an automated message. Please do not reply directly to this email.</div>";
    $from = ['from' => $sql($from_email), 'from_name' => $sql($config_ticket_from_name), 'subject' => $mail_subject, 'cal_str' => $cal_str];
    $data = [];

    // The agent doing the work
    if (filter_var($row['user_email'] ?? '', FILTER_VALIDATE_EMAIL)) {
        $data[] = $from + ['recipient' => $sql($row['user_email']), 'recipient_name' => $sql($row['user_name']),
            'body' => $sql('Hello, ' . $html($row['user_name']) . "<br><br>$what<br><br>--------------------------------<br><a href=\"$agent_link\">" . $html($reference) . "</a><br>--------------------------------<br><br>Please do not reply to this email. <br><br>Ticket: " . $html($reference) . "<br>Subject: " . $html($subject) . "<br>Portal: $agent_link<br><br>~<br>" . $html($company_name) . "<br>Support Department<br>" . $html($from_email))];
    }

    if (intval($config_ticket_client_general_notifications) === 1) {
        // The contact
        if (filter_var($row['contact_email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            $data[] = $from + ['recipient' => $sql($row['contact_email']), 'recipient_name' => $sql($row['contact_name']),
                'body' => $sql("<div class='header'>Hello, " . $html($row['contact_name']) . "</div>$what_yours<br><br><a href='$client_link' class='link-button'>Access your ticket here</a><br><br>Please do not reply to this email.<br><br><strong>Ticket:</strong> " . $html($reference) . "<br><strong>Subject:</strong> " . $html($subject) . "<br><br><br>$footer")];
        }
        // And the watchers
        $watchers = mysqli_query($mysqli, "SELECT watcher_email FROM ticket_watchers WHERE watcher_ticket_id = $ticket_id");
        foreach (ticketWatcherEmails(array_column(mysqli_fetch_all($watchers, MYSQLI_ASSOC), 'watcher_email')) as $watcher_email) {
            if (strcasecmp($watcher_email, (string) ($row['contact_email'] ?? '')) === 0) {
                continue;
            }
            $data[] = $from + ['recipient' => $sql($watcher_email), 'recipient_name' => $sql($watcher_email),
                'body' => $sql("<div class='header'>Hello,</div>$what<br><br><a href='$client_link' class='link-button'>" . $html($reference) . "</a><br><br>Please do not reply to this email.<br><br><strong>Ticket:</strong> " . $html($reference) . "<br><strong>Subject:</strong> " . $html($subject) . "<br><strong>Portal:</strong> <a href='$client_link'>Access the ticket here</a><br><br>$footer")];
        }
    }

    if ($data) {
        addToMailQueue($data);
    }
}

/**
 * Book the work on a ticket for $schedule ('Y-m-d H:i:s'), onsite or remote:
 * the agent, the contact and the watchers are emailed with the calendar file,
 * and it is noted on the ticket. Returns ['when_text' => ..., 'conflicts' =>
 * the same agent's other open work within two hours, as plain text].
 */
function ticketSetSchedule(array $row, $schedule, $onsite) {
    global $mysqli, $config_base_url, $session_user_id, $session_name;
    $ticket_id = intval($row['ticket_id']);
    $client_id = intval($row['ticket_client_id']);
    $onsite = $onsite ? 1 : 0;
    $schedule = escapeSql($schedule);

    mysqli_query($mysqli, "UPDATE tickets SET ticket_schedule = '$schedule', ticket_onsite = $onsite WHERE ticket_id = $ticket_id");

    // The same agent's other open tickets within two hours of it
    $conflicts = [];
    $assigned_to = intval($row['ticket_assigned_to']);
    if ($assigned_to) {
        $start = date('Y-m-d H:i:s', strtotime($schedule) - 7200);
        $end = date('Y-m-d H:i:s', strtotime($schedule) + 7200);
        $sql = mysqli_query($mysqli, "SELECT ticket_prefix, ticket_number, ticket_schedule, ticket_subject FROM tickets
            WHERE ticket_schedule BETWEEN '$start' AND '$end' AND ticket_id <> $ticket_id AND ticket_assigned_to = $assigned_to
            AND ticket_resolved_at IS NULL AND ticket_closed_at IS NULL ORDER BY ticket_schedule");
        while ($conflict = mysqli_fetch_assoc($sql)) {
            $conflicts[] = ticketReference($conflict) . ' - ' . $conflict['ticket_subject'] . ' @ ' . date('g:ia', strtotime($conflict['ticket_schedule']));
        }
    }

    // Who to tell, with the calendar file
    $mail_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT tickets.ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_details, ticket_client_id, client_name,
            contact_name, contact_email, user_name, user_email, location_address
        FROM tickets
        LEFT JOIN clients ON ticket_client_id = client_id
        LEFT JOIN contacts ON ticket_contact_id = contact_id
        LEFT JOIN locations ON location_id = IF(ticket_location_id > 0, ticket_location_id, contact_location_id)
        LEFT JOIN users ON ticket_assigned_to = user_id
        WHERE tickets.ticket_id = $ticket_id"));
    $cal_subject = intval($mail_row['ticket_number']) . ": " . $mail_row['client_name'] . " - " . $mail_row['ticket_subject'];
    $cal_description = mb_substr(strip_tags((string) $mail_row['ticket_details']), 0, 100) . " - https://$config_base_url/client/ticket.php?id=$ticket_id";
    $cal_str = createiCalStr($schedule, $cal_subject, $cal_description, (string) $mail_row['location_address'], getTicketCalendarUid($ticket_id));
    $when_text = date('l, F j, Y \a\t g:ia', strtotime($schedule));
    ticketMailSchedule($mail_row, $cal_str, false, $when_text);

    // Note it on the ticket
    $note = escapeSql("Ticket scheduled for $when_text " . ($onsite ? '(onsite).' : '(remote).'));
    mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = '$note', ticket_reply_type = 'Internal', ticket_reply_time_worked = '00:00:00', ticket_reply_by = " . intval($session_user_id) . ", ticket_reply_ticket_id = $ticket_id");

    logTicketHistory($ticket_id, "$session_name scheduled the work for " . escapeSql($when_text) . ($onsite ? ' (onsite)' : ' (remote)'));
    logAudit("Ticket", "Edit", "$session_name scheduled ticket " . escapeSql(ticketReference($row)) . " for " . escapeSql($when_text), $client_id, $ticket_id);
    triggerCustomAction('ticket_schedule', $ticket_id);

    return ['when_text' => $when_text, 'conflicts' => $conflicts];
}

/** Take the scheduled work off a ticket, cancelling the calendar file everyone was sent. */
function ticketCancelSchedule(array $row) {
    global $mysqli, $session_user_id, $session_name;
    $ticket_id = intval($row['ticket_id']);
    $client_id = intval($row['ticket_client_id']);
    $old_schedule = $row['ticket_schedule'];

    mysqli_query($mysqli, "UPDATE tickets SET ticket_schedule = NULL WHERE ticket_id = $ticket_id");

    $mail_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT tickets.ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_client_id, client_name,
            contact_name, contact_email, user_name, user_email
        FROM tickets
        LEFT JOIN clients ON ticket_client_id = client_id
        LEFT JOIN contacts ON ticket_contact_id = contact_id
        LEFT JOIN users ON ticket_assigned_to = user_id
        WHERE tickets.ticket_id = $ticket_id"));
    $cal_subject = intval($mail_row['ticket_number']) . ": " . $mail_row['client_name'] . " - " . $mail_row['ticket_subject'];
    $cal_str = createiCalStrCancel($old_schedule, $cal_subject, getTicketCalendarUid($ticket_id));
    ticketMailSchedule($mail_row, $cal_str, true, '');

    mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = 'Ticket schedule cancelled.', ticket_reply_type = 'Internal', ticket_reply_time_worked = '00:00:00', ticket_reply_by = " . intval($session_user_id) . ", ticket_reply_ticket_id = $ticket_id");

    logTicketHistory($ticket_id, "$session_name cancelled the scheduled work");
    logAudit("Ticket", "Edit", "$session_name cancelled the schedule of ticket " . escapeSql(ticketReference($row)), $client_id, $ticket_id);
    triggerCustomAction('ticket_unschedule', $ticket_id);
}
