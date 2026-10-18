<?php

/*
 * Calendar - events, the schedule the rest of ITFlow puts on the calendar, and
 * the iCalendar (RFC 5545) the subscription links and invitations are made of.
 *
 * An event lives in one of the calendars (Onsite visits, Maintenance windows,
 * Internal...), may be for a client and one of their sites, and has attendees:
 * agents, the client's contacts, or anyone by email. Attendees can be sent an
 * invitation; when the event changes or is deleted they can be sent the update
 * or the cancellation, which their calendar applies to the invitation it
 * already has (same UID, higher SEQUENCE).
 *
 * A repeat is every N days, weeks, months or years, optionally until a date,
 * and single occurrences can be skipped. It is stored once and expanded here -
 * in the server's timezone, so a 9:00 meeting stays at 9:00 across DST - and
 * published to subscribers as an RRULE with the timezone attached.
 *
 * Besides events, the calendar shows what other modules have dated: scheduled
 * tickets, project starts and due dates, service reviews, recurring tickets,
 * renewals and invoices falling due (calendarSources()). Each agent can also
 * subscribe to their own schedule - their scheduled tickets, the events they
 * are on, the projects they manage - from a phone, Nextcloud or Google.
 *
 * Stored datetimes are wall clock in the instance timezone (config_timezone).
 */

/** How a repeat is worded: [stored value => [RRULE FREQ, one, many]]. */
const CALENDAR_REPEATS = [
    'Day' => ['DAILY', 'day', 'days'],
    'Week' => ['WEEKLY', 'week', 'weeks'],
    'Month' => ['MONTHLY', 'month', 'months'],
    'Year' => ['YEARLY', 'year', 'years'],
];

/** How long a scheduled ticket is taken to last on the calendar, in feeds and in its invitation - tickets store only the start. */
const CALENDAR_TICKET_MINUTES = 60;

/** How far a subscription link reaches, back and forward. Repeating events are always included. */
const CALENDAR_FEED_MONTHS_PAST = 12;
const CALENDAR_FEED_MONTHS_FUTURE = 24;

/*
 * ---------- who may do what ----------
 */

/** Calendars themselves - adding, renaming, deleting and publishing one - are an admin's to manage. */
function calendarCanManage() {
    global $session_is_admin;
    return !empty($session_is_admin);
}

/*
 * Events follow the role of what they belong to. A client's event is the
 * client's: Clients at Read to see it, Modify to add, change, move, skip or
 * send it, Full to delete it - or Modify, for the agent who added it. An
 * event with no client is anyone's. An agent always sees the events they are
 * on or added, as their own schedule. $event: the row (event_id,
 * event_client_id, event_created_by), or ['event_client_id' => id] for a new
 * one. $action: view, edit or delete.
 */
function calendarEventCan($event, $action = 'view') {
    global $session_user_id;
    if (!intval($event['event_client_id'] ?? 0)) {
        return true;
    }
    $clients = intval(lookupUserPermission('module_client'));
    $own = intval($event['event_id'] ?? 0) && intval($event['event_created_by'] ?? 0) === intval($session_user_id ?? 0);
    if ($action === 'delete') {
        return $clients >= 3 || ($clients >= 2 && $own);
    }
    if ($action === 'edit') {
        return $clients >= 2;
    }
    return $clients >= 1 || $own || calendarIsOn(intval($event['event_id'] ?? 0));
}

/** Turn the agent away unless calendarEventCan(), saying what it takes. */
function enforceCalendarEvent($event, $action = 'view') {
    if (!calendarEventCan($event, $action)) {
        permissionRefuse(permissionRefusedText('module_client', ['view' => 1, 'edit' => 2, 'delete' => 3][$action] ?? 1, intval(lookupUserPermission('module_client'))));
    }
}

/** Is the signed-in agent one of the event's attendees? */
function calendarIsOn($event_id) {
    global $mysqli, $session_user_id;
    $event_id = intval($event_id);
    $user_id = intval($session_user_id ?? 0);
    return $event_id && $user_id && mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM calendar_event_attendees WHERE attendee_event_id = $event_id AND attendee_user_id = $user_id LIMIT 1")) > 0;
}

/*
 * For a list of events: " AND ..." keeping a client's events to agents whose
 * role can see Clients, and their own - '' when it can. calendar_events must
 * be in the query unaliased.
 */
function calendarEventScopeSql() {
    global $session_user_id;
    if (intval(lookupUserPermission('module_client')) >= 1) {
        return '';
    }
    $user_id = intval($session_user_id ?? 0);
    return " AND (calendar_events.event_client_id = 0 OR calendar_events.event_created_by = $user_id
        OR EXISTS (SELECT 1 FROM calendar_event_attendees scope_a WHERE scope_a.attendee_event_id = calendar_events.event_id AND scope_a.attendee_user_id = $user_id))";
}

/** May this agent move scheduled tickets on the calendar? The same as rescheduling one on the ticket. */
function calendarCanScheduleTickets() {
    return lookupUserPermission('module_support') >= 2;
}

/** Every calendar, by name - [id => row]. */
function calendarList() {
    global $mysqli;
    $calendars = [];
    $sql = mysqli_query($mysqli, "SELECT calendar_id, calendar_name, calendar_color, calendar_feed_key, calendar_feed_busy_only, calendar_feed_created_at, calendar_feed_accessed_at
        FROM calendars WHERE calendar_archived_at IS NULL ORDER BY calendar_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $calendars[intval($row['calendar_id'])] = $row;
    }
    return $calendars;
}

/**
 * A calendar's colour as a safe CSS hex value. The picker stores hex; older
 * calendars may hold a colour name, drawn in the matching shade of the app's
 * palette rather than the browser's pure blue / green.
 */
function calendarColor($color, $fallback = '#6c757d') {
    $color = strtolower(trim((string) $color));
    $named = [
        'blue' => '#0d6efd', 'navy' => '#1d3f8f', 'indigo' => '#6610f2', 'purple' => '#6f42c1', 'pink' => '#d63384',
        'red' => '#dc3545', 'orange' => '#fd7e14', 'yellow' => '#e0a800', 'gold' => '#c9a227', 'green' => '#198754',
        'teal' => '#20c997', 'cyan' => '#0dcaf0', 'lightblue' => '#5dade2', 'brown' => '#8b5a2b', 'black' => '#343a40',
        'gray' => '#6c757d', 'grey' => '#6c757d', 'slategray' => '#64748b', 'maroon' => '#8b1e3f', 'olive' => '#6b8e23',
    ];
    if (isset($named[$color])) {
        return $named[$color];
    }
    return preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/', $color) ? $color : $fallback;
}

/*
 * ---------- events ----------
 */

/** One event, with its calendar, client, site and organiser - raw, or null. */
function calendarEventRow($event_id) {
    global $mysqli;
    $event_id = intval($event_id);
    if (!$event_id) {
        return null;
    }
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT calendar_events.*, calendar_name, calendar_color, client_name,
            location_name, location_address, location_city, location_state, location_zip, location_country, location_phone, location_phone_country_code,
            user_name AS organizer_name, user_email AS organizer_email, user_avatar AS organizer_avatar
        FROM calendar_events
        LEFT JOIN calendars ON calendar_id = event_calendar_id
        LEFT JOIN clients ON client_id = event_client_id AND event_client_id > 0
        LEFT JOIN locations ON location_id = event_location_id AND event_location_id > 0
        LEFT JOIN users ON user_id = event_created_by AND event_created_by > 0
        WHERE event_id = $event_id AND event_archived_at IS NULL LIMIT 1"));
    return $row ?: null;
}

/**
 * For post handlers: the event a request names, after the client check (an
 * event with no client is anyone's) and the role's (calendarEventCan() for
 * $action) - or back with an error if it is gone. Sets the global $client_id.
 */
function calendarEventForRequest($event_id, $action = 'view') {
    global $client_id;
    $row = calendarEventRow($event_id);
    if (!$row) {
        flashAlert('That event no longer exists.', 'error');
        redirect();
    }
    $client_id = intval($row['event_client_id']);
    if ($client_id) {
        enforceClientAccess();
    }
    enforceCalendarEvent($row, $action);
    return $row;
}

/** For modals: the same, or the modal's error. */
function calendarEventForModal($event_id, $action = 'view') {
    global $client_id;
    $row = calendarEventRow($event_id);
    if (!$row) {
        echo json_encode(['error' => 'That event no longer exists.']);
        exit;
    }
    $client_id = intval($row['event_client_id']);
    if ($client_id && !hasClientAccess($client_id)) {
        echo json_encode(['error' => 'You do not have access to that client.']);
        exit;
    }
    enforceCalendarEvent($row, $action);
    return $row;
}

/**
 * When an event happens, in words: "Thu, Oct 15, 2026, 10:00 AM - 12:30 PM",
 * "Mon, Oct 19 - Wed, Oct 21, 2026 (all day)". $start/$end default to the
 * event's own (pass an occurrence's to word that one).
 */
function calendarWhenText($row, $start = null, $end = null) {
    $start = $start ?: $row['event_start'];
    $end = $end ?: ($row['event_end'] ?: $start);
    $s = strtotime($start);
    $e = strtotime($end) ?: $s;
    if (!empty($row['event_all_day'])) {
        if (date('Y-m-d', $s) >= date('Y-m-d', $e)) {
            return date('D, M j, Y', $s) . ' (all day)';
        }
        return date('D, M j', $s) . ' - ' . date('D, M j, Y', $e) . ' (all day)';
    }
    if (date('Y-m-d', $s) === date('Y-m-d', $e)) {
        return date('D, M j, Y, g:i A', $s) . ($e > $s ? ' - ' . date('g:i A', $e) : '');
    }
    return date('D, M j, g:i A', $s) . ' - ' . date('D, M j, Y, g:i A', $e);
}

/** Where an event is as one line - the site's name and address - or the location typed in. */
function calendarWhereText($row) {
    if (!empty($row['location_name'])) {
        $address = implode(', ', array_filter([$row['location_address'] ?? '', $row['location_city'] ?? '', trim(($row['location_state'] ?? '') . ' ' . ($row['location_zip'] ?? ''))]));
        return trim($row['location_name'] . ($address !== '' ? " - $address" : ''));
    }
    return trim((string) ($row['event_location'] ?? ''));
}

/** A link to the place on a map, or '' when there is nowhere to look up. */
function calendarMapUrl($row) {
    $where = !empty($row['location_name'])
        ? implode(', ', array_filter([$row['location_address'] ?? '', $row['location_city'] ?? '', $row['location_state'] ?? '', $row['location_zip'] ?? '']))
        : trim((string) ($row['event_location'] ?? ''));
    // Something that reads as an address - a number in it, or a comma - not "Office" or a meeting link
    if ($where === '' || preg_match('#^(https?://|remote|teams|zoom|online|phone|call)#i', $where) || !preg_match('/\d|,/', $where)) {
        return '';
    }
    return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($where);
}

/** A meeting link typed into the location - Teams, Zoom, Meet or any web address - or ''. */
function calendarMeetingUrl($row) {
    if (preg_match('#https?://[^\s<>"\']+#i', (string) ($row['event_location'] ?? '') . ' ' . (string) ($row['event_description'] ?? ''), $match)) {
        return rtrim($match[0], '.,);');
    }
    return '';
}

/** Does it repeat? */
function calendarEventRepeats($row) {
    return isset(CALENDAR_REPEATS[(string) ($row['event_repeat'] ?? '')]);
}

/** "Every week", "Every 2 weeks until Mar 3, 2027", or '' for an event that does not repeat. */
function calendarRepeatText($row) {
    if (!calendarEventRepeats($row)) {
        return '';
    }
    $interval = max(1, intval($row['event_repeat_interval'] ?? 1));
    [, $one, $many] = CALENDAR_REPEATS[(string) $row['event_repeat']];
    $text = $interval === 1 ? "Every $one" : "Every $interval $many";
    if (!empty($row['event_repeat_until'])) {
        $text .= ' until ' . date('M j, Y', strtotime($row['event_repeat_until']));
    }
    return $text;
}

/** The dates of occurrences that have been skipped - ['Y-m-d', ...]. */
function calendarSkippedDates($row) {
    $skip = trim((string) ($row['event_repeat_skip'] ?? ''));
    if ($skip === '') {
        return [];
    }
    return array_values(array_unique(array_filter(array_map('trim', explode(',', $skip)), function ($date) {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
    })));
}

/**
 * Expands a repeating event into concrete occurrences within a window.
 *
 * Every N days, weeks, months or years from the start, until event_repeat_until
 * (inclusive) when it has one, leaving out skipped dates. Offsets are worked out
 * from the original start in the server's timezone rather than by stepping a
 * running date, so a long series cannot drift and a 9:00 meeting stays at 9:00
 * either side of a DST change. Monthly on the 31st and yearly on Feb 29 skip the
 * months without that day, as RFC 5545 does, rather than rolling into the next.
 *
 * An occurrence still under way at the start of the window is included. The
 * bundled FullCalendar build has no rrule plugin, so the page gets the
 * occurrences from here; subscription feeds publish the RRULE instead.
 * Returns [['start' => ..., 'end' => ...], ...]; an event that does not repeat
 * comes back as itself.
 */
function expandRecurringEvent(array $event, $window_start, $window_end, $limit = 750) {

    $start = $event['event_start'];
    $repeat = (string) ($event['event_repeat'] ?? '');

    if (empty($start) || !isset(CALENDAR_REPEATS[$repeat])) {
        return [['start' => $start, 'end' => $event['event_end']]];
    }

    try {
        $base = new DateTime($start);
        $from = (new DateTime($window_start))->format('Y-m-d H:i:s');
        $to = new DateTime($window_end);
    } catch (Exception $e) {
        return [['start' => $start, 'end' => $event['event_end']]];
    }

    // Each occurrence keeps the original's length
    $duration = 0;
    if (!empty($event['event_end']) && $event['event_end'] !== '0000-00-00 00:00:00') {
        $duration = max(0, strtotime($event['event_end']) - strtotime($start));
    }

    $interval = max(1, intval($event['event_repeat_interval'] ?? 1));
    $until = !empty($event['event_repeat_until']) ? substr($event['event_repeat_until'], 0, 10) . ' 23:59:59' : null;
    $skip = array_flip(calendarSkippedDates($event));
    $step = ['Day' => 'day', 'Week' => 'week', 'Month' => 'month', 'Year' => 'year'][$repeat];
    $base_day = (int) $base->format('j');

    $occurrences = [];
    for ($i = 0; count($occurrences) < $limit && $i < 50000; $i++) {

        $candidate = clone $base;
        if ($i > 0) {
            $candidate->modify('+' . ($i * $interval) . " $step");
            if (($step === 'month' || $step === 'year') && (int) $candidate->format('j') !== $base_day) {
                if ($candidate > $to) {
                    break;
                }
                continue;
            }
        }

        $occurrence_start = $candidate->format('Y-m-d H:i:s');
        if ($candidate > $to || ($until !== null && $occurrence_start > $until)) {
            break;
        }

        $occurrence_end = null;
        if ($duration > 0) {
            $end_dt = clone $candidate;
            $end_dt->modify("+$duration second");
            $occurrence_end = $end_dt->format('Y-m-d H:i:s');
        }

        if (($occurrence_end ?: $occurrence_start) < $from || isset($skip[$candidate->format('Y-m-d')])) {
            continue;
        }

        $occurrences[] = ['start' => $occurrence_start, 'end' => $occurrence_end];
    }

    return $occurrences;
}

/**
 * The people on an event: agents, the client's contacts and anyone else by
 * email - each with a kind, a name, an email and when they were last sent
 * the invitation.
 */
function calendarEventAttendees($event_id) {
    global $mysqli;
    $event_id = intval($event_id);
    $sql = mysqli_query($mysqli, "SELECT attendee_id, attendee_name, attendee_email, attendee_invited_at, attendee_user_id, attendee_contact_id,
            user_name, user_email, user_avatar, contact_name, contact_email, contact_photo, contact_title, contact_client_id,
            contact_phone, contact_phone_country_code, contact_extension, contact_mobile, contact_mobile_country_code
        FROM calendar_event_attendees
        LEFT JOIN users ON user_id = attendee_user_id AND attendee_user_id > 0
        LEFT JOIN contacts ON contact_id = attendee_contact_id AND attendee_contact_id > 0
        WHERE attendee_event_id = $event_id AND attendee_archived_at IS NULL
        ORDER BY attendee_user_id = 0, attendee_contact_id = 0, COALESCE(user_name, contact_name, attendee_name, attendee_email)");
    $rows = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $row['kind'] = intval($row['attendee_user_id']) ? 'agent' : (intval($row['attendee_contact_id']) ? 'contact' : 'guest');
        // A contact or agent since deleted leaves a row with nobody behind it
        if (($row['kind'] === 'agent' && $row['user_name'] === null) || ($row['kind'] === 'contact' && $row['contact_name'] === null)) {
            continue;
        }
        $row['name'] = $row['user_name'] ?: ($row['contact_name'] ?: ($row['attendee_name'] ?: $row['attendee_email']));
        $row['email'] = $row['user_email'] ?: ($row['contact_email'] ?: $row['attendee_email']);
        $rows[] = $row;
    }
    return $rows;
}

/**
 * Set who is on an event: agents (active ones), contacts (the event's client's
 * own) and other email addresses. Anyone already on it keeps their row - and
 * when they were invited; anyone taken off is removed. Returns
 * ['new' => attendee ids added, 'removed' => the rows taken off].
 */
function calendarSetAttendees($event_id, $client_id, array $user_ids, array $contact_ids, array $emails) {
    global $mysqli;
    $event_id = intval($event_id);
    $client_id = intval($client_id);

    $user_ids = array_values(array_unique(array_filter(array_map('intval', $user_ids))));
    if ($user_ids) {
        $valid = [];
        $sql = mysqli_query($mysqli, "SELECT user_id FROM users WHERE user_id IN (" . implode(',', $user_ids) . ") AND user_type = 1 AND user_archived_at IS NULL");
        while ($row = mysqli_fetch_row($sql)) {
            $valid[] = intval($row[0]);
        }
        $user_ids = $valid;
    }

    $contact_ids = array_values(array_unique(array_filter(array_map('intval', $contact_ids))));
    if ($contact_ids && $client_id) {
        $valid = [];
        $sql = mysqli_query($mysqli, "SELECT contact_id FROM contacts WHERE contact_id IN (" . implode(',', $contact_ids) . ") AND contact_client_id = $client_id AND contact_archived_at IS NULL");
        while ($row = mysqli_fetch_row($sql)) {
            $valid[] = intval($row[0]);
        }
        $contact_ids = $valid;
    } else {
        $contact_ids = [];
    }

    $clean_emails = [];
    foreach ($emails as $email) {
        $email = strtolower(trim((string) $email));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $clean_emails[$email] = true;
        }
    }
    $emails = array_slice(array_keys($clean_emails), 0, 50);

    // What is there now
    $existing = ['user' => [], 'contact' => [], 'email' => []];
    $sql = mysqli_query($mysqli, "SELECT attendee_id, attendee_user_id, attendee_contact_id, LOWER(attendee_email) AS email FROM calendar_event_attendees WHERE attendee_event_id = $event_id");
    while ($row = mysqli_fetch_assoc($sql)) {
        if (intval($row['attendee_user_id'])) {
            $existing['user'][intval($row['attendee_user_id'])] = intval($row['attendee_id']);
        } elseif (intval($row['attendee_contact_id'])) {
            $existing['contact'][intval($row['attendee_contact_id'])] = intval($row['attendee_id']);
        } else {
            $existing['email'][(string) $row['email']] = intval($row['attendee_id']);
        }
    }

    $keep = [];
    $new = [];
    foreach ($user_ids as $user_id) {
        if (isset($existing['user'][$user_id])) {
            $keep[] = $existing['user'][$user_id];
        } else {
            mysqli_query($mysqli, "INSERT INTO calendar_event_attendees SET attendee_user_id = $user_id, attendee_event_id = $event_id");
            $keep[] = $new[] = mysqli_insert_id($mysqli);
        }
    }
    foreach ($contact_ids as $contact_id) {
        if (isset($existing['contact'][$contact_id])) {
            $keep[] = $existing['contact'][$contact_id];
        } else {
            mysqli_query($mysqli, "INSERT INTO calendar_event_attendees SET attendee_contact_id = $contact_id, attendee_event_id = $event_id");
            $keep[] = $new[] = mysqli_insert_id($mysqli);
        }
    }
    foreach ($emails as $email) {
        if (isset($existing['email'][$email])) {
            $keep[] = $existing['email'][$email];
        } else {
            mysqli_query($mysqli, "INSERT INTO calendar_event_attendees SET attendee_email = '" . escapeSql($email) . "', attendee_event_id = $event_id");
            $keep[] = $new[] = mysqli_insert_id($mysqli);
        }
    }

    // Those taken off - kept for a moment, so the caller can tell them it is off
    $removed = [];
    $not_kept = $keep ? " AND attendee_id NOT IN (" . implode(',', array_map('intval', $keep)) . ")" : '';
    foreach (calendarEventAttendees($event_id) as $attendee) {
        if (!in_array(intval($attendee['attendee_id']), $keep, true)) {
            $removed[] = $attendee;
        }
    }
    mysqli_query($mysqli, "DELETE FROM calendar_event_attendees WHERE attendee_event_id = $event_id$not_kept");

    return ['new' => $new, 'removed' => $removed];
}

/**
 * The fields of the add and edit forms, checked, as a SET clause - or '' with
 * $error. Dates and times arrive separately and are recombined; an all-day
 * event is pinned to midnight with event_end holding the LAST day it covers
 * (FullCalendar and iCalendar treat an all-day end as exclusive, so those
 * paths add the day). $client_id is the client (already access-checked) and
 * location_id must be one of their sites.
 */
function calendarEventFormSet($post, $client_id, &$error = '') {
    global $mysqli;

    $title = trim((string) ($post['title'] ?? ''));
    if ($title === '') {
        $error = 'An event needs a title.';
        return '';
    }
    $calendar_id = intval($post['calendar'] ?? 0);
    if (!$calendar_id || !mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM calendars WHERE calendar_id = $calendar_id AND calendar_archived_at IS NULL"))) {
        $error = 'Pick the calendar it goes on.';
        return '';
    }

    $all_day = !empty($post['all_day']) ? 1 : 0;
    $start_date = (string) ($post['start_date'] ?? '');
    $end_date = !empty($post['end_date']) ? (string) $post['end_date'] : $start_date;
    if (!calendarIsDate($start_date)) {
        $error = 'Pick the day it starts.';
        return '';
    }
    if (!calendarIsDate($end_date)) {
        $end_date = $start_date;
    }
    if ($all_day) {
        $start = "$start_date 00:00:00";
        $end = "$end_date 00:00:00";
    } else {
        $start_time = preg_match('/^\d{2}:\d{2}$/', (string) ($post['start_time'] ?? '')) ? $post['start_time'] : '09:00';
        $end_time = preg_match('/^\d{2}:\d{2}$/', (string) ($post['end_time'] ?? '')) ? $post['end_time'] : '';
        $start = "$start_date $start_time:00";
        $end = $end_time !== '' ? "$end_date $end_time:00" : date('Y-m-d H:i:s', strtotime($start) + 3600);
    }
    if ($end < $start) {
        $error = 'It has to end after it starts.';
        return '';
    }

    $repeat = isset(CALENDAR_REPEATS[$post['repeat'] ?? '']) ? $post['repeat'] : '';
    $interval = $repeat ? max(1, min(99, intval($post['repeat_interval'] ?? 1))) : 1;
    $until = $repeat ? trim((string) ($post['repeat_until'] ?? '')) : '';
    if ($until !== '' && (!calendarIsDate($until) || $until < $start_date)) {
        $until = '';
    }

    $location_id = 0;
    if ($client_id && intval($post['location_id'] ?? 0)) {
        $location_id = intval(assetClientOwnedId('location', $post['location_id'], $client_id));
    }

    return "event_title = '" . escapeSql(mb_substr($title, 0, 200)) . "',
        event_location = '" . escapeSql(mb_substr(trim((string) ($post['location'] ?? '')), 0, 500)) . "',
        event_description = '" . escapeSql(trim((string) ($post['description'] ?? ''))) . "',
        event_start = '$start', event_end = '$end', event_all_day = $all_day,
        event_repeat = '$repeat', event_repeat_interval = $interval, event_repeat_until = " . ($until !== '' ? "'$until'" : 'NULL') . ",
        event_calendar_id = $calendar_id, event_client_id = " . intval($client_id) . ", event_location_id = $location_id";
}

/** Is it a real Y-m-d date? */
function calendarIsDate($value) {
    $date = DateTime::createFromFormat('!Y-m-d', (string) $value);
    return $date && $date->format('Y-m-d') === $value;
}

/*
 * ---------- what the rest of ITFlow puts on the calendar ----------
 */

/**
 * The schedule other modules contribute, each a source that can be shown or
 * hidden: [key => label, icon, colour, the module it needs, shown by default].
 * Only the ones this agent may see.
 */
function calendarSources() {
    $sources = [
        'tickets' => ['label' => 'Scheduled tickets', 'icon' => 'fa-life-ring', 'color' => '#0d6efd', 'module' => 'module_support', 'default' => true,
            'title' => 'Onsite and remote work booked on tickets'],
        'projects' => ['label' => 'Projects', 'icon' => 'fa-project-diagram', 'color' => '#0f9d8a', 'module' => 'module_support', 'default' => true,
            'title' => 'When open projects start and are due'],
        'reviews' => ['label' => 'Service reviews', 'icon' => 'fa-clipboard-check', 'color' => '#64748b', 'module' => 'module_support', 'default' => true,
            'title' => 'When each service is next due for review'],
        'recurring' => ['label' => 'Recurring tickets', 'icon' => 'fa-redo-alt', 'color' => '#7c3aed', 'module' => 'module_support', 'default' => false,
            'title' => 'When each recurring ticket is next raised'],
        'renewals' => ['label' => 'Renewals', 'icon' => 'fa-hourglass-half', 'color' => '#d97706', 'module' => 'module_support', 'default' => false,
            'title' => 'Domains, certificates, licenses and warranties expiring'],
        'invoices' => ['label' => 'Invoices due', 'icon' => 'fa-file-invoice-dollar', 'color' => '#15803d', 'module' => 'module_sales', 'default' => false,
            'title' => 'Unpaid invoices on the day they fall due'],
    ];
    return array_filter($sources, function ($source) {
        return lookupUserPermission($source['module']) >= 1;
    });
}

/** A scheduled ticket's title on the calendar: "TCK-12 Onsite - Client: subject". */
function calendarTicketTitle($row, $with_client = true) {
    return ticketReference($row) . ' ' . (intval($row['ticket_onsite']) ? 'Onsite' : 'Remote') . ' - '
        . ($with_client && !empty($row['client_name']) ? $row['client_name'] . ': ' : '') . $row['ticket_subject'];
}

/**
 * The items one built-in source has between two datetimes, ready for
 * FullCalendar. $opts: client_id (one client, or 0 for every client this
 * agent can reach) and user_id (only that agent's; 0 for everyone's).
 */
function calendarSourceItems($key, $from, $to, array $opts = []) {
    global $mysqli;
    $sources = calendarSources();
    if (!isset($sources[$key])) {
        return [];
    }
    $source = $sources[$key];
    $client_id = intval($opts['client_id'] ?? 0);
    $user_id = intval($opts['user_id'] ?? 0);
    $from = escapeSql($from);
    $to = escapeSql($to);
    $from_date = substr($from, 0, 10);
    $to_date = substr($to, 0, 10);
    $items = [];
    $base = ['color' => $source['color'], 'editable' => false];
    $client_label = function ($row) use ($client_id) {
        return !$client_id && !empty($row['client_name']) ? $row['client_name'] . ': ' : '';
    };

    if ($key === 'tickets') {
        $can_move = calendarCanScheduleTickets();
        $sql = mysqli_query($mysqli, "SELECT tickets.ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_schedule, ticket_onsite, ticket_client_id,
                ticket_resolved_at, ticket_closed_at, ticket_assigned_to, client_name, user_name, ticket_status_name
            FROM tickets
            LEFT JOIN clients ON client_id = ticket_client_id
            LEFT JOIN users ON user_id = ticket_assigned_to
            LEFT JOIN ticket_statuses ON ticket_status_id = ticket_status
            WHERE ticket_schedule BETWEEN '$from' AND '$to' AND ticket_archived_at IS NULL
            " . ($client_id ? "AND ticket_client_id = $client_id" : '') . "
            " . ($user_id ? "AND ticket_assigned_to = $user_id" : '') . "
            " . clientScopeSql('ticket_client_id'));
        while ($row = mysqli_fetch_assoc($sql)) {
            $open = ticketIsOpen($row);
            $items[] = [
                'id' => 'ticket-' . intval($row['ticket_id']),
                'title' => calendarTicketTitle($row, !$client_id),
                'start' => $row['ticket_schedule'],
                'end' => date('Y-m-d H:i:s', strtotime($row['ticket_schedule']) + CALENDAR_TICKET_MINUTES * 60),
                'allDay' => false,
                'editable' => $can_move && $open,
                'durationEditable' => false,
                'className' => $open ? '' : 'itflow-cal-done',
                'extendedProps' => ['kind' => 'ticket', 'ticket_id' => intval($row['ticket_id']), 'client_id' => intval($row['ticket_client_id']),
                    'icon' => intval($row['ticket_onsite']) ? 'fa-car' : 'fa-headset',
                    'modal' => 'modals/ticket/ticket.php?id=' . intval($row['ticket_id']),
                    'detail' => implode(' · ', array_filter([$row['user_name'] ?: 'Unassigned', $row['ticket_status_name']]))],
            ] + $base;
        }
    } elseif ($key === 'projects') {
        $sql = mysqli_query($mysqli, "SELECT project_id, project_prefix, project_number, project_name, project_start, project_due, project_client_id, client_name
            FROM projects LEFT JOIN clients ON client_id = project_client_id
            WHERE " . PROJECT_OPEN_SQL . " AND (project_start BETWEEN '$from_date' AND '$to_date' OR project_due BETWEEN '$from_date' AND '$to_date')
            " . ($client_id ? "AND project_client_id = $client_id" : '') . "
            " . ($user_id ? "AND project_manager = $user_id" : '') . "
            " . clientScopeSql('project_client_id'));
        while ($row = mysqli_fetch_assoc($sql)) {
            foreach (['project_start' => ['starts', 'fa-play'], 'project_due' => ['due', 'fa-flag-checkered']] as $column => [$what, $icon]) {
                if ($row[$column] && $row[$column] >= $from_date && $row[$column] <= $to_date) {
                    $items[] = [
                        'id' => "project-$what-" . intval($row['project_id']),
                        'title' => projectReference($row) . " $what - " . $client_label($row) . $row['project_name'],
                        'start' => $row[$column], 'allDay' => true,
                        'extendedProps' => ['kind' => 'project', 'icon' => $icon, 'modal' => 'modals/project/project.php?id=' . intval($row['project_id']),
                            'url' => projectUrl($row['project_id'], $row['project_client_id'])],
                    ] + $base;
                }
            }
        }
    } elseif ($key === 'reviews') {
        $sql = mysqli_query($mysqli, "SELECT service_id, service_name, service_review_due, service_client_id, client_name FROM services
            JOIN clients ON client_id = service_client_id
            WHERE service_archived_at IS NULL AND client_archived_at IS NULL AND service_review_due BETWEEN '$from_date' AND '$to_date'
            " . ($client_id ? "AND service_client_id = $client_id" : '') . "
            " . clientScopeSql('service_client_id'));
        while ($row = mysqli_fetch_assoc($sql)) {
            $items[] = [
                'id' => 'review-' . intval($row['service_id']),
                'title' => 'Review - ' . $client_label($row) . $row['service_name'],
                'start' => $row['service_review_due'], 'allDay' => true,
                'extendedProps' => ['kind' => 'review', 'icon' => 'fa-clipboard-check', 'modal' => 'modals/service/service.php?id=' . intval($row['service_id']),
                    'url' => serviceUrl($row['service_id'], $row['service_client_id'])],
            ] + $base;
        }
    } elseif ($key === 'recurring') {
        $sql = mysqli_query($mysqli, "SELECT recurring_ticket_id, recurring_ticket_subject, recurring_ticket_frequency, recurring_ticket_next_run, recurring_ticket_client_id, client_name
            FROM recurring_tickets LEFT JOIN clients ON client_id = recurring_ticket_client_id
            WHERE recurring_ticket_next_run BETWEEN '$from_date' AND '$to_date' AND recurring_ticket_paused_at IS NULL
            " . ($client_id ? "AND recurring_ticket_client_id = $client_id" : '') . "
            " . ($user_id ? "AND recurring_ticket_assigned_to = $user_id" : '') . "
            " . clientScopeSql('recurring_ticket_client_id'));
        while ($row = mysqli_fetch_assoc($sql)) {
            $row_client = intval($row['recurring_ticket_client_id']);
            $items[] = [
                'id' => 'recurring-' . intval($row['recurring_ticket_id']),
                'title' => 'Raised - ' . $client_label($row) . $row['recurring_ticket_subject'],
                'start' => $row['recurring_ticket_next_run'], 'allDay' => true,
                'extendedProps' => ['kind' => 'recurring', 'icon' => 'fa-redo-alt', 'detail' => $row['recurring_ticket_frequency'],
                    'url' => 'recurring_tickets.php' . ($row_client ? "?client_id=$row_client" : '')],
            ] + $base;
        }
    } elseif ($key === 'renewals') {
        $queries = [
            'domain' => ["SELECT domain_id AS id, domain_name AS name, domain_expire AS d, client_name FROM domains LEFT JOIN clients ON client_id = domain_client_id
                WHERE domain_archived_at IS NULL AND domain_expire BETWEEN '$from_date' AND '$to_date'" . ($client_id ? " AND domain_client_id = $client_id" : '') . clientScopeSql('domain_client_id'),
                'Domain expires', 'fa-globe', 'modals/domain/domain.php?id='],
            'certificate' => ["SELECT certificate_id AS id, certificate_name AS name, certificate_expire AS d, client_name FROM certificates LEFT JOIN clients ON client_id = certificate_client_id
                WHERE certificate_archived_at IS NULL AND certificate_expire BETWEEN '$from_date' AND '$to_date'" . ($client_id ? " AND certificate_client_id = $client_id" : '') . clientScopeSql('certificate_client_id'),
                'Certificate expires', 'fa-lock', 'modals/certificate/certificate.php?id='],
            'software' => ["SELECT software_id AS id, software_name AS name, software_expire AS d, client_name FROM software LEFT JOIN clients ON client_id = software_client_id
                WHERE software_archived_at IS NULL AND software_expire BETWEEN '$from_date' AND '$to_date'" . ($client_id ? " AND software_client_id = $client_id" : '') . clientScopeSql('software_client_id'),
                'License renews', 'fa-cube', 'modals/software/software.php?id='],
            'warranty' => ["SELECT asset_id AS id, asset_name AS name, asset_warranty_expire AS d, client_name FROM assets LEFT JOIN clients ON client_id = asset_client_id
                WHERE asset_archived_at IS NULL AND asset_warranty_expire BETWEEN '$from_date' AND '$to_date'" . ($client_id ? " AND asset_client_id = $client_id" : '') . clientScopeSql('asset_client_id'),
                'Warranty ends', 'fa-shield-alt', 'modals/asset/asset.php?id='],
        ];
        foreach ($queries as $kind => [$query, $what, $icon, $modal]) {
            $sql = mysqli_query($mysqli, $query);
            while ($row = mysqli_fetch_assoc($sql)) {
                $items[] = [
                    'id' => "renewal-$kind-" . intval($row['id']),
                    'title' => "$what - " . $client_label($row) . $row['name'],
                    'start' => $row['d'], 'allDay' => true,
                    'extendedProps' => ['kind' => 'renewal', 'icon' => $icon, 'modal' => $modal . intval($row['id'])],
                ] + $base;
            }
        }
    } elseif ($key === 'invoices') {
        $sql = mysqli_query($mysqli, "SELECT invoice_id, invoice_prefix, invoice_number, invoice_due, invoice_client_id, client_name
            FROM invoices LEFT JOIN clients ON client_id = invoice_client_id
            WHERE invoice_status IN ('Sent', 'Viewed', 'Partial') AND invoice_archived_at IS NULL AND invoice_due BETWEEN '$from_date' AND '$to_date'
            " . ($client_id ? "AND invoice_client_id = $client_id" : '') . "
            " . clientScopeSql('invoice_client_id'));
        while ($row = mysqli_fetch_assoc($sql)) {
            $items[] = [
                'id' => 'invoice-' . intval($row['invoice_id']),
                'title' => $row['invoice_prefix'] . $row['invoice_number'] . ' due - ' . $row['client_name'],
                'start' => $row['invoice_due'], 'allDay' => true,
                'extendedProps' => ['kind' => 'invoice', 'icon' => 'fa-file-invoice-dollar',
                    'url' => 'invoice.php?client_id=' . intval($row['invoice_client_id']) . '&invoice_id=' . intval($row['invoice_id'])],
            ] + $base;
        }
    }

    return $items;
}

/**
 * The events on some calendars between two datetimes, repeats expanded, ready
 * for FullCalendar. $opts: client_id (one client's, or every client this agent
 * can reach plus the events with no client), user_id (only the events that
 * agent is on or organised).
 */
function calendarEventItems(array $calendar_ids, $from, $to, array $opts = []) {
    global $mysqli;
    $calendar_ids = array_values(array_filter(array_map('intval', $calendar_ids)));
    if (!$calendar_ids) {
        return [];
    }
    $from = escapeSql($from);
    $to = escapeSql($to);
    $client_id = intval($opts['client_id'] ?? 0);
    $user_id = intval($opts['user_id'] ?? 0);
    $user_query = $user_id ? "AND (event_created_by = $user_id OR EXISTS (SELECT 1 FROM calendar_event_attendees a WHERE a.attendee_event_id = event_id AND a.attendee_user_id = $user_id))" : '';

    $sql = mysqli_query($mysqli, "SELECT event_id, event_title, event_start, event_end, event_all_day, event_repeat, event_repeat_interval, event_repeat_until, event_repeat_skip,
            event_location, event_client_id, event_calendar_id, event_created_by, calendar_color, client_name, location_name,
            (SELECT COUNT(*) FROM calendar_event_attendees a WHERE a.attendee_event_id = event_id) AS attendee_count,
            (SELECT COUNT(*) FROM calendar_event_attendees a WHERE a.attendee_event_id = event_id AND a.attendee_invited_at IS NOT NULL) AS invited_count
        FROM calendar_events
        JOIN calendars ON calendar_id = event_calendar_id AND calendar_archived_at IS NULL
        LEFT JOIN clients ON client_id = event_client_id AND event_client_id > 0
        LEFT JOIN locations ON location_id = event_location_id AND event_location_id > 0
        WHERE event_calendar_id IN (" . implode(',', $calendar_ids) . ") AND event_archived_at IS NULL
        AND event_start <= '$to' AND ((event_repeat IS NOT NULL AND event_repeat <> '') OR COALESCE(event_end, event_start) >= '$from')
        " . ($client_id ? "AND event_client_id = $client_id" : '') . "
        $user_query
        " . clientScopeSql('event_client_id') . calendarEventScopeSql());

    $items = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $event_id = intval($row['event_id']);
        $all_day = !empty($row['event_all_day']);
        $repeats = calendarEventRepeats($row);
        foreach (expandRecurringEvent($row, $from, $to) as $occurrence) {
            $end = $occurrence['end'];
            // event_end is the last day an all-day event covers; FullCalendar's all-day end is exclusive
            if ($all_day) {
                $end = date('Y-m-d', strtotime(($end ?: $occurrence['start']) . ' +1 day'));
            }
            $items[] = [
                'id' => "event-$event_id-" . date('YmdHi', strtotime($occurrence['start'])),
                'title' => $row['event_title'],
                'start' => $all_day ? substr($occurrence['start'], 0, 10) : $occurrence['start'],
                'end' => $end,
                'allDay' => $all_day,
                'color' => calendarColor($row['calendar_color']),
                // A repeat is moved from its form, so a drag cannot shift the whole series by accident
                'editable' => !$repeats && calendarEventCan($row, 'edit'),
                'extendedProps' => [
                    'kind' => 'event', 'event_id' => $event_id, 'calendar_id' => intval($row['event_calendar_id']), 'occurrence' => substr($occurrence['start'], 0, 10),
                    'invited' => intval($row['invited_count']),
                    'icon' => $repeats ? 'fa-redo' : (intval($row['attendee_count']) ? 'fa-user-friends' : ''),
                    'modal' => "modals/calendar/calendar_event.php?id=$event_id&occurrence=" . substr($occurrence['start'], 0, 10),
                    'detail' => implode(' · ', array_filter([$client_id ? '' : ($row['client_name'] ?? ''), $row['location_name'] ?: $row['event_location']])),
                ],
            ];
        }
    }
    return $items;
}

/*
 * ---------- iCalendar ----------
 */

/*
 * Escapes a value for an iCalendar TEXT property (RFC 5545 3.3.11)
 * Backslashes are escaped first, otherwise the escapes added below get escaped again
 */
function icsEscapeText($text) {
    $text = str_replace("\\", "\\\\", (string) $text);
    $text = str_replace(["\r\n", "\r", "\n"], "\\n", $text);
    return str_replace([";", ","], ["\\;", "\\,"], $text);
}

/** A parameter value (CN=...) - quoted when it has anything a parameter cannot carry bare. */
function icsParam($value) {
    $value = str_replace(['"', "\r", "\n"], ["'", ' ', ' '], (string) $value);
    return preg_match('/[;:,]/', $value) ? '"' . $value . '"' : $value;
}

/*
 * Folds a content line to 75 octets per RFC 5545 3.1
 * Continuation lines begin with a single space which counts toward the 75, so
 * $buffer already carries it. Folding happens on character boundaries so a
 * multi-byte UTF-8 sequence is never cut in half - splitting mid-sequence is
 * what makes hand-rolled feeds fail to parse on non-ASCII event titles.
 */
function icsFoldLine($line) {

    if (strlen($line) <= 75) {
        return $line;
    }

    $chars = preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY);

    // Invalid UTF-8 - fold on byte boundaries rather than emit an over-long line
    if ($chars === false) {
        return rtrim(chunk_split($line, 74, "\r\n "), "\r\n ");
    }

    $folded = '';
    $buffer = '';

    foreach ($chars as $char) {
        if (strlen($buffer) + strlen($char) > 75) {
            $folded .= $buffer . "\r\n";
            $buffer = ' ';
        }
        $buffer .= $char;
    }

    return $folded . $buffer;
}

/*
 * Converts an ITFlow datetime to an iCalendar UTC DATE-TIME
 * Stored datetimes are wall clock in the instance's configured timezone.
 */
function icsFormatUtc($datetime, $timezone) {

    if (empty($datetime) || $datetime === '0000-00-00 00:00:00') {
        return null;
    }

    try {
        $dt = new DateTime($datetime, new DateTimeZone($timezone ?: 'UTC'));
        $dt->setTimezone(new DateTimeZone('UTC'));
    } catch (Exception $e) {
        return null;
    }

    return $dt->format('Ymd\THis\Z');
}

/** An ITFlow datetime as a local iCalendar DATE-TIME, for a property carrying TZID. */
function icsFormatLocal($datetime) {
    $timestamp = strtotime((string) $datetime);
    return $timestamp === false ? null : date('Ymd\THis', $timestamp);
}

/*
 * Converts an ITFlow datetime to an iCalendar DATE value (all-day events)
 * No timezone conversion - a floating date must stay on the day it was entered
 */
function icsFormatDate($datetime, $offset_days = 0) {

    if (empty($datetime) || $datetime === '0000-00-00 00:00:00') {
        return null;
    }

    $timestamp = strtotime($datetime);

    if ($timestamp === false) {
        return null;
    }

    return date('Ymd', strtotime("$offset_days day", $timestamp));
}

/** Is this a timezone PHP knows, other than UTC? Timed events are then published in it, with its VTIMEZONE. */
function icsUsesTzid($timezone) {
    if (empty($timezone) || in_array(strtoupper((string) $timezone), ['UTC', 'Z', 'GMT', 'ETC/UTC'], true)) {
        return false;
    }
    try {
        new DateTimeZone($timezone);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/** An offset in seconds as an iCalendar UTC-OFFSET: -0500, +0530. */
function icsOffset($seconds) {
    $sign = $seconds < 0 ? '-' : '+';
    $seconds = abs(intval($seconds));
    return $sign . sprintf('%02d%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60));
}

/**
 * The lines of a VTIMEZONE for $timezone covering $from to $to (timestamps),
 * built from PHP's own transition data: one STANDARD or DAYLIGHT observance
 * per change, each starting at the local time the change happens. Clients
 * that know the zone by name use their own rules; the rest have them here.
 */
function icsVtimezone($timezone, $from, $to) {
    $tz = new DateTimeZone($timezone);
    $transitions = $tz->getTransitions($from, $to) ?: [];
    $lines = ['BEGIN:VTIMEZONE', 'TZID:' . $timezone];
    if (!$transitions) {
        $offset = $tz->getOffset(new DateTime('@' . $from));
        return array_merge($lines, ['BEGIN:STANDARD', 'DTSTART:19700101T000000', 'TZOFFSETFROM:' . icsOffset($offset), 'TZOFFSETTO:' . icsOffset($offset), 'END:STANDARD', 'END:VTIMEZONE']);
    }
    $previous = $transitions[0]['offset'];
    foreach ($transitions as $transition) {
        $kind = $transition['isdst'] ? 'DAYLIGHT' : 'STANDARD';
        $lines[] = "BEGIN:$kind";
        // Local time as it read just before the change
        $lines[] = 'DTSTART:' . gmdate('Ymd\THis', $transition['ts'] + $previous);
        $lines[] = 'TZOFFSETFROM:' . icsOffset($previous);
        $lines[] = 'TZOFFSETTO:' . icsOffset($transition['offset']);
        if (preg_match('/^[A-Za-z]{2,6}$/', (string) $transition['abbr'])) {
            $lines[] = 'TZNAME:' . $transition['abbr'];
        }
        $lines[] = "END:$kind";
        $previous = $transition['offset'];
    }
    $lines[] = 'END:VTIMEZONE';
    return $lines;
}

/*
 * Maps ITFlow's event_repeat wording onto an RRULE: FREQ, then INTERVAL when
 * more than one, then UNTIL. A timed event's UNTIL is the end of that day in
 * UTC (RFC 5545 requires UTC when DTSTART carries a TZID); an all-day event's
 * is the date itself.
 */
function icsRepeatToRrule($repeat, $interval = 1, $until = null, $all_day = false, $timezone = 'UTC') {

    if (!isset(CALENDAR_REPEATS[(string) $repeat])) {
        return null;
    }

    $rule = 'FREQ=' . CALENDAR_REPEATS[(string) $repeat][0];
    if (intval($interval) > 1) {
        $rule .= ';INTERVAL=' . intval($interval);
    }
    if (!empty($until)) {
        $rule .= ';UNTIL=' . ($all_day ? date('Ymd', strtotime($until)) : icsFormatUtc(substr($until, 0, 10) . ' 23:59:59', $timezone));
    }

    return $rule;
}

/*
 * True when an event looks like an all-day event
 * Fallback for rows written before event_all_day existed (added in 2.5.9): a
 * midnight start, and either no end or a midnight end.
 */
function icsEventIsAllDay($start, $end) {

    if (empty($start) || date('H:i:s', strtotime($start)) !== '00:00:00') {
        return false;
    }

    if (empty($end) || $end === '0000-00-00 00:00:00') {
        return true;
    }

    return date('H:i:s', strtotime($end)) === '00:00:00';
}

/**
 * The lines of one VEVENT. $item:
 *   uid, start, end, all_day, summary, description, location, url, stamp,
 *   created, sequence, status, categories, busy (title and details withheld),
 *   repeat, repeat_interval, repeat_until, skip (dates), organizer ([name,
 *   email]), attendees ([[name, email], ...]).
 * Timed events carry TZID=$timezone, or are in UTC when $tzid is false.
 */
function icsEventLines(array $item, $timezone, $tzid) {
    $all_day = !empty($item['all_day']);
    $lines = ['BEGIN:VEVENT', 'UID:' . $item['uid']];
    $stamp = !empty($item['stamp']) ? icsFormatUtc($item['stamp'], $timezone) : null;
    $lines[] = 'DTSTAMP:' . ($stamp ?: gmdate('Ymd\THis\Z'));

    $start = $item['start'];
    $end = $item['end'] ?? null;
    if ($all_day) {
        $dtstart = icsFormatDate($start);
        // The last day covered, plus one - DTEND is exclusive for DATE values
        $dtend = icsFormatDate($end ?: $start, 1);
        if (empty($dtend) || $dtend <= $dtstart) {
            $dtend = icsFormatDate($start, 1);
        }
        $lines[] = "DTSTART;VALUE=DATE:$dtstart";
        $lines[] = "DTEND;VALUE=DATE:$dtend";
    } else {
        if (empty($end) || $end === '0000-00-00 00:00:00' || $end <= $start) {
            $end = date('Y-m-d H:i:s', strtotime($start) + 3600);
        }
        if ($tzid) {
            $lines[] = "DTSTART;TZID=$timezone:" . icsFormatLocal($start);
            $lines[] = "DTEND;TZID=$timezone:" . icsFormatLocal($end);
        } else {
            $lines[] = 'DTSTART:' . icsFormatUtc($start, $timezone);
            $lines[] = 'DTEND:' . icsFormatUtc($end, $timezone);
        }
    }

    $rrule = icsRepeatToRrule($item['repeat'] ?? '', $item['repeat_interval'] ?? 1, $item['repeat_until'] ?? null, $all_day, $timezone);
    if ($rrule) {
        $lines[] = "RRULE:$rrule";
        foreach ($item['skip'] ?? [] as $date) {
            if ($all_day) {
                $lines[] = 'EXDATE;VALUE=DATE:' . date('Ymd', strtotime($date));
            } else {
                $at = $date . ' ' . date('H:i:s', strtotime($start));
                $lines[] = $tzid ? "EXDATE;TZID=$timezone:" . icsFormatLocal($at) : 'EXDATE:' . icsFormatUtc($at, $timezone);
            }
        }
    }

    if (!empty($item['busy'])) {
        // Titles, descriptions and locations all withheld - only that the time is taken
        $lines[] = 'SUMMARY:Busy';
        $lines[] = 'CLASS:PRIVATE';
    } else {
        $lines[] = 'SUMMARY:' . icsEscapeText($item['summary'] ?? '');
        if (!empty($item['location'])) {
            $lines[] = 'LOCATION:' . icsEscapeText($item['location']);
        }
        if (!empty($item['description'])) {
            $lines[] = 'DESCRIPTION:' . icsEscapeText($item['description']);
        }
        if (!empty($item['url'])) {
            $lines[] = 'URL:' . str_replace(["\r", "\n", ' '], '', $item['url']);
        }
        if (!empty($item['categories'])) {
            $lines[] = 'CATEGORIES:' . icsEscapeText($item['categories']);
        }
    }
    $lines[] = 'TRANSP:OPAQUE';

    if (!empty($item['organizer'][1])) {
        $lines[] = 'ORGANIZER;CN=' . icsParam($item['organizer'][0] ?: $item['organizer'][1]) . ':mailto:' . $item['organizer'][1];
    }
    foreach ($item['attendees'] ?? [] as [$name, $email]) {
        $lines[] = 'ATTENDEE;CN=' . icsParam($name ?: $email) . ';ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=FALSE:mailto:' . $email;
    }

    if (isset($item['sequence'])) {
        $lines[] = 'SEQUENCE:' . intval($item['sequence']);
    }
    if (!empty($item['status'])) {
        $lines[] = 'STATUS:' . $item['status'];
    }
    if (!empty($item['created'])) {
        $created = icsFormatUtc($item['created'], $timezone);
        if ($created) {
            $lines[] = "CREATED:$created";
        }
    }
    if ($stamp) {
        $lines[] = "LAST-MODIFIED:$stamp";
    }
    $lines[] = 'END:VEVENT';
    return $lines;
}

/**
 * A whole VCALENDAR - a subscription feed (METHOD:PUBLISH) or an invitation
 * (REQUEST / CANCEL) - with CRLF line endings, folded, ready to send.
 * $calendar: name, color, method, invite (no feed headers).
 */
function icsCalendar(array $calendar, array $items, $timezone) {
    $tzid = icsUsesTzid($timezone);
    $lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//ITFlow//ITFlow Calendar//EN',
        'CALSCALE:GREGORIAN',
        'METHOD:' . ($calendar['method'] ?? 'PUBLISH'),
    ];
    if (empty($calendar['invite'])) {
        // X-WR-CALNAME is non-standard and clients render its value literally, so a
        // TEXT-escaped comma would show as a visible backslash in Google's sidebar.
        // Strip only what would break the line structure.
        $lines[] = 'X-WR-CALNAME:' . str_replace(["\r\n", "\r", "\n"], ' ', (string) ($calendar['name'] ?? 'ITFlow'));
        $lines[] = 'X-WR-TIMEZONE:' . $timezone;
        // Nextcloud reads these, but only during a refresh and only when no rate is
        // stored for the subscription yet - its first refresh is still a day out
        // unless the admin lowers calendarSubscriptionRefreshRate. Google ignores them.
        $lines[] = 'REFRESH-INTERVAL;VALUE=DURATION:PT15M';
        $lines[] = 'X-PUBLISHED-TTL:PT15M';
        if (!empty($calendar['color']) && preg_match('/^#[0-9a-f]{6}$/i', $calendar['color'])) {
            $lines[] = 'X-APPLE-CALENDAR-COLOR:' . $calendar['color'];
        }
    }

    // One VTIMEZONE covering every timed item, when the zone is published by name
    $timed = array_filter($items, function ($item) {
        return empty($item['all_day']);
    });
    if ($tzid && $timed) {
        $starts = array_map(function ($item) {
            return strtotime($item['start']);
        }, $timed);
        $repeating = (bool) array_filter($timed, function ($item) {
            return !empty($item['repeat']);
        });
        $from = min($starts) - 86400 * 366;
        // A repeat runs on - cover the years a subscriber will look ahead
        $to = max(max($starts), time()) + 86400 * 366 * ($repeating ? 5 : 1);
        $lines = array_merge($lines, icsVtimezone($timezone, $from, $to));
    }

    foreach ($items as $item) {
        $lines = array_merge($lines, icsEventLines($item, $timezone, $tzid));
    }
    $lines[] = 'END:VCALENDAR';

    $output = '';
    foreach ($lines as $line) {
        $output .= icsFoldLine($line) . "\r\n";
    }
    return $output;
}

/** The host every UID and link is built from - config_base_url, without a scheme. */
function calendarHost() {
    global $config_base_url;
    return rtrim(preg_replace('#^[a-z]+://#i', '', (string) $config_base_url), '/') ?: 'itflow.local';
}

/** An event as an iCalendar item. $busy withholds the title and details. */
function calendarEventIcsItem(array $event, $busy = false) {
    $host = calendarHost();
    $event_id = intval($event['event_id']);
    $all_day = isset($event['event_all_day']) ? !empty($event['event_all_day']) : icsEventIsAllDay($event['event_start'], $event['event_end']);
    return [
        // Stable across edits and feed regenerations, so subscribers update in place
        'uid' => "itflow-event-$event_id@$host",
        'start' => $event['event_start'],
        'end' => $event['event_end'],
        'all_day' => $all_day,
        'summary' => $event['event_title'],
        'location' => calendarWhereText($event),
        'description' => trim(strip_tags((string) $event['event_description'])),
        // Derived from the event, not the request clock - bytes that changed on every
        // fetch could never give an ETag hit
        'stamp' => $event['event_updated_at'] ?: $event['event_created_at'],
        'created' => $event['event_created_at'],
        'sequence' => intval($event['event_sequence'] ?? 0),
        'repeat' => $event['event_repeat'] ?? '',
        'repeat_interval' => $event['event_repeat_interval'] ?? 1,
        'repeat_until' => $event['event_repeat_until'] ?? null,
        'skip' => calendarSkippedDates($event),
        'busy' => $busy,
    ];
}

/*
 * The feed for one shared calendar: its events through icsCalendar(). $host
 * is no longer needed - UIDs come from calendarHost() - and kept for callers.
 */
function buildCalendarFeedIcs(array $calendar, array $events, $timezone, $host = null) {
    $busy = !empty($calendar['calendar_feed_busy_only']);
    $items = [];
    foreach ($events as $event) {
        $items[] = calendarEventIcsItem($event, $busy);
    }
    return icsCalendar(['name' => $calendar['calendar_name'], 'color' => calendarColor($calendar['calendar_color'] ?? '', '')], $items, $timezone);
}

/*
 * ---------- an agent's own schedule, as a subscription ----------
 */

/** An agent's schedule link and its settings, or null when they have none. */
function calendarUserFeed($user_id) {
    global $mysqli;
    $user_id = intval($user_id);
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM user_calendar_feeds WHERE feed_user_id = $user_id LIMIT 1"));
    return $row ?: null;
}

/** The https:// and webcal:// forms of a feed link. */
function calendarFeedUrls($key) {
    $host = calendarHost();
    $path = '/guest/guest_calendar_feed.php?key=' . rawurlencode((string) $key);
    return ['https' => "https://$host$path", 'webcal' => "webcal://$host$path"];
}

/**
 * Act as the feed's owner for the rest of the request - their role and the
 * clients they may reach - so the permission and client-scope checks the app
 * already has decide what the link shows. False when they can no longer sign
 * in (disabled, archived, not an agent).
 */
function calendarActAs($user_id) {
    global $mysqli, $session_user_id, $session_user_role, $session_is_admin, $client_access_array, $client_deny_array;
    $user_id = intval($user_id);
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT user_id, user_role_id, user_status, user_type, user_archived_at, role_is_admin
        FROM users LEFT JOIN user_roles ON role_id = user_role_id WHERE user_id = $user_id LIMIT 1"));
    if (!$row || intval($row['user_type']) !== 1 || intval($row['user_status']) !== 1 || $row['user_archived_at'] !== null) {
        return false;
    }
    $session_user_id = $user_id;
    $session_user_role = intval($row['user_role_id']);
    $session_is_admin = intval($row['role_is_admin']) === 1;
    $client_access_array = [];
    $client_deny_array = [];
    $sql = mysqli_query($mysqli, "SELECT client_id, permission_type FROM user_client_permissions WHERE user_id = $user_id");
    while ($perm = mysqli_fetch_assoc($sql)) {
        if ($perm['permission_type'] === 'deny') {
            $client_deny_array[] = intval($perm['client_id']);
        } else {
            $client_access_array[] = intval($perm['client_id']);
        }
    }
    return true;
}

/**
 * The items of an agent's schedule feed, as that agent (calendarActAs()
 * first): their scheduled tickets, the events they are on or organised, and
 * the projects they manage - or the whole team's, for a feed set to everyone.
 * A scheduled ticket carries the client, the site, who to see and how to
 * reach them, and a link back to it.
 */
function calendarUserFeedItems(array $feed, $from, $to) {
    global $mysqli;
    $user_id = intval($feed['feed_user_id']);
    $everyone = ($feed['feed_scope'] ?? 'mine') === 'everyone';
    $busy = !empty($feed['feed_busy_only']);
    $host = calendarHost();
    $items = [];

    if (!empty($feed['feed_tickets']) && lookupUserPermission('module_support') >= 1) {
        $sql = mysqli_query($mysqli, "SELECT tickets.ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_schedule, ticket_onsite, ticket_client_id,
                ticket_created_at, ticket_updated_at, ticket_resolved_at, ticket_closed_at, client_name, user_name,
                contact_name, contact_phone, contact_phone_country_code, contact_extension, contact_mobile, contact_mobile_country_code,
                location_name, location_address, location_city, location_state, location_zip
            FROM tickets
            LEFT JOIN clients ON client_id = ticket_client_id
            LEFT JOIN users ON user_id = ticket_assigned_to
            LEFT JOIN contacts ON contact_id = ticket_contact_id AND ticket_contact_id > 0
            LEFT JOIN locations ON location_id = IF(ticket_location_id > 0, ticket_location_id, contacts.contact_location_id)
            WHERE ticket_schedule BETWEEN '$from' AND '$to' AND ticket_archived_at IS NULL
            " . ($everyone ? '' : "AND ticket_assigned_to = $user_id") . "
            " . clientScopeSql('ticket_client_id'));
        while ($row = mysqli_fetch_assoc($sql)) {
            $ticket_id = intval($row['ticket_id']);
            $phone = $row['contact_phone'] ? formatPhoneNumber($row['contact_phone'], $row['contact_phone_country_code']) . ($row['contact_extension'] ? ' x' . $row['contact_extension'] : '') : '';
            $mobile = $row['contact_mobile'] ? formatPhoneNumber($row['contact_mobile'], $row['contact_mobile_country_code']) : '';
            $link = "https://$host/agent/" . ticketUrl($ticket_id, $row['ticket_client_id']);
            $items[] = [
                'uid' => "itflow-ticket-$ticket_id@$host",
                'start' => $row['ticket_schedule'],
                'end' => date('Y-m-d H:i:s', strtotime($row['ticket_schedule']) + CALENDAR_TICKET_MINUTES * 60),
                'summary' => calendarTicketTitle($row) . ($everyone && $row['user_name'] ? ' (' . $row['user_name'] . ')' : '') . (ticketIsOpen($row) ? '' : ' [done]'),
                'location' => implode(', ', array_filter([$row['location_address'], $row['location_city'], trim($row['location_state'] . ' ' . $row['location_zip'])])),
                'description' => implode("\n", array_filter([
                    $row['client_name'] ? 'Client: ' . $row['client_name'] : '',
                    $row['location_name'] ? 'Site: ' . $row['location_name'] : '',
                    $row['contact_name'] ? 'Contact: ' . $row['contact_name'] . ($phone || $mobile ? ' - ' . implode(' / ', array_filter([$phone, $mobile])) : '') : '',
                    'Assigned: ' . ($row['user_name'] ?: 'Nobody'),
                    $link,
                ])),
                'url' => $link,
                'stamp' => $row['ticket_updated_at'] ?: $row['ticket_created_at'],
                'categories' => intval($row['ticket_onsite']) ? 'Onsite' : 'Remote',
                'busy' => $busy,
            ];
        }
    }

    if (!empty($feed['feed_events'])) {
        $sql = mysqli_query($mysqli, "SELECT calendar_events.*, location_name, location_address, location_city, location_state, location_zip
            FROM calendar_events
            JOIN calendars ON calendar_id = event_calendar_id AND calendar_archived_at IS NULL
            LEFT JOIN locations ON location_id = event_location_id AND event_location_id > 0
            WHERE event_archived_at IS NULL
            AND ((event_repeat IS NOT NULL AND event_repeat <> '') OR event_start BETWEEN '$from' AND '$to')
            " . ($everyone ? '' : "AND (event_created_by = $user_id OR EXISTS (SELECT 1 FROM calendar_event_attendees a WHERE a.attendee_event_id = event_id AND a.attendee_user_id = $user_id))") . "
            " . clientScopeSql('event_client_id') . calendarEventScopeSql() . "
            ORDER BY event_start");
        while ($row = mysqli_fetch_assoc($sql)) {
            $items[] = calendarEventIcsItem($row, $busy);
        }
    }

    if (!empty($feed['feed_projects']) && lookupUserPermission('module_support') >= 1) {
        $sql = mysqli_query($mysqli, "SELECT project_id, project_prefix, project_number, project_name, project_start, project_due, project_client_id, project_created_at, project_updated_at, client_name
            FROM projects LEFT JOIN clients ON client_id = project_client_id
            WHERE " . PROJECT_OPEN_SQL . " AND (project_start IS NOT NULL OR project_due IS NOT NULL)
            " . ($everyone ? '' : "AND project_manager = $user_id") . "
            " . clientScopeSql('project_client_id'));
        while ($row = mysqli_fetch_assoc($sql)) {
            foreach (['project_start' => 'starts', 'project_due' => 'due'] as $column => $what) {
                if (empty($row[$column])) {
                    continue;
                }
                $link = "https://$host/agent/" . projectUrl($row['project_id'], $row['project_client_id']);
                $items[] = [
                    'uid' => 'itflow-project-' . intval($row['project_id']) . "-$what@$host",
                    'start' => $row[$column] . ' 00:00:00', 'all_day' => true,
                    'summary' => projectReference($row) . " $what - " . (intval($row['project_client_id']) ? $row['client_name'] . ': ' : '') . $row['project_name'],
                    'description' => (intval($row['project_client_id']) ? 'Client: ' . $row['client_name'] . "\n" : '') . $link,
                    'url' => $link,
                    'stamp' => $row['project_updated_at'] ?: $row['project_created_at'],
                    'busy' => $busy,
                ];
            }
        }
    }

    return $items;
}

/*
 * ---------- invitations ----------
 */

/** Can mail go out from this install? */
function calendarCanEmail() {
    return emailReady();
}

/**
 * Send an event's invitation, its update, or its cancellation to some of its
 * attendees - every one with an email when $attendees is null. Each gets a
 * short email saying what, when and where, with the event attached as an
 * iCalendar invitation their calendar adds - or updates, or removes, matched
 * on the UID with the newer SEQUENCE winning. $kind: invite, update, cancel.
 * Returns how many were queued.
 */
function calendarSendInvites(array $event, $kind = 'invite', $attendees = null) {
    global $mysqli, $config_timezone, $session_company_name, $session_name;

    if (!calendarCanEmail()) {
        return 0;
    }
    $event_id = intval($event['event_id']);
    if ($attendees === null) {
        $attendees = calendarEventAttendees($event_id);
    }
    $attendees = array_values(array_filter($attendees, function ($attendee) {
        return filter_var($attendee['email'] ?? '', FILTER_VALIDATE_EMAIL);
    }));
    if (!$attendees) {
        return 0;
    }

    $cancel = $kind === 'cancel';
    $from = emailFrom();
    $item = calendarEventIcsItem($event);
    $item['stamp'] = date('Y-m-d H:i:s');
    $item['organizer'] = [$from['name'] ?: stripslashes((string) $session_company_name), $from['email']];
    $item['attendees'] = array_map(function ($attendee) {
        return [$attendee['name'], $attendee['email']];
    }, $attendees);
    $item['status'] = $cancel ? 'CANCELLED' : 'CONFIRMED';
    $ics = icsCalendar(['method' => $cancel ? 'CANCEL' : 'REQUEST', 'invite' => true], [$item], $config_timezone);

    $when = calendarWhenText($event);
    $repeat = calendarRepeatText($event);
    $where = calendarWhereText($event);
    $meeting = calendarMeetingUrl($event);
    $description = trim(html_entity_decode(strip_tags((string) $event['event_description']), ENT_QUOTES, 'UTF-8'));
    $intro = [
        'invite' => 'You are invited to the event below. The invitation is attached, so it can go straight into your calendar.',
        'update' => 'The event below has changed. The attached invitation updates the one in your calendar.',
        'cancel' => 'The event below has been cancelled. The attached cancellation takes it off your calendar.',
    ][$kind] ?? '';
    $subject_prefix = ['invite' => 'Invitation', 'update' => 'Updated', 'cancel' => 'Cancelled'][$kind] ?? 'Invitation';

    $recipients = array_map(function ($attendee) use ($cancel) {
        return ['email' => $attendee['email'], 'name' => (string) $attendee['name'], 'footnote' => $cancel ? 'You get this because you were invited to this event.' : 'You get this because you are invited to this event.'];
    }, $attendees);
    emailQueue($recipients, mb_substr("$subject_prefix: " . $event['event_title'] . ' - ' . $when, 0, 250), [
        'kind' => ['invite' => 'Invitation', 'update' => 'Event changed', 'cancel' => 'Event cancelled'][$kind] ?? 'Invitation',
        'tone' => $cancel ? 'secondary' : 'primary',
        'preview' => "$when" . ($where !== '' ? " - $where" : ''),
        'title' => (string) $event['event_title'],
        'intro' => [$intro],
        'summary' => array_filter(['When' => $when, 'Repeats' => (string) $repeat, 'Where' => $where]),
        'note' => !$cancel ? $description : '',
        'button' => $meeting && !$cancel ? ['label' => 'Join the meeting', 'url' => $meeting, 'show_url' => true] : null,
    ], ['from' => $from, 'cal_str' => $ics]);

    $ids = array_filter(array_map(function ($attendee) {
        return intval($attendee['attendee_id'] ?? 0);
    }, $attendees));
    if (!$cancel && $ids) {
        mysqli_query($mysqli, "UPDATE calendar_event_attendees SET attendee_invited_at = NOW(), attendee_invitation_status = 1 WHERE attendee_id IN (" . implode(',', $ids) . ")");
    }
    logAudit("Calendar Event", "Email", "$session_name sent the " . ($cancel ? 'cancellation' : ($kind === 'update' ? 'update' : 'invitation')) . " for " . escapeSql($event['event_title']) . " to " . count($attendees) . " attendee(s)", intval($event['event_client_id']), $event_id);

    return count($attendees);
}

/** The attendees of an event who have been sent its invitation - the ones an update or a cancellation goes to. */
function calendarInvitedAttendees($event_id) {
    return array_values(array_filter(calendarEventAttendees($event_id), function ($attendee) {
        return !empty($attendee['attendee_invited_at']);
    }));
}
