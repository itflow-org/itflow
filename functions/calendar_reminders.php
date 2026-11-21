<?php

/*
 * Reminders before what is coming up:
 *
 *   - an event on the calendar, at the time set on it (calendar_events.event_reminder,
 *     minutes before) - for the agents on it, or the agent who made it when no agent is
 *   - work booked on a ticket - for the agent it is assigned to, at the time they chose in
 *     Your account > Preferences (user_settings.user_config_booking_reminder)
 *
 * Each comes as a notification, and as an email too to agents who asked for that
 * (user_config_reminder_email). cron/calendar_reminders.php looks every minute.
 * calendar_reminders keeps what went - what for, the time it was for and to whom - so a
 * reminder goes once, a repeating event's each time, and a ticket booked again gets a new
 * one. Nothing is reminded of once it has started more than CALENDAR_REMINDER_GRACE_MINUTES
 * ago, so a cron that was off doesn't send a pile of them for things gone by.
 */

/** How far into something a reminder still goes, for a minute or a few the cron didn't run. */
const CALENDAR_REMINDER_GRACE_MINUTES = 10;

/** The times a reminder can go - minutes before => how it reads. */
const CALENDAR_REMINDER_CHOICES = [
    0 => 'When it starts',
    5 => '5 minutes before',
    15 => '15 minutes before',
    30 => '30 minutes before',
    60 => 'An hour before',
    120 => 'Two hours before',
    1440 => 'A day before',
    2880 => 'Two days before',
];

/** Is there somewhere to keep reminders yet (database update 4.0.6)? Until there is, none are set or sent. */
function calendarRemindersReady() {
    global $mysqli;
    static $ready = null;
    if ($ready === null) {
        if (defined('CURRENT_DATABASE_VERSION')) {
            $version = CURRENT_DATABASE_VERSION;
        } else {
            $result = mysqli_query($mysqli, "SELECT config_current_database_version FROM settings WHERE company_id = 1");
            $version = $result && ($row = mysqli_fetch_row($result)) ? (string) $row[0] : '0';
        }
        $ready = version_compare($version, '4.0.6', '>=');
    }
    return $ready;
}

/** A reminder time from a form - minutes, one of CALENDAR_REMINDER_CHOICES, or null for none. */
function calendarReminderFromInput($value) {
    if ($value === null || $value === '') {
        return null;
    }
    $minutes = intval($value);
    return array_key_exists($minutes, CALENDAR_REMINDER_CHOICES) ? $minutes : null;
}

/** The options of a reminder picker, the one set picked - "No reminder" first. HTML. */
function calendarReminderOptions($minutes) {
    $html = '<option value=""' . ($minutes === null ? ' selected' : '') . '>- No reminder -</option>';
    foreach (CALENDAR_REMINDER_CHOICES as $value => $label) {
        $html .= '<option value="' . $value . '"' . ($minutes !== null && intval($minutes) === $value ? ' selected' : '') . '>' . escapeHtml($label) . '</option>';
    }
    return $html;
}

/** "in 15 minutes", "in an hour", "tomorrow" - how far off something is, from now. */
function calendarReminderWhen($start) {
    $minutes = intval(round((strtotime((string) $start) - time()) / 60));
    if ($minutes <= 0) {
        return 'now';
    }
    if ($minutes < 60) {
        return "in $minutes minute" . ($minutes === 1 ? '' : 's');
    }
    if ($minutes < 1440) {
        $hours = intval(round($minutes / 60));
        return $hours === 1 ? 'in an hour' : "in $hours hours";
    }
    $days = intval(round($minutes / 1440));
    return $days === 1 ? 'tomorrow' : "in $days days";
}

/**
 * Send one reminder to one agent, once: kept in calendar_reminders first, so a second
 * run - or a second cron - finds it gone. A notification, and an email when they asked.
 * $item: ['kind', 'id', 'for' (the start it is for), 'title', 'when', 'where', 'client',
 * 'client_id', 'url' (app-relative), 'type' (the notification type)]. Whether it went.
 */
function calendarReminderSend(array $user, array $item) {
    global $mysqli, $config_base_url;
    $user_id = intval($user['user_id']);
    $kind = $item['kind'] === 'ticket' ? 'ticket' : 'event';
    mysqli_query($mysqli, "INSERT IGNORE INTO calendar_reminders SET reminder_kind = '$kind', reminder_item_id = " . intval($item['id']) . ",
        reminder_for = '" . escapeSql($item['for']) . "', reminder_user_id = $user_id");
    if (mysqli_affected_rows($mysqli) < 1) {
        return false;
    }

    $when = calendarReminderWhen($item['for']);
    $text = $item['title'] . ' - ' . ($when === 'now' ? 'starting now' : $when) . ', ' . $item['when'] . ($item['client'] !== '' ? ' (' . $item['client'] . ')' : '');
    mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = '" . escapeSql($item['type']) . "', notification = '" . escapeSql(mb_substr($text, 0, 1000)) . "',
        notification_action = '" . escapeSql($item['url']) . "', notification_client_id = " . intval($item['client_id']) . ", notification_entity_id = " . intval($item['id']) . ", notification_user_id = $user_id");

    if (intval($user['user_config_reminder_email'] ?? 0) === 1 && trim((string) $user['user_email']) !== '') {
        emailQueue([['email' => $user['user_email'], 'name' => $user['user_name'], 'footnote' => 'You asked for reminders by email in Your account > Preferences in ITFlow.']],
            'Reminder: ' . $item['title'] . ' ' . ($when === 'now' ? 'now' : $when), [
                'kind' => 'Reminder', 'tone' => 'primary',
                'title' => $item['title'],
                'intro' => [($when === 'now' ? 'Starting now' : ucfirst($when)) . ' - ' . $item['when'] . '.'],
                'summary' => array_filter(['When' => $item['when'], 'Where' => $item['where'], 'Client' => $item['client']]),
                'button' => ['label' => 'Open in ITFlow', 'url' => "https://$config_base_url" . $item['url']],
            ]);
    }
    return true;
}

/**
 * The reminders that are due, sent - events on the calendar (while the calendar is on)
 * and work booked on tickets. How many went.
 */
function calendarRemindersRun() {
    global $mysqli;
    if (!calendarRemindersReady()) {
        return 0;
    }
    $now = date('Y-m-d H:i:s');
    $grace = CALENDAR_REMINDER_GRACE_MINUTES;
    $sent = 0;

    // Events: one that doesn't repeat by its start; one that repeats by each time it comes round
    if (moduleOn('calendar')) {
        $furthest = max(array_keys(CALENDAR_REMINDER_CHOICES));
        $sql = mysqli_query($mysqli, "SELECT calendar_events.*, client_name, location_name, location_address, location_city
            FROM calendar_events
            JOIN calendars ON calendar_id = event_calendar_id AND calendar_archived_at IS NULL
            LEFT JOIN clients ON client_id = event_client_id AND event_client_id > 0
            LEFT JOIN locations ON location_id = event_location_id AND event_location_id > 0
            WHERE event_archived_at IS NULL AND event_reminder IS NOT NULL
            AND ((event_repeat IS NULL OR event_repeat = '') AND event_start BETWEEN '$now' - INTERVAL $grace MINUTE AND '$now' + INTERVAL event_reminder MINUTE
                OR (event_repeat IS NOT NULL AND event_repeat != '' AND event_start <= '$now' + INTERVAL $furthest MINUTE AND (event_repeat_until IS NULL OR event_repeat_until >= DATE('$now'))))");
        while ($sql && $event = mysqli_fetch_assoc($sql)) {
            $lead = intval($event['event_reminder']);
            $from = date('Y-m-d H:i:s', strtotime($now) - $grace * 60);
            $to = date('Y-m-d H:i:s', strtotime($now) + $lead * 60);
            $agents = calendarReminderEventAgents($event);
            foreach (expandRecurringEvent($event, $from, $to) as $occurrence) {
                if ($occurrence['start'] < $from || $occurrence['start'] > $to) {
                    continue;
                }
                $where = trim((string) ($event['location_name'] ?? '')) !== '' ? $event['location_name'] : trim((string) $event['event_location']);
                foreach ($agents as $agent) {
                    $sent += calendarReminderSend($agent, [
                        'kind' => 'event', 'id' => intval($event['event_id']), 'for' => $occurrence['start'],
                        'title' => (string) $event['event_title'], 'when' => calendarWhenText($event, $occurrence['start'], $occurrence['end']),
                        'where' => $where, 'client' => (string) ($event['client_name'] ?? ''), 'client_id' => intval($event['event_client_id']),
                        'url' => '/agent/calendar.php?date=' . substr($occurrence['start'], 0, 10) . '&event=' . intval($event['event_id']), 'type' => 'Calendar Reminder',
                    ]) ? 1 : 0;
                }
            }
        }
    }

    // Work booked on open tickets, for the agent it is assigned to - at the time they chose
    $sql = mysqli_query($mysqli, "SELECT ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_schedule, ticket_schedule_end, ticket_onsite, ticket_client_id,
            client_name, location_name, users.user_id, user_name, user_email, user_config_reminder_email
        FROM tickets
        JOIN users ON user_id = ticket_assigned_to AND user_status = 1 AND user_archived_at IS NULL
        JOIN user_settings ON user_settings.user_id = users.user_id AND user_config_booking_reminder IS NOT NULL
        LEFT JOIN clients ON client_id = ticket_client_id
        LEFT JOIN locations ON location_id = ticket_location_id AND ticket_location_id > 0
        WHERE ticket_schedule IS NOT NULL AND ticket_archived_at IS NULL AND " . TICKET_OPEN_SQL . "
        AND ticket_schedule BETWEEN '$now' - INTERVAL $grace MINUTE AND '$now' + INTERVAL user_config_booking_reminder MINUTE");
    while ($sql && $row = mysqli_fetch_assoc($sql)) {
        $sent += calendarReminderSend($row, [
            'kind' => 'ticket', 'id' => intval($row['ticket_id']), 'for' => $row['ticket_schedule'],
            'title' => calendarTicketTitle($row, false), 'when' => calendarWhenText(['event_start' => $row['ticket_schedule'], 'event_end' => $row['ticket_schedule_end'], 'event_all_day' => 0]),
            'where' => intval($row['ticket_onsite']) ? (string) ($row['location_name'] ?? '') : 'Remote', 'client' => (string) ($row['client_name'] ?? ''), 'client_id' => intval($row['ticket_client_id']),
            'url' => '/agent/ticket.php?client_id=' . intval($row['ticket_client_id']) . '&ticket_id=' . intval($row['ticket_id']), 'type' => 'Booking Reminder',
        ]) ? 1 : 0;
    }

    return $sent;
}

/** Who an event's reminder goes to - the active agents on it, or the agent who made it when none are. */
function calendarReminderEventAgents(array $event) {
    global $mysqli;
    $event_id = intval($event['event_id']);
    $agents = [];
    $sql = mysqli_query($mysqli, "SELECT users.user_id, user_name, user_email, user_config_reminder_email FROM calendar_event_attendees
        JOIN users ON users.user_id = attendee_user_id AND user_status = 1 AND user_archived_at IS NULL
        LEFT JOIN user_settings ON user_settings.user_id = users.user_id
        WHERE attendee_event_id = $event_id AND attendee_user_id > 0 AND attendee_archived_at IS NULL");
    while ($sql && $row = mysqli_fetch_assoc($sql)) {
        $agents[intval($row['user_id'])] = $row;
    }
    if (!$agents && intval($event['event_created_by'])) {
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT users.user_id, user_name, user_email, user_config_reminder_email FROM users
            LEFT JOIN user_settings ON user_settings.user_id = users.user_id
            WHERE users.user_id = " . intval($event['event_created_by']) . " AND user_status = 1 AND user_archived_at IS NULL"));
        if ($row) {
            $agents[intval($row['user_id'])] = $row;
        }
    }
    return array_values($agents);
}

/** Forget the reminders sent more than 60 days ago - they are only kept so each goes once. */
function calendarRemindersCleanup() {
    global $mysqli;
    if (calendarRemindersReady()) {
        mysqli_query($mysqli, "DELETE FROM calendar_reminders WHERE reminder_sent_at < NOW() - INTERVAL 60 DAY");
    }
}
