<?php

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_sla'])) {

    validateCSRFToken();

    $name = escapeSql($_POST['name']);
    $description = escapeSql($_POST['description']);
    $response_minutes = intval($_POST['response_minutes']);
    $resolution_minutes = intval($_POST['resolution_minutes']);
    $resolution_minutes_set = $resolution_minutes > 0 ? $resolution_minutes : "NULL";

    mysqli_query($mysqli, "INSERT INTO slas SET sla_name = '$name', sla_description = '$description', sla_response_minutes = $response_minutes, sla_resolution_minutes = $resolution_minutes_set");

    logAudit("SLA", "Create", "$session_name created SLA $name");

    flashAlert("SLA <strong>$name</strong> created");

    redirect();

}

if (isset($_POST['edit_sla'])) {

    validateCSRFToken();

    $sla_id = intval($_POST['sla_id']);
    $name = escapeSql($_POST['name']);
    $description = escapeSql($_POST['description']);
    $response_minutes = intval($_POST['response_minutes']);
    $resolution_minutes = intval($_POST['resolution_minutes']);
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

    flashAlert("SLA <strong>$name</strong> updated - targets recalculated on $restamped open ticket(s)");

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

    logAudit("SLA", "Unarchive", "$session_name restored SLA ID $sla_id");

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
    $business_days = escapeSql(implode(',', $business_days));

    $business_hours_start = escapeSql($_POST['business_hours_start']);
    $business_hours_end = escapeSql($_POST['business_hours_end']);
    $warning_percent = intval($_POST['warning_percent']);
    $notification_email = escapeSql($_POST['notification_email']);

    mysqli_query($mysqli, "UPDATE settings SET config_business_days = '$business_days', config_business_hours_start = '$business_hours_start', config_business_hours_end = '$business_hours_end', config_sla_warning_percent = $warning_percent, config_sla_notification_email = '$notification_email' WHERE company_id = 1");

    // Drop the cached copy so the restamp below uses the hours just saved
    getSlaSettings(true);

    // Business hours feed the due date math - re-stamp open SLA tickets
    $restamped = restampOpenSlaTickets();

    logAudit("Settings", "Edit", "$session_name edited SLA / business hours settings");

    flashAlert("SLA settings updated - targets recalculated on $restamped open ticket(s)");

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

    flashAlert("Closure day added - targets recalculated on $restamped open ticket(s)");

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

    flashAlert("Closure day removed - targets recalculated on $restamped open ticket(s)");

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

    flashAlert("Added $added US federal holiday(s) for $holiday_year - targets recalculated on $restamped open ticket(s)");

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

    flashAlert("Default SLA assignments saved - $restamped open ticket(s) re-evaluated");

    redirect();

}
