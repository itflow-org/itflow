<?php

/*
 * ITFlow - Dispatch (agent/dispatch.php, agent/js/dispatch.js, agent/ajax.php ?dispatch_*).
 *
 * Each agent's booked work beside the queue of open tickets still to give out:
 * those nobody has, those with an agent and no time booked, and those left
 * behind (booked on a day that has gone). Day is a column per agent, Week a row
 * per agent and a column per day. Dropping a ticket on an agent and a time - or
 * moving a booking to another agent or time - opens the Schedule window, then
 * assigns it (ticketChangeAssignee()) and books it (ticketSetSchedule()) as the
 * ticket's own Schedule window does. A booking dragged back onto the queue is
 * taken off the schedule. Workload is each agent's open tickets, SLAs and booked
 * hours, flagged when they fall behind or carry far more than the team; This
 * week is the week's tickets in and closed, SLA results and each agent's share.
 *
 * Seeing it takes Support at View; booking and moving takes Modify
 * (calendarCanScheduleTickets()). Which agents show, the view and weekends are
 * kept per agent with the calendar's (user_settings.user_config_calendar_state).
 */

/** Days without a reply from us after which an open ticket (not waiting on the client) counts as quiet. */
const DISPATCH_QUIET_DAYS = 3;

/** The lengths offered when booking, in minutes - a ticket's own is added when it isn't one of them. */
const DISPATCH_LENGTHS = [15, 30, 45, 60, 90, 120, 180, 240, 360, 480];

/** May the signed-in agent see Dispatch? */
function dispatchCan() {
    return lookupUserPermission('module_support') >= 1;
}

/** May they book, move and assign there? */
function dispatchCanEdit() {
    return calendarCanScheduleTickets();
}

/** The agents who can be dispatched to - active, and able to work tickets (Support at Modify, or an admin): user_id => [id, name, color]. */
function dispatchAgents() {
    global $mysqli;
    static $agents = null;
    if ($agents !== null) {
        return $agents;
    }
    $support_id = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT module_id FROM modules WHERE module_name = 'module_support' LIMIT 1"))[0] ?? 0);
    $sql = mysqli_query($mysqli, "SELECT user_id, user_name, role_is_admin,
            COALESCE((SELECT user_role_permission_level FROM user_role_permissions WHERE user_role_permissions.user_role_id = users.user_role_id AND module_id = $support_id LIMIT 1), 0) AS support_level
        FROM users LEFT JOIN user_roles ON role_id = user_role_id
        WHERE user_type = 1" . serviceAccountsOutSql() . " AND user_status = 1 AND user_archived_at IS NULL ORDER BY user_name");
    $agents = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        if (intval($row['role_is_admin']) === 1 || intval($row['support_level']) >= 2) {
            $agents[intval($row['user_id'])] = ['id' => intval($row['user_id']), 'name' => $row['user_name'], 'color' => calendarAgentColor($row['user_id'])];
        }
    }
    return $agents;
}

/** What the signed-in agent last had on Dispatch: view (day, week, workload), hidden (agent ids) and weekends. */
function dispatchState() {
    global $mysqli, $session_user_id;
    $state = [];
    if (calendarAgentSettingsReady()) {
        $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT user_config_calendar_state FROM user_settings WHERE user_id = " . intval($session_user_id)));
        $saved = json_decode((string) ($row[0] ?? ''), true);
        $state = is_array($saved['dispatch'] ?? null) ? $saved['dispatch'] : [];
    }
    return [
        'view' => in_array($state['view'] ?? '', ['week', 'workload'], true) ? $state['view'] : 'day',
        'hidden' => array_values(array_filter(array_map('intval', (array) ($state['hidden'] ?? [])))),
        'weekends' => !empty($state['weekends']),
    ];
}

/** Keep what Dispatch showed, with the calendar's. */
function dispatchStateSave(array $post) {
    global $mysqli, $session_user_id;
    $user_id = intval($session_user_id);
    if (!$user_id || !calendarAgentSettingsReady()) {
        return false;
    }
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT user_config_calendar_state FROM user_settings WHERE user_id = $user_id"));
    $saved = json_decode((string) ($row[0] ?? ''), true);
    $saved = is_array($saved) ? array_intersect_key($saved, ['all' => 1, 'client' => 1, 'dispatch' => 1]) : [];
    $saved['dispatch'] = [
        'view' => in_array($post['view'] ?? '', ['week', 'workload'], true) ? $post['view'] : 'day',
        'hidden' => array_slice(array_values(array_filter(array_map('intval', explode(',', (string) ($post['hidden'] ?? ''))))), 0, 500),
        'weekends' => !empty($post['weekends']),
    ];
    $json = mysqli_real_escape_string($mysqli, json_encode($saved));
    return (bool) mysqli_query($mysqli, "INSERT INTO user_settings SET user_id = $user_id, user_config_calendar_state = '$json' ON DUPLICATE KEY UPDATE user_config_calendar_state = '$json'");
}

/** No business days or hours in Admin > SLA - the SLA clocks run around the clock, and so does the board. */
function dispatchAroundTheClock() {
    $sla = getSlaSettings();
    return empty($sla['business_days']) || empty($sla['business_hours_start']) || empty($sla['business_hours_end'])
        || $sla['business_hours_start'] >= $sla['business_hours_end'];
}

/** The board's working hours, from Admin > SLA - FullCalendar's businessHours. */
function dispatchBusinessHours() {
    if (dispatchAroundTheClock()) {
        return ['daysOfWeek' => [0, 1, 2, 3, 4, 5, 6], 'startTime' => '00:00', 'endTime' => '24:00'];
    }
    $sla = getSlaSettings();
    return [
        // ISO weekdays (Monday 1 to Sunday 7) as FullCalendar's (Sunday 0 to Saturday 6)
        'daysOfWeek' => array_values(array_map(function ($day) {
            return $day % 7;
        }, $sla['business_days'])),
        'startTime' => substr((string) $sla['business_hours_start'], 0, 5),
        'endTime' => substr((string) $sla['business_hours_end'], 0, 5),
    ];
}

/** Working minutes a day and the working weekdays (ISO), from Admin > SLA - around the clock is still an 8-hour weekday for one person. */
function dispatchWorkingDay() {
    if (dispatchAroundTheClock()) {
        return [480, [1, 2, 3, 4, 5]];
    }
    $sla = getSlaSettings();
    [$sh, $sm] = array_map('intval', explode(':', substr((string) $sla['business_hours_start'], 0, 5)));
    [$eh, $em] = array_map('intval', explode(':', substr((string) $sla['business_hours_end'], 0, 5)));
    return [max(60, ($eh * 60 + $em) - ($sh * 60 + $sm)), $sla['business_days']];
}

/*
 * ---------- the queue and the board ----------
 */

/*
 * The queue: open tickets nobody has ('unassigned'), with an agent and nothing
 * booked ('unbooked'), or left behind ('left_behind', longest first) - most
 * urgent and oldest first. $filters: client_id, priority, q. With each tab's count.
 */
function dispatchQueue($tab, array $filters = []) {
    global $mysqli;
    // A project's tickets are left to the project while they are kept apart (functions/project_tickets.php)
    $where = TICKET_OPEN_SQL . " AND ticket_archived_at IS NULL" . clientScopeSql('ticket_client_id') . projectTicketsServiceSql('tickets.ticket_project_id');
    $client_id = intval($filters['client_id'] ?? 0);
    if ($client_id && hasClientAccess($client_id)) {
        $where .= " AND ticket_client_id = $client_id";
    }
    $priority = (string) ($filters['priority'] ?? '');
    if (in_array($priority, TICKET_PRIORITIES, true)) {
        $where .= " AND ticket_priority = '" . escapeSql($priority) . "'";
    }
    $q = trim((string) ($filters['q'] ?? ''));
    if ($q !== '') {
        $like = escapeSql(mb_substr($q, 0, 100));
        $where .= " AND (ticket_subject LIKE '%$like%' OR client_name LIKE '%$like%' OR CONCAT(ticket_prefix, ticket_number) LIKE '%$like%' OR ticket_number = " . intval($q) . ")";
    }
    $tabs = [
        'unassigned' => 'ticket_assigned_to = 0',
        'unbooked' => 'ticket_assigned_to > 0 AND ticket_schedule IS NULL',
        'left_behind' => calendarLeftBehindSql(),
    ];
    $tab = isset($tabs[$tab]) ? $tab : 'unassigned';
    $counts = [];
    foreach ($tabs as $key => $condition) {
        $counts[$key] = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM tickets LEFT JOIN clients ON client_id = ticket_client_id WHERE $where AND $condition"))[0]);
    }
    $sql = mysqli_query($mysqli, "SELECT tickets.ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_priority, ticket_onsite, ticket_client_id, ticket_created_at,
            ticket_schedule, " . (ticketScheduleEndReady() ? 'ticket_schedule_end' : 'NULL AS ticket_schedule_end') . ", ticket_assigned_to, client_name, user_name, ticket_status_name
        FROM tickets
        LEFT JOIN clients ON client_id = ticket_client_id
        LEFT JOIN users ON user_id = ticket_assigned_to
        LEFT JOIN ticket_statuses ON ticket_status_id = ticket_status
        WHERE $where AND {$tabs[$tab]}
        ORDER BY " . ($tab === 'left_behind' ? 'ticket_schedule ASC, ' : '') . "FIELD(ticket_priority, 'Urgent', 'High', 'Medium', 'Low'), ticket_created_at ASC
        LIMIT 200");
    $tickets = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $tickets[] = [
            'id' => intval($row['ticket_id']),
            'reference' => ticketReference($row),
            'subject' => $row['ticket_subject'],
            'client' => $row['client_name'] ?: 'Internal',
            'priority' => (string) $row['ticket_priority'],
            'status' => (string) $row['ticket_status_name'],
            'onsite' => intval($row['ticket_onsite']),
            'agent' => intval($row['ticket_assigned_to']),
            'agent_name' => (string) $row['user_name'],
            'age' => timeAgo($row['ticket_created_at']),
            'minutes' => ticketScheduleMinutes($row['ticket_schedule'], $row['ticket_schedule_end']),
            'booked' => $row['ticket_schedule'] ? date('D M j', strtotime(timezoneToView($row['ticket_schedule']))) : '',
        ];
    }
    return ['tab' => $tab, 'counts' => $counts, 'tickets' => $tickets];
}

/*
 * The board's items from $from to $to (Y-m-d, on the viewer's clock) for these
 * agents: their booked tickets - leading or helping - the events they are on,
 * and the holidays, each with 'agent' set. Tickets they lead and one-off events
 * move; the rest is there to book around. Times on the viewer's clock.
 */
function dispatchItems($from, $to, array $agent_ids) {
    $agents = dispatchAgents();
    $sources = calendarSources();
    $calendar_ids = array_keys(calendarList());
    $can_edit = dispatchCanEdit();
    [$q_from, $q_to] = [timezoneFromView("$from 00:00:00"), timezoneFromView("$to 23:59:59")];
    $holidays = isset($sources['holidays']) ? calendarSourceItems('holidays', "$from 00:00:00", "$to 23:59:59") : [];
    $items = [];
    $ticket_ids = [];
    foreach (array_unique(array_map('intval', $agent_ids)) as $agent_id) {
        if (!isset($agents[$agent_id])) {
            continue;
        }
        $opts = ['client_id' => 0, 'user_id' => $agent_id];
        $found = array_merge(
            isset($sources['tickets']) ? calendarSourceItems('tickets', $q_from, $q_to, $opts) : [],
            calendarEventItems($calendar_ids, $q_from, $q_to, $opts),
            $holidays
        );
        foreach ($found as $item) {
            $kind = $item['extendedProps']['kind'] ?? '';
            $ticket_ids[] = intval($item['extendedProps']['ticket_id'] ?? 0);
            // The same event or holiday on more than one agent's line
            $item['id'] = ($item['id'] ?? '') . ($kind === 'ticket' || $kind === 'helper' ? '' : "-a$agent_id");
            $item['extendedProps']['agent'] = $agent_id;
            $item['editable'] = $can_edit && in_array($kind, ['ticket', 'event'], true) && !empty($item['editable']);
            $item['durationEditable'] = $item['editable'];
            $items[] = $item;
        }
    }
    // Each booking's ticket reference, for the Schedule window's title
    $ticket_ids = array_values(array_filter(array_unique($ticket_ids)));
    if ($ticket_ids) {
        $references = [];
        $sql = mysqli_query($GLOBALS['mysqli'], "SELECT ticket_id, ticket_prefix, ticket_number FROM tickets WHERE ticket_id IN (" . implode(',', $ticket_ids) . ")");
        while ($row = mysqli_fetch_assoc($sql)) {
            $references[intval($row['ticket_id'])] = ticketReference($row);
        }
        foreach ($items as &$item) {
            $item['extendedProps']['reference'] = $references[intval($item['extendedProps']['ticket_id'] ?? 0)] ?? '';
        }
        unset($item);
    }
    return timezoneItems($items);
}

/*
 * ---------- booking ----------
 */

/*
 * Give a ticket to an agent (when it's someone new) and book it for $start (on
 * the company's clock) for $minutes, onsite or remote. $tell_client false keeps
 * the contact and watchers out of it - the agent is always told.
 * [ok, message, conflicts].
 */
function dispatchBook($ticket_id, $agent_id, $start, $minutes, $onsite, $tell_client = true) {
    global $session_user_id;
    $row = ticketRow($ticket_id);
    if (!$row || !empty($row['ticket_archived_at'])) {
        return [false, 'That ticket no longer exists.', []];
    }
    if (intval($row['ticket_client_id']) && !hasClientAccess($row['ticket_client_id'])) {
        return [false, 'You do not have access to that client.', []];
    }
    if (!ticketIsOpen($row)) {
        return [false, 'Ticket ' . ticketReference($row) . ' is resolved or closed - reopen it to schedule work.', []];
    }
    $start = ticketScheduleValue($start);
    if ($start === '') {
        return [false, 'Pick the date and time.', []];
    }
    $agent_id = intval($agent_id);
    $agents = dispatchAgents();
    if (!isset($agents[$agent_id])) {
        return [false, 'Pick an agent.', []];
    }
    if (!isset(ticketMentionAgents($row['ticket_client_id'])[$agent_id])) {
        return [false, $agents[$agent_id]['name'] . ' cannot open tickets of this client.', []];
    }
    if ($agent_id !== intval($row['ticket_assigned_to'])) {
        [$assigned_ok, $assigned_message] = ticketChangeAssignee($row['ticket_id'], $agent_id);
        if (!$assigned_ok) {
            return [false, strip_tags($assigned_message), []];
        }
        // The agent who had the booking has it taken off their calendar
        ticketMailScheduleAgentCancelled($row);
        $row = ticketRow($ticket_id);
    }
    $end = date('Y-m-d H:i:s', strtotime($start) + max(15, min(1440, intval($minutes))) * 60);
    $result = ticketSetSchedule($row, $start, $onsite ? 1 : 0, $end, (bool) $tell_client);
    return [true, ticketReference($row) . ' scheduled with ' . $agents[$agent_id]['name'] . ' for ' . timezoneWhenFor($start, $end, $session_user_id), $result['conflicts']];
}

/** Take a ticket's booking off - dragged back onto the queue. [ok, message]. */
function dispatchUnbook($ticket_id) {
    $row = ticketRow($ticket_id);
    if (!$row || (intval($row['ticket_client_id']) && !hasClientAccess($row['ticket_client_id']))) {
        return [false, 'That ticket no longer exists.'];
    }
    if (empty($row['ticket_schedule'])) {
        return [false, ticketReference($row) . ' has nothing booked.'];
    }
    ticketCancelSchedule($row);
    return [true, ticketReference($row) . ' taken off the schedule - it is back in the queue'];
}

/*
 * The contact's other open tickets, for the Schedule window - so a duplicate is
 * seen, and can be merged, before work is booked on it: ['contact' => name,
 * 'tickets' => [{id, reference, subject, status, agent, booked, url}]], newest
 * first. No contact, or a ticket out of reach, has none.
 */
function dispatchContactTickets($ticket_id) {
    global $mysqli;
    $row = ticketRow($ticket_id);
    $contact_id = intval($row['ticket_contact_id'] ?? 0);
    $client_id = intval($row['ticket_client_id'] ?? 0);
    if (!$row || !$contact_id || ($client_id && !hasClientAccess($client_id))) {
        return ['contact' => '', 'tickets' => []];
    }
    $sql = mysqli_query($mysqli, "SELECT tickets.ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_client_id, ticket_schedule, ticket_status_name, user_name
        FROM tickets
        LEFT JOIN ticket_statuses ON ticket_status_id = ticket_status
        LEFT JOIN users ON user_id = ticket_assigned_to
        WHERE ticket_contact_id = $contact_id AND ticket_client_id = $client_id AND tickets.ticket_id <> " . intval($row['ticket_id']) . "
            AND " . TICKET_OPEN_SQL . " AND ticket_archived_at IS NULL
        ORDER BY ticket_created_at DESC LIMIT 15");
    $tickets = [];
    while ($other = mysqli_fetch_assoc($sql)) {
        $tickets[] = [
            'id' => intval($other['ticket_id']),
            'reference' => ticketReference($other),
            'subject' => (string) $other['ticket_subject'],
            'status' => (string) $other['ticket_status_name'],
            'agent' => (string) ($other['user_name'] ?? ''),
            'booked' => $other['ticket_schedule'] ? date('D M j', strtotime(timezoneToView($other['ticket_schedule']))) : '',
            'url' => ticketUrl($other['ticket_id'], $other['ticket_client_id']),
        ];
    }
    return ['contact' => (string) ($row['contact_name'] ?? ''), 'tickets' => $tickets];
}

/*
 * Merge one ticket into another from the Schedule window - ticketMergeInto(),
 * with the checks of the ticket's own Merge: both open and reachable, the same
 * client. A booking on the one closed is taken off first, so its agent and the
 * client are sent the cancellation. $comment as typed. [ok, message].
 */
function dispatchMerge($child_id, $parent_id, $comment, $move_replies) {
    $child = ticketRow($child_id);
    $parent = ticketRow($parent_id);
    foreach ([$child, $parent] as $row) {
        if (!$row || !empty($row['ticket_closed_at']) || (intval($row['ticket_client_id']) && !hasClientAccess($row['ticket_client_id']))) {
            return [false, 'Both tickets have to be open, and ones you can reach.'];
        }
    }
    if (intval($child['ticket_id']) === intval($parent['ticket_id'])) {
        return [false, "A ticket can't be merged into itself."];
    }
    if (intval($child['ticket_client_id']) !== intval($parent['ticket_client_id'])) {
        return [false, 'A ticket merges into one of the same client\'s.'];
    }
    if (!empty($child['ticket_schedule'])) {
        ticketCancelSchedule($child);
        $child = ticketRow($child['ticket_id']);
    }
    ticketMergeInto($child, $parent, escapeSql(mb_substr(trim((string) $comment), 0, 500)), (bool) $move_replies);
    return [true, ticketReference($child) . ' merged into ' . ticketReference($parent) . ' and closed'];
}

/** Give a ticket to another agent without booking it - Workload. A booked one is moved on the board instead. [ok, message]. */
function dispatchAssign($ticket_id, $agent_id) {
    $row = ticketRow($ticket_id);
    if (!$row || !empty($row['ticket_archived_at'])) {
        return [false, 'That ticket no longer exists.'];
    }
    if (intval($row['ticket_client_id']) && !hasClientAccess($row['ticket_client_id'])) {
        return [false, 'You do not have access to that client.'];
    }
    if (!ticketIsOpen($row)) {
        return [false, 'Ticket ' . ticketReference($row) . ' is resolved or closed.'];
    }
    $agent_id = intval($agent_id);
    $agents = dispatchAgents();
    if (!isset($agents[$agent_id])) {
        return [false, 'Pick an agent.'];
    }
    if (!isset(ticketMentionAgents($row['ticket_client_id'])[$agent_id])) {
        return [false, $agents[$agent_id]['name'] . ' cannot open tickets of this client.'];
    }
    if ($agent_id === intval($row['ticket_assigned_to'])) {
        return [true, ticketReference($row) . ' is already theirs.'];
    }
    if (!empty($row['ticket_schedule'])) {
        return [false, ticketReference($row) . ' is booked - move the booking on the board instead, so the new agent gets the calendar file.'];
    }
    [$ok, $message] = ticketChangeAssignee($row['ticket_id'], $agent_id);
    return [$ok, $ok ? ticketReference($row) . ' given to ' . $agents[$agent_id]['name'] : strip_tags($message)];
}

/*
 * A one-off event dragged to another time and/or from one agent's line to
 * another's ($from, $to - the same for a move in time). Those who have the
 * invitation are sent the update. [ok, message].
 */
function dispatchEventMove($event_id, $start, $end, $all_day, $from, $to) {
    global $mysqli, $session_name;
    $event = calendarEventRow($event_id);
    if (!$event || !empty($event['event_archived_at'])) {
        return [false, 'That event no longer exists.'];
    }
    if (intval($event['event_client_id']) && !hasClientAccess($event['event_client_id'])) {
        return [false, 'You do not have access to that client.'];
    }
    if (!calendarEventCan($event, 'edit')) {
        return [false, permissionRefusedText('module_client', 2, intval(lookupUserPermission('module_client')))];
    }
    if (calendarEventRepeats($event)) {
        return [false, 'A repeating event is moved from Edit, so the whole series moves together.'];
    }
    $event_id = intval($event['event_id']);
    $from = intval($from);
    $to = intval($to);
    if ($from !== $to && !isset(dispatchAgents()[$to])) {
        return [false, 'Pick an agent.'];
    }
    $start = ticketScheduleValue($start);
    if ($start === '') {
        return [false, 'That is not a date and time.'];
    }
    if ($all_day) {
        $days = $event['event_all_day'] ? max(0, intval((strtotime(substr($event['event_end'] ?: $event['event_start'], 0, 10)) - strtotime(substr($event['event_start'], 0, 10))) / 86400)) : 0;
        $start = substr($start, 0, 10) . ' 00:00:00';
        $end = date('Y-m-d 00:00:00', strtotime(substr($start, 0, 10) . " +$days day"));
    } else {
        $end = ticketScheduleValue($end);
        if ($end === '' || $end <= $start) {
            $length = $event['event_all_day'] ? 3600 : max(900, strtotime($event['event_end'] ?: $event['event_start']) - strtotime($event['event_start']));
            $end = date('Y-m-d H:i:s', strtotime($start) + $length);
        }
    }
    $all_day = $all_day ? 1 : 0;
    $moved = $start !== $event['event_start'] || $end !== (string) $event['event_end'] || $all_day !== intval($event['event_all_day']);
    if ($moved) {
        mysqli_query($mysqli, "UPDATE calendar_events SET event_start = '" . escapeSql($start) . "', event_end = '" . escapeSql($end) . "', event_all_day = $all_day,
            event_sequence = event_sequence + 1 WHERE event_id = $event_id");
    }
    $event = calendarEventRow($event_id);
    $sent = 0;
    if ($moved) {
        // Those who have it get the new time - not an agent being taken off, who is sent the cancellation
        $sent += calendarSendInvites($event, 'update', array_values(array_filter(calendarInvitedAttendees($event_id), function ($attendee) use ($from, $to) {
            return $from === $to || intval($attendee['attendee_user_id']) !== $from;
        })));
    }
    $words = '';
    if ($from !== $to) {
        $agents = array_keys(calendarEventAgents([$event_id])[$event_id] ?? []);
        $agents = array_values(array_unique(array_merge(array_diff($agents, [$from]), [$to])));
        [$added, $removed, $mailed] = calendarEventSetAgents($event, $agents);
        $sent += $mailed;
        $words = calendarEventAgentsText($added, $removed);
    }
    $what = trim(($moved ? 'moved to ' . timezoneEventWhenText($event, $event['event_start'], $event['event_end']) : '') . ($moved && $words ? ', ' : '') . $words);
    logAudit("Calendar Event", "Edit", "$session_name (Dispatch) " . escapeSql($what ?: 'left') . " the event " . escapeSql($event['event_title']), intval($event['event_client_id']), $event_id);
    return [true, $event['event_title'] . ($what ? " $what" : '') . ($sent ? " - emailed to $sent " . ($sent === 1 ? 'person' : 'people') : '')];
}

/*
 * ---------- Workload ----------
 */

/** The week $date is in, from the agent's first day - [from, to] as Y-m-d. */
function dispatchWeek($first_day, $date = null) {
    $ts = $date ? strtotime($date) : time();
    $offset = (intval(date('w', $ts)) - intval($first_day) + 7) % 7;
    $from = date('Y-m-d', strtotime("-$offset days", $ts));
    return [$from, date('Y-m-d', strtotime("$from +6 days"))];
}

/*
 * Each agent's load: their open tickets as lead or helper, ITFlow's SLA state
 * for each, due dates passed, tickets not booked and gone quiet, and the hours
 * booked today and this week against the working hours in Admin > SLA - with
 * flags (dispatchWorkloadFlags()). $first_day 0 is a week from Sunday, 1 from
 * Monday. [agents => [...], team => averages, week => [from, to]].
 */
function dispatchWorkload(array $agent_ids, $first_day = 0) {
    global $mysqli;
    $agents = dispatchAgents();
    $agent_ids = array_values(array_filter(array_unique(array_map('intval', $agent_ids)), function ($id) use ($agents) {
        return isset($agents[$id]);
    }));
    [$week_from, $week_to] = dispatchWeek($first_day);
    $today = date('Y-m-d');
    [$day_minutes, $work_days] = dispatchWorkingDay();
    $week_days = 0;
    for ($i = 0; $i < 7; $i++) {
        $week_days += in_array(intval(date('N', strtotime("$week_from +$i days"))), $work_days, true) ? 1 : 0;
    }
    $works_today = in_array(intval(date('N')), $work_days, true);

    $rows = [];
    foreach ($agent_ids as $id) {
        $rows[$id] = [
            'id' => $id, 'name' => $agents[$id]['name'], 'color' => $agents[$id]['color'],
            'open' => 0, 'lead' => 0, 'helping' => 0,
            'sla' => ['breached' => 0, 'at_risk' => 0, 'running' => 0, 'paused' => 0, 'none' => 0],
            'overdue' => 0, 'unbooked' => 0, 'quiet' => 0, 'oldest' => '',
            'booked_today' => 0, 'booked_week' => 0,
            'capacity_today' => $works_today ? $day_minutes : 0, 'capacity_week' => $week_days * $day_minutes,
            'flags' => [], 'tickets' => [],
        ];
    }
    if (!$agent_ids) {
        return ['agents' => [], 'team' => ['open' => 0, 'booked_week' => 0], 'week' => [$week_from, $week_to]];
    }
    $in = implode(',', $agent_ids);
    $helpers = ticketHelpersReady();

    $sql = mysqli_query($mysqli, "SELECT tickets.ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_priority, ticket_status,
            ticket_sla_id, ticket_response_sla_alert_stage, ticket_resolution_sla_alert_stage, ticket_response_sla_met, ticket_resolution_sla_met,
            ticket_first_response_at, ticket_response_due_at, ticket_resolution_due_at, ticket_due_at, ticket_resolved_at, ticket_closed_at,
            ticket_schedule, " . (ticketScheduleEndReady() ? 'ticket_schedule_end' : 'NULL AS ticket_schedule_end') . ", ticket_onsite, ticket_created_at,
            ticket_assigned_to, ticket_client_id, client_name, ticket_status_name, ticket_status_pauses_sla,
            " . ($helpers ? 'th.user_id' : '0') . " AS helper_id,
            (SELECT MAX(ticket_reply_created_at) FROM ticket_replies WHERE ticket_reply_ticket_id = tickets.ticket_id AND ticket_reply_type <> 'Client') AS last_reply_at
        FROM tickets
        LEFT JOIN clients ON client_id = ticket_client_id
        LEFT JOIN ticket_statuses ON ticket_status_id = ticket_status
        " . ($helpers ? "LEFT JOIN ticket_helpers th ON th.ticket_id = tickets.ticket_id AND th.user_id IN ($in)" : '') . "
        WHERE " . TICKET_OPEN_SQL . " AND ticket_archived_at IS NULL" . clientScopeSql('ticket_client_id') . "
        AND (ticket_assigned_to IN ($in)" . ($helpers ? ' OR th.user_id IS NOT NULL' : '') . ")");
    $order = ['breached' => 0, 'at_risk' => 1, 'running' => 2, 'paused' => 3, 'none' => 4];
    $quiet_before = time() - DISPATCH_QUIET_DAYS * 86400;
    $seen = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $lead = intval($row['ticket_assigned_to']);
        $helper = intval($row['helper_id']);
        $owners = [];
        if (isset($rows[$lead])) {
            $owners[$lead] = 'lead';
        }
        if ($helper && isset($rows[$helper]) && $helper !== $lead) {
            $owners[$helper] = 'helper';
        }
        $sla = ticketSlaState($row);
        $due = ticketDueState($row);
        $last = max(strtotime($row['ticket_created_at']), $row['last_reply_at'] ? strtotime($row['last_reply_at']) : 0);
        $quiet = $last < $quiet_before && $sla['key'] !== 'paused';
        foreach ($owners as $agent_id => $role) {
            if (isset($seen[$row['ticket_id'] . '-' . $agent_id])) {
                continue;
            }
            $seen[$row['ticket_id'] . '-' . $agent_id] = true;
            $r = &$rows[$agent_id];
            $r['open']++;
            $r[$role === 'lead' ? 'lead' : 'helping']++;
            $r['sla'][in_array($sla['key'], ['met', 'missed'], true) ? 'none' : $sla['key']]++;
            $r['overdue'] += ($due['key'] ?? '') === 'overdue' ? 1 : 0;
            $r['unbooked'] += $role === 'lead' && empty($row['ticket_schedule']) ? 1 : 0;
            $r['quiet'] += $quiet ? 1 : 0;
            if ($r['oldest'] === '' || $row['ticket_created_at'] < $r['oldest']) {
                $r['oldest'] = $row['ticket_created_at'];
            }
            $r['tickets'][] = [
                'id' => intval($row['ticket_id']),
                'reference' => ticketReference($row),
                'subject' => (string) $row['ticket_subject'],
                'client' => $row['client_name'] ?: 'Internal',
                'priority' => (string) $row['ticket_priority'],
                'status' => (string) $row['ticket_status_name'],
                'role' => $role,
                'sla' => $sla['key'],
                'sla_due' => $sla['due'] ?? '',
                'sla_html' => ticketSlaPill($sla),
                'due_html' => in_array($due['key'] ?? '', ['overdue', 'soon'], true) ? ticketDuePill($due) : '',
                'overdue' => ($due['key'] ?? '') === 'overdue',
                'quiet' => $quiet,
                'last' => timeAgo(date('Y-m-d H:i:s', $last)),
                'scheduled' => $row['ticket_schedule'] ? ticketScheduleShortText($row['ticket_schedule'], $row['ticket_schedule_end']) : '',
                'url' => ticketUrl($row['ticket_id'], $row['ticket_client_id']),
            ];
            unset($r);
        }
    }

    // Booked time this week - their tickets and helper bookings
    foreach (dispatchItems($week_from, $week_to, $agent_ids) as $item) {
        $kind = $item['extendedProps']['kind'] ?? '';
        $agent_id = intval($item['extendedProps']['agent'] ?? 0);
        if (!isset($rows[$agent_id]) || !in_array($kind, ['ticket', 'helper'], true) || empty($item['start']) || empty($item['end'])) {
            continue;
        }
        $start = strtotime($item['start']);
        $minutes = max(0, intval((strtotime($item['end']) - $start) / 60));
        $rows[$agent_id]['booked_week'] += $minutes;
        if (date('Y-m-d', $start) === substr(timezoneToView(date('Y-m-d H:i:s')), 0, 10)) {
            $rows[$agent_id]['booked_today'] += $minutes;
        }
    }

    // Most urgent first: the SLA, a due date passed, the clock that runs out first, then the oldest
    foreach ($rows as &$r) {
        usort($r['tickets'], function ($a, $b) use ($order) {
            return [$order[$a['sla']] ?? 9, $a['overdue'] ? 0 : 1, $a['sla_due'] ?: '9999', $a['id']] <=> [$order[$b['sla']] ?? 9, $b['overdue'] ? 0 : 1, $b['sla_due'] ?: '9999', $b['id']];
        });
        $r['oldest'] = $r['oldest'] !== '' ? timeAgo($r['oldest']) : '';
    }
    unset($r);

    $team = dispatchWorkloadFlags($rows);
    return ['agents' => array_values($rows), 'team' => $team, 'week' => [$week_from, $week_to], 'today' => $today];
}

/*
 * Each agent's flags: behind (an SLA breached or a due date passed), watch (an
 * SLA close to it), overloaded (well above the team in open tickets or booked
 * hours, or booked past their working week) or room (well below it). The team
 * averages: [open, booked_week].
 */
function dispatchWorkloadFlags(array &$rows) {
    $count = count($rows);
    $avg_open = $count ? array_sum(array_column($rows, 'open')) / $count : 0;
    $avg_booked = $count ? array_sum(array_column($rows, 'booked_week')) / $count : 0;
    foreach ($rows as &$r) {
        $flags = [];
        if ($r['sla']['breached'] || $r['overdue']) {
            $parts = array_filter([$r['sla']['breached'] ? $r['sla']['breached'] . ' SLA breached' : '', $r['overdue'] ? $r['overdue'] . ' past due' : '']);
            $flags[] = ['key' => 'behind', 'text' => 'Falling behind', 'why' => implode(', ', $parts)];
        } elseif ($r['sla']['at_risk']) {
            $flags[] = ['key' => 'watch', 'text' => 'Watch', 'why' => $r['sla']['at_risk'] . ' close to the SLA'];
        }
        $why = [];
        if ($count > 1 && $r['open'] >= 1.5 * $avg_open && $r['open'] - $avg_open >= 3) {
            $why[] = $r['open'] . ' open (team average ' . round($avg_open, 1) . ')';
        }
        if ($count > 1 && $r['booked_week'] >= 1.5 * $avg_booked && $r['booked_week'] - $avg_booked >= 240) {
            $why[] = round($r['booked_week'] / 60, 1) . ' h booked this week (team average ' . round($avg_booked / 60, 1) . ' h)';
        }
        if ($r['capacity_week'] && $r['booked_week'] > $r['capacity_week']) {
            $why[] = 'booked past their working week';
        }
        if ($why) {
            $flags[] = ['key' => 'overloaded', 'text' => 'Overloaded', 'why' => implode('; ', $why)];
        } elseif ($count > 1 && $avg_open >= 2 && $r['open'] <= 0.5 * $avg_open && $r['booked_week'] <= 0.5 * max($avg_booked, 1) && !$r['sla']['breached']) {
            $flags[] = ['key' => 'room', 'text' => 'Has room', 'why' => $r['open'] . ' open (team average ' . round($avg_open, 1) . ')'];
        }
        $r['flags'] = $flags;
    }
    unset($r);
    return ['open' => round($avg_open, 1), 'booked_week' => intval(round($avg_booked))];
}

/*
 * ---------- This week ----------
 */

/** SQL: done between two SQL datetimes - resolved, or closed without resolving. */
function dispatchDoneIn($from, $to, $to_op = '<=') {
    return "(tickets.ticket_resolved_at >= $from AND tickets.ticket_resolved_at $to_op $to
        OR (tickets.ticket_resolved_at IS NULL AND tickets.ticket_closed_at >= $from AND tickets.ticket_closed_at $to_op $to))";
}

/*
 * The week's numbers: tickets in and closed each day, closed against last week
 * and the four weeks before, the week's SLA results, and for each agent the
 * tickets given to them this week (raised for them, or the ticket's "assigned
 * the ticket to" history) against those they closed. $first_day as Workload's.
 */
function dispatchWeekStats(array $agent_ids, $first_day = 0) {
    global $mysqli;
    [$from, $to] = dispatchWeek($first_day);
    $start = "'$from 00:00:00'";
    $end = "'$to 23:59:59'";
    $scope = "tickets.ticket_archived_at IS NULL" . clientScopeSql('tickets.ticket_client_id');
    $one = function ($sql) use ($mysqli) {
        return intval(mysqli_fetch_row(mysqli_query($mysqli, $sql))[0] ?? 0);
    };

    $days = [];
    for ($i = 0; $i < 7; $i++) {
        $date = date('Y-m-d', strtotime("$from +$i days"));
        $days[$date] = ['date' => $date, 'label' => date('D j', strtotime($date)), 'inbound' => 0, 'closed' => 0, 'future' => $date > date('Y-m-d')];
    }
    $sql = mysqli_query($mysqli, "SELECT DATE(ticket_created_at), COUNT(*) FROM tickets WHERE $scope AND ticket_created_at BETWEEN $start AND $end GROUP BY 1");
    while ($row = mysqli_fetch_row($sql)) {
        if (isset($days[$row[0]])) {
            $days[$row[0]]['inbound'] = intval($row[1]);
        }
    }
    $sql = mysqli_query($mysqli, "SELECT DATE(COALESCE(tickets.ticket_resolved_at, tickets.ticket_closed_at)), COUNT(*) FROM tickets WHERE $scope AND " . dispatchDoneIn($start, $end) . " GROUP BY 1");
    while ($row = mysqli_fetch_row($sql)) {
        if (isset($days[$row[0]])) {
            $days[$row[0]]['closed'] = intval($row[1]);
        }
    }
    $inbound = array_sum(array_column($days, 'inbound'));
    $closed = array_sum(array_column($days, 'closed'));

    $last_from = date('Y-m-d', strtotime("$from -7 days"));
    $four_from = date('Y-m-d', strtotime("$from -28 days"));
    $totals = [
        'inbound' => $inbound,
        'closed' => $closed,
        'net' => $inbound - $closed,
        'open' => $one("SELECT COUNT(*) FROM tickets WHERE $scope AND " . TICKET_OPEN_SQL),
        'last_inbound' => $one("SELECT COUNT(*) FROM tickets WHERE $scope AND ticket_created_at >= '$last_from' AND ticket_created_at < '$from'"),
        'last_closed' => $one("SELECT COUNT(*) FROM tickets WHERE $scope AND " . dispatchDoneIn("'$last_from'", "'$from'", '<')),
        'avg_closed' => round($one("SELECT COUNT(*) FROM tickets WHERE $scope AND " . dispatchDoneIn("'$four_from'", "'$from'", '<')) / 4, 1),
    ];

    // SLA results: responses due this week, and resolutions due or done this week - the ones judged
    $response = "tickets.ticket_sla_id > 0 AND tickets.ticket_response_due_at BETWEEN $start AND $end";
    $done_week = dispatchDoneIn($start, $end);
    $resolution = "tickets.ticket_sla_id > 0 AND (tickets.ticket_resolution_due_at BETWEEN $start AND $end OR $done_week)";
    $sla = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT
            SUM($response AND ticket_response_sla_met = 1) AS response_met,
            SUM($response AND ticket_response_sla_met = 0) AS response_missed,
            SUM($resolution AND ticket_resolution_sla_met = 1) AS resolution_met,
            SUM($resolution AND ticket_resolution_sla_met = 0) AS resolution_missed,
            SUM(" . TICKET_OPEN_SQL . " AND ticket_sla_id > 0 AND GREATEST(ticket_response_sla_alert_stage, ticket_resolution_sla_alert_stage) = 2) AS breached_now,
            SUM(" . TICKET_OPEN_SQL . " AND ticket_sla_id > 0 AND GREATEST(ticket_response_sla_alert_stage, ticket_resolution_sla_alert_stage) = 1) AS at_risk_now
        FROM tickets WHERE $scope AND (tickets.ticket_response_due_at BETWEEN $start AND $end
            OR tickets.ticket_resolution_due_at BETWEEN $start AND $end OR $done_week OR " . TICKET_OPEN_SQL . ")"));
    $sla = array_map('intval', $sla ?: []);

    // Given to each agent this week: raised for them, or the history's "assigned the ticket to <name>" - the longest name that fits
    $agents = dispatchAgents();
    $ids = array_values(array_filter(array_unique(array_map('intval', $agent_ids)), function ($id) use ($agents) {
        return isset($agents[$id]);
    }));
    $given = array_fill_keys($ids, []);
    if ($ids) {
        $sql = mysqli_query($mysqli, "SELECT ticket_id, ticket_assigned_to FROM tickets WHERE $scope AND ticket_created_at BETWEEN $start AND $end AND ticket_assigned_to IN (" . implode(',', $ids) . ")");
        while ($row = mysqli_fetch_row($sql)) {
            $given[intval($row[1])][intval($row[0])] = true;
        }
        $by_name = [];
        foreach ($ids as $id) {
            $by_name[$id] = (string) $agents[$id]['name'];
        }
        uasort($by_name, function ($a, $b) {
            return mb_strlen($b) <=> mb_strlen($a);
        });
        $sql = mysqli_query($mysqli, "SELECT ticket_history_ticket_id, ticket_history_description FROM ticket_history
            JOIN tickets ON tickets.ticket_id = ticket_history_ticket_id
            WHERE $scope AND ticket_history_created_at BETWEEN $start AND $end AND ticket_history_description LIKE '%assigned the ticket to %'");
        while ($row = mysqli_fetch_row($sql)) {
            foreach ($by_name as $id => $name) {
                if ($name !== '' && preg_match('/assigned the ticket to ' . preg_quote($name, '/') . '( |$)/iu', (string) $row[1])) {
                    $given[$id][intval($row[0])] = true;
                    break;
                }
            }
        }
    }
    $rows = [];
    foreach ($ids as $id) {
        $rows[] = [
            'id' => $id,
            'name' => $agents[$id]['name'],
            'color' => $agents[$id]['color'],
            'assigned' => count($given[$id]),
            'closed' => $one("SELECT COUNT(*) FROM tickets WHERE $scope AND ticket_assigned_to = $id AND " . dispatchDoneIn($start, $end)),
            'open' => $one("SELECT COUNT(*) FROM tickets WHERE $scope AND ticket_assigned_to = $id AND " . TICKET_OPEN_SQL),
            'sla_missed' => $one("SELECT COUNT(*) FROM tickets WHERE $scope AND ticket_assigned_to = $id
                AND (($response AND ticket_response_sla_met = 0) OR ($resolution AND ticket_resolution_sla_met = 0))"),
        ];
    }
    return ['week' => [$from, $to], 'days' => array_values($days), 'totals' => $totals, 'sla' => $sla, 'agents' => $rows];
}
