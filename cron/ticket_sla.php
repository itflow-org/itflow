<?php
// Set working directory to the directory this cron script lives at.
chdir(dirname(__FILE__));

// Ensure we're running from command line
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

// Prevent overlapping runs of this script
$cron_lock_script = __FILE__;
require_once "../includes/cron_lock.php";

require_once "../config.php";
require_once "../includes/inc_set_timezone.php";
require_once "../functions.php";

/*
 * ITFlow - Ticket SLA monitor
 *
 * Intended to run every minute:
 *   * * * * * php /var/www/itflow/cron/ticket_sla.php
 *
 * Walks open tickets that carry SLA targets and moves each track (response /
 * resolution) through its alert stages:
 *   0 = on track, 1 = warning sent, 2 = breach recorded + notified
 * The stages are what the ticket list colours on (1 = yellow, 2 = red) and
 * they make every notification fire exactly once. Tickets without an SLA
 * (ticket_sla_id = 0) are never selected, so with no SLAs assigned this cron
 * is a no-op.
 */

$sla_settings = getSlaSettings();

$warning_percent = intval($sla_settings['warning_percent']);
if ($warning_percent < 1 || $warning_percent > 99) {
    $warning_percent = 0; // Out of range = warnings disabled, breach alerts only
}

$sla_notification_email = trim(strval($sla_settings['notification_email']));
$from_email = $sla_settings['ticket_from_email'];
$from_name = $sla_settings['ticket_from_name'];

$now = time();

// Queue in-app + email notifications for an SLA event
function sendSlaAlert($ticket, $subject_line, $body_line)
{
    global $mysqli, $sla_notification_email, $from_email, $from_name, $config_base_url;

    $ticket_id = intval($ticket['ticket_id']);
    $client_id = intval($ticket['ticket_client_id']);

    $ticket_ref = "{$ticket['ticket_prefix']}{$ticket['ticket_number']}";
    $ticket_subject = strval($ticket['ticket_subject']);

    // appNotify inserts what it is given - escape at the boundary
    appNotify("Ticket SLA", escapeSql("$subject_line - $ticket_ref - $ticket_subject"), "/agent/ticket.php?ticket_id=$ticket_id", $client_id, $ticket_id);

    // addToMailQueue also inserts raw, and the body's link markup contains
    // single quotes - escape whole strings once here. The body keeps its HTML,
    // so it gets mysqli_real_escape_string directly (escapeSql strips tags),
    // same as the ticket email parser does for its bodies.
    $email_subject = escapeSql("$subject_line: $ticket_ref - $ticket_subject");
    $email_body = mysqli_real_escape_string($mysqli, "Hello,<br><br>$body_line<br><br>Ticket: $ticket_ref<br>Subject: $ticket_subject<br><br><a href='https://$config_base_url/agent/ticket.php?ticket_id=$ticket_id'>View ticket</a>");

    $email_data = [];

    if (!empty($sla_notification_email)) {
        $email_data[] = [
            'from' => $from_email,
            'from_name' => escapeSql($from_name),
            'recipient' => $sla_notification_email,
            'recipient_name' => 'SLA Notifications',
            'subject' => $email_subject,
            'body' => $email_body,
        ];
    }

    // Assigned agent (skip a duplicate if they are also the notification address)
    if (!empty($ticket['user_email']) && strtolower($ticket['user_email']) != strtolower($sla_notification_email)) {
        $email_data[] = [
            'from' => $from_email,
            'from_name' => escapeSql($from_name),
            'recipient' => $ticket['user_email'],
            'recipient_name' => escapeSql(strval($ticket['user_name'])),
            'subject' => $email_subject,
            'body' => $email_body,
        ];
    }

    if (!empty($email_data)) {
        addToMailQueue($email_data);
    }
}

// --- Response SLA track ---
// Open tickets awaiting a first response, not yet marked breached
$sql_response = mysqli_query($mysqli, "SELECT ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_client_id, ticket_created_at, ticket_response_due_at, ticket_response_sla_alert_stage, sla_response_minutes, user_email, user_name
    FROM tickets
    LEFT JOIN slas ON ticket_sla_id = sla_id
    LEFT JOIN users ON ticket_assigned_to = user_id
    WHERE ticket_sla_id > 0
    AND ticket_response_due_at IS NOT NULL
    AND ticket_first_response_at IS NULL
    AND ticket_resolved_at IS NULL
    AND ticket_closed_at IS NULL
    AND ticket_archived_at IS NULL
    AND ticket_response_sla_alert_stage < 2"
);

while ($ticket = mysqli_fetch_assoc($sql_response)) {

    $ticket_id = intval($ticket['ticket_id']);
    $stage = intval($ticket['ticket_response_sla_alert_stage']);
    $due = strtotime($ticket['ticket_response_due_at']);

    if ($now >= $due) {
        // Breached without a response - the verdict is final, record the miss
        mysqli_query($mysqli, "UPDATE tickets SET ticket_response_sla_alert_stage = 2, ticket_response_sla_met = 0 WHERE ticket_id = $ticket_id");
        sendSlaAlert($ticket, "Response SLA breached", "The response SLA on this ticket was missed (due {$ticket['ticket_response_due_at']}).");

    } elseif ($stage < 1 && $warning_percent) {
        $warn_at = strtotime(addBusinessMinutes($ticket['ticket_created_at'], floor(intval($ticket['sla_response_minutes']) * $warning_percent / 100)));
        if ($now >= $warn_at) {
            mysqli_query($mysqli, "UPDATE tickets SET ticket_response_sla_alert_stage = 1 WHERE ticket_id = $ticket_id");
            sendSlaAlert($ticket, "Response SLA at risk", "This ticket is approaching its response SLA (due {$ticket['ticket_response_due_at']}).");
        }
    }
}

// --- Resolution SLA track ---
// Open tickets with a resolution target, not yet resolved or marked breached
$sql_resolution = mysqli_query($mysqli, "SELECT ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_client_id, ticket_created_at, ticket_resolution_due_at, ticket_resolution_sla_alert_stage, sla_resolution_minutes, user_email, user_name
    FROM tickets
    LEFT JOIN slas ON ticket_sla_id = sla_id
    LEFT JOIN users ON ticket_assigned_to = user_id
    WHERE ticket_sla_id > 0
    AND ticket_resolution_due_at IS NOT NULL
    AND ticket_resolved_at IS NULL
    AND ticket_closed_at IS NULL
    AND ticket_archived_at IS NULL
    AND ticket_resolution_sla_alert_stage < 2"
);

while ($ticket = mysqli_fetch_assoc($sql_resolution)) {

    $ticket_id = intval($ticket['ticket_id']);
    $stage = intval($ticket['ticket_resolution_sla_alert_stage']);
    $due = strtotime($ticket['ticket_resolution_due_at']);

    if ($now >= $due) {
        mysqli_query($mysqli, "UPDATE tickets SET ticket_resolution_sla_alert_stage = 2, ticket_resolution_sla_met = 0 WHERE ticket_id = $ticket_id");
        sendSlaAlert($ticket, "Resolution SLA breached", "The resolution SLA on this ticket was missed (due {$ticket['ticket_resolution_due_at']}).");

    } elseif ($stage < 1 && $warning_percent) {
        $warn_at = strtotime(addBusinessMinutes($ticket['ticket_created_at'], floor(intval($ticket['sla_resolution_minutes']) * $warning_percent / 100)));
        if ($now >= $warn_at) {
            mysqli_query($mysqli, "UPDATE tickets SET ticket_resolution_sla_alert_stage = 1 WHERE ticket_id = $ticket_id");
            sendSlaAlert($ticket, "Resolution SLA at risk", "This ticket is approaching its resolution SLA (due {$ticket['ticket_resolution_due_at']}).");
        }
    }
}
