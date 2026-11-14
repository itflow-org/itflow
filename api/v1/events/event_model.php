<?php

// An event's fields from POST, as calendarEventFormSet() takes them - with what the
// event has now ($event_row) for those not sent, on an update. $client_id is the
// client it is for (0: nobody's). Sets $event_set (the SET clause) and $event_attendees
// (null to leave them), or turns the request down with 400 and why.

if (!function_exists('apiDeny')) {
    http_response_code(404);
    exit();
}

$event_row = $event_row ?? null;

$event_refuse = function ($message) use (&$return_arr) {
    http_response_code(400);
    $return_arr['success'] = "False";
    $return_arr['message'] = $message;
    echo json_encode($return_arr);
    exit();
};
$sent = function ($field, $current) {
    return array_key_exists($field, $_POST) ? (is_scalar($_POST[$field]) ? (string) $_POST[$field] : '') : (string) $current;
};

// When - "2026-11-20 14:00", or a day for an all-day event
$all_day = intval($sent('event_all_day', $event_row['event_all_day'] ?? 0)) ? 1 : 0;
$when = function ($field, $current) use ($sent, $all_day, $event_refuse) {
    $value = trim($sent($field, $current !== null ? substr((string) $current, 0, 16) : ''));
    if ($value === '') {
        return ['', ''];
    }
    if (!preg_match('/^(\d{4}-\d{2}-\d{2})(?:[ T](\d{2}:\d{2}))?$/', $value, $m) || !calendarIsDate($m[1])) {
        $event_refuse("$field is a date and time, such as 2026-11-20 14:00" . ($all_day ? ' - or a day, 2026-11-20, for an all-day event.' : '.'));
    }
    return [$m[1], $m[2] ?? ''];
};
[$start_date, $start_time] = $when('event_start', $event_row['event_start'] ?? null);
[$end_date, $end_time] = $when('event_end', $event_row['event_end'] ?? null);
if ($start_date === '') {
    $event_refuse('event_start is when it starts, such as 2026-11-20 14:00.');
}
if (!$all_day && $start_time === '' && array_key_exists('event_start', $_POST)) {
    $event_refuse('event_start needs a time, such as 2026-11-20 14:00 - or send event_all_day 1.');
}

$repeat = $sent('event_repeat', $event_row['event_repeat'] ?? '');
if ($repeat !== '' && !isset(CALENDAR_REPEATS[$repeat])) {
    $event_refuse('event_repeat is Day, Week, Month or Year - or empty for once.');
}
$calendar_id = intval($sent('event_calendar_id', $event_row['event_calendar_id'] ?? 0));
if (!$calendar_id || !mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM calendars WHERE calendar_id = $calendar_id AND calendar_archived_at IS NULL"))) {
    $event_refuse('event_calendar_id is the calendar it goes on - see calendars/read.php.');
}
$location_id = intval($sent('event_location_id', $client_id && intval($event_row['event_client_id'] ?? 0) === intval($client_id) ? ($event_row['event_location_id'] ?? 0) : 0));
if ($location_id && (!$client_id || !assetClientOwnedId('location', $location_id, $client_id))) {
    $event_refuse("event_location_id isn't one of the client's locations.");
}

$error = '';
$event_set = calendarEventFormSet([
    'title' => $sent('event_title', $event_row['event_title'] ?? ''),
    'calendar' => $calendar_id,
    'all_day' => $all_day,
    'start_date' => $start_date,
    'start_time' => $start_time,
    'end_date' => $end_date,
    'end_time' => $all_day ? '' : $end_time,
    'repeat' => $repeat,
    'repeat_interval' => $sent('event_repeat_interval', $event_row['event_repeat_interval'] ?? 1),
    'repeat_until' => $sent('event_repeat_until', $event_row['event_repeat_until'] ?? ''),
    'location' => $sent('event_location', $event_row['event_location'] ?? ''),
    'location_id' => $location_id,
    'description' => $sent('event_description', $event_row['event_description'] ?? ''),
], $client_id, $error);
if ($event_set === '') {
    $event_refuse($error);
}

// Who is on it - agents, the client's contacts and anyone by email - when any of them is sent
$event_attendees = null;
if (array_key_exists('attendee_user_ids', $_POST) || array_key_exists('attendee_contact_ids', $_POST) || array_key_exists('attendee_emails', $_POST)) {
    $current = $event_row ? calendarEventAttendees($event_row['event_id']) : [];
    $keep = function ($kind) use ($current) {
        return array_values(array_map(function ($a) use ($kind) {
            return $kind === 'guest' ? $a['attendee_email'] : intval($a[$kind === 'agent' ? 'attendee_user_id' : 'attendee_contact_id']);
        }, array_filter($current, function ($a) use ($kind) {
            return $a['kind'] === $kind;
        })));
    };
    $emails = $_POST['attendee_emails'] ?? $keep('guest');
    $event_attendees = [
        array_key_exists('attendee_user_ids', $_POST) ? (array) $_POST['attendee_user_ids'] : $keep('agent'),
        array_key_exists('attendee_contact_ids', $_POST) ? (array) $_POST['attendee_contact_ids'] : $keep('contact'),
        is_array($emails) ? $emails : preg_split('/[\s,;]+/', (string) $emails, -1, PREG_SPLIT_NO_EMPTY),
    ];
}
