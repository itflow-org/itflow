<?php
/*
 * API - Calendar Events - Create
 * POST /api/v1/events/create.php
 *
 * Put an event on a calendar, as New Event does - for a client or for nobody,
 * once or repeating, with agents, the client's contacts and others by email on
 * it. send_invites 1 emails each of them an invitation for their calendar.
 * A client's event needs Modify on Clients.
 *
 * Parameters (POST, JSON body):
 *   api_key                 required - Your API key
 *   event_title             required
 *   event_calendar_id       required - See calendars/read.php
 *   event_start             required - "2026-11-20 14:00", or "2026-11-20" with event_all_day 1
 *   event_end               optional - The same; an hour after the start if left out
 *   event_all_day           optional - 1 for a whole-day event
 *   client_id               optional - The client it is for
 *   event_location          optional - Where, in words
 *   event_location_id       optional - One of the client's locations
 *   event_description       optional
 *   event_repeat            optional - Day, Week, Month or Year
 *   event_repeat_interval   optional - Every so many, 1 to 99
 *   event_repeat_until      optional - Its last day, Y-m-d
 *   attendee_user_ids, attendee_contact_ids, attendee_emails   optional - Who is on it
 *   send_invites            optional - 1 emails them the invitation
 *
 * Answer: insert_id, and invited - how many it was emailed to.
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once "../../../includes/load_global_settings.php";
require_once "../../../includes/load_company_settings.php";

$insert_id = false;

if ($client_id) {
    if (!mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM clients WHERE client_id = $client_id"))) {
        http_response_code(400);
        $return_arr['success'] = "False";
        $return_arr['message'] = "There is no client $client_id. Leave client_id out for an event that is nobody's.";
        echo json_encode($return_arr);
        exit();
    }
    if (!calendarEventCan(['event_client_id' => $client_id], 'edit')) {
        apiDeny(apiPermissionText('module_client', 2));
    }
}

require_once 'event_model.php';

mysqli_query($mysqli, "INSERT INTO calendar_events SET $event_set, event_created_by = " . intval($session_user_id));
$event_id = intval(mysqli_insert_id($mysqli));

if ($event_id) {
    $insert_id = $event_id;
    if ($event_attendees) {
        calendarSetAttendees($event_id, $client_id, ...$event_attendees);
    }
    $event = calendarEventRow($event_id);
    $return_arr['invited'] = !empty($_POST['send_invites']) ? calendarSendInvites($event, 'invite') : 0;

    $label = escapeSql($event['event_title'] . ' to calendar ' . $event['calendar_name']);
    logAudit("Calendar Event", "Create", "Added the event $label via API ($api_key_name)", $client_id, $event_id);
    logAudit("API", "Success", "Added the event $label via API ($api_key_name)", $client_id);
}

// Output
require_once '../create_output.php';
