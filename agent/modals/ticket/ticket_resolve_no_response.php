<?php

/*
 * Resolve - no response: for a client who hasn't answered after several
 * tries. The message from Admin > Settings > Tickets, filled in for this
 * ticket, to change before it goes - it goes on the ticket as a public note
 * and, with client emails on, to the contact and watchers in place of the
 * usual "resolved" words. Closing it later asks them nothing.
 * resolve_ticket_no_response (agent/post/ticket.php). ?ticket_id=
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$row = ticketForModal($_GET['ticket_id'] ?? 0, 2, true);
$ticket_id = intval($row['ticket_id']);

// Who it goes to - as the Resolve button's own email
$watcher_count = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM ticket_watchers WHERE watcher_ticket_id = $ticket_id"))[0] ?? 0);
$contact_email = trim((string) ($row['contact_email'] ?? ''));
$emailed_to = [];
if ($contact_email !== '') {
    $emailed_to[] = trim((string) $row['contact_name']) !== '' ? (string) $row['contact_name'] : $contact_email;
}
if ($watcher_count) {
    $emailed_to[] = $watcher_count . ' watcher' . ($watcher_count === 1 ? '' : 's');
}
if (!emailReady()) {
    $why_not = 'mail isn\'t set up';
} elseif (intval($config_ticket_client_general_notifications) !== 1) {
    $why_not = 'client emails are off';
} elseif (!$emailed_to) {
    $why_not = 'there\'s no contact or watcher with an email address';
} else {
    $why_not = '';
}

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title">
        <i class="fas fa-fw fa-comment-slash me-2"></i>Resolve - No Response <small class="ms-1 opacity-75"><?= escapeHtml(ticketReference($row)) ?></small>
    </h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<form action="post.php" method="post" autocomplete="off" id="noResponseForm">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="ticket_id" value="<?= $ticket_id ?>">

    <div class="modal-body">
        <p class="small text-body-secondary">
            For a client who hasn't answered after several tries. The message goes on the ticket as a public note<?= $why_not === '' ? ' and is emailed to <strong>' . escapeHtml(implode(' and ', $emailed_to)) . '</strong>' : ' - it isn\'t emailed, as ' . $why_not ?>.
            The ticket is resolved and closes on its own as usual, without asking them how we did. If they reply before then, it reopens.
        </p>

        <label class="form-label" for="noResponseMessage">Message <strong class="text-danger">*</strong></label>
        <textarea class="form-control" name="message" id="noResponseMessage" rows="9" maxlength="5000" required><?= escapeHtml(ticketNoResponseText($ticket_id, stripslashes((string) $session_name))) ?></textarea>
        <div class="form-text">Change it here for this ticket. The usual wording is set in Admin &gt; Settings &gt; Tickets.</div>
    </div>

    <div class="modal-footer">
        <button type="submit" name="resolve_ticket_no_response" class="btn btn-success fw-bold"><i class="fas fa-check me-2"></i><?= $why_not === '' ? 'Send and resolve' : 'Resolve' ?></button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<script>
    // Resolving lets go of the ticket's timer, as the Resolve button does (agent/js/ticket_time_tracking.js)
    document.getElementById('noResponseForm').addEventListener('submit', function () {
        try {
            ['-startTime', '-pausedTime'].forEach(function (key) {
                localStorage.removeItem('<?= $ticket_id ?>' + key);
            });
            localStorage.removeItem('ticket-timer-running-<?= $ticket_id ?>');
        } catch (e) {
            // No storage - nothing to let go of
        }
    });
</script>

<?php

require_once '../../../includes/modal_footer.php';
