<?php

/*
 * ITFlow - POST handlers for related tickets (functions/ticket_link.php): the x on each related
 * ticket, and Link together on the tickets list. Linking from a ticket is its Link picker
 * (functions/link_picker.php).
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

// The x on a related ticket - it comes out of the group, the rest stay related
if (isset($_GET['unlink_ticket'])) {

    validateCSRFToken();

    $row = ticketForRequest($_GET['unlink_ticket'], 2);
    $ticket_id = intval($row['ticket_id']);

    if (ticketUnlink($ticket_id)) {
        logAudit("Ticket", "Unlink", "$session_name took ticket " . escapeSql(ticketReference($row)) . " out of its related tickets", intval($row['ticket_client_id']), $ticket_id);
        flashAlert("<strong>" . escapeHtml(ticketReference($row)) . "</strong> is no longer related to the others");
    }

    redirect();

}

// Bulk Action > Link together on the tickets list - the tickets ticked, as one group
if (isset($_POST['bulk_link_tickets'])) {

    validateCSRFToken();

    $rows = ticketsForBulk(2);
    if (count($rows) < 2) {
        flashAlert('Tick at least two tickets to link together.', 'error');
        redirect();
    }

    $joined = ticketLinkTogether(array_keys($rows));

    logAudit("Ticket", "Bulk Link", "$session_name linked " . count($rows) . " tickets together");

    flashAlert($joined ? "Linked <strong>" . count($rows) . "</strong> tickets together" : 'They were already related.');

    redirect();

}
