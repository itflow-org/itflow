<?php
/*
 * API - Calendar Events - Send
 * POST /api/v1/events/send.php
 *
 * Email the event's invitation, as Email the invitation does - to those on it not
 * sent it yet, and again, as an update, to those who were.
 *
 * Parameters (POST, JSON body): api_key, event_id, client_id (its client - needed when
 * the key's user is limited to certain clients)
 *
 * Answer: emailed - how many it went to.
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once "../../../includes/load_global_settings.php";
require_once "../../../includes/load_company_settings.php";

$update_count = false;

$event_action = 'edit';
require_once 'event_find.php';

if ($event_row) {

    $event_id = intval($event_row['event_id']);

    $refuse = '';
    if (!calendarCanEmail()) {
        $refuse = 'Email is not set up, so there is nothing to send the invitation with.';
    } else {
        $not_invited = array_values(array_filter(calendarEventAttendees($event_id), function ($attendee) {
            return empty($attendee['attendee_invited_at']);
        }));
        $emailed = calendarSendInvites($event_row, 'invite', $not_invited) + calendarSendInvites($event_row, 'update', calendarInvitedAttendees($event_id));
        if (!$emailed) {
            $refuse = 'Nobody on this event has an email address to send it to.';
        }
    }
    if ($refuse !== '') {
        http_response_code(400);
        $return_arr['success'] = "False";
        $return_arr['message'] = $refuse;
        echo json_encode($return_arr);
        exit();
    }

    $update_count = 1;
    $return_arr['emailed'] = $emailed;
    logAudit("API", "Success", "Emailed the invitation for " . escapeSql($event_row['event_title']) . " via API ($api_key_name)", $client_id);
}

// Output
require_once '../update_output.php';
