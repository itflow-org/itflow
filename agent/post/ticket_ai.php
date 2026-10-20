<?php

/*
 * ITFlow - AI on a ticket (functions/ai_ticket.php): a summary saved as an
 * internal note, and triage - asked for from the ticket's menu, then applied
 * or dismissed from the suggestion on the ticket. Each needs the role's Use
 * AI permission, Support to change tickets, and the ticket's client.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

// The AI summary, kept on the ticket as an internal note
if (isset($_POST['add_ticket_ai_note'])) {

    validateCSRFToken();

    $row = ticketForRequest($_POST['ticket_id'] ?? 0, 2);
    $ticket_id = intval($row['ticket_id']);
    if (!empty($row['ticket_closed_at'])) {
        flashAlert('Ticket <strong>' . escapeHtml(ticketReference($row)) . '</strong> is closed - nothing more goes on it.', 'error');
        redirect();
    }
    // What the window showed, cleaned again - it came back from the page
    $note = trim(documentHtmlPurify((string) ($_POST['note'] ?? '')));
    if (trim(strip_tags($note)) === '') {
        flashAlert('There was no summary to save - ask for it again.', 'error');
        redirect();
    }
    $note = mysqli_real_escape_string($mysqli, '<p><strong>AI summary</strong></p>' . $note);

    mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = '$note', ticket_reply_type = 'Internal', ticket_reply_time_worked = '00:00:00', ticket_reply_by = $session_user_id, ticket_reply_ticket_id = $ticket_id");
    mysqli_query($mysqli, "UPDATE tickets SET ticket_updated_at = NOW() WHERE ticket_id = $ticket_id");

    logAudit("Ticket", "Reply", "$session_name saved an AI summary as an internal note on ticket " . escapeSql(ticketReference($row)), $client_id, $ticket_id);
    flashAlert('Summary saved as an internal note on <strong>' . escapeHtml(ticketReference($row)) . '</strong>');
    redirect();

}

// Triage with AI, from the ticket's menu - what it suggests shows on the ticket, to apply or dismiss
if (isset($_GET['triage_ticket_ai'])) {

    validateCSRFToken();

    $row = ticketForRequest($_GET['triage_ticket_ai'], 2);
    if (!aiCan()) {
        permissionRefuse(permissionRefusedText('module_ai', 1, 0));
    }
    $result = aiTicketTriage(intval($row['ticket_id']));
    if (!$result['ok']) {
        flashAlert('AI triage didn\'t work - ' . escapeHtml($result['error']), 'error');
        redirect();
    }
    $changes = aiTriageChanges($result['triage'], $row);
    flashAlert($changes ? 'AI triage suggests a ' . escapeHtml(implode(' and a ', array_keys($changes))) . ' - apply it or dismiss it on the ticket' : 'AI triage agrees with the category and priority it has', 'info');
    redirect();

}

// Apply what AI triage suggests - or dismiss it
if (isset($_POST['apply_ticket_ai_triage']) || isset($_POST['dismiss_ticket_ai_triage'])) {

    validateCSRFToken();

    $row = ticketForRequest($_POST['ticket_id'] ?? 0, 2);
    $ticket_id = intval($row['ticket_id']);
    $triage = aiTriageRow($ticket_id);
    if (!$triage || $triage['ai_triage_state'] !== 'suggested') {
        flashAlert('There is no AI triage waiting on this ticket.', 'info');
        redirect();
    }

    if (isset($_POST['dismiss_ticket_ai_triage'])) {
        mysqli_query($mysqli, "UPDATE ai_ticket_triage SET ai_triage_state = 'dismissed', ai_triage_by = $session_user_id WHERE ai_triage_ticket_id = $ticket_id");
        flashAlert('AI triage dismissed', 'info');
        redirect();
    }

    if (!ticketIsOpen($row)) {
        flashAlert('Ticket <strong>' . escapeHtml(ticketReference($row)) . '</strong> isn\'t open - reopen it to change it.', 'error');
        redirect();
    }
    // Those ticked - both, without the window's script
    $only = array_values(array_intersect((array) ($_POST['apply'] ?? ['category', 'priority']), ['category', 'priority']));
    $changes = aiTriageApply($ticket_id, stripslashes($session_name), $only, 'applied', $session_user_id);
    if (!$changes) {
        flashAlert('Nothing to change - the ticket has that already', 'info');
        redirect();
    }
    $said = [];
    foreach ($changes as $field => $change) {
        $said[] = ucfirst($field) . ' <strong>' . escapeHtml($change[1]) . '</strong>';
    }
    flashAlert(implode(' and ', $said) . ' set on <strong>' . escapeHtml(ticketReference($row)) . '</strong>');
    redirect();

}
