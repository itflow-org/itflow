<?php
/*
 * API - Recurring Tickets - Raise
 * POST /api/v1/recurring_tickets/raise.php
 *
 * Raise its ticket now, as Raise now does - set up as the schedule says, the client
 * told when general notifications are on, and the new-ticket address told. Its
 * next run moves on, so this one takes that one's place.
 *
 * Parameters (POST, JSON body): api_key, recurring_ticket_id, client_id (its client -
 * needed when the key's user is limited to certain clients)
 *
 * Answer: ticket_id, ticket_reference and next_date.
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once "../../../includes/load_global_settings.php";

$update_count = false;

require_once 'rt_find.php';

if ($rt_row) {

    $ticket_id = recurringTicketRunNow($rt_row, 'The API (' . html_entity_decode($api_key_name, ENT_QUOTES, 'UTF-8') . ')');

    if (!$ticket_id) {
        http_response_code(400);
        $return_arr['success'] = "False";
        $return_arr['message'] = 'The ticket could not be raised.';
        echo json_encode($return_arr);
        exit();
    }

    $update_count = 1;
    $ticket = ticketRow($ticket_id);
    $return_arr['ticket_id'] = intval($ticket_id);
    $return_arr['ticket_reference'] = ticketReference($ticket);
    $return_arr['next_date'] = substr((string) getFieldById('recurring_tickets', $recurring_ticket_id, 'recurring_ticket_next_run'), 0, 10);

    logAudit("API", "Success", "Raised ticket " . escapeSql(ticketReference($ticket)) . " from recurring ticket " . escapeSql($rt_row['recurring_ticket_subject']) . " via API ($api_key_name)", $client_id);
}

// Output
require_once '../update_output.php';
