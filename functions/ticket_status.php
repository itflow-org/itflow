<?php

/*
 * Ticket statuses - the five ITFlow has (New, Open, On Hold, Resolved and
 * Closed, rows 1-5, which the code relies on) and your own, like Waiting on
 * Vendor or Scheduled. Each has a name, a colour, what it is for, whether
 * the SLA's resolution clock runs in it, what a client's reply does to a
 * ticket in it (back to Open, stay, or move to another status - a "Client
 * responded" one), whether an agent moving a ticket into it counts as the
 * first response, and a place in the order the pickers and the board show
 * them in. A status can be switched off - it leaves the pickers and the
 * board - and one of your own deleted; either way the tickets in it can be
 * moved first. Admin > Ticket Statuses (admin/ticket_statuses.php) and its
 * handlers (admin/post/ticket_status.php) are built on these.
 *
 * The built-in five keep their names, stay switched on, and have fixed SLA
 * behaviour (getTicketStatusSlaLock() in functions/app.php). A client's reply
 * moves a ticket in one back to Open, or to another status picked for it -
 * never leaves it, and Closed takes no replies. Their colour, description,
 * place in the order, and - for Open and On Hold - first response can change.
 */

/*
 * The five built-in statuses - id => name, icon, what it means (the default
 * description), and why it is set as it is.
 */
function ticketStatusBuiltIns() {
    return [
        TICKET_STATUS_NEW => ['name' => 'New', 'icon' => 'fa-inbox', 'about' => 'Just come in - nobody has picked it up yet.', 'set_by' => 'Set when a ticket comes in. Assigning it or replying opens it.'],
        TICKET_STATUS_OPEN => ['name' => 'Open', 'icon' => 'fa-folder-open', 'about' => 'Being worked on.', 'set_by' => 'Where a ticket goes when it is picked up, and when a client replies.'],
        TICKET_STATUS_ON_HOLD => ['name' => 'On Hold', 'icon' => 'fa-pause-circle', 'about' => 'Waiting on someone else - the client, a vendor or a part.', 'set_by' => 'Set by hand. The SLA\'s resolution clock is paused here.'],
        TICKET_STATUS_RESOLVED => ['name' => 'Resolved', 'icon' => 'fa-check', 'about' => 'Fixed, and waiting to be closed - a reply from the client reopens it.', 'set_by' => 'Set by Resolve, which tells the client. Closes by itself after the days set in Admin > Settings > Tickets.'],
        TICKET_STATUS_CLOSED => ['name' => 'Closed', 'icon' => 'fa-check-circle', 'about' => 'Finished - it can\'t be replied to.', 'set_by' => 'Set by Close, or when a resolved ticket closes by itself.'],
    ];
}

/** Whether a status is one of the five ITFlow has. */
function ticketStatusIsBuiltIn($ticket_status_id) {
    return isset(ticketStatusBuiltIns()[intval($ticket_status_id)]);
}

/** What a client's reply does to a ticket in a status - 1 moves it back to Open, 0 leaves it where it is. */
const TICKET_STATUS_CLIENT_REPLY = [
    1 => ['label' => 'Move it back to Open', 'short' => 'Back to Open', 'icon' => 'fa-undo-alt'],
    0 => ['label' => 'Leave it in this status', 'short' => 'Stays', 'icon' => 'fa-equals'],
];

/*
 * Whether an agent moving a ticket into a status counts as its first response -
 * 0 no, 1 yes, 2 yes once the client is emailed the "ticket assigned" message
 * (ticketStatusMovedByAgent()). Not for New, Resolved or Closed.
 */
const TICKET_STATUS_FIRST_RESPONSE = [
    0 => ['label' => 'No - only a reply to the client counts', 'short' => ''],
    2 => ['label' => 'Yes - after emailing the client that it is assigned', 'short' => 'First response, emails the client'],
    1 => ['label' => 'Yes - without an email', 'short' => 'First response'],
];

/** The built-in wording of the "ticket assigned" message - Admin > Settings > Tickets can change it (config_ticket_assigned_message). */
const TICKET_ASSIGNED_MESSAGE = "Thank you for getting in touch. Your ticket [Ticket] - [Subject] is with [Agent], who will be in touch with you shortly.\n\n"
    . "If you have anything to add in the meantime, just reply to this email.";

/** Whether the 3.4.0 update has run - ticket_status_description and ticket_status_client_reply. */
function ticketStatusColumnsReady() {
    static $ready = null;
    if ($ready === null) {
        $ready = defined('CURRENT_DATABASE_VERSION') ? version_compare(CURRENT_DATABASE_VERSION, '3.4.0', '>=') : true;
    }
    return $ready;
}

/** Whether the 3.7.3 update has run - ticket_status_reply_status_id, ticket_status_first_response and config_ticket_assigned_message. */
function ticketStatusReplyReady() {
    global $mysqli;
    static $ready = null;
    if ($ready === null) {
        if (defined('CURRENT_DATABASE_VERSION')) {
            $version = CURRENT_DATABASE_VERSION;
        } else {
            $result = mysqli_query($mysqli, "SELECT config_current_database_version FROM settings WHERE company_id = 1");
            $version = $result && ($row = mysqli_fetch_row($result)) ? (string) $row[0] : '0';
        }
        $ready = version_compare($version, '3.7.3', '>=');
    }
    return $ready;
}

/** The columns a status row is read with - the 3.4.0 and 3.7.3 ones only once they are there. */
function ticketStatusColumnsSql() {
    return 'ticket_status_id, ticket_status_name, ticket_status_color, ticket_status_active, ticket_status_pauses_sla, ticket_status_order'
        . (ticketStatusColumnsReady() ? ', ticket_status_description, ticket_status_client_reply' : ", '' AS ticket_status_description, 1 AS ticket_status_client_reply")
        . (ticketStatusReplyReady() ? ', ticket_status_reply_status_id, ticket_status_first_response' : ', 0 AS ticket_status_reply_status_id, 0 AS ticket_status_first_response');
}

/*
 * The statuses a client's reply can move a ticket to - the active ones an
 * agent can set (not New, Resolved or Closed), other than $except - id => row.
 */
function ticketStatusReplyTargets($except = 0) {
    $targets = [];
    foreach (ticketStatusesAll() as $id => $row) {
        if ($id !== intval($except) && intval($row['ticket_status_active']) === 1 && !in_array($id, [TICKET_STATUS_NEW, TICKET_STATUS_RESOLVED, TICKET_STATUS_CLOSED], true)) {
            $targets[$id] = $row;
        }
    }
    return $targets;
}

/** Whether a status can count as the first response - every one but New, Resolved and Closed. */
function ticketStatusCanBeFirstResponse($ticket_status_id) {
    $ticket_status_id = intval($ticket_status_id);
    return $ticket_status_id > 0 && !in_array($ticket_status_id, [TICKET_STATUS_NEW, TICKET_STATUS_RESOLVED, TICKET_STATUS_CLOSED], true);
}

/** One status, or null. */
function ticketStatusRow($ticket_status_id) {
    global $mysqli;
    $ticket_status_id = intval($ticket_status_id);
    if ($ticket_status_id <= 0) {
        return null;
    }
    return mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT " . ticketStatusColumnsSql() . " FROM ticket_statuses WHERE ticket_status_id = $ticket_status_id LIMIT 1")) ?: null;
}

/*
 * Every status, in the pickers' order - id => row. Read once a page; pass
 * $fresh after changing one.
 */
function ticketStatusesAll($fresh = false) {
    global $mysqli;
    static $statuses = null;
    if ($statuses === null || $fresh) {
        $statuses = [];
        $sql = mysqli_query($mysqli, "SELECT " . ticketStatusColumnsSql() . " FROM ticket_statuses ORDER BY ticket_status_order ASC, ticket_status_id ASC");
        while ($row = mysqli_fetch_assoc($sql)) {
            $statuses[intval($row['ticket_status_id'])] = $row;
        }
    }
    return $statuses;
}

/** What a status is for - its description, or a built-in's meaning when it has none. */
function ticketStatusAbout(array $row) {
    $description = trim((string) ($row['ticket_status_description'] ?? ''));
    if ($description !== '') {
        return $description;
    }
    return ticketStatusBuiltIns()[intval($row['ticket_status_id'] ?? 0)]['about'] ?? '';
}

/** A status's colour as #rrggbb - grey when it hasn't a usable one. */
function ticketStatusColor($color) {
    $color = categoryColor($color);
    return $color !== '' ? $color : '#6c757d';
}

/*
 * A status as its badge - in its colour, with text that reads on it, and
 * what it is for on hover. $style 'list' is the padded pill of the lists,
 * 'detail' the plain one.
 */
function ticketStatusBadgeHtml($name, $color, $style = 'detail', $about = '') {
    $color = ticketStatusColor($color);
    return '<span class="badge rounded-pill itflow-ticket-status' . ($style === 'list' ? ' p-2' : '') . '" style="--itflow-status-bg: ' . $color . '; --itflow-status-text: ' . tagTextColor($color) . '"'
        . ($about !== '' ? ' title="' . escapeHtml($about) . '"' : '') . '>' . escapeHtml($name) . '</span>';
}

/** A colour for a new status - the first of the palette no status has yet. */
function ticketStatusNextColor() {
    $used = array_map(function ($row) {
        return ticketStatusColor($row['ticket_status_color']);
    }, ticketStatusesAll());
    foreach (CATEGORY_COLORS as $color) {
        if (!in_array($color, $used, true)) {
            return $color;
        }
    }
    return CATEGORY_COLORS[count($used) % count(CATEGORY_COLORS)];
}

/* The statuses' names - id => name - for a picker or a filter, the active ones and any of $keep. */
function ticketStatusOptions(array $keep = []) {
    $options = [];
    foreach (ticketStatusesAll() as $id => $row) {
        if (intval($row['ticket_status_active']) === 1 || in_array($id, array_map('intval', $keep), true)) {
            $options[$id] = $row;
        }
    }
    return $options;
}

/*
 * How many tickets are in each status - id => ['shown' (not archived - what
 * the tickets list shows), 'open' (nor resolved or closed), 'all'].
 */
function ticketStatusTicketCounts() {
    global $mysqli;
    $counts = [];
    $sql = mysqli_query($mysqli, "SELECT ticket_status, COUNT(*) AS n_all, SUM(ticket_archived_at IS NULL) AS n_shown,
            SUM(ticket_resolved_at IS NULL AND ticket_closed_at IS NULL AND ticket_archived_at IS NULL) AS n_open
        FROM tickets GROUP BY ticket_status");
    while ($row = mysqli_fetch_assoc($sql)) {
        $counts[intval($row['ticket_status'])] = ['shown' => intval($row['n_shown']), 'open' => intval($row['n_open']), 'all' => intval($row['n_all'])];
    }
    return $counts;
}

/** How many canned responses set this status. */
function ticketStatusCannedCount($ticket_status_id) {
    global $mysqli;
    $result = mysqli_query($mysqli, "SELECT COUNT(*) FROM canned_responses WHERE canned_response_ticket_status = " . intval($ticket_status_id));
    return $result ? intval(mysqli_fetch_row($result)[0]) : 0;
}

/*
 * Where a status's tickets can be moved when it is switched off or deleted:
 * the active statuses an agent can put an open ticket in (not New, Resolved
 * or Closed), other than itself - id => row.
 */
function ticketStatusMoveTargets($ticket_status_id) {
    $targets = [];
    foreach (ticketStatusesAll() as $id => $row) {
        if ($id !== intval($ticket_status_id) && intval($row['ticket_status_active']) === 1 && !in_array($id, [TICKET_STATUS_NEW, TICKET_STATUS_RESOLVED, TICKET_STATUS_CLOSED], true)) {
            $targets[$id] = $row;
        }
    }
    return $targets;
}

/*
 * A status's fields from a form - name, color, description, pauses_sla,
 * client_reply ("1" back to Open, "0" stay, "to-<id>" move to that status -
 * reply_status_id), first_response and active. A built-in keeps its name,
 * stays on, has its fixed SLA answer, and a reply never leaves it where it is;
 * Closed takes no replies. Sent no first_response, a status keeps what it had.
 */
function ticketStatusFromInput(array $in, $row = null) {
    $id = $row ? intval($row['ticket_status_id']) : 0;
    $builtin = $id && ticketStatusIsBuiltIn($id);
    $name = trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($in['name'] ?? ''))));
    $color = categoryColor($in['color'] ?? '');
    $reply = (string) ($in['client_reply'] ?? '1');
    $reply_to = preg_match('/^to-(\d+)$/', $reply, $match) ? intval($match[1]) : 0;
    $f = [
        'name' => $builtin ? (string) $row['ticket_status_name'] : mb_substr($name, 0, 200),
        'color' => $color !== '' ? $color : ($row ? ticketStatusColor($row['ticket_status_color']) : CATEGORY_COLORS[0]),
        'description' => mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($in['description'] ?? '')))), 0, 255),
        'pauses_sla' => intval($in['pauses_sla'] ?? 0) === 1 ? 1 : 0,
        'client_reply' => $reply === '0' ? 0 : 1,
        'reply_status_id' => $reply_to && isset(ticketStatusReplyTargets($id)[$reply_to]) ? $reply_to : 0,
        'first_response' => array_key_exists('first_response', $in) ? intval($in['first_response']) : intval($row['ticket_status_first_response'] ?? 0),
        'active' => array_key_exists('active', $in) ? (intval($in['active']) === 1 ? 1 : 0) : ($row ? intval($row['ticket_status_active']) : 1),
    ];
    if (!isset(TICKET_STATUS_FIRST_RESPONSE[$f['first_response']]) || ($id && !ticketStatusCanBeFirstResponse($id))) {
        $f['first_response'] = 0;
    }
    if ($builtin) {
        $f['pauses_sla'] = intval(getTicketStatusSlaLock($id));
        $f['client_reply'] = 1;
        $f['active'] = 1;
    }
    if ($id === TICKET_STATUS_CLOSED) {
        $f['reply_status_id'] = 0;
    }
    return $f;
}

/* What is wrong with a status's fields - '' when nothing. $id is the status being changed, 0 for a new one. */
function ticketStatusProblem(array $f, $id = 0) {
    global $mysqli;
    if ($f['name'] === '') {
        return 'A status needs a name.';
    }
    $name = escapeSql($f['name']);
    $taken = mysqli_fetch_row(mysqli_query($mysqli, "SELECT ticket_status_name FROM ticket_statuses WHERE ticket_status_name = '$name' AND ticket_status_id <> " . intval($id) . " LIMIT 1"));
    if ($taken) {
        return 'There is already a status called "' . $taken[0] . '".';
    }
    return '';
}

/** The SET clause for a status's fields. */
function ticketStatusSetSql(array $f) {
    return "ticket_status_name = '" . escapeSql($f['name']) . "', ticket_status_color = '" . escapeSql($f['color']) . "',
        ticket_status_pauses_sla = {$f['pauses_sla']}, ticket_status_active = {$f['active']}"
        . (ticketStatusColumnsReady() ? ", ticket_status_description = " . ($f['description'] !== '' ? "'" . escapeSql($f['description']) . "'" : 'NULL') . ", ticket_status_client_reply = {$f['client_reply']}" : '')
        . (ticketStatusReplyReady() ? ", ticket_status_reply_status_id = " . intval($f['reply_status_id']) . ", ticket_status_first_response = " . intval($f['first_response']) : '');
}

/** A new status, at the end of the order - its id. */
function ticketStatusCreate(array $f) {
    global $mysqli;
    $order = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COALESCE(MAX(ticket_status_order), 0) FROM ticket_statuses"))[0]) + 1;
    mysqli_query($mysqli, "INSERT INTO ticket_statuses SET " . ticketStatusSetSql($f) . ", ticket_status_order = $order");
    $id = intval(mysqli_insert_id($mysqli));
    ticketStatusesAll(true);
    return $id;
}

/*
 * Save a status's fields - what changed, as phrases for the audit log. A
 * change to whether its clock is paused is applied to the tickets in it
 * straight away.
 */
function ticketStatusUpdate(array $row, array $f) {
    global $mysqli;
    $id = intval($row['ticket_status_id']);
    mysqli_query($mysqli, "UPDATE ticket_statuses SET " . ticketStatusSetSql($f) . " WHERE ticket_status_id = $id");

    $changes = [];
    $was = [
        'name' => (string) $row['ticket_status_name'], 'color' => ticketStatusColor($row['ticket_status_color']),
        'description' => trim((string) ($row['ticket_status_description'] ?? '')),
    ];
    foreach (['name' => 'name', 'color' => 'color', 'description' => 'description'] as $key => $label) {
        if ($was[$key] !== $f[$key]) {
            $changes[] = "$label from " . ($was[$key] !== '' ? $was[$key] : 'none') . ' to ' . ($f[$key] !== '' ? $f[$key] : 'none');
        }
    }
    if (intval($row['ticket_status_pauses_sla']) !== $f['pauses_sla']) {
        $changes[] = $f['pauses_sla'] ? 'the resolution clock now pauses' : 'the resolution clock now keeps running';
        ticketStatusSyncClocks($id);
    }
    $reply_was = intval($row['ticket_status_reply_status_id'] ?? 0);
    if (intval($row['ticket_status_client_reply'] ?? 1) !== $f['client_reply'] || $reply_was !== $f['reply_status_id']) {
        $changes[] = 'a client\'s reply now ' . ($f['reply_status_id'] ? 'moves the ticket to ' . (ticketStatusesAll()[$f['reply_status_id']]['ticket_status_name'] ?? $f['reply_status_id'])
            : ($f['client_reply'] ? 'moves the ticket back to Open' : 'leaves the ticket in it'));
    }
    if (intval($row['ticket_status_first_response'] ?? 0) !== $f['first_response']) {
        $changes[] = $f['first_response'] ? 'moving a ticket into it counts as the first response' . ($f['first_response'] === 2 ? ', after emailing the client that it is assigned' : '')
            : 'moving a ticket into it no longer counts as the first response';
    }
    if (intval($row['ticket_status_active']) !== $f['active']) {
        $changes[] = $f['active'] ? 'switched on' : 'switched off';
    }
    ticketStatusesAll(true);
    return $changes;
}

/* Bring the SLA clocks of the open tickets in a status in line with it - after its pause setting changes. */
function ticketStatusSyncClocks($ticket_status_id) {
    global $mysqli;
    $sql = mysqli_query($mysqli, "SELECT ticket_id FROM tickets WHERE ticket_status = " . intval($ticket_status_id) . " AND ticket_closed_at IS NULL AND ticket_archived_at IS NULL");
    while ($ticket = mysqli_fetch_row($sql)) {
        syncTicketSlaClock(intval($ticket[0]));
    }
}

/*
 * Move every ticket in one status to another - as when the status is
 * switched off or deleted. Each says so in its history, and its SLA clock
 * follows the new status. How many moved.
 */
function ticketStatusMoveTickets($from_id, $to_id, $why) {
    global $mysqli, $session_name;
    $from_id = intval($from_id);
    $to_id = intval($to_id);
    $statuses = ticketStatusesAll();
    if (!isset($statuses[$from_id], $statuses[$to_id]) || $from_id === $to_id) {
        return 0;
    }
    $from_name = escapeSql($statuses[$from_id]['ticket_status_name']);
    $to_name = escapeSql($statuses[$to_id]['ticket_status_name']);
    $ids = [];
    $sql = mysqli_query($mysqli, "SELECT ticket_id FROM tickets WHERE ticket_status = $from_id");
    while ($ticket = mysqli_fetch_row($sql)) {
        $ids[] = intval($ticket[0]);
    }
    foreach ($ids as $ticket_id) {
        mysqli_query($mysqli, "UPDATE tickets SET ticket_status = $to_id WHERE ticket_id = $ticket_id AND ticket_status = $from_id");
        syncTicketSlaClock($ticket_id);
        // $session_name is SQL-safe already
        logTicketHistory($ticket_id, ($session_name ?? 'ITFlow') . " moved it from $from_name to $to_name - " . escapeSql($why));
    }
    return count($ids);
}

/*
 * Switch a status off or on. Switching off can move its tickets to another
 * status first ($move_to, 0 to leave them). Built-in statuses stay on.
 * [ok, how many tickets moved].
 */
function ticketStatusSetActive(array $row, $active, $move_to = 0) {
    global $mysqli;
    $id = intval($row['ticket_status_id']);
    if (!$active && ticketStatusIsBuiltIn($id)) {
        return [false, 0];
    }
    $moved = 0;
    if (!$active && intval($move_to) && isset(ticketStatusMoveTargets($id)[intval($move_to)])) {
        $moved = ticketStatusMoveTickets($id, $move_to, 'the status was switched off');
    }
    mysqli_query($mysqli, "UPDATE ticket_statuses SET ticket_status_active = " . ($active ? 1 : 0) . " WHERE ticket_status_id = $id");
    ticketStatusesAll(true);
    return [true, $moved];
}

/*
 * Delete one of your own statuses. Its tickets have to go somewhere -
 * $move_to, one of ticketStatusMoveTargets() - and canned responses that set
 * it leave the status as it is instead. [ok, tickets moved, responses changed].
 */
function ticketStatusDelete(array $row, $move_to) {
    global $mysqli;
    $id = intval($row['ticket_status_id']);
    if (ticketStatusIsBuiltIn($id)) {
        return [false, 0, 0];
    }
    $counts = ticketStatusTicketCounts()[$id] ?? ['all' => 0];
    $move_to = intval($move_to);
    if ($counts['all'] && !isset(ticketStatusMoveTargets($id)[$move_to])) {
        return [false, 0, 0];
    }
    $moved = $counts['all'] ? ticketStatusMoveTickets($id, $move_to, 'the status was deleted') : 0;
    $canned = ticketStatusCannedCount($id);
    mysqli_query($mysqli, "UPDATE canned_responses SET canned_response_ticket_status = NULL WHERE canned_response_ticket_status = $id");
    // Statuses a client's reply moved to it go back to what their own setting says
    if (ticketStatusReplyReady()) {
        mysqli_query($mysqli, "UPDATE ticket_statuses SET ticket_status_reply_status_id = 0 WHERE ticket_status_reply_status_id = $id");
    }
    mysqli_query($mysqli, "DELETE FROM ticket_statuses WHERE ticket_status_id = $id");
    ticketStatusesAll(true);
    return [true, $moved, $canned];
}

/*
 * Put the statuses in this order - the ids as listed, then any others in
 * the order they had. Numbered from 1. How many moved.
 */
function ticketStatusReorder(array $ids) {
    global $mysqli;
    $rows = [];
    foreach (ticketStatusesAll(true) as $id => $row) {
        $rows[$id] = intval($row['ticket_status_order']);
    }
    $ordered = [];
    foreach ($ids as $id) {
        $id = intval($id);
        if (isset($rows[$id]) && !in_array($id, $ordered, true)) {
            $ordered[] = $id;
        }
    }
    foreach (array_keys($rows) as $id) {
        if (!in_array($id, $ordered, true)) {
            $ordered[] = $id;
        }
    }
    $moved = 0;
    foreach ($ordered as $position => $id) {
        if ($rows[$id] !== $position + 1) {
            mysqli_query($mysqli, "UPDATE ticket_statuses SET ticket_status_order = " . ($position + 1) . " WHERE ticket_status_id = $id");
            $moved++;
        }
    }
    ticketStatusesAll(true);
    return $moved;
}

/*
 * The status a ticket goes to when its client replies - by email, in the
 * portal or by text: the one its status sends replies to (a "Client
 * responded" status, say) while that is still in use; else back to Open,
 * unless it is in one of your own statuses set to leave it there (Scheduled,
 * say, where a "thanks, see you Tuesday" shouldn't send it back to the queue).
 */
function ticketStatusAfterClientReply($current_status_id) {
    $current_status_id = intval($current_status_id);
    $reply_to = intval(ticketStatusesAll()[$current_status_id]['ticket_status_reply_status_id'] ?? 0);
    if ($reply_to && $current_status_id !== TICKET_STATUS_CLOSED && isset(ticketStatusReplyTargets($current_status_id)[$reply_to])) {
        return $reply_to;
    }
    if (ticketStatusIsBuiltIn($current_status_id)) {
        return TICKET_STATUS_OPEN;
    }
    $row = ticketStatusesAll()[$current_status_id] ?? null;
    if ($row && intval($row['ticket_status_active']) === 1 && intval($row['ticket_status_client_reply']) === 0) {
        return $current_status_id;
    }
    return TICKET_STATUS_OPEN;
}

/*
 * ---------- moving into a status as the first response ----------
 */

/** The "ticket assigned" message as Admin > Settings > Tickets has it - plain text with placeholders - or the built-in one. */
function ticketAssignedMessage() {
    global $mysqli;
    if (!ticketStatusReplyReady()) {
        return TICKET_ASSIGNED_MESSAGE;
    }
    $text = (string) (mysqli_fetch_row(mysqli_query($mysqli, "SELECT config_ticket_assigned_message FROM settings WHERE company_id = 1"))[0] ?? '');
    return trim($text) !== '' ? $text : TICKET_ASSIGNED_MESSAGE;
}

/*
 * An agent moved a ticket from $from_status into $to_status - set by hand, in
 * a reply, by assigning it, or as it was made. When $to_status counts as the
 * first response (ticket_status_first_response) and nobody has answered the
 * client yet, it is recorded as the first response (setTicketFirstResponse(),
 * met or missed against the response target). When the status says so, the
 * client is emailed the "ticket assigned" message first - kept on the ticket
 * as a public note - and it only counts once someone got it. A client's own
 * reply moving the ticket is not this. Whether it counted.
 */
function ticketStatusMovedByAgent($ticket_id, $from_status, $to_status) {
    global $mysqli, $session_user_id, $session_name, $config_ticket_client_general_notifications;
    $ticket_id = intval($ticket_id);
    $to_status = intval($to_status);
    if (!$ticket_id || intval($from_status) === $to_status || !ticketStatusReplyReady() || !ticketStatusCanBeFirstResponse($to_status)) {
        return false;
    }
    $mode = intval(ticketStatusesAll()[$to_status]['ticket_status_first_response'] ?? 0);
    if (!$mode) {
        return false;
    }
    $ticket = ticketRow($ticket_id);
    if (!$ticket || !empty($ticket['ticket_first_response_at']) || !empty($ticket['ticket_resolved_at']) || !empty($ticket['ticket_closed_at'])
        || intval($ticket['ticket_status']) !== $to_status) {
        return false;
    }
    $status_name = escapeSql((string) $ticket['ticket_status_name']);

    if ($mode === 2) {
        // The message as a public note - from whoever moved it - then to the contact and watchers
        $agent_name = intval($ticket['ticket_assigned_to']) ? stripslashes((string) $ticket['user_name']) : '';
        $html = cannedResponsePlaceholders(nl2br(escapeHtml(trim(ticketAssignedMessage()))), $ticket_id, $agent_name !== '' ? $agent_name : 'our team');
        $by = intval($session_user_id ?? 0) ?: intval($ticket['ticket_assigned_to']);
        mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = '" . mysqli_real_escape_string($mysqli, $html) . "', ticket_reply_type = 'Public',
            ticket_reply_time_worked = '00:00:00', ticket_reply_by = $by, ticket_reply_ticket_id = $ticket_id");
        $reply_id = intval(mysqli_insert_id($mysqli));
        $sent = 0;
        if (intval($config_ticket_client_general_notifications) === 1) {
            $sent = ticketMailSend($ticket_id, ($agent_name !== '' ? 'Ticket assigned' : 'Ticket update') . " - [" . ticketReference($ticket) . "] - " . $ticket['ticket_subject'],
                ticketEmailContent($ticket, 'assigned', ['message' => $html, 'agent' => $agent_name]), ['ticket_reply_id' => $reply_id]);
        }
        if ($sent < 1) {
            // Nobody got it - a note saying they were told would be wrong
            mysqli_query($mysqli, "DELETE FROM ticket_replies WHERE ticket_reply_id = $reply_id");
            logTicketHistory($ticket_id, "Moved to $status_name, but the client could not be emailed that it is assigned - the response target is still open");
            return false;
        }
        ticketReplyEmailed($reply_id, true);
        setTicketFirstResponse($ticket_id);
        logTicketHistory($ticket_id, "The client was told it is assigned ($sent emailed) - moving to $status_name counts as the first response");
        return true;
    }

    setTicketFirstResponse($ticket_id);
    logTicketHistory($ticket_id, "Moving to $status_name counts as the first response");
    return true;
}
