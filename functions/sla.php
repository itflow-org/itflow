<?php

/*
 * ITFlow - Ticket SLA helpers
 *
 * SLAs are optional at every level. A ticket only gets SLA targets when an
 * assignment resolves for its client + priority (client-level assignments win,
 * client 0 rows are the global default, and an assignment pointing at SLA 0 is
 * an explicit "no SLA" override). Tickets with ticket_sla_id = 0 are simply
 * ignored by the SLA cron, the list highlighting and the reports.
 *
 * Deadlines are computed ONCE at write time (creation / priority change /
 * client change / manual SLA change) and stored on the ticket as
 * ticket_response_due_at / ticket_resolution_due_at, so the ticket list and
 * cron/ticket_sla.php only ever compare datetimes - no business-hours math at
 * render time.
 */

// Business hours + SLA settings, fetched once per request
function getSlaSettings()
{
    global $mysqli;

    static $sla_settings = null;

    if (!is_null($sla_settings)) {
        return $sla_settings;
    }

    $sql = mysqli_query($mysqli, "SELECT config_business_days, config_business_hours_start, config_business_hours_end, config_sla_warning_percent, config_sla_notification_email, config_ticket_from_name, config_ticket_from_email, config_mail_from_email, config_mail_from_name FROM settings WHERE company_id = 1");
    $row = mysqli_fetch_assoc($sql);

    // ISO weekday numbers (1 = Monday .. 7 = Sunday)
    $business_days = [];
    foreach (explode(',', strval($row['config_business_days'])) as $day) {
        $day = intval($day);
        if ($day >= 1 && $day <= 7) {
            $business_days[] = $day;
        }
    }

    $sla_settings = [
        'business_days' => $business_days,
        'business_hours_start' => $row['config_business_hours_start'],
        'business_hours_end' => $row['config_business_hours_end'],
        'warning_percent' => intval($row['config_sla_warning_percent']),
        'notification_email' => $row['config_sla_notification_email'],
        'ticket_from_name' => $row['config_ticket_from_name'] ?: $row['config_mail_from_name'],
        'ticket_from_email' => $row['config_ticket_from_email'] ?: $row['config_mail_from_email'],
    ];

    return $sla_settings;
}

// Add $minutes of business time to a datetime, honouring the configured
// business days and hours. Returns a Y-m-d H:i:s string in the app timezone
// (includes/inc_set_timezone.php has already set it). With no usable business
// calendar configured the clock is treated as 24x7.
function addBusinessMinutes($start_datetime, $minutes)
{
    $minutes = intval($minutes);
    $cursor = new DateTime($start_datetime);

    if ($minutes <= 0) {
        return $cursor->format('Y-m-d H:i:s');
    }

    $sla_settings = getSlaSettings();
    $business_days = $sla_settings['business_days'];
    $day_start = $sla_settings['business_hours_start'];
    $day_end = $sla_settings['business_hours_end'];

    if (empty($business_days) || empty($day_start) || empty($day_end) || $day_start >= $day_end) {
        $cursor->modify("+$minutes minutes");
        return $cursor->format('Y-m-d H:i:s');
    }

    $remaining_seconds = $minutes * 60;

    // Walk forward a day at a time consuming available business time. Interval
    // math is done on timestamps (real elapsed time), window edges by wall
    // clock - so a DST-shortened business day yields less SLA time, which is
    // the honest reading. Guard: two years of calendar.
    for ($i = 0; $i < 731; $i++) {

        if (in_array(intval($cursor->format('N')), $business_days)) {

            $window_start = new DateTime($cursor->format('Y-m-d') . " $day_start");
            $window_end = new DateTime($cursor->format('Y-m-d') . " $day_end");

            if ($cursor < $window_start) {
                $cursor = $window_start;
            }

            if ($cursor < $window_end) {
                $available_seconds = $window_end->getTimestamp() - $cursor->getTimestamp();

                if ($remaining_seconds <= $available_seconds) {
                    $cursor->setTimestamp($cursor->getTimestamp() + $remaining_seconds);
                    return $cursor->format('Y-m-d H:i:s');
                }

                $remaining_seconds -= $available_seconds;
            }
        }

        // Start of the next calendar day
        $cursor = new DateTime($cursor->format('Y-m-d') . ' 00:00:00');
        $cursor->modify('+1 day');
    }

    // Unreachable with a sane configuration - fail open rather than loop
    $cursor->setTimestamp($cursor->getTimestamp() + $remaining_seconds);
    return $cursor->format('Y-m-d H:i:s');
}

// Resolve which SLA (if any) applies to a client + priority combination.
// Returns an sla_id, or 0 when no SLA applies.
function getTicketSlaId($client_id, $priority)
{
    global $mysqli;

    $client_id = intval($client_id);
    $priority = escapeSql($priority);

    $sla_id = null;

    // Client-level assignment wins; a row pointing at SLA 0 is an explicit
    // "no SLA for this client/priority" override of the global default
    if ($client_id > 0) {
        $sql = mysqli_query($mysqli, "SELECT sla_assignment_sla_id FROM sla_assignments WHERE sla_assignment_client_id = $client_id AND sla_assignment_priority = '$priority' LIMIT 1");
        if (mysqli_num_rows($sql)) {
            $sla_id = intval(mysqli_fetch_assoc($sql)['sla_assignment_sla_id']);
        }
    }

    // Fall back to the global default (client 0)
    if (is_null($sla_id)) {
        $sql = mysqli_query($mysqli, "SELECT sla_assignment_sla_id FROM sla_assignments WHERE sla_assignment_client_id = 0 AND sla_assignment_priority = '$priority' LIMIT 1");
        if (mysqli_num_rows($sql)) {
            $sla_id = intval(mysqli_fetch_assoc($sql)['sla_assignment_sla_id']);
        }
    }

    if (empty($sla_id)) {
        return 0;
    }

    // Ignore assignments pointing at archived SLAs
    $sql = mysqli_query($mysqli, "SELECT sla_id FROM slas WHERE sla_id = $sla_id AND sla_archived_at IS NULL LIMIT 1");
    if (!mysqli_num_rows($sql)) {
        return 0;
    }

    return $sla_id;
}

// Stamp (or re-stamp) a ticket's SLA and computed due dates. Call after ticket
// creation and after anything that changes which SLA applies (priority edit,
// client change, manual SLA change). Pass $forced_sla_id to pin a specific SLA
// (0 = explicitly none) instead of resolving from the assignments.
function applyTicketSla($ticket_id, $forced_sla_id = null)
{
    global $mysqli;

    $ticket_id = intval($ticket_id);

    $sql = mysqli_query($mysqli, "SELECT ticket_client_id, ticket_priority, ticket_created_at, ticket_first_response_at, ticket_resolved_at FROM tickets WHERE ticket_id = $ticket_id LIMIT 1");
    if (!$sql || !mysqli_num_rows($sql)) {
        return;
    }
    $row = mysqli_fetch_assoc($sql);

    if (is_null($forced_sla_id)) {
        $sla_id = getTicketSlaId($row['ticket_client_id'], $row['ticket_priority']);
    } else {
        $sla_id = intval($forced_sla_id);
    }

    // No SLA applies - clear any previous targets
    if ($sla_id == 0) {
        mysqli_query($mysqli, "UPDATE tickets SET ticket_sla_id = 0, ticket_response_due_at = NULL, ticket_resolution_due_at = NULL, ticket_response_sla_met = NULL, ticket_resolution_sla_met = NULL, ticket_response_sla_alert_stage = 0, ticket_resolution_sla_alert_stage = 0 WHERE ticket_id = $ticket_id");
        return;
    }

    $sla_sql = mysqli_query($mysqli, "SELECT sla_response_minutes, sla_resolution_minutes FROM slas WHERE sla_id = $sla_id LIMIT 1");
    if (!$sla_sql || !mysqli_num_rows($sla_sql)) {
        return;
    }
    $sla = mysqli_fetch_assoc($sla_sql);

    $created_at = $row['ticket_created_at'];

    $response_due_at = addBusinessMinutes($created_at, $sla['sla_response_minutes']);

    $resolution_due_at = null;
    $resolution_due_at_set = "NULL";
    if (intval($sla['sla_resolution_minutes']) > 0) {
        $resolution_due_at = addBusinessMinutes($created_at, $sla['sla_resolution_minutes']);
        $resolution_due_at_set = "'$resolution_due_at'";
    }

    // Re-judge met flags for milestones already reached; alert stages reset so
    // the SLA cron re-evaluates pending milestones against the new targets
    $response_met_set = "NULL";
    if (!empty($row['ticket_first_response_at'])) {
        $response_met_set = strtotime($row['ticket_first_response_at']) <= strtotime($response_due_at) ? 1 : 0;
    }

    $resolution_met_set = "NULL";
    if (!empty($row['ticket_resolved_at']) && !is_null($resolution_due_at)) {
        $resolution_met_set = strtotime($row['ticket_resolved_at']) <= strtotime($resolution_due_at) ? 1 : 0;
    }

    mysqli_query($mysqli, "UPDATE tickets SET ticket_sla_id = $sla_id, ticket_response_due_at = '$response_due_at', ticket_resolution_due_at = $resolution_due_at_set, ticket_response_sla_met = $response_met_set, ticket_resolution_sla_met = $resolution_met_set, ticket_response_sla_alert_stage = 0, ticket_resolution_sla_alert_stage = 0 WHERE ticket_id = $ticket_id");
}

// Record the ticket's first response (if not already recorded) and judge the
// response SLA against the stored due date. Replaces the previous inline
// ticket_first_response_at updates so the SLA verdict can never drift from
// the timestamp.
function setTicketFirstResponse($ticket_id)
{
    global $mysqli;

    $ticket_id = intval($ticket_id);

    $sql = mysqli_query($mysqli, "SELECT ticket_first_response_at, ticket_response_due_at FROM tickets WHERE ticket_id = $ticket_id LIMIT 1");
    if (!$sql || !mysqli_num_rows($sql)) {
        return;
    }
    $row = mysqli_fetch_assoc($sql);

    if (!empty($row['ticket_first_response_at'])) {
        return;
    }

    $response_met_set = "NULL";
    if (!empty($row['ticket_response_due_at'])) {
        $response_met_set = time() <= strtotime($row['ticket_response_due_at']) ? 1 : 0;
    }

    mysqli_query($mysqli, "UPDATE tickets SET ticket_first_response_at = NOW(), ticket_response_sla_met = $response_met_set WHERE ticket_id = $ticket_id");
}

// Judge the resolution SLA when a ticket is resolved (or closed without being
// resolved, which also stops the clock). No-op for tickets without a
// resolution target.
function setTicketResolutionSlaMet($ticket_id)
{
    global $mysqli;

    $ticket_id = intval($ticket_id);

    $sql = mysqli_query($mysqli, "SELECT ticket_resolution_due_at, ticket_resolved_at FROM tickets WHERE ticket_id = $ticket_id LIMIT 1");
    if (!$sql || !mysqli_num_rows($sql)) {
        return;
    }
    $row = mysqli_fetch_assoc($sql);

    if (empty($row['ticket_resolution_due_at'])) {
        return;
    }

    $ended_at = !empty($row['ticket_resolved_at']) ? strtotime($row['ticket_resolved_at']) : time();
    $resolution_met = $ended_at <= strtotime($row['ticket_resolution_due_at']) ? 1 : 0;

    mysqli_query($mysqli, "UPDATE tickets SET ticket_resolution_sla_met = $resolution_met WHERE ticket_id = $ticket_id");
}

// A reopened ticket goes back on the resolution clock (original due date - no
// pause/extend logic yet, that arrives with SLA pausing)
function resetTicketResolutionSla($ticket_id)
{
    global $mysqli;

    $ticket_id = intval($ticket_id);

    mysqli_query($mysqli, "UPDATE tickets SET ticket_resolution_sla_met = NULL, ticket_resolution_sla_alert_stage = 0 WHERE ticket_id = $ticket_id");
}
