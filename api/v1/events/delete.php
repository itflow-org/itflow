<?php
/*
 * API - Calendar Events - Delete
 * POST /api/v1/events/delete.php
 *
 * Delete an event, as Delete does - those who have it in their calendar are told
 * it is off. A client's event needs Full on Clients, or Modify for one the key's
 * agent made.
 *
 * Parameters (POST, JSON body): api_key, event_id, client_id (its client - needed when
 * the key's user is limited to certain clients)
 *
 * Answer: emailed - how many cancellations went.
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once "../../../includes/load_global_settings.php";
require_once "../../../includes/load_company_settings.php";

$delete_count = false;

$event_action = 'delete';
require_once 'event_find.php';

if ($event_row) {

    $event_id = intval($event_row['event_id']);
    $return_arr['emailed'] = calendarSendInvites($event_row, 'cancel', calendarInvitedAttendees($event_id));

    mysqli_query($mysqli, "DELETE FROM calendar_event_attendees WHERE attendee_event_id = $event_id");
    mysqli_query($mysqli, "DELETE FROM calendar_events WHERE event_id = $event_id");
    $delete_count = mysqli_affected_rows($mysqli);

    if ($delete_count > 0) {
        $label = escapeSql($event_row['event_title']);
        logAudit("Calendar Event", "Delete", "Deleted the event $label via API ($api_key_name)", $client_id, $event_id);
        logAudit("API", "Success", "Deleted the event $label via API ($api_key_name)", $client_id);
    }
}

// Output
require_once '../delete_output.php';
