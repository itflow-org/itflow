<?php

/*
 * ITFlow - additional agents on a ticket: helpers (database 3.8.5, ticket_helpers).
 *
 * The ticket's assigned agent stays the lead (tickets.ticket_assigned_to - nothing
 * that reads it changes). Other agents can be added to help, each booked at the
 * same time as the lead (follows_lead - it moves and is cancelled with the lead's
 * booking), at a time of their own (schedule_start / schedule_end), or not booked.
 * A helper finds the ticket under Mine and in their lists, sees their booking on
 * the calendar, in their calendar link and on the dashboard, gets the client's
 * replies by email, and is sent their own booking with its calendar file - its
 * UID their own, so it never replaces the lead's. The client's emails stay about
 * the lead's booking.
 *
 * Helpers are the agents who can open the ticket (ticketMentionAgents()). A helper
 * row for whoever is now the lead is left out everywhere and cleared by
 * ticketHelpersTidy() - the lead changes in several places, none of which need to
 * know about helpers.
 */

/** A helper's booking - the lead's when they follow the lead, else their own (ticket_helpers as th). */
const TICKET_HELPER_START_SQL = "IF(th.follows_lead = 1, tickets.ticket_schedule, th.schedule_start)";
const TICKET_HELPER_END_SQL = "IF(th.follows_lead = 1, tickets.ticket_schedule_end, th.schedule_end)";

/** Whether the 3.8.5 update has run. */
function ticketHelpersReady() {
    global $mysqli;
    static $ready = null;
    if ($ready === null) {
        if (defined('CURRENT_DATABASE_VERSION')) {
            $version = CURRENT_DATABASE_VERSION;
        } else {
            $result = mysqli_query($mysqli, "SELECT config_current_database_version FROM settings WHERE company_id = 1");
            $version = $result && ($row = mysqli_fetch_row($result)) ? (string) $row[0] : '0';
        }
        $ready = version_compare($version, '3.8.5', '>=');
    }
    return $ready;
}

/*
 * A helper who has since become the lead is no longer a helper - cleared once a
 * request, before helpers are read. A booking they had as a helper is cancelled,
 * and as the lead they are sent the ticket's own, unless that just went to them.
 */
function ticketHelpersTidy() {
    global $mysqli;
    static $done = false;
    if ($done || !ticketHelpersReady()) {
        return;
    }
    $done = true;
    $sql = mysqli_query($mysqli, "SELECT th.ticket_id, th.user_id, " . TICKET_HELPER_START_SQL . " AS start FROM ticket_helpers th
        JOIN tickets ON tickets.ticket_id = th.ticket_id WHERE th.user_id = tickets.ticket_assigned_to");
    $rows = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[] = $row;
    }
    if (!$rows) {
        return;
    }
    mysqli_query($mysqli, "DELETE th FROM ticket_helpers th JOIN tickets ON tickets.ticket_id = th.ticket_id WHERE th.user_id = tickets.ticket_assigned_to");
    foreach ($rows as $row) {
        if (!empty($row['start'])) {
            ticketHelperMail($row['ticket_id'], $row['user_id'], $row['start'], null, 'cancelled');
            ticketHelperLeadInvite($row['ticket_id']);
        }
    }
}

/** A ticket's helpers (not the lead), by name, with their booking: user_id => row, start and end '' when not booked. */
function ticketHelpers($ticket_id) {
    global $mysqli;
    $ticket_id = intval($ticket_id);
    if (!$ticket_id || !ticketHelpersReady()) {
        return [];
    }
    ticketHelpersTidy();
    $sql = mysqli_query($mysqli, "SELECT th.ticket_id, th.user_id, th.follows_lead, th.schedule_start, th.schedule_end, th.added_by, th.added_at,
            user_name, user_email, user_avatar, " . TICKET_HELPER_START_SQL . " AS start, " . TICKET_HELPER_END_SQL . " AS end
        FROM ticket_helpers th
        JOIN tickets ON tickets.ticket_id = th.ticket_id
        JOIN users ON users.user_id = th.user_id
        WHERE th.ticket_id = $ticket_id AND th.user_id <> tickets.ticket_assigned_to AND users.user_archived_at IS NULL
        ORDER BY user_name");
    $helpers = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $row['end'] = $row['start'] ? ticketScheduleEnd($row['start'], $row['end']) : '';
        $row['start'] = (string) $row['start'];
        $helpers[intval($row['user_id'])] = $row;
    }
    return $helpers;
}

/** Every ticket's helpers, ticket_id => [user_id => name] - read once a page, for the lists. */
function ticketHelperNames() {
    global $mysqli;
    static $names = null;
    if ($names === null) {
        $names = [];
        if (ticketHelpersReady()) {
            ticketHelpersTidy();
            $sql = mysqli_query($mysqli, "SELECT th.ticket_id, th.user_id, user_name FROM ticket_helpers th
                JOIN tickets ON tickets.ticket_id = th.ticket_id
                JOIN users ON users.user_id = th.user_id
                WHERE th.user_id <> tickets.ticket_assigned_to AND users.user_archived_at IS NULL ORDER BY user_name");
            while ($row = mysqli_fetch_assoc($sql)) {
                $names[intval($row['ticket_id'])][intval($row['user_id'])] = $row['user_name'];
            }
        }
    }
    return $names;
}

/** SQL: the ticket is this agent's - the lead, or helping. $id_column is how the query names the ticket's id. */
function ticketAssignedSql($user_id, $id_column = 'tickets.ticket_id') {
    $user_id = intval($user_id);
    if (!ticketHelpersReady()) {
        return "ticket_assigned_to = $user_id";
    }
    return "(ticket_assigned_to = $user_id OR $id_column IN (SELECT th.ticket_id FROM ticket_helpers th WHERE th.user_id = $user_id))";
}

/** A helper's booking in words: "Same time as the lead", a time, or "Not booked". */
function ticketHelperWhenText(array $helper) {
    if (intval($helper['follows_lead']) === 1) {
        return $helper['start'] !== '' ? 'Same time as the lead' : 'With the lead - not booked yet';
    }
    return $helper['start'] !== '' ? ticketScheduleShortText($helper['start'], $helper['end']) : 'Not booked';
}

/** The tickets list's assignee cell: "+2" after the lead - "Helping" when you are one of them. */
function ticketHelpersBadge($ticket_id) {
    global $session_user_id;
    $names = ticketHelperNames()[intval($ticket_id)] ?? [];
    if (!$names) {
        return '';
    }
    $mine = isset($names[intval($session_user_id)]);
    return ' <span class="badge rounded-pill ' . ($mine ? 'text-bg-primary' : 'bg-body-secondary text-body-secondary border') . ' align-middle" title="' . escapeHtml(($mine ? 'You are helping. ' : '') . 'Also on it: ' . implode(', ', $names)) . '">'
        . ($mine ? '<i class="fas fa-user-friends me-1"></i>Helping' : '+' . count($names)) . '</span>';
}

/*
 * ---------- adding, changing and taking off ----------
 */

/*
 * Add a helper, or change their booking. $mode: lead (the lead's time), own
 * ($start to $end) or none. They are emailed their booking, its cancellation, or
 * that they are on it, and it is noted on the ticket. [ok, message (plain text),
 * conflicts - their other work at that time].
 */
function ticketHelperSave(array $ticket, $user_id, $mode, $start = '', $end = '') {
    global $mysqli, $session_user_id, $session_name;
    $ticket_id = intval($ticket['ticket_id']);
    $user_id = intval($user_id);
    $who = stripslashes((string) $session_name);
    if (!ticketHelpersReady()) {
        return [false, 'The database update for additional agents has not run yet.', []];
    }
    $agents = ticketMentionAgents($ticket['ticket_client_id']);
    if (!isset($agents[$user_id])) {
        return [false, 'Pick an agent who can open this ticket.', []];
    }
    $name = $agents[$user_id]['name'];
    if ($user_id === intval($ticket['ticket_assigned_to'])) {
        return [false, "$name is the lead agent on this ticket.", []];
    }
    $mode = in_array($mode, ['lead', 'own', 'none'], true) ? $mode : 'lead';
    if ($mode === 'own' && ($start === '' || $end === '' || $end <= $start)) {
        return [false, "Pick the date and time for $name.", []];
    }

    $before = ticketHelpers($ticket_id)[$user_id] ?? null;
    $follows = $mode === 'lead' ? 1 : 0;
    $start_sql = $mode === 'own' ? "'" . escapeSql($start) . "'" : 'NULL';
    $end_sql = $mode === 'own' ? "'" . escapeSql($end) . "'" : 'NULL';
    mysqli_query($mysqli, "INSERT INTO ticket_helpers SET ticket_id = $ticket_id, user_id = $user_id, follows_lead = $follows,
            schedule_start = $start_sql, schedule_end = $end_sql, added_by = " . intval($session_user_id) . "
        ON DUPLICATE KEY UPDATE follows_lead = $follows, schedule_start = $start_sql, schedule_end = $end_sql");
    $after = ticketHelpers($ticket_id)[$user_id] ?? null;

    $when = $after && $after['start'] !== '' ? ticketScheduleWhenText($after['start'], $after['end']) : '';
    $conflicts = $when !== '' ? ticketScheduleConflicts($user_id, $after['start'], $after['end'], $ticket_id) : [];

    // Tell them - their booking, new or moved; its cancellation; or that they are on it
    $was_booked = $before && $before['start'] !== '';
    $changed = !$before || $before['start'] !== ($after['start'] ?? '') || $before['end'] !== ($after['end'] ?? '');
    if ($when !== '' && $changed) {
        ticketHelperMail($ticket_id, $user_id, $after['start'], $after['end'], 'booked');
    } elseif ($when === '' && $was_booked) {
        ticketHelperMail($ticket_id, $user_id, $before['start'], $before['end'], 'cancelled');
    } elseif (!$before) {
        ticketHelperMail($ticket_id, $user_id, '', '', 'added');
    }

    $what = $mode === 'lead' ? 'at the same time as the lead' . ($when !== '' ? " ($when)" : '') : ($mode === 'own' ? "for $when" : 'not booked');
    $note = $before ? "$name's booking changed - $what." : "$name added as an additional agent - $what.";
    mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = '" . escapeSql($note) . "', ticket_reply_type = 'Internal', ticket_reply_time_worked = '00:00:00', ticket_reply_by = " . intval($session_user_id) . ", ticket_reply_ticket_id = $ticket_id");
    logTicketHistory($ticket_id, escapeSql("$who " . ($before ? "changed $name's booking - $what" : "added $name as an additional agent - $what")));
    logAudit("Ticket", "Edit", escapeSql("$who " . ($before ? "changed $name's booking on " : "added $name to ") . ticketReference($ticket)), intval($ticket['ticket_client_id']), $ticket_id);

    // Told in ITFlow too, when someone else added them
    if (!$before && $user_id !== intval($session_user_id)) {
        mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = 'Ticket', notification = '" . escapeSql('Ticket ' . ticketReference($ticket) . ' - ' . $ticket['ticket_subject'] . " - $who added you to help") . "',
            notification_action = '/agent/" . escapeSql(ticketUrl($ticket_id, $ticket['ticket_client_id'])) . "', notification_client_id = " . intval($ticket['ticket_client_id']) . ", notification_user_id = $user_id");
    }

    return [true, $before ? "$name's booking is saved" : "$name added to the ticket", $conflicts];
}

/** Take a helper off a ticket - a booking they had is cancelled. Their name, or false when they weren't on it. */
function ticketHelperRemove(array $ticket, $user_id) {
    global $mysqli, $session_user_id, $session_name;
    $ticket_id = intval($ticket['ticket_id']);
    $user_id = intval($user_id);
    $who = stripslashes((string) $session_name);
    $helper = ticketHelpers($ticket_id)[$user_id] ?? null;
    if (!$helper) {
        return false;
    }
    mysqli_query($mysqli, "DELETE FROM ticket_helpers WHERE ticket_id = $ticket_id AND user_id = $user_id");
    if ($helper['start'] !== '') {
        ticketHelperMail($ticket_id, $user_id, $helper['start'], $helper['end'], 'cancelled');
    }
    $name = $helper['user_name'];
    mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = '" . escapeSql("$name taken off the ticket.") . "', ticket_reply_type = 'Internal', ticket_reply_time_worked = '00:00:00', ticket_reply_by = " . intval($session_user_id) . ", ticket_reply_ticket_id = $ticket_id");
    logTicketHistory($ticket_id, escapeSql("$who took $name off the ticket"));
    logAudit("Ticket", "Edit", escapeSql("$who took $name off " . ticketReference($ticket)), intval($ticket['ticket_client_id']), $ticket_id);
    return $name;
}

/*
 * ---------- the lead's booking (ticketSetSchedule(), ticketCancelSchedule()) ----------
 */

/** The lead's booking moved: helpers who follow it are sent the new time. Their clashes, "Name: ..." lines. */
function ticketHelpersLeadMoved($ticket_id) {
    $conflicts = [];
    foreach (ticketHelpers($ticket_id) as $user_id => $helper) {
        if (intval($helper['follows_lead']) === 1 && $helper['start'] !== '') {
            ticketHelperMail($ticket_id, $user_id, $helper['start'], $helper['end'], 'booked');
            foreach (ticketScheduleConflicts($user_id, $helper['start'], $helper['end'], $ticket_id) as $line) {
                $conflicts[] = $helper['user_name'] . ': ' . $line;
            }
        }
    }
    return $conflicts;
}

/** The lead's booking cancelled: helpers who followed it are sent the cancellation. */
function ticketHelpersLeadCancelled($ticket_id, $old_start) {
    if (!$old_start) {
        return;
    }
    foreach (ticketHelpers($ticket_id) as $user_id => $helper) {
        if (intval($helper['follows_lead']) === 1) {
            ticketHelperMail($ticket_id, $user_id, $old_start, '', 'cancelled');
        }
    }
}

/*
 * ---------- emails ----------
 */

/** A helper's booking's calendar UID - their own, so it never replaces the lead's. */
function ticketHelperUid($ticket_id, $user_id) {
    return 'ticket-' . intval($ticket_id) . '-helper-' . intval($user_id) . '@' . ($_SERVER['SERVER_NAME'] ?? 'itflow');
}

/*
 * Email a helper - $kind booked (with the calendar file), cancelled (its
 * cancellation) or added (on it, not booked). Nothing new goes to one who can
 * no longer open the ticket; a cancellation still does, to clear their calendar.
 */
function ticketHelperMail($ticket_id, $user_id, $start, $end, $kind) {
    global $session_name;
    if (!emailReady()) {
        return;
    }
    $ticket = ticketRow($ticket_id);
    $agent = ticketMailAgent($user_id);
    if (!$ticket || !$agent) {
        return;
    }
    if ($kind !== 'cancelled' && !isset(ticketMentionAgents($ticket['ticket_client_id'])[intval($user_id)])) {
        return;
    }
    $reference = ticketReference($ticket);
    $mail_row = ticketScheduleRow($ticket_id);
    $title = intval($ticket['ticket_number']) . ": " . $ticket['client_name'] . " - " . $ticket['ticket_subject'];
    $opts = ['lead' => (string) ($ticket['user_name'] ?? ''), 'by' => stripslashes((string) $session_name)];
    $cal_str = '';
    if ($kind === 'booked') {
        // On their own clock (functions/user_timezone.php)
        $opts['when'] = timezoneWhenFor($start, $end, $user_id);
        $opts['where'] = (string) ($mail_row['location_address'] ?? '');
        $subject = "Ticket Scheduled - [$reference] - " . $ticket['ticket_subject'];
        $cal_str = createiCalStr($start, $title, mb_substr(strip_tags((string) $ticket['ticket_details']), 0, 100) . ' - ' . ticketAgentUrl($ticket),
            (string) ($mail_row['location_address'] ?? ''), ticketHelperUid($ticket_id, $user_id), ticketScheduleEnd($start, $end));
    } elseif ($kind === 'cancelled') {
        $opts['cancelled'] = true;
        $subject = "Ticket Schedule Cancelled - [$reference] - " . $ticket['ticket_subject'];
        $cal_str = createiCalStrCancel($start, $title, ticketHelperUid($ticket_id, $user_id));
    } else {
        $opts['added'] = true;
        $subject = "Ticket - [$reference] - " . $ticket['ticket_subject'];
    }
    emailQueue([$agent + ['footnote' => "You get this as an agent helping on $reference."]], $subject,
        ticketAgentEmailContent($ticket, 'helper', $opts), ['from' => ticketEmailFrom()] + ($cal_str !== '' ? ['cal_str' => $cal_str] : []));
}

/*
 * The ticket's own booking, to its lead alone - a helper who became the lead
 * (ticketHelpersTidy()) - unless it went to them in the last 10 minutes.
 */
function ticketHelperLeadInvite($ticket_id) {
    global $mysqli;
    $ticket = ticketRow($ticket_id);
    $agent = $ticket ? ticketMailAgent($ticket['ticket_assigned_to']) : null;
    if (!$ticket || !$agent || empty($ticket['ticket_schedule']) || !ticketIsOpen($ticket) || !emailReady()) {
        return;
    }
    $reference = ticketReference($ticket);
    $subject = "Ticket Scheduled - [$reference] - " . $ticket['ticket_subject'];
    if (mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM email_queue WHERE email_recipient = '" . escapeSql($agent['email']) . "' AND email_subject = '" . escapeSql($subject) . "'
        AND email_queued_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE) AND email_cal_str LIKE '%UID:" . escapeSql(getTicketCalendarUid($ticket['ticket_id'])) . "%' LIMIT 1"))) {
        return;
    }
    $mail_row = ticketScheduleRow($ticket['ticket_id']);
    $end = ticketScheduleEnd($ticket['ticket_schedule'], $ticket['ticket_schedule_end'] ?? null);
    emailQueue([$agent + ['footnote' => "You get this as the agent $reference is assigned to."]], $subject,
        ticketAgentEmailContent($ticket, 'scheduled', ['when' => timezoneWhenFor($ticket['ticket_schedule'], $end, $ticket['ticket_assigned_to']), 'where' => (string) ($mail_row['location_address'] ?? '')]),
        ['from' => ticketEmailFrom(), 'cal_str' => ticketScheduleIcs($mail_row, $ticket['ticket_schedule'], $end)]);
}

/*
 * A client replied (ticketMailAssigneeReplied()): the same email the lead gets,
 * to each helper who can open the ticket. As for the lead, a ticket nobody
 * leads mails nobody. How many went.
 */
function ticketHelpersMailReplied(array $row, array $lead, $how, $reply, $by) {
    global $config_app_name;
    // Switched off in Admin > Settings > Notifications - the email preview still shows it
    if (!ticketHelpersReady() || (!ticketTeamNotifyOn('helper_replies') && !emailCapturing())) {
        return 0;
    }
    $agents = ticketMentionAgents($row['ticket_client_id']);
    $recipients = [];
    foreach (ticketHelpers($row['ticket_id']) as $user_id => $helper) {
        if (!isset($agents[$user_id]) || !filter_var($helper['user_email'], FILTER_VALIDATE_EMAIL) || strcasecmp($helper['user_email'], $lead['email']) === 0) {
            continue;
        }
        $recipients[] = ['email' => $helper['user_email'], 'name' => $helper['user_name'], 'footnote' => 'You get this as an agent helping on ' . ticketReference($row) . '.'];
    }
    if (!$recipients) {
        return 0;
    }
    return ticketMailAgents($recipients, ($config_app_name ?: 'ITFlow') . " - Ticket updated - [" . ticketReference($row) . "] " . $row['ticket_subject'],
        ticketAgentEmailContent($row, 'replied', ['how' => $how, 'message' => (string) $reply, 'by' => (string) $by]));
}

/*
 * ---------- calendar, dashboard and clashes ----------
 */

/*
 * Helpers' bookings for the calendar (the tickets source) - in the helper's
 * colour, with their name. $user_id: only that agent's (0 for everyone's).
 */
function ticketHelperCalendarItems($from, $to, $client_id, $user_id, $can_move) {
    global $mysqli;
    if (!ticketHelpersReady()) {
        return [];
    }
    ticketHelpersTidy();
    $from = escapeSql($from);
    $to = escapeSql($to);
    $client_id = intval($client_id);
    $user_id = intval($user_id);
    $start = TICKET_HELPER_START_SQL;
    $end = TICKET_HELPER_END_SQL;
    $sql = mysqli_query($mysqli, "SELECT tickets.ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_onsite, ticket_client_id,
            ticket_resolved_at, ticket_closed_at, client_name, ticket_status_name, th.user_id, th.follows_lead, helper.user_name, lead.user_name AS lead_name,
            $start AS start, $end AS end
        FROM ticket_helpers th
        JOIN tickets ON tickets.ticket_id = th.ticket_id
        JOIN users helper ON helper.user_id = th.user_id
        LEFT JOIN users lead ON lead.user_id = ticket_assigned_to
        LEFT JOIN clients ON client_id = ticket_client_id
        LEFT JOIN ticket_statuses ON ticket_status_id = ticket_status
        WHERE $start BETWEEN DATE_SUB('$from', INTERVAL 1 DAY) AND '$to'
        AND COALESCE(IF($end > $start, $end, NULL), DATE_ADD($start, INTERVAL " . intval(CALENDAR_TICKET_MINUTES) . " MINUTE)) > '$from'
        AND ticket_archived_at IS NULL AND th.user_id <> ticket_assigned_to AND helper.user_archived_at IS NULL
        " . ($client_id ? "AND ticket_client_id = $client_id" : '') . "
        " . ($user_id ? "AND th.user_id = $user_id" : '') . "
        " . clientScopeSql('ticket_client_id'));
    $items = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $open = ticketIsOpen($row);
        $ticket_id = intval($row['ticket_id']);
        $items[] = [
            'id' => "helper-$ticket_id-" . intval($row['user_id']),
            'title' => calendarTicketTitle($row, !$client_id) . ' (helping)',
            'start' => $row['start'],
            'end' => ticketScheduleEnd($row['start'], $row['end']),
            'allDay' => false,
            'editable' => $can_move && $open,
            'durationEditable' => $can_move && $open,
            'color' => calendarAgentColor($row['user_id']),
            'className' => $open ? '' : 'itflow-cal-done',
            'extendedProps' => ['kind' => 'helper', 'ticket_id' => $ticket_id, 'user_id' => intval($row['user_id']), 'follows_lead' => intval($row['follows_lead']),
                'client_id' => intval($row['ticket_client_id']),
                'icon' => intval($row['ticket_onsite']) ? 'fa-car' : 'fa-headset',
                'modal' => "modals/ticket/ticket.php?id=$ticket_id",
                'agent_name' => calendarFirstName($row['user_name']), 'agent_full' => $row['user_name'],
                'detail' => implode(' · ', array_filter([$row['lead_name'] ? 'Helping ' . $row['lead_name'] : 'Helping', $row['ticket_status_name']]))],
        ];
    }
    return $items;
}

/* Helpers' bookings for a calendar link (calendarUserFeedItems()) - theirs, or everyone's for a feed set to everyone. */
function ticketHelperFeedItems(array $feed, $from, $to) {
    global $mysqli;
    if (!ticketHelpersReady()) {
        return [];
    }
    $user_id = intval($feed['feed_user_id']);
    $everyone = ($feed['feed_scope'] ?? 'mine') === 'everyone';
    $host = calendarHost();
    $from = escapeSql($from);
    $to = escapeSql($to);
    $start = TICKET_HELPER_START_SQL;
    $sql = mysqli_query($mysqli, "SELECT tickets.ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_onsite, ticket_client_id,
            ticket_created_at, ticket_updated_at, ticket_resolved_at, ticket_closed_at, client_name, th.user_id, helper.user_name,
            location_name, location_address, location_city, location_state, location_zip,
            $start AS start, " . TICKET_HELPER_END_SQL . " AS end
        FROM ticket_helpers th
        JOIN tickets ON tickets.ticket_id = th.ticket_id
        JOIN users helper ON helper.user_id = th.user_id
        LEFT JOIN clients ON client_id = ticket_client_id
        LEFT JOIN contacts ON contact_id = ticket_contact_id AND ticket_contact_id > 0
        LEFT JOIN locations ON location_id = IF(ticket_location_id > 0, ticket_location_id, contacts.contact_location_id)
        WHERE $start BETWEEN '$from' AND '$to' AND ticket_archived_at IS NULL AND th.user_id <> ticket_assigned_to
        " . ($everyone ? '' : "AND th.user_id = $user_id") . "
        " . clientScopeSql('ticket_client_id'));
    $items = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $ticket_id = intval($row['ticket_id']);
        $link = "https://$host/agent/" . ticketUrl($ticket_id, $row['ticket_client_id']);
        $items[] = [
            'uid' => "itflow-ticket-$ticket_id-helper-" . intval($row['user_id']) . "@$host",
            'start' => $row['start'],
            'end' => ticketScheduleEnd($row['start'], $row['end']),
            'summary' => calendarTicketTitle($row) . ' (' . ($everyone ? $row['user_name'] . ' ' : '') . 'helping)' . (ticketIsOpen($row) ? '' : ' [done]'),
            'location' => implode(', ', array_filter([$row['location_address'], $row['location_city'], trim($row['location_state'] . ' ' . $row['location_zip'])])),
            'description' => implode("\n", array_filter([$row['client_name'] ? 'Client: ' . $row['client_name'] : '', $row['location_name'] ? 'Site: ' . $row['location_name'] : '', 'Helping: ' . $row['user_name'], $link])),
            'url' => $link,
            'stamp' => $row['ticket_updated_at'] ?: $row['ticket_created_at'],
            'categories' => intval($row['ticket_onsite']) ? 'Onsite' : 'Remote',
            'busy' => !empty($feed['feed_busy_only']),
        ];
    }
    return $items;
}

/** The dashboard's My schedule: the open tickets I am helping on, booked in the window, in its item shape. */
function ticketHelperDashboardItems($from, $to) {
    global $session_user_id;
    $items = [];
    foreach (ticketHelperCalendarItems($from, $to, 0, $session_user_id, false) as $item) {
        if (strpos((string) ($item['className'] ?? ''), 'itflow-cal-done') !== false) {
            continue;
        }
        $props = $item['extendedProps'];
        $items[] = [
            'when' => $item['start'], 'all_day' => false, 'ongoing' => false,
            'title' => $item['title'], 'ref' => '', 'detail' => $props['detail'],
            'onsite' => $props['icon'] === 'fa-car', 'color' => null,
            'url' => '/agent/' . ticketUrl($props['ticket_id'], $props['client_id']),
        ];
    }
    return $items;
}

/** An agent's open helper bookings that overlap $start - $end (ticketScheduleConflicts()), as text lines. */
function ticketHelperConflicts($user_id, $start, $end, $ticket_id = 0) {
    global $mysqli;
    if (!ticketHelpersReady()) {
        return [];
    }
    $user_id = intval($user_id);
    $ticket_id = intval($ticket_id);
    $start = escapeSql($start);
    $end = escapeSql($end);
    $s = TICKET_HELPER_START_SQL;
    $e = TICKET_HELPER_END_SQL;
    $sql = mysqli_query($mysqli, "SELECT ticket_prefix, ticket_number, ticket_subject, ticket_client_id, $s AS start, $e AS end FROM ticket_helpers th
        JOIN tickets ON tickets.ticket_id = th.ticket_id
        WHERE th.user_id = $user_id AND th.ticket_id <> $ticket_id AND th.user_id <> ticket_assigned_to
        AND ticket_resolved_at IS NULL AND ticket_closed_at IS NULL AND ticket_archived_at IS NULL
        AND $s IS NOT NULL AND $s < '$end'
        AND COALESCE(IF($e > $s, $e, NULL), DATE_ADD($s, INTERVAL " . intval(CALENDAR_TICKET_MINUTES) . " MINUTE)) > '$start'
        ORDER BY start");
    $lines = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $hidden = !empty($GLOBALS['session_user_id']) && intval($row['ticket_client_id']) && !hasClientAccess($row['ticket_client_id']);
        $what = $hidden ? 'Another booking (a client you can\'t see)' : ticketReference($row) . ' - ' . $row['ticket_subject'];
        $lines[] = "$what (helping) @ " . date('g:ia', strtotime($row['start'])) . '-' . date('g:ia', strtotime(ticketScheduleEnd($row['start'], $row['end'])));
    }
    return $lines;
}
