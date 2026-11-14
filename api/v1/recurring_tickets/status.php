<?php
/*
 * API - Recurring Tickets - Status
 * POST /api/v1/recurring_tickets/status.php
 *
 * Pause a recurring ticket (status 0) - kept, raising nothing - or resume it
 * (status 1), as Pause and Resume do. Runs it missed while paused aren't
 * raised; it picks up from today.
 *
 * Parameters (POST, JSON body): api_key, recurring_ticket_id, client_id (its client -
 * needed when the key's user is limited to certain clients), status (0 or 1)
 *
 * Answer: next_date.
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

$update_count = false;

require_once 'rt_find.php';

if ($rt_row && isset($_POST['status'])) {

    $pause = !intval($_POST['status']);
    $subject = escapeSql($rt_row['recurring_ticket_subject']);
    $next_run = substr((string) $rt_row['recurring_ticket_next_run'], 0, 10);

    if ($pause) {
        mysqli_query($mysqli, "UPDATE recurring_tickets SET recurring_ticket_paused_at = COALESCE(recurring_ticket_paused_at, NOW()) WHERE recurring_ticket_id = $recurring_ticket_id");
    } else {
        if ($next_run < date('Y-m-d')) {
            $next_run = recurringTicketNextRun($next_run, $rt_row['recurring_ticket_frequency'], $rt_row['recurring_ticket_start_date'], date('Y-m-d', strtotime('-1 day')));
        }
        mysqli_query($mysqli, "UPDATE recurring_tickets SET recurring_ticket_paused_at = NULL, recurring_ticket_next_run = '" . escapeSql($next_run) . "' WHERE recurring_ticket_id = $recurring_ticket_id");
    }

    $update_count = 1;
    $return_arr['next_date'] = $next_run;

    logAudit("Recurring Ticket", "Edit", ($pause ? 'Paused' : 'Resumed') . " recurring ticket $subject via API ($api_key_name)", $client_id, $recurring_ticket_id);
    logAudit("API", "Success", ($pause ? 'Paused' : 'Resumed') . " recurring ticket $subject via API ($api_key_name)", $client_id);
}

// Output
require_once '../update_output.php';
