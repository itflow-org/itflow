<?php
/*
 * API - Calendar Events - Read
 * GET /api/v1/events/read.php
 *
 * Events on your calendars, by when they start - a client's only for a key whose
 * role can see Clients, or that its agent made or is on. One, by event_id, also
 * has its attendees: agents, the client's contacts and others by email, and when
 * each was sent the invitation. A repeating event is one row: event_repeat (Day,
 * Week, Month or Year), every event_repeat_interval, until event_repeat_until,
 * without the days in event_repeat_skip.
 *
 *   event_id       optional - Just this event, with its attendees
 *   calendar_id    optional - Just this calendar's
 *   client_id      optional - Just this client's; 0 for those of no client
 *   from, to       optional - Starting on or after, and on or before, these days (Y-m-d)
 *   limit, offset  optional - Paging
 */

require_once '../validate_api_key.php';
require_once '../require_get_method.php';

$where = 'event_archived_at IS NULL' . calendarEventScopeSql();
if (isset($_GET['event_id'])) {
    $where .= ' AND event_id = ' . intval($_GET['event_id']);
}
if (isset($_GET['calendar_id'])) {
    $where .= ' AND event_calendar_id = ' . intval($_GET['calendar_id']);
}
foreach (['from' => '>=', 'to' => '<='] as $field => $op) {
    if (!empty($_GET[$field]) && calendarIsDate($_GET[$field])) {
        $where .= " AND DATE(event_start) $op '" . escapeSql($_GET[$field]) . "'";
    }
}

$sql = mysqli_query($mysqli, "SELECT calendar_events.*, calendar_name FROM calendar_events LEFT JOIN calendars ON calendar_id = event_calendar_id
    WHERE $where " . apiClientScopeSql('event_client_id') . " ORDER BY event_start, event_id LIMIT $limit OFFSET $offset");

if (isset($_GET['event_id']) && $sql && ($row = mysqli_fetch_assoc($sql))) {
    $row['attendees'] = array_map(function ($a) {
        return ['attendee_id' => intval($a['attendee_id']), 'kind' => $a['kind'], 'user_id' => intval($a['attendee_user_id']), 'contact_id' => intval($a['attendee_contact_id']),
            'name' => $a['name'], 'email' => $a['email'], 'invited_at' => $a['attendee_invited_at']];
    }, calendarEventAttendees($row['event_id']));
    echo json_encode(['success' => 'True', 'count' => 1, 'data' => [$row]]);
    exit();
}

// Output
require_once "../read_output.php";
