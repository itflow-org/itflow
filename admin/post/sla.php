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

    // Business hours feed the due date math - re-stamp open SLA tickets
    $restamped = 0;
    $sql_tickets = mysqli_query($mysqli, "SELECT ticket_id, ticket_sla_id FROM tickets WHERE ticket_sla_id > 0 AND ticket_closed_at IS NULL AND ticket_archived_at IS NULL");
    while ($ticket_row = mysqli_fetch_assoc($sql_tickets)) {
        applyTicketSla($ticket_row['ticket_id'], $ticket_row['ticket_sla_id']);
        $restamped++;
    }

    logAudit("Settings", "Edit", "$session_name edited SLA / business hours settings");

    flashAlert("SLA settings updated - targets recalculated on $restamped open ticket(s)");

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
