<?php

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

/*
 * Admin > SLAs. An SLA's window (modals/sla/sla_form.php) sends each target as
 * hours and minutes - response_hours and response_minutes, resolution_hours and
 * resolution_minutes - and slaTargetMinutes() makes minutes of the two. A
 * resolution target left empty is an SLA that only times the first reply.
 */

// "12 tickets not yet closed" for the messages - what a save worked out again.
// That is every ticket still to close, the resolved ones included, so it can be
// more than the open tickets the list counts against an SLA.
$sla_open_tickets_text = function ($count) {
    return number_format($count) . ' ticket' . (intval($count) === 1 ? '' : 's') . ' not yet closed';
};

if (isset($_POST['add_sla']) || isset($_POST['edit_sla'])) {

    validateCSRFToken();

    $name_input = trim((string) ($_POST['name'] ?? ''));
    $response_minutes = slaTargetMinutes($_POST['response_hours'] ?? 0, $_POST['response_minutes'] ?? 0);
    $resolution_minutes = slaTargetMinutes($_POST['resolution_hours'] ?? 0, $_POST['resolution_minutes'] ?? 0);

    // Said here, not left to the database: an SLA with no name can't be picked, and one with no response target stamps tickets as due the moment they are raised
    if ($name_input === '') {
        flashAlert("Give the SLA a name.", 'error');
        redirect();
    }
    if ($response_minutes < 1) {
        flashAlert("Give the SLA a response target - how long there is to first reply.", 'error');
        redirect();
    }
    if ($resolution_minutes > 0 && $resolution_minutes < $response_minutes) {
        flashAlert("The resolution target can't be shorter than the response target.", 'error');
        redirect();
    }

}

if (isset($_POST['add_sla'])) {

    $name = escapeSql($name_input);
    $description = escapeSql(trim((string) ($_POST['description'] ?? '')));
    $resolution_minutes_set = $resolution_minutes > 0 ? $resolution_minutes : "NULL";

    mysqli_query($mysqli, "INSERT INTO slas SET sla_name = '$name', sla_description = '$description', sla_response_minutes = $response_minutes, sla_resolution_minutes = $resolution_minutes_set");

    logAudit("SLA", "Create", "$session_name created SLA $name");

    flashAlert("SLA <strong>$name_input</strong> created");

    redirect();

}

if (isset($_POST['edit_sla'])) {

    $sla_id = intval($_POST['sla_id']);
    $name = escapeSql($name_input);
    $description = escapeSql(trim((string) ($_POST['description'] ?? '')));
    $resolution_minutes_set = $resolution_minutes > 0 ? $resolution_minutes : "NULL";

    mysqli_query($mysqli, "UPDATE slas SET sla_name = '$name', sla_description = '$description', sla_response_minutes = $response_minutes, sla_resolution_minutes = $resolution_minutes_set WHERE sla_id = $sla_id");

    // Re-stamp open tickets on this SLA so their targets follow the new minutes
    $restamped = 0;
    $sql_tickets = mysqli_query($mysqli, "SELECT ticket_id FROM tickets WHERE ticket_sla_id = $sla_id AND ticket_closed_at IS NULL AND ticket_archived_at IS NULL");
    while ($ticket_row = mysqli_fetch_assoc($sql_tickets)) {
        applyTicketSla($ticket_row['ticket_id'], $sla_id);
        $restamped++;
    }

    logAudit("SLA", "Edit", "$session_name edited SLA $name");

    flashAlert("SLA <strong>$name_input</strong> saved" . ($restamped ? " - targets worked out again on " . $sla_open_tickets_text($restamped) : ''));

    redirect();

}

if (isset($_GET['archive_sla'])) {

    validateCSRFToken();

    $sla_id = intval($_GET['archive_sla']);

    mysqli_query($mysqli, "UPDATE slas SET sla_archived_at = NOW() WHERE sla_id = $sla_id");

    // Assignments pointing at an archived SLA resolve to "no SLA" for new
    // tickets; existing tickets keep their stamped targets

    logAudit("SLA", "Archive", "$session_name archived SLA ID $sla_id");

    flashAlert("SLA archived");

    redirect();

}

if (isset($_GET['unarchive_sla'])) {

    validateCSRFToken();

    $sla_id = intval($_GET['unarchive_sla']);

    mysqli_query($mysqli, "UPDATE slas SET sla_archived_at = NULL WHERE sla_id = $sla_id");

    logAudit("SLA", "Restore", "$session_name restored SLA ID $sla_id");

    flashAlert("SLA restored");

    redirect();

}

if (isset($_POST['edit_sla_settings'])) {

    validateCSRFToken();

    // Business days arrive as an array of ISO weekday numbers (1 = Mon .. 7 = Sun)
    $business_days = [];
    if (isset($_POST['business_days']) && is_array($_POST['business_days'])) {
        foreach ($_POST['business_days'] as $day) {
            $day = intval($day);
            if ($day >= 1 && $day <= 7) {
                $business_days[] = $day;
            }
        }
    }
    // The hours as HH:MM, the day ending after it starts. Anything else the clock
    // can't use, and addBusinessMinutes() would quietly run it around the clock instead
    $business_hours_start = substr(trim((string) ($_POST['business_hours_start'] ?? '')), 0, 5);
    $business_hours_end = substr(trim((string) ($_POST['business_hours_end'] ?? '')), 0, 5);
    $time_pattern = '/^([01][0-9]|2[0-3]):[0-5][0-9]$/';

    if (!preg_match($time_pattern, $business_hours_start) || !preg_match($time_pattern, $business_hours_end)) {
        flashAlert("Enter the business hours as times, for example 09:00 to 17:00.", 'error');
        redirect();
    }
    if ($business_days && $business_hours_start >= $business_hours_end) {
        flashAlert("Business hours have to end after they start. For cover around the clock, leave every day unpicked.", 'error');
        redirect();
    }

    $business_days = escapeSql(implode(',', $business_days));

    $business_hours_start = escapeSql($business_hours_start);
    $business_hours_end = escapeSql($business_hours_end);
    $warning_percent = min(99, max(1, intval($_POST['warning_percent'] ?? 75)));
    $notification_email = escapeSql(trim((string) ($_POST['notification_email'] ?? '')));

    mysqli_query($mysqli, "UPDATE settings SET config_business_days = '$business_days', config_business_hours_start = '$business_hours_start', config_business_hours_end = '$business_hours_end', config_sla_warning_percent = $warning_percent, config_sla_notification_email = '$notification_email' WHERE company_id = 1");

    // Drop the cached copy so the restamp below uses the hours just saved
    getSlaSettings(true);

    // Business hours feed the due date math - re-stamp open SLA tickets
    $restamped = restampOpenSlaTickets();

    logAudit("Settings", "Edit", "$session_name edited SLA / business hours settings");

    flashAlert("Business hours and alerts saved" . ($restamped ? " - targets worked out again on " . $sla_open_tickets_text($restamped) : ''));

    redirect();

}

if (isset($_POST['add_holiday'])) {

    validateCSRFToken();

    // Deliberately NOT validateDate() - that falls back to today's date on bad
    // input, which would silently close the office today. Reject instead. The
    // round-trip comparison also catches impossible dates like 2026-02-30,
    // which createFromFormat would otherwise roll forward into March.
    $holiday_date_input = $_POST['holiday_date'] ?? '';
    $parsed_date = DateTime::createFromFormat('Y-m-d', $holiday_date_input);

    if (!$parsed_date || $parsed_date->format('Y-m-d') !== $holiday_date_input) {
        flashAlert("Enter a valid date for the closure day.", 'error');
        redirect();
    }

    $holiday_name_input = trim($_POST['holiday_name'] ?? '');
    if ($holiday_name_input === '') {
        flashAlert("Enter a name for the closure day.", 'error');
        redirect();
    }

    $holiday_date = escapeSql($holiday_date_input);
    $holiday_name = escapeSql($holiday_name_input);

    // INSERT IGNORE rather than an error: the date is UNIQUE, and re-adding a day
    // that is already listed is a no-op the operator does not need telling about
    mysqli_query($mysqli, "INSERT IGNORE INTO business_holidays SET holiday_date = '$holiday_date', holiday_name = '$holiday_name'");

    getBusinessHolidays(true);
    $restamped = restampOpenSlaTickets();

    logAudit("Settings", "Create", "$session_name added SLA closure day $holiday_date - $holiday_name");

    flashAlert("Closure day added" . ($restamped ? " - targets worked out again on " . $sla_open_tickets_text($restamped) : ''));

    redirect();

}

if (isset($_POST['delete_holiday'])) {

    validateCSRFToken();

    $holiday_id = intval($_POST['holiday_id']);

    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT holiday_date, holiday_name FROM business_holidays WHERE holiday_id = $holiday_id LIMIT 1"));
    if (!$row) {
        flashAlert("Closure day not found.", 'error');
        redirect();
    }
    $holiday_date = escapeSql($row['holiday_date']);
    $holiday_name = escapeSql($row['holiday_name']);

    mysqli_query($mysqli, "DELETE FROM business_holidays WHERE holiday_id = $holiday_id");

    getBusinessHolidays(true);
    $restamped = restampOpenSlaTickets();

    logAudit("Settings", "Delete", "$session_name removed SLA closure day $holiday_date - $holiday_name");

    flashAlert("Closure day removed" . ($restamped ? " - targets worked out again on " . $sla_open_tickets_text($restamped) : ''));

    redirect();

}

if (isset($_POST['generate_holidays'])) {

    validateCSRFToken();

    $holiday_year = intval($_POST['holiday_year']);

    if ($holiday_year < 2000 || $holiday_year > 2100) {
        flashAlert("Enter a year between 2000 and 2100.", 'error');
        redirect();
    }

    // Existing rows win - INSERT IGNORE leaves a hand-entered name on a date the
    // generator also produces, so running this over a partly-filled year is safe
    $added = 0;
    foreach (usFederalHolidays($holiday_year) as $holiday) {
        $holiday_date = escapeSql($holiday['date']);
        $holiday_name = escapeSql($holiday['name']);
        mysqli_query($mysqli, "INSERT IGNORE INTO business_holidays SET holiday_date = '$holiday_date', holiday_name = '$holiday_name'");
        $added += mysqli_affected_rows($mysqli) > 0 ? 1 : 0;
    }

    getBusinessHolidays(true);
    $restamped = restampOpenSlaTickets();

    logAudit("Settings", "Create", "$session_name generated $added US federal holiday closure day(s) for $holiday_year");

    flashAlert($added
        ? "Added $added US federal holiday" . ($added === 1 ? '' : 's') . " for $holiday_year" . ($restamped ? " - targets worked out again on " . $sla_open_tickets_text($restamped) : '')
        : "Every US federal holiday for $holiday_year is on the list already");

    redirect();

}

if (isset($_POST['save_sla_assignments'])) {

    validateCSRFToken();

    // Global defaults - one select per priority; 0 means no SLA, which for the
    // global row is simply no assignment
    foreach (['Low', 'Medium', 'High', 'Urgent'] as $priority) {

        $field = 'global_sla_' . strtolower($priority);
        $sla_id = intval($_POST[$field] ?? 0);

        mysqli_query($mysqli, "DELETE FROM sla_assignments WHERE sla_assignment_client_id = 0 AND sla_assignment_priority = '$priority'");
        if ($sla_id > 0) {
            mysqli_query($mysqli, "INSERT INTO sla_assignments SET sla_assignment_client_id = 0, sla_assignment_priority = '$priority', sla_assignment_sla_id = $sla_id");
        }
    }

    // Re-resolve open tickets against the new defaults
    $restamped = 0;
    $sql_tickets = mysqli_query($mysqli, "SELECT ticket_id FROM tickets WHERE ticket_closed_at IS NULL AND ticket_archived_at IS NULL");
    while ($ticket_row = mysqli_fetch_assoc($sql_tickets)) {
        applyTicketSla($ticket_row['ticket_id']);
        $restamped++;
    }

    logAudit("SLA", "Edit", "$session_name updated default SLA assignments");

    flashAlert("Default SLAs saved" . ($restamped ? " - looked again at " . $sla_open_tickets_text($restamped) : ''));

    redirect();

}
