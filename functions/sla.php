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
function getSlaSettings($refresh = false)
{
    global $mysqli;

    static $sla_settings = null;

    // Callers that have just written to the settings row pass true - otherwise
    // they would restamp tickets using the business hours this request started
    // with rather than the ones just saved
    if ($refresh) {
        $sla_settings = null;
    }

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

// Business minutes elapsed between two datetimes - the inverse of
// addBusinessMinutes, used to measure how much of a resolution budget an
// interval actually consumed.
function businessMinutesBetween($start_datetime, $end_datetime)
{
    $start = new DateTime($start_datetime);
    $end = new DateTime($end_datetime);

    if ($end <= $start) {
        return 0;
    }

    $sla_settings = getSlaSettings();
    $business_days = $sla_settings['business_days'];
    $day_start = $sla_settings['business_hours_start'];
    $day_end = $sla_settings['business_hours_end'];

    if (empty($business_days) || empty($day_start) || empty($day_end) || $day_start >= $day_end) {
        return intval(floor(($end->getTimestamp() - $start->getTimestamp()) / 60));
    }

    $seconds = 0;
    $cursor = clone $start;

    // Sum the overlap between the interval and each day's business window
    for ($i = 0; $i < 731; $i++) {

        if ($cursor > $end) {
            break;
        }

        if (in_array(intval($cursor->format('N')), $business_days)) {

            $window_start = new DateTime($cursor->format('Y-m-d') . " $day_start");
            $window_end = new DateTime($cursor->format('Y-m-d') . " $day_end");

            $from = $cursor > $window_start ? $cursor : $window_start;
            $to = $end < $window_end ? $end : $window_end;

            if ($to > $from) {
                $seconds += $to->getTimestamp() - $from->getTimestamp();
            }
        }

        $cursor = new DateTime($cursor->format('Y-m-d') . ' 00:00:00');
        $cursor->modify('+1 day');
    }

    return intval(floor($seconds / 60));
}

// Business minutes already spent on a ticket's resolution clock, including the
// interval currently running.
//
// Tickets with no clock history at all fall back to the business time elapsed
// since they were raised. Only tickets carrying a resolution target get
// intervals, so this covers response-only plans, and tickets that were already
// resolved when SLA pausing was introduced. Without the fallback both report
// zero time spent, which reads as instant resolution.
function getTicketSlaConsumedMinutes($ticket_id)
{
    global $mysqli;

    $ticket_id = intval($ticket_id);
    $consumed = 0;
    $has_history = false;

    $sql = mysqli_query($mysqli, "SELECT sla_history_started_at, sla_history_ended_at, sla_history_minutes FROM sla_history WHERE sla_history_ticket_id = $ticket_id");
    while ($row = mysqli_fetch_assoc($sql)) {
        $has_history = true;
        if (!is_null($row['sla_history_ended_at'])) {
            $consumed += intval($row['sla_history_minutes']);
        } else {
            $consumed += businessMinutesBetween($row['sla_history_started_at'], date('Y-m-d H:i:s'));
        }
    }

    if (!$has_history) {
        $ticket_sql = mysqli_query($mysqli, "SELECT ticket_created_at, ticket_resolved_at, ticket_closed_at FROM tickets WHERE ticket_id = $ticket_id LIMIT 1");
        if (!$ticket_sql || !mysqli_num_rows($ticket_sql)) {
            return 0;
        }
        $ticket = mysqli_fetch_assoc($ticket_sql);
        $ended_at = $ticket['ticket_resolved_at'] ?: ($ticket['ticket_closed_at'] ?: date('Y-m-d H:i:s'));
        return businessMinutesBetween($ticket['ticket_created_at'], $ended_at);
    }

    return $consumed;
}

// How many times a ticket's clock has been stopped. Zero means it has run
// continuously since creation, so its deadline is still simply creation plus
// the budget - the same answer pausing-free installs have always had.
function getTicketSlaPausedCount($ticket_id)
{
    global $mysqli;

    $ticket_id = intval($ticket_id);
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(sla_history_id) AS paused_count FROM sla_history WHERE sla_history_ticket_id = $ticket_id AND sla_history_ended_at IS NOT NULL"));

    return intval($row['paused_count']);
}

// Colour an SLA compliance percentage for the reports. Null means nothing has
// been judged yet, which is not the same as zero.
function slaPercentDisplay($percent)
{
    if (is_null($percent)) {
        return "<span class='text-secondary'>-</span>";
    }
    if ($percent >= 95) {
        return "<span class='text-success text-bold'>$percent%</span>";
    }
    if ($percent >= 80) {
        return "<span class='text-warning text-bold'>$percent%</span>";
    }
    return "<span class='text-danger text-bold'>$percent%</span>";
}

// Reconcile a ticket's resolution clock with its current status. Safe to call
// after any status change (and after applyTicketSla) - it opens an interval
// when the clock should be running, closes it when it should not, and on
// resume re-bases the resolution deadline on the remaining budget. The response
// clock is deliberately never paused: a ticket cannot wait on a client before
// anyone has replied to it.
function syncTicketSlaClock($ticket_id)
{
    global $mysqli;

    $ticket_id = intval($ticket_id);

    $sql = mysqli_query($mysqli, "SELECT ticket_sla_id, ticket_created_at, ticket_resolved_at, ticket_closed_at, ticket_archived_at, ticket_resolution_sla_alert_stage, sla_resolution_minutes, ticket_status_pauses_sla
        FROM tickets
        LEFT JOIN slas ON ticket_sla_id = sla_id
        LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
        WHERE ticket_id = $ticket_id
        LIMIT 1"
    );
    if (!$sql || !mysqli_num_rows($sql)) {
        return;
    }
    $row = mysqli_fetch_assoc($sql);

    $open_sql = mysqli_query($mysqli, "SELECT sla_history_id, sla_history_started_at FROM sla_history WHERE sla_history_ticket_id = $ticket_id AND sla_history_ended_at IS NULL ORDER BY sla_history_id DESC LIMIT 1");
    $open_interval = mysqli_num_rows($open_sql) ? mysqli_fetch_assoc($open_sql) : null;

    // Only tickets carrying a resolution target have a clock to track
    $resolution_minutes = intval($row['sla_resolution_minutes']);
    $tracked = intval($row['ticket_sla_id']) > 0 && $resolution_minutes > 0;

    $should_run = $tracked
        && empty($row['ticket_resolved_at'])
        && empty($row['ticket_closed_at'])
        && empty($row['ticket_archived_at'])
        && intval($row['ticket_status_pauses_sla']) == 0;

    if ($should_run && is_null($open_interval)) {

        // Prior intervals mean this is a resume, so the deadline moves out by
        // whatever budget is left rather than staying where it was
        $had_history = getTicketSlaPausedCount($ticket_id) > 0;
        $consumed = $had_history ? getTicketSlaConsumedMinutes($ticket_id) : 0;

        // The very first interval is anchored to the ticket's creation time, not
        // to now - otherwise a backdated ticket would start life with its whole
        // budget intact and re-stamping it would keep handing that budget back
        $interval_start = $had_history ? date('Y-m-d H:i:s') : escapeSql($row['ticket_created_at']);
        mysqli_query($mysqli, "INSERT INTO sla_history SET sla_history_started_at = '$interval_start', sla_history_ticket_id = $ticket_id");

        if ($had_history) {
            $remaining = $resolution_minutes - $consumed;
            if ($remaining < 0) {
                $remaining = 0;
            }
            $resolution_due_at = addBusinessMinutes(date('Y-m-d H:i:s'), $remaining);
            // A ticket that already breached stays breached
            $alert_stage = intval($row['ticket_resolution_sla_alert_stage']) == 2 ? 2 : 0;
            mysqli_query($mysqli, "UPDATE tickets SET ticket_resolution_due_at = '$resolution_due_at', ticket_resolution_sla_alert_stage = $alert_stage WHERE ticket_id = $ticket_id");
        }

    } elseif (!$should_run && !is_null($open_interval)) {

        $interval_id = intval($open_interval['sla_history_id']);
        $minutes = businessMinutesBetween($open_interval['sla_history_started_at'], date('Y-m-d H:i:s'));
        mysqli_query($mysqli, "UPDATE sla_history SET sla_history_ended_at = NOW(), sla_history_minutes = $minutes WHERE sla_history_id = $interval_id");
    }
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
        syncTicketSlaClock($ticket_id);
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
        // Once a ticket has been paused the budget is measured from what was
        // actually consumed, so paused time is not silently handed back. A
        // ticket that has only ever run keeps its original creation-anchored
        // deadline, including when that deadline is already in the past.
        if (getTicketSlaPausedCount($ticket_id) > 0) {
            $remaining = intval($sla['sla_resolution_minutes']) - getTicketSlaConsumedMinutes($ticket_id);
            if ($remaining < 0) {
                $remaining = 0;
            }
            $resolution_due_at = addBusinessMinutes(date('Y-m-d H:i:s'), $remaining);
        } else {
            $resolution_due_at = addBusinessMinutes($created_at, $sla['sla_resolution_minutes']);
        }
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

    syncTicketSlaClock($ticket_id);
}

// Human-readable SLA target, e.g. "45 minutes", "4 business hours" or
// "3 business days". The "business" qualifier is dropped when no business
// calendar is configured, because addBusinessMinutes runs 24x7 in that case
// and the promise would otherwise be misleading. The condition mirrors that
// function's own guard.
//
// Anything at or over one business day rolls up to days: with 9-5 hours a
// 1440-minute target is three working days, but "24 business hours" reads to
// a client as tomorrow. The exact deadline always travels next to this text
// in the email, so the wording only has to give the right impression - a
// remainder under five minutes is dropped rather than rendering the likes of
// "1 business hour 1 minute".
function formatSlaMinutes($minutes)
{
    $minutes = intval($minutes);

    $sla_settings = getSlaSettings();
    $day_start = $sla_settings['business_hours_start'];
    $day_end = $sla_settings['business_hours_end'];

    $qualifier = '';
    $day_minutes = 1440;
    if (!empty($sla_settings['business_days']) && !empty($day_start) && !empty($day_end) && $day_start < $day_end) {
        $qualifier = 'business ';
        $working_minutes = intval((strtotime($day_end) - strtotime($day_start)) / 60);
        if ($working_minutes > 0) {
            $day_minutes = $working_minutes;
        }
    }

    if ($minutes < 60) {
        return $minutes . ' minute' . ($minutes == 1 ? '' : 's');
    }

    if ($minutes < $day_minutes) {
        $hours = intdiv($minutes, 60);
        $remainder = $minutes % 60;

        $text = $hours . ' ' . $qualifier . 'hour' . ($hours == 1 ? '' : 's');
        if ($remainder >= 5) {
            $text .= ' ' . $remainder . ' minutes';
        }

        return $text;
    }

    $days = intdiv($minutes, $day_minutes);
    $hours = intdiv($minutes % $day_minutes, 60);

    $text = $days . ' ' . $qualifier . 'day' . ($days == 1 ? '' : 's');
    if ($hours > 0) {
        $text .= ' ' . $hours . ' hour' . ($hours == 1 ? '' : 's');
    }

    return $text;
}

// Client-facing SLA block for a "ticket created" email. Returns '' when no SLA
// applies to the ticket or the plan carries no response target, so callers can
// append it unconditionally.
//
// Call this AFTER applyTicketSla(), which is what stamps ticket_response_due_at.
//
// The returned HTML deliberately contains NO single or double quotes. Most of
// these email bodies are assembled pre-escaped and handed to addToMailQueue,
// which interpolates the body straight into its INSERT without escaping it, so
// a stray quote here would break the query at those call sites.
function getTicketSlaEmailNotice($ticket_id, $company_phone = '')
{
    global $mysqli;

    $ticket_id = intval($ticket_id);

    $sql = mysqli_query($mysqli, "SELECT ticket_priority, ticket_response_due_at, sla_response_minutes
        FROM tickets
        LEFT JOIN slas ON ticket_sla_id = sla_id
        WHERE ticket_id = $ticket_id LIMIT 1");
    if (!$sql || !mysqli_num_rows($sql)) {
        return '';
    }
    $row = mysqli_fetch_assoc($sql);

    $response_minutes = intval($row['sla_response_minutes']);

    if (empty($row['ticket_response_due_at']) || $response_minutes <= 0) {
        return '';
    }

    // The only value in this notice that comes from the database rather than a
    // literal - escaped so the quote-free guarantee above holds for it too
    $priority = escapeHtml($row['ticket_priority']);
    $target = formatSlaMinutes($response_minutes);
    $due = date('D j M, g:i A', strtotime($row['ticket_response_due_at']));

    $notice = "<br><br>Priority: $priority<br>Target response: within $target (by $due)";

    // Higher priorities get told to phone rather than sit on an email thread
    if (!empty($company_phone) && ($priority == 'Urgent' || $priority == 'High')) {
        if ($priority == 'Urgent') {
            $notice .= "<br><br><strong>This ticket is marked Urgent.</strong> If the issue is stopping work right now, please call us on $company_phone rather than waiting for a reply to this email.";
        } else {
            $notice .= "<br><br><strong>This ticket is marked High priority.</strong> If it is business-impacting, calling us on $company_phone will get you the fastest response.";
        }
    }

    return $notice;
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

    $sql = mysqli_query($mysqli, "SELECT ticket_resolution_due_at, ticket_resolved_at, ticket_resolution_sla_met FROM tickets WHERE ticket_id = $ticket_id LIMIT 1");
    if (!$sql || !mysqli_num_rows($sql)) {
        return;
    }
    $row = mysqli_fetch_assoc($sql);

    if (empty($row['ticket_resolution_due_at'])) {
        return;
    }

    // A recorded miss is final at judge time. Reopening an exhausted ticket
    // re-bases its deadline to the present, so without this a resolve in the
    // same clock second would grade against that deadline and flip the miss
    // to a met. Only an explicit re-stamp (applyTicketSla) may re-judge.
    if (!is_null($row['ticket_resolution_sla_met']) && intval($row['ticket_resolution_sla_met']) === 0) {
        syncTicketSlaClock($ticket_id);
        return;
    }

    $ended_at = !empty($row['ticket_resolved_at']) ? strtotime($row['ticket_resolved_at']) : time();
    $resolution_met = $ended_at <= strtotime($row['ticket_resolution_due_at']) ? 1 : 0;

    mysqli_query($mysqli, "UPDATE tickets SET ticket_resolution_sla_met = $resolution_met WHERE ticket_id = $ticket_id");

    syncTicketSlaClock($ticket_id);
}

// A reopened ticket goes back on the resolution clock. syncTicketSlaClock
// reopens an interval and re-bases the deadline on the budget that is left, so
// a ticket that was resolved with time to spare gets that remainder back.
//
// A missed verdict survives the reopen when the budget is already spent.
// Without this, re-basing would hand an exhausted ticket a zero-length fresh
// window, and resolving it again straight away would overwrite the recorded
// miss with a met - reopen must never be a way to launder a breach.
function resetTicketResolutionSla($ticket_id)
{
    global $mysqli;

    $ticket_id = intval($ticket_id);

    $sql = mysqli_query($mysqli, "SELECT ticket_resolution_sla_met, sla_resolution_minutes FROM tickets LEFT JOIN slas ON ticket_sla_id = sla_id WHERE ticket_id = $ticket_id LIMIT 1");
    if ($sql && mysqli_num_rows($sql)) {
        $row = mysqli_fetch_assoc($sql);
        $resolution_minutes = intval($row['sla_resolution_minutes']);
        $was_missed = !is_null($row['ticket_resolution_sla_met']) && intval($row['ticket_resolution_sla_met']) === 0;

        if ($was_missed && $resolution_minutes > 0 && getTicketSlaConsumedMinutes($ticket_id) >= $resolution_minutes) {
            // Budget gone: keep the miss and the breach stage (so the cron does
            // not re-alert), just restart the clock for the time-spent record
            syncTicketSlaClock($ticket_id);
            return;
        }
    }

    mysqli_query($mysqli, "UPDATE tickets SET ticket_resolution_sla_met = NULL, ticket_resolution_sla_alert_stage = 0 WHERE ticket_id = $ticket_id");

    syncTicketSlaClock($ticket_id);
}
