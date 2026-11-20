<?php

/*
 * ITFlow - POST handlers for related tickets (functions/ticket_link.php): the ticket page's
 * Link window and the x on each related ticket, and Link together on the tickets list.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

// From a ticket's Link window: this ticket and the ones picked, each a ticket of a client the agent can open
if (isset($_POST['link_tickets'])) {

    validateCSRFToken();

    $row = ticketForRequest($_POST['ticket_id'] ?? 0, 2);
    $ticket_id = intval($row['ticket_id']);

    $ids = [$ticket_id];
    foreach (array_unique(array_map('intval', (array) ($_POST['related_ticket_ids'] ?? []))) as $related_id) {
        $related = $related_id ? ticketRow($related_id) : null;
        if ($related && (!intval($related['ticket_client_id']) || hasClientAccess($related['ticket_client_id']))) {
            $ids[] = $related_id;
        }
    }
    if (count($ids) < 2) {
        flashAlert('Pick the tickets to link it with.', 'error');
        redirect();
    }

    $joined = ticketLinkTogether($ids);
    $related_count = count(ticketRelated($ticket_id));

    logAudit("Ticket", "Link", "$session_name linked ticket " . escapeSql(ticketReference($row)) . " with " . (count($ids) - 1) . " other ticket(s)", intval($row['ticket_client_id']), $ticket_id);

    flashAlert($joined
        ? "<strong>" . escapeHtml(ticketReference($row)) . "</strong> is related to <strong>$related_count</strong> ticket" . ($related_count === 1 ? '' : 's') . " now"
        : 'They were already related.');

    redirect();

}

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
