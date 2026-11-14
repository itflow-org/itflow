<?php
/*
 * API - Calendar Events - Skip
 * POST /api/v1/events/skip.php
 *
 * Skip one day of a repeating event, as Skip this one does - the rest of the
 * series stays. Those who have it in their calendar get it without that day.
 *
 * Parameters (POST, JSON body): api_key, event_id, client_id (its client - needed when
 * the key's user is limited to certain clients), date (the day to skip, Y-m-d)
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
    $date = (string) ($_POST['date'] ?? '');

    if (!calendarEventRepeats($event_row) || !calendarIsDate($date)) {
        http_response_code(400);
        $return_arr['success'] = "False";
        $return_arr['message'] = calendarEventRepeats($event_row) ? 'date is the day to skip, such as 2026-11-20.' : "This event doesn't repeat - change or delete it instead.";
        echo json_encode($return_arr);
        exit();
    }

    $skip = calendarSkippedDates($event_row);
    $skip[] = $date;
    $skip = array_values(array_unique($skip));
    sort($skip);
    mysqli_query($mysqli, "UPDATE calendar_events SET event_repeat_skip = '" . escapeSql(implode(',', $skip)) . "', event_sequence = event_sequence + 1 WHERE event_id = $event_id");

    $event = calendarEventRow($event_id);
    $return_arr['emailed'] = calendarSendInvites($event, 'update', calendarInvitedAttendees($event_id));

    $update_count = 1;
    $label = date('D, M j', strtotime($date)) . ' of the event ' . escapeSql($event_row['event_title']);
    logAudit("Calendar Event", "Edit", "Skipped $label via API ($api_key_name)", $client_id, $event_id);
    logAudit("API", "Success", "Skipped $label via API ($api_key_name)", $client_id);
}

// Output
require_once '../update_output.php';
