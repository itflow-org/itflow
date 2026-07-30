<?php

/*
 * ITFlow - GET/POST request handler for calendar & events
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_calendar'])) {

    validateCSRFToken();

    $name = escapeSql($_POST['name']);
    $color = escapeSql($_POST['color']);

    mysqli_query($mysqli,"INSERT INTO calendars SET calendar_name = '$name', calendar_color = '$color'");

    $calendar_id = mysqli_insert_id($mysqli);

    logAudit("Calendar", "Create", "$session_name created calendar $name", 0, $calendar_id);

    flashAlert("Calendar <strong>$name</strong> created");

    redirect();

}

if (isset($_POST['edit_calendar'])) {

    validateCSRFToken();

    $calendar_id = intval($_POST['calendar_id']);
    $name = escapeSql($_POST['name']);
    $color = escapeSql($_POST['color']);

    mysqli_query($mysqli,"UPDATE calendars SET calendar_name = '$name', calendar_color = '$color' WHERE calendar_id = $calendar_id");

    logAudit("Calendar", "Edit", "$session_name edited calendar $name", 0, $calendar_id);

    flashAlert("Calendar <strong>$name</strong> edited");

    redirect();

}

if (isset($_GET['delete_calendar'])) {

    validateCSRFToken();

    $calendar_id = intval($_GET['delete_calendar']);

    // Get Calendar Name
    $sql = mysqli_query($mysqli,"SELECT * FROM calendars WHERE calendar_id = $calendar_id");
    $row = mysqli_fetch_assoc($sql);
    $calendar_name = escapeSql($row['calendar_name']);

    // Delete Calendar
    mysqli_query($mysqli,"DELETE FROM calendars WHERE calendar_id = $calendar_id");

    // Delete Events
    mysqli_query($mysqli,"DELETE FROM calendar_events WHERE event_calendar_id = $calendar_id");

    logAudit("Calendar", "Delete", "$session_name deleted calendar $calendar_name and associated events");

    flashAlert("Calendar <strong>$calendar_name</strong> deleted", 'error');

    redirect();

}

if (isset($_POST['share_calendar'])) {

    validateCSRFToken();

    // Publishing a calendar mints an unauthenticated public URL, so keep it to
    // admins. This is a deliberately narrow check using $session_is_admin rather
    // than the module permission system - calendars are not owned by a module.
    enforceAdminPermission();

    $calendar_id = intval($_POST['calendar_id']);
    $busy_only = isset($_POST['busy_only']) ? 1 : 0;

    $calendar_name = escapeSql(getFieldById('calendars', $calendar_id, 'calendar_name'));
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

        logAudit("Calendar", "Share", "$session_name published calendar $calendar_name as a read-only feed", 0, $calendar_id);

        flashAlert("Calendar <strong>$calendar_name</strong> published - copy the subscription link from the share dialog");

    } else {

        mysqli_query($mysqli, "UPDATE calendars SET
            calendar_feed_busy_only = $busy_only
            WHERE calendar_id = $calendar_id");

        logAudit("Calendar", "Edit", "$session_name updated feed settings for calendar $calendar_name", 0, $calendar_id);

        flashAlert("Feed settings for <strong>$calendar_name</strong> updated");
    }

    redirect();

}

if (isset($_GET['regenerate_calendar_feed'])) {

    validateCSRFToken();

    enforceAdminPermission();

    $calendar_id = intval($_GET['regenerate_calendar_feed']);

    $calendar_name = escapeSql(getFieldById('calendars', $calendar_id, 'calendar_name'));

    $feed_key = escapeSql(randomString(32));

    mysqli_query($mysqli, "UPDATE calendars SET
        calendar_feed_key = '$feed_key',
        calendar_feed_created_at = NOW(),
        calendar_feed_accessed_at = NULL
        WHERE calendar_id = $calendar_id");

    logAudit("Calendar", "Share", "$session_name regenerated the feed link for calendar $calendar_name", 0, $calendar_id);

    flashAlert("Feed link for <strong>$calendar_name</strong> regenerated - existing subscribers will stop updating and must re-subscribe", 'error');

    redirect();

}

if (isset($_GET['unshare_calendar'])) {

    validateCSRFToken();

    enforceAdminPermission();

    $calendar_id = intval($_GET['unshare_calendar']);

    $calendar_name = escapeSql(getFieldById('calendars', $calendar_id, 'calendar_name'));

    mysqli_query($mysqli, "UPDATE calendars SET
        calendar_feed_key = NULL,
        calendar_feed_busy_only = 0,
        calendar_feed_created_at = NULL,
        calendar_feed_accessed_at = NULL
        WHERE calendar_id = $calendar_id");

    logAudit("Calendar", "Share", "$session_name stopped sharing calendar $calendar_name", 0, $calendar_id);

    flashAlert("Calendar <strong>$calendar_name</strong> is no longer shared", 'error');

    redirect();

}

if (isset($_POST['add_event'])) {

    validateCSRFToken();

    require_once 'event_model.php';

    // Don't Enforce Client Access if Calendar event doesn't have a client
    if ($client_id) {
        enforceClientAccess();
    }

    mysqli_query($mysqli,"INSERT INTO calendar_events SET event_title = '$title', event_location = '$location', event_description = '$description', event_start = '$start', event_end = '$end', event_all_day = $all_day, event_repeat = '$repeat', event_calendar_id = $calendar_id, event_client_id = $client_id");

    $event_id = mysqli_insert_id($mysqli);

    // Get Calendar Name
    $calendar_name = escapeSql(getFieldById('calendars', $calendar_id, 'calendar_name'));

    //If email is checked
    if ($email_event == 1) {

        $sql_client = mysqli_query($mysqli,"SELECT * FROM clients JOIN contacts ON contact_client_id = client_id WHERE contact_primary = 1 AND client_id = $client_id");
        $row = mysqli_fetch_assoc($sql_client);
        $client_name = escapeSql($row['client_name']);
        $contact_name = escapeSql($row['contact_name']);
        $contact_email = escapeSql($row['contact_email']);

        $sql_company = mysqli_query($mysqli,"SELECT * FROM companies WHERE company_id = 1");
        $row = mysqli_fetch_assoc($sql_company);
        $company_name = escapeSql($row['company_name']);
        $company_country = escapeSql($row['company_country']);
        $company_address = escapeSql($row['company_address']);
        $company_city = escapeSql($row['company_city']);
        $company_state = escapeSql($row['company_state']);
        $company_zip = escapeSql($row['company_zip']);
        $company_phone = escapeSql(formatPhoneNumber($row['company_phone']));
        $company_email = escapeSql($row['company_email']);
        $company_website = escapeSql($row['company_website']);
        $company_logo = escapeSql($row['company_logo']);

        // Sanitize Config Vars from get_settings.php and Session Vars from check_login.php
        $config_mail_from_name = escapeSql($config_mail_from_name);
        $config_mail_from_email = escapeSql($config_mail_from_email);

        $subject = "New Calendar Event";
        $body = "Hello $contact_name,<br><br>A calendar event has been scheduled:<br><br>Event Title: $title<br>Event Date: $start<br><br><br>--<br>$company_name<br>$company_phone";

        $data = [
            [
                'from' => $config_mail_from_email,
                'from_name' => $config_mail_from_name,
                'recipient' => $contact_email,
                'recipient_name' => $contact_name,
                'subject' => $subject,
                'body' => $body
            ]
        ];
        $mail = addToMailQueue($data);

        // Logging for email (success/fail)
        if ($mail === true) {
            logAudit("Calendar Event", "Email", "$session_name emailed event $title to $contact_name from client $client_name", $client_id, $event_id);
        } else {
            appNotify("Mail", "Failed to send email to $contact_email");
            logAudit("Mail", "Error", "Failed to send email to $contact_email regarding $subject. $mail");
        }

    } // End mail IF

    logAudit("Calendar Event", "Create", "$session_name created a calendar event titled $title in calendar $calendar_name", $client_id, $event_id);

    flashAlert("Event <strong>$title</strong> created in calendar <strong>$calendar_name</strong>");

    redirect();

}

if (isset($_POST['edit_event'])) {

    validateCSRFToken();

    require_once 'event_model.php';

    // Don't Enforce Client Access if Calendar event doesn't have a client
    if ($client_id) {
        enforceClientAccess();
    }

    $event_id = intval($_POST['event_id']);

    mysqli_query($mysqli,"UPDATE calendar_events SET event_title = '$title', event_location = '$location', event_description = '$description', event_start = '$start', event_end = '$end', event_all_day = $all_day, event_repeat = '$repeat', event_calendar_id = $calendar_id, event_client_id = $client_id WHERE event_id = $event_id");

    //If email is checked
    if ($email_event == 1) {

        $sql_client = mysqli_query($mysqli,"SELECT * FROM clients JOIN contacts ON contact_client_id = client_id WHERE contact_primary = 1 AND client_id = $client_id");
        $row = mysqli_fetch_assoc($sql_client);
        $client_name = escapeSql($row['client_name']);
        $contact_name = escapeSql($row['contact_name']);
        $contact_email = escapeSql($row['contact_email']);

        $sql_company = mysqli_query($mysqli,"SELECT * FROM companies WHERE company_id = 1");
        $row = mysqli_fetch_assoc($sql_company);
        $company_name = escapeSql($row['company_name']);
        $company_country = escapeSql($row['company_country']);
        $company_address = escapeSql($row['company_address']);
        $company_city = escapeSql($row['company_city']);
        $company_state = escapeSql($row['company_state']);
        $company_zip = escapeSql($row['company_zip']);
        $company_phone = escapeSql(formatPhoneNumber($row['company_phone']));
        $company_email = escapeSql($row['company_email']);
        $company_website = escapeSql($row['company_website']);
        $company_logo = escapeSql($row['company_logo']);

        // Sanitize Config Vars from get_settings.php and Session Vars from check_login.php
        $config_mail_from_name = escapeSql($config_mail_from_name);
        $config_mail_from_email = escapeSql($config_mail_from_email);


        $subject = "Calendar Event Rescheduled";
        $body = "Hello $contact_name,<br><br>A calendar event has been rescheduled:<br><br>Event Title: $title<br>Event Date: $start<br><br><br>--<br>$company_name<br>$company_phone";

        $data = [
            [
                'from' => $config_mail_from_email,
                'from_name' => $config_mail_from_name,
                'recipient' => $contact_email,
                'recipient_name' => $contact_name,
                'subject' => $subject,
                'body' => $body
            ]
            ];
        $mail = addToMailQueue($data);
        // Logging for email (success/fail)
        if ($mail === true) {
            logAudit("Calendar Event", "Email", "$session_name Emailed modified event $title to $contact_name email $contact_email", $client_id, $event_id);
        } else {
            appNotify("Mail", "Failed to send email to $contact_email");
            logAudit("Mail", "Error", "Failed to send email to $contact_email regarding $subject. $mail");
        }

    } // End mail IF

    logAudit("Calendar Event", "Edit", "$session_name edited calendar event $title", $client_id, $event_id);

    flashAlert("Calendar event titled <strong>$title</strong> edited");

    redirect();

}

if (isset($_GET['delete_event'])) {

    validateCSRFToken();

    $event_id = intval($_GET['delete_event']);

    // Get Event Title
    $sql = mysqli_query($mysqli,"SELECT * FROM calendar_events WHERE event_id = $event_id");
    $row = mysqli_fetch_assoc($sql);
    $event_title = escapeSql($row['event_title']);
    $client_id = intval($row['event_client_id']);

    // Don't Enforce Client Access if Calendar event doesn't have a client
    if ($client_id) {
        enforceClientAccess();
    }

    mysqli_query($mysqli,"DELETE FROM calendar_events WHERE event_id = $event_id");

    logAudit("Calendar Event", "Delete", "$session_name deleted calendar event $event_title", $client_id);

    flashAlert("Calendar event titled <strong>$event_title</strong> deleted", 'error');

    redirect();

}
