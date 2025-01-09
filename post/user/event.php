<?php

/*
 * ITFlow - GET/POST request handler for calendar & events
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_calendar'])) {

    $name = sanitizeInput($_POST['name']);
    $color = sanitizeInput($_POST['color']);

    mysqli_query($mysqli,"INSERT INTO calendars SET calendar_name = '$name', calendar_color = '$color'");

    $calendar_id = mysqli_insert_id($mysqli);

    // Logging
    logAction("Calendar", "Create", "$session_name created calendar $name", 0, $calendar_id);

    $_SESSION['alert_message'] = "Calendar <strong>$name</strong> created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_calendar'])) {

    $calendar_id = intval($_POST['calendar_id']);
    $name = sanitizeInput($_POST['name']);
    $color = sanitizeInput($_POST['color']);

    mysqli_query($mysqli,"UPDATE calendars SET calendar_name = '$name', calendar_color = '$color' WHERE calendar_id = $calendar_id");

    // Logging
    logAction("Calendar", "Edit", "$session_name edited calendar $name", 0, $calendar_id);

    $_SESSION['alert_message'] = "Calendar <strong>$name</strong> edited";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['add_event'])) {

    require_once 'post/user/event_model.php';


    mysqli_query($mysqli,"INSERT INTO events SET event_title = '$title', event_location = '$location', event_description = '$description', event_start = '$start', event_end = '$end', event_repeat = '$repeat', event_calendar_id = $calendar_id, event_client_id = $client");

    $event_id = mysqli_insert_id($mysqli);

    //Get Calendar Name
    $sql = mysqli_query($mysqli,"SELECT * FROM calendars WHERE calendar_id = $calendar_id");
    $row = mysqli_fetch_array($sql);
    $calendar_name = sanitizeInput($row['calendar_name']);

    //If email is checked
    if ($email_event == 1) {

        $sql_client = mysqli_query($mysqli,"SELECT * FROM clients JOIN contacts ON contact_client_id = client_id WHERE contact_primary = 1 AND client_id = $client");
        $row = mysqli_fetch_array($sql_client);
        $client_name = sanitizeInput($row['client_name']);
        $contact_name = sanitizeInput($row['contact_name']);
        $contact_email = sanitizeInput($row['contact_email']);

        $sql_company = mysqli_query($mysqli,"SELECT * FROM companies WHERE company_id = 1");
        $row = mysqli_fetch_array($sql_company);
        $company_name = sanitizeInput($row['company_name']);
        $company_country = sanitizeInput($row['company_country']);
        $company_address = sanitizeInput($row['company_address']);
        $company_city = sanitizeInput($row['company_city']);
        $company_state = sanitizeInput($row['company_state']);
        $company_zip = sanitizeInput($row['company_zip']);
        $company_phone = sanitizeInput(formatPhoneNumber($row['company_phone']));
        $company_email = sanitizeInput($row['company_email']);
        $company_website = sanitizeInput($row['company_website']);
        $company_logo = sanitizeInput($row['company_logo']);

        // Sanitize Config Vars from get_settings.php and Session Vars from check_login.php
        $config_mail_from_name = sanitizeInput($config_mail_from_name);
        $config_mail_from_email = sanitizeInput($config_mail_from_email);

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
        $mail = addToMailQueue($mysqli, $data);

        // Logging for email (success/fail)
        if ($mail === true) {
            logAction("Calendar Event", "Email", "$session_name emailed event $title to $contact_name from client $client_name", $client, $event_id);
        } else {
            appNotify("Mail", "Failed to send email to $contact_email");
            logAction("Mail", "Error", "Failed to send email to $contact_email regarding $subject. $mail");
        }

    } // End mail IF

    // Logging
    logAction("Calendar Event", "Create", "$session_name created a calendar event titled $title in calendar $calendar_name", $client, $event_id);

    $_SESSION['alert_message'] = "Event <strong>$title</strong> created in calendar <strong>$calendar_name</strong>";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_event'])) {

    require_once 'post/user/event_model.php';

    $event_id = intval($_POST['event_id']);

    mysqli_query($mysqli,"UPDATE events SET event_title = '$title', event_location = '$location', event_description = '$description', event_start = '$start', event_end = '$end', event_repeat = '$repeat', event_calendar_id = $calendar_id, event_client_id = $client WHERE event_id = $event_id");

    //If email is checked
    if ($email_event == 1) {

        $sql_client = mysqli_query($mysqli,"SELECT * FROM clients JOIN contacts ON contact_client_id = client_id WHERE contact_primary = 1 AND client_id = $client");
        $row = mysqli_fetch_array($sql_client);
        $client_name = sanitizeInput($row['client_name']);
        $contact_name = sanitizeInput($row['contact_name']);
        $contact_email = sanitizeInput($row['contact_email']);

        $sql_company = mysqli_query($mysqli,"SELECT * FROM companies WHERE company_id = 1");
        $row = mysqli_fetch_array($sql_company);
        $company_name = sanitizeInput($row['company_name']);
        $company_country = sanitizeInput($row['company_country']);
        $company_address = sanitizeInput($row['company_address']);
        $company_city = sanitizeInput($row['company_city']);
        $company_state = sanitizeInput($row['company_state']);
        $company_zip = sanitizeInput($row['company_zip']);
        $company_phone = sanitizeInput(formatPhoneNumber($row['company_phone']));
        $company_email = sanitizeInput($row['company_email']);
        $company_website = sanitizeInput($row['company_website']);
        $company_logo = sanitizeInput($row['company_logo']);

        // Sanitize Config Vars from get_settings.php and Session Vars from check_login.php
        $config_mail_from_name = sanitizeInput($config_mail_from_name);
        $config_mail_from_email = sanitizeInput($config_mail_from_email);


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
        $mail = addToMailQueue($mysqli, $data);
        // Logging for email (success/fail)
        if ($mail === true) {
            logAction("Calendar Event", "Email", "$session_name Emailed modified event $title to $contact_name email $contact_email", $client, $event_id);
        } else {
            appNotify("Mail", "Failed to send email to $contact_email");
            logAction("Mail", "Error", "Failed to send email to $contact_email regarding $subject. $mail");
        }

    } // End mail IF

    //Logging
    logAction("Calendar Event", "Edit", "$session_name edited calendar event $title", $client, $event_id);

    $_SESSION['alert_message'] = "Calendar event titled <strong>$title</strong> edited";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['delete_event'])) {
    $event_id = intval($_GET['delete_event']);

    // Get Event Title
    $sql = mysqli_query($mysqli,"SELECT * FROM events WHERE event_id = $event_id");
    $row = mysqli_fetch_array($sql);
    $event_title = sanitizeInput($row['event_title']);
    $client_id = intval($row['event_client_id']);

    mysqli_query($mysqli,"DELETE FROM events WHERE event_id = $event_id");

    // Logging
    logAction("Calendar Event", "Delete", "$session_name deleted calendar event $event_title", $client_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Calendar event titled <strong>$event_title</strong> deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}
