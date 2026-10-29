<?php

/*
 * ITFlow - GET/POST request handler for calendars, events and calendar links
 *
 * Calendars themselves (add, rename, delete, publish) are an admin's. An event
 * with no client is anyone's; a client's event follows the role's Clients
 * level, and needs access to that client (calendarEventCan()).
 * Attendees are agents, the client's contacts and anyone by email; ticking
 * "Email the invitation" sends each of them an iCalendar invitation, and from
 * then on a change, a skipped occurrence or the deletion goes to the people
 * who have it in their calendar.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

/** A colour from the picker - #rrggbb - or a default. */
function calendarPostedColor($color) {
    $color = trim((string) $color);
    return preg_match('/^#[0-9a-f]{6}$/i', $color) ? strtolower($color) : '#3c8dbc';
}

/** The attendee fields of the event form, as calendarSetAttendees() takes them. */
function calendarPostedAttendees() {
    return [
        (array) ($_POST['agents'] ?? []),
        (array) ($_POST['contacts'] ?? []),
        preg_split('/[\s,;]+/', (string) ($_POST['emails'] ?? ''), -1, PREG_SPLIT_NO_EMPTY),
    ];
}

/** " - invitation emailed to 3 people" (or just "emailed to") - nothing when nobody was. */
function calendarSentText($count, $what = 'invitation') {
    return $count ? ' - ' . ($what ? "$what " : '') . "emailed to $count " . ($count === 1 ? 'person' : 'people') : '';
}

/*
 * ---------- calendars ----------
 */

if (isset($_POST['add_calendar'])) {

    validateCSRFToken();
    enforceAdminPermission();

    $name = trim(strip_tags((string) ($_POST['name'] ?? '')));
    if ($name === '') {
        flashAlert('A calendar needs a name.', 'error');
        redirect();
    }
    $color = calendarPostedColor($_POST['color'] ?? '');

    mysqli_query($mysqli, "INSERT INTO calendars SET calendar_name = '" . escapeSql(mb_substr($name, 0, 200)) . "', calendar_color = '$color'");
    $calendar_id = mysqli_insert_id($mysqli);

    logAudit("Calendar", "Create", "$session_name created calendar " . escapeSql($name), 0, $calendar_id);

    flashAlert("Calendar <strong>" . escapeHtml($name) . "</strong> created");

    redirect();

}

if (isset($_POST['edit_calendar'])) {

    validateCSRFToken();
    enforceAdminPermission();

    $calendar_id = intval($_POST['calendar_id'] ?? 0);
    $name = trim(strip_tags((string) ($_POST['name'] ?? '')));
    if ($name === '') {
        flashAlert('A calendar needs a name.', 'error');
        redirect();
    }
    $color = calendarPostedColor($_POST['color'] ?? '');

    mysqli_query($mysqli, "UPDATE calendars SET calendar_name = '" . escapeSql(mb_substr($name, 0, 200)) . "', calendar_color = '$color' WHERE calendar_id = $calendar_id");

    logAudit("Calendar", "Edit", "$session_name edited calendar " . escapeSql($name), 0, $calendar_id);

    flashAlert("Calendar <strong>" . escapeHtml($name) . "</strong> saved");

    redirect();

}

if (isset($_GET['delete_calendar'])) {

    validateCSRFToken();
    enforceAdminPermission();

    $calendar_id = intval($_GET['delete_calendar']);
    $calendar_name = (string) getFieldById('calendars', $calendar_id, 'calendar_name');

    // Its events go with it, and whoever was invited to one is told it is off
    $sql = mysqli_query($mysqli, "SELECT event_id FROM calendar_events WHERE event_calendar_id = $calendar_id AND event_archived_at IS NULL");
    $cancelled = 0;
    while ($row = mysqli_fetch_row($sql)) {
        $event = calendarEventRow($row[0]);
        $invited = calendarInvitedAttendees($row[0]);
        if ($event && $invited) {
            $cancelled += calendarSendInvites($event, 'cancel', $invited);
        }
    }
    mysqli_query($mysqli, "DELETE calendar_event_attendees FROM calendar_event_attendees JOIN calendar_events ON event_id = attendee_event_id WHERE event_calendar_id = $calendar_id");
    mysqli_query($mysqli, "DELETE FROM calendar_events WHERE event_calendar_id = $calendar_id");
    mysqli_query($mysqli, "DELETE FROM calendars WHERE calendar_id = $calendar_id");

    logAudit("Calendar", "Delete", "$session_name deleted calendar " . escapeSql($calendar_name) . " and its events");

    flashAlert("Calendar <strong>" . escapeHtml($calendar_name) . "</strong> and its events deleted" . calendarSentText($cancelled, 'cancellation'), 'error');

    redirect();

}

if (isset($_POST['share_calendar'])) {

    validateCSRFToken();

    // Publishing a calendar mints an unauthenticated public URL, so keep it to
    // admins - calendars are not owned by a module.
    enforceAdminPermission();

    $calendar_id = intval($_POST['calendar_id']);
    $busy_only = isset($_POST['busy_only']) ? 1 : 0;

    $calendar_name = (string) getFieldById('calendars', $calendar_id, 'calendar_name');
    $existing_key = getFieldById('calendars', $calendar_id, 'calendar_feed_key');

    if (empty($existing_key)) {

        // 32 URL-safe base64 chars (~192 bits). Stored in cleartext, like
        // invoice_url_key, so the link stays re-copyable for a second device.
        $feed_key = escapeSql(randomString(32));

        mysqli_query($mysqli, "UPDATE calendars SET
            calendar_feed_key = '$feed_key',
            calendar_feed_busy_only = $busy_only,
            calendar_feed_created_at = NOW()
            WHERE calendar_id = $calendar_id");

        logAudit("Calendar", "Share", "$session_name published calendar " . escapeSql($calendar_name) . " as a read-only feed", 0, $calendar_id);

        flashAlert("Calendar <strong>" . escapeHtml($calendar_name) . "</strong> published - copy the subscription link from Share");

    } else {

        mysqli_query($mysqli, "UPDATE calendars SET
            calendar_feed_busy_only = $busy_only
            WHERE calendar_id = $calendar_id");

        logAudit("Calendar", "Edit", "$session_name updated feed settings for calendar " . escapeSql($calendar_name), 0, $calendar_id);

        flashAlert("Sharing for <strong>" . escapeHtml($calendar_name) . "</strong> saved");
    }

    redirect();

}

if (isset($_GET['regenerate_calendar_feed'])) {

    validateCSRFToken();
    enforceAdminPermission();

    $calendar_id = intval($_GET['regenerate_calendar_feed']);
    $calendar_name = (string) getFieldById('calendars', $calendar_id, 'calendar_name');
    $feed_key = escapeSql(randomString(32));

    mysqli_query($mysqli, "UPDATE calendars SET
        calendar_feed_key = '$feed_key',
        calendar_feed_created_at = NOW(),
        calendar_feed_accessed_at = NULL
        WHERE calendar_id = $calendar_id");

    logAudit("Calendar", "Share", "$session_name regenerated the feed link for calendar " . escapeSql($calendar_name), 0, $calendar_id);

    flashAlert("New link for <strong>" . escapeHtml($calendar_name) . "</strong> - the old one has stopped working, so subscribers must subscribe again", 'warning');

    redirect();

}

if (isset($_GET['unshare_calendar'])) {

    validateCSRFToken();
    enforceAdminPermission();

    $calendar_id = intval($_GET['unshare_calendar']);
    $calendar_name = (string) getFieldById('calendars', $calendar_id, 'calendar_name');

    mysqli_query($mysqli, "UPDATE calendars SET
        calendar_feed_key = NULL,
        calendar_feed_busy_only = 0,
        calendar_feed_created_at = NULL,
        calendar_feed_accessed_at = NULL
        WHERE calendar_id = $calendar_id");

    logAudit("Calendar", "Share", "$session_name stopped sharing calendar " . escapeSql($calendar_name), 0, $calendar_id);

    flashAlert("Calendar <strong>" . escapeHtml($calendar_name) . "</strong> is no longer shared", 'error');

    redirect();

}

/*
 * ---------- events ----------
 */

if (isset($_POST['add_event'])) {

    validateCSRFToken();

    $client_id = intval($_POST['client_id'] ?? 0);
    if ($client_id) {
        enforceClientAccess();
        enforceCalendarEvent(['event_client_id' => $client_id], 'edit');
    }

    $set = calendarEventFormSet($_POST, $client_id, $error);
    if ($set === '') {
        flashAlert($error, 'error');
        redirect();
    }

    mysqli_query($mysqli, "INSERT INTO calendar_events SET $set, event_created_by = $session_user_id");
    $event_id = mysqli_insert_id($mysqli);

    [$agents, $contacts, $emails] = calendarPostedAttendees();
    calendarSetAttendees($event_id, $client_id, $agents, $contacts, $emails);

    $event = calendarEventRow($event_id);
    $sent = !empty($_POST['send_invites']) ? calendarSendInvites($event, 'invite') : 0;

    logAudit("Calendar Event", "Create", "$session_name added the event " . escapeSql($event['event_title']) . " to calendar " . escapeSql($event['calendar_name']), $client_id, $event_id);

    flashAlert("Event <strong>" . escapeHtml($event['event_title']) . "</strong> added" . calendarSentText($sent));

    redirect();

}

if (isset($_POST['edit_event'])) {

    validateCSRFToken();

    $old = calendarEventForRequest($_POST['event_id'] ?? 0, 'edit');
    $event_id = intval($old['event_id']);

    // It can move to another client - which needs access too
    $client_id = intval($_POST['client_id'] ?? 0);
    if ($client_id) {
        enforceClientAccess();
        enforceCalendarEvent(['event_client_id' => $client_id], 'edit');
    }

    $set = calendarEventFormSet($_POST, $client_id, $error);
    if ($set === '') {
        flashAlert($error, 'error');
        redirect();
    }

    mysqli_query($mysqli, "UPDATE calendar_events SET $set WHERE event_id = $event_id");

    // A change a calendar that already has it needs to know about moves it on a version
    $event = calendarEventRow($event_id);
    $changed = false;
    foreach (['event_title', 'event_start', 'event_end', 'event_all_day', 'event_location', 'event_location_id', 'event_description', 'event_repeat', 'event_repeat_interval', 'event_repeat_until'] as $field) {
        if ((string) $old[$field] !== (string) $event[$field]) {
            $changed = true;
        }
    }
    if ($changed) {
        mysqli_query($mysqli, "UPDATE calendar_events SET event_sequence = event_sequence + 1 WHERE event_id = $event_id");
        $event['event_sequence'] = intval($event['event_sequence']) + 1;
    }

    [$agents, $contacts, $emails] = calendarPostedAttendees();
    $attendees = calendarSetAttendees($event_id, $client_id, $agents, $contacts, $emails);

    $sent = 0;
    $cancelled = 0;
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
        // Newcomers and anyone not yet invited get the invitation; those who have
        // it get the update when something about it changed
        $sent += calendarSendInvites($event, 'invite', $invite);
        if ($changed) {
            $sent += calendarSendInvites($event, 'update', $update);
        }
        // Whoever was taken off and had it in their calendar is told it is off for them
        $cancelled = calendarSendInvites($event, 'cancel', array_values(array_filter($attendees['removed'], function ($attendee) {
            return !empty($attendee['attendee_invited_at']);
        })));
    }

    logAudit("Calendar Event", "Edit", "$session_name edited the event " . escapeSql($event['event_title']), $client_id, $event_id);

    flashAlert("Event <strong>" . escapeHtml($event['event_title']) . "</strong> saved" . calendarSentText($sent + $cancelled, ''));

    redirect();

}

if (isset($_POST['send_event_invites'])) {

    validateCSRFToken();

    $event = calendarEventForRequest($_POST['event_id'] ?? 0, 'edit');
    $event_id = intval($event['event_id']);

    if (!calendarCanEmail()) {
        flashAlert('Email is not set up, so there is nothing to send the invitation with.', 'error');
        redirect();
    }

    // Those who have it get it again as an update; the rest are invited
    $invited = calendarInvitedAttendees($event_id);
    $not_invited = array_values(array_filter(calendarEventAttendees($event_id), function ($attendee) {
        return empty($attendee['attendee_invited_at']);
    }));
    $sent = calendarSendInvites($event, 'invite', $not_invited) + calendarSendInvites($event, 'update', $invited);

    if (!$sent) {
        flashAlert('Nobody on this event has an email address to send it to.', 'warning');
        redirect();
    }

    flashAlert("Invitation for <strong>" . escapeHtml($event['event_title']) . "</strong> emailed to $sent " . ($sent === 1 ? 'person' : 'people'));

    redirect();

}

if (isset($_GET['skip_event_occurrence'])) {

    validateCSRFToken();

    $event = calendarEventForRequest($_GET['skip_event_occurrence'], 'edit');
    $event_id = intval($event['event_id']);
    $date = (string) ($_GET['date'] ?? '');

    if (!calendarEventRepeats($event) || !calendarIsDate($date)) {
        flashAlert('That is not one of its occurrences.', 'error');
        redirect();
    }

    $skip = calendarSkippedDates($event);
    $skip[] = $date;
    $skip = array_values(array_unique($skip));
    sort($skip);
    mysqli_query($mysqli, "UPDATE calendar_events SET event_repeat_skip = '" . escapeSql(implode(',', $skip)) . "', event_sequence = event_sequence + 1 WHERE event_id = $event_id");

    // The people who have the series in their calendar get it without that day
    $event = calendarEventRow($event_id);
    $sent = calendarSendInvites($event, 'update', calendarInvitedAttendees($event_id));

    $when = date('D, M j', strtotime($date));
    logAudit("Calendar Event", "Edit", "$session_name skipped $when of the event " . escapeSql($event['event_title']), intval($event['event_client_id']), $event_id);

    flashAlert("<strong>" . escapeHtml($event['event_title']) . "</strong> skipped on $when" . calendarSentText($sent, 'update'));

    redirect();

}

if (isset($_GET['delete_event'])) {

    validateCSRFToken();

    $event = calendarEventForRequest($_GET['delete_event'], 'delete');
    $event_id = intval($event['event_id']);

    // The people who have it in their calendar are told it is off
    $cancelled = calendarSendInvites($event, 'cancel', calendarInvitedAttendees($event_id));

    mysqli_query($mysqli, "DELETE FROM calendar_event_attendees WHERE attendee_event_id = $event_id");
    mysqli_query($mysqli, "DELETE FROM calendar_events WHERE event_id = $event_id");

    logAudit("Calendar Event", "Delete", "$session_name deleted the event " . escapeSql($event['event_title']), intval($event['event_client_id']), $event_id);

    flashAlert("Event <strong>" . escapeHtml($event['event_title']) . "</strong> deleted" . calendarSentText($cancelled, 'cancellation'), 'error');

    redirect();

}

if (isset($_GET['export_event_ics'])) {

    $event = calendarEventForRequest($_GET['export_event_ics']);

    $ics = icsCalendar(['name' => $event['event_title']], [calendarEventIcsItem($event)], $config_timezone);
    $filename = trim(preg_replace('/[^A-Za-z0-9_-]+/', '_', $event['event_title']), '_') ?: 'event';

    header('Content-Type: text/calendar; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"$filename.ics\"");
    echo $ics;
    exit;

}

/*
 * ---------- my schedule, as a subscription ----------
 * Each agent's own: their scheduled tickets, the events they are on and the
 * projects they manage, for the calendar on their phone, Nextcloud or Google.
 */

if (isset($_POST['calendar_feed_save'])) {

    validateCSRFToken();

    $scope = ($_POST['scope'] ?? 'mine') === 'everyone' ? 'everyone' : 'mine';
    $tickets = !empty($_POST['feed_tickets']) ? 1 : 0;
    $events = !empty($_POST['feed_events']) ? 1 : 0;
    $projects = !empty($_POST['feed_projects']) ? 1 : 0;
    $busy_only = !empty($_POST['busy_only']) ? 1 : 0;
    $fields = "feed_scope = '$scope', feed_tickets = $tickets, feed_events = $events, feed_projects = $projects, feed_busy_only = $busy_only";

    if (calendarUserFeed($session_user_id)) {
        mysqli_query($mysqli, "UPDATE user_calendar_feeds SET $fields WHERE feed_user_id = $session_user_id");
        flashAlert('Your calendar link is saved - subscribers pick up the change on their next refresh');
    } else {
        $feed_key = escapeSql(randomString(40));
        mysqli_query($mysqli, "INSERT INTO user_calendar_feeds SET feed_user_id = $session_user_id, feed_key = '$feed_key', $fields");
        logAudit("Calendar", "Share", "$session_name created a subscription link to their schedule");
        flashAlert('Your calendar link is ready - copy it from My calendar link');
    }

    redirect();

}

if (isset($_GET['calendar_feed_regenerate'])) {

    validateCSRFToken();

    $feed_key = escapeSql(randomString(40));
    mysqli_query($mysqli, "UPDATE user_calendar_feeds SET feed_key = '$feed_key', feed_created_at = NOW(), feed_accessed_at = NULL WHERE feed_user_id = $session_user_id");

    logAudit("Calendar", "Share", "$session_name regenerated the subscription link to their schedule");

    flashAlert('New calendar link made - the old one has stopped working, so subscribe again with the new one', 'warning');

    redirect();

}

if (isset($_GET['calendar_feed_stop'])) {

    validateCSRFToken();

    mysqli_query($mysqli, "DELETE FROM user_calendar_feeds WHERE feed_user_id = $session_user_id");

    logAudit("Calendar", "Share", "$session_name stopped the subscription link to their schedule");

    flashAlert('Your calendar link has stopped working', 'error');

    redirect();

}
