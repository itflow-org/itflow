<?php

/*
 * ITFlow - POST/GET request handler for the agents helping on a ticket
 * (functions/ticket_helpers.php, agent/modals/ticket/ticket_helper.php).
 * Modify on Support, and the ticket's client.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['save_ticket_helper'])) {

    validateCSRFToken();

    $row = ticketForRequest($_POST['ticket_id'] ?? 0, 2);
    if (!ticketIsOpen($row)) {
        flashAlert("Ticket <strong>" . escapeHtml(ticketReference($row)) . "</strong> is resolved or closed - reopen it to add agents.", 'error');
        redirect();
    }

    $mode = (string) ($_POST['helper_mode'] ?? 'lead');
    $start = $end = '';
    if ($mode === 'own') {
        // Typed on the agent's own clock - kept in the company's (functions/user_timezone.php)
        $start = ticketScheduleValue(timezoneFromView(str_replace('T', ' ', trim((string) ($_POST['scheduled_date_time'] ?? '')))));
        $end = $start !== '' ? date('Y-m-d H:i:s', strtotime($start) + ticketScheduleMinutesValue($_POST['schedule_minutes'] ?? 0) * 60) : '';
    }

    [$ok, $message, $conflicts] = ticketHelperSave($row, intval($_POST['user_id'] ?? 0), $mode, $start, $end);
    if (!$ok) {
        flashAlert(escapeHtml($message), 'error');
        redirect();
    }

    if ($conflicts) {
        flashAlert(escapeHtml($message) . ".<br>They already have work at that time: <br>" . implode(", <br>", array_map('escapeHtml', $conflicts)), 'warning');
    } else {
        flashAlert(escapeHtml($message));
    }

    redirect();

}

if (isset($_GET['remove_ticket_helper'])) {

    validateCSRFToken();

    $row = ticketForRequest($_GET['remove_ticket_helper'], 2);

    $name = ticketHelperRemove($row, intval($_GET['user_id'] ?? 0));
    if ($name === false) {
        flashAlert('That agent is not on this ticket.', 'error');
        redirect();
    }

    flashAlert(escapeHtml($name) . ' taken off the ticket');

    redirect();

}
