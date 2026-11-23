<?php

/*
 * ITFlow - calls from a phone system (functions/phone.php): Add to Ticket - the call on one of the
 * client's open tickets, as an internal note with its talk time. New Ticket puts it on the ticket
 * it makes from add_ticket (agent/post/ticket.php).
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['phone_call_ticket'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $call = phoneCallRow($_POST['phone_call_id'] ?? 0);
    if (!$call || (phoneCallClientFor($call) && !hasClientAccess($call['phone_call_client_id']))) {
        flashAlert('That call is gone.', 'error');
        redirect();
    }
    $ticket = ticketRow($_POST['ticket_id'] ?? 0);
    if (!$ticket || !hasClientAccess($ticket['ticket_client_id'])) {
        flashAlert('Pick one of the open tickets.', 'error');
        redirect();
    }
    $client_id = intval($ticket['ticket_client_id']);
    if (phoneCallClientFor($call) && phoneCallClientFor($call) !== $client_id) {
        flashAlert('That ticket is another client\'s.', 'error');
        redirect();
    }

    $count_time = intval($_POST['count_time'] ?? 0) === 1;
    phoneCallToTicket($call, $ticket, intval($session_user_id), $count_time, (string) ($_POST['note'] ?? ''));

    $reference = ticketReference($ticket);
    $talk = $call['phone_call_status'] === 'answered' ? intval($call['phone_call_talk_seconds']) : 0;
    logTicketHistory(intval($ticket['ticket_id']), escapeSql("$session_name added a phone call: " . phoneCallSentence($call)));
    logAudit("Ticket", "Edit", "$session_name added a phone call to ticket " . escapeSql($reference . ' - ' . phoneCallSentence($call)), $client_id, intval($ticket['ticket_id']));

    flashAlert('Call added to <strong>' . escapeHtml($reference) . '</strong>'
        . ($count_time && $talk > 0 ? ' with ' . escapeHtml(formatDuration(phoneTimeWorked($talk))) . ' worked' : ''));
    redirect();

}
