<?php
/*
 * API - Calendar Events - Update
 * POST /api/v1/events/update.php
 *
 * Change an event, as Edit does - only what is sent. Sending any of the attendee
 * lists sets who is on it (a list left out stays). With send_invites 1, anyone new
 * gets the invitation, those who have it get the change, and anyone taken off is
 * told it is off for them.
 *
 * Parameters (POST, JSON body):
 *   api_key    required - Your API key
 *   event_id   required - The event
 *   client_id  its client, or a client to move it to - needed when the key's user is limited to certain clients
 *   the fields of events/create.php   optional
 *
 * Answer: emailed - how many invitations, updates and cancellations went.
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once "../../../includes/load_global_settings.php";
require_once "../../../includes/load_company_settings.php";

$update_count = false;

// It can move to another client - which has to be reachable too
$move_to = array_key_exists('client_id', $_POST) ? intval($_POST['client_id']) : null;
$client_id = 0;
$event_action = 'edit';
require_once 'event_find.php';

if ($event_row) {

    $event_id = intval($event_row['event_id']);
    if ($move_to !== null && $move_to !== $client_id) {
        if (!apiUserCanAccessClient($move_to) || ($move_to && !calendarEventCan(['event_client_id' => $move_to], 'edit'))) {
            apiDeny("The user linked to this API key does not have access to the target client for this write.");
        }
        $client_id = $move_to;
    }

    require_once 'event_model.php';

    mysqli_query($mysqli, "UPDATE calendar_events SET $event_set WHERE event_id = $event_id");

    // A change a calendar that already has it needs to know about moves it on a version
    $event = calendarEventRow($event_id);
    $changed = false;
    foreach (['event_title', 'event_start', 'event_end', 'event_all_day', 'event_location', 'event_location_id', 'event_description', 'event_repeat', 'event_repeat_interval', 'event_repeat_until'] as $field) {
        if ((string) $event_row[$field] !== (string) $event[$field]) {
            $changed = true;
        }
    }
    if ($changed) {
        mysqli_query($mysqli, "UPDATE calendar_events SET event_sequence = event_sequence + 1 WHERE event_id = $event_id");
        $event['event_sequence'] = intval($event['event_sequence']) + 1;
    }

    $attendees = $event_attendees ? calendarSetAttendees($event_id, $client_id, ...$event_attendees) : ['new' => [], 'removed' => []];

    $emailed = 0;
    if (!empty($_POST['send_invites'])) {
        $invite = [];
        $update = [];
        foreach (calendarEventAttendees($event_id) as $attendee) {
            if (!in_array(intval($attendee['attendee_id']), $attendees['new'], true) && !empty($attendee['attendee_invited_at'])) {
                $update[] = $attendee;
            } else {
                $invite[] = $attendee;
            }
        }
        $emailed += calendarSendInvites($event, 'invite', $invite);
        if ($changed) {
            $emailed += calendarSendInvites($event, 'update', $update);
        }
        $emailed += calendarSendInvites($event, 'cancel', array_values(array_filter($attendees['removed'], function ($attendee) {
            return !empty($attendee['attendee_invited_at']);
        })));
    }

    $update_count = 1;
    $return_arr['emailed'] = $emailed;

    $label = escapeSql($event['event_title']);
    logAudit("Calendar Event", "Edit", "Edited the event $label via API ($api_key_name)", $client_id, $event_id);
    logAudit("API", "Success", "Edited the event $label via API ($api_key_name)", $client_id);
}

// Output
require_once '../update_output.php';
