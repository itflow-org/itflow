<?php

// The event a request names (event_id) - of a client the key's user can reach and
// client_id's when it is sent - as $event_row, or null. $event_action is what is to
// be done (edit or delete): calendarEventCan() decides, as on the calendar - an event
// with no client is anyone's, a client's follows the role's Clients level.

if (!function_exists('apiDeny')) {
    http_response_code(404);
    exit();
}

$event_row = !empty($_POST['event_id']) ? calendarEventRow($_POST['event_id']) : null;
if ($event_row && (!apiUserCanAccessClient($event_row['event_client_id']) || ($client_id && intval($event_row['event_client_id']) !== $client_id))) {
    $event_row = null;
}
if ($event_row && !calendarEventCan($event_row, $event_action)) {
    apiDeny(apiPermissionText('module_client', $event_action === 'delete' ? 3 : 2));
}
if ($event_row) {
    $client_id = intval($event_row['event_client_id']);
}
