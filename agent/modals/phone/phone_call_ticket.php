<?php

/*
 * Calls - Add to Ticket: one of the call's client's open tickets (the contact's first), with an
 * internal note saying what the call was and, ticked, its talk time as the time worked - rounded
 * up to the minute (phoneCallToTicket(), functions/phone.php; saved by phone_call_ticket in
 * agent/post/phone.php). New Ticket opens New Ticket with the call filled in, and puts the call on
 * the ticket it makes.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$call = phoneCallRow($_GET['id'] ?? 0);
if (!$call || (phoneCallClientFor($call) && !hasClientAccess($call['phone_call_client_id']))) {
    echo json_encode(['error' => 'That call is gone.']);
    exit;
}
$phone_call_id = intval($call['phone_call_id']);
// A caller nobody knows can go on any client's ticket
$client_id = phoneCallClientFor($call);
$contact_id = intval($call['phone_call_contact_id']);
$talk = $call['phone_call_status'] === 'answered' ? intval($call['phone_call_talk_seconds']) : 0;
$sentence = phoneCallSentence($call);

// The open tickets it could go on - the client's, the contact's first; with no client, any the agent can reach
$tickets = [];
$where = $client_id ? "ticket_client_id = $client_id" : "1 = 1" . clientScopeSql('ticket_client_id');
$sql = mysqli_query($mysqli, "SELECT ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_contact_id, ticket_updated_at, ticket_created_at, client_name
    FROM tickets
    LEFT JOIN clients ON client_id = ticket_client_id
    WHERE $where AND ticket_closed_at IS NULL AND ticket_resolved_at IS NULL
    ORDER BY ticket_contact_id = $contact_id DESC, COALESCE(ticket_updated_at, ticket_created_at) DESC
    LIMIT 200");
while ($row = mysqli_fetch_assoc($sql)) {
    $tickets[] = $row;
}
$picked = intval($call['phone_call_ticket_id']) ?: (($tickets && $contact_id && intval($tickets[0]['ticket_contact_id']) === $contact_id) ? intval($tickets[0]['ticket_id']) : 0);
// The ticket made for it while it rang (js/phone_pop.js)
$asked = intval($_GET['ticket_id'] ?? 0);
if ($asked && in_array($asked, array_map('intval', array_column($tickets, 'ticket_id')), true)) {
    $picked = $asked;
}

// New Ticket, with the call in it
$who = phoneCallWho($call);
$new_ticket_url = 'modals/ticket/ticket_add.php?' . http_build_query(array_filter([
    'client_id' => $client_id ?: null,
    'contact_id' => $contact_id ?: null,
    'subject' => ($call['phone_call_direction'] === 'outbound' ? 'Call to ' : 'Call from ') . $who,
    'details' => $sentence,
    'phone_call_id' => $phone_call_id,
]));

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-life-ring me-2"></i>Add Call to Ticket</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="phone_call_id" value="<?= $phone_call_id ?>">

    <div class="modal-body">
        <p class="mb-3"><i class="fas fa-fw fa-phone-volume text-secondary me-1"></i><?= escapeHtml($sentence) ?> <span class="small text-body-secondary"><?= formatDateTime($call['phone_call_started_at']) ?></span></p>

        <?php if (intval($call['phone_call_ticket_id']) && $call['ticket_number'] !== null) { ?>
            <div class="alert alert-info small py-2"><i class="fas fa-fw fa-info-circle me-1"></i>It is on <?= escapeHtml($call['ticket_prefix'] . $call['ticket_number']) ?> already - adding it again puts another note there.</div>
        <?php } ?>

        <div class="mb-3">
            <label class="form-label">Ticket <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-life-ring"></i></span>
                <select class="form-select select2" name="ticket_id" required>
                    <option value="">- Select an open ticket -</option>
                    <?php foreach ($tickets as $ticket) {
                        $ticket_id = intval($ticket['ticket_id']); ?>
                        <option value="<?= $ticket_id ?>" <?= $ticket_id === $picked ? 'selected' : '' ?><?= selectOptionData(['icon' => 'fas fa-life-ring', 'description' => ($client_id ? '' : $ticket['client_name'] . ' - ') . (intval($ticket['ticket_contact_id']) === $contact_id && $contact_id ? 'their ticket' : 'updated ' . timeAgo($ticket['ticket_updated_at'] ?: $ticket['ticket_created_at']))]) ?>><?= escapeHtml($ticket['ticket_prefix'] . $ticket['ticket_number'] . ' - ' . $ticket['ticket_subject']) ?></option>
                    <?php } ?>
                </select>
            </div>
            <?php if (!$tickets) { ?>
                <div class="form-text"><?= $client_id ? 'This client has no open tickets' : 'There are no open tickets' ?> - New Ticket makes one with the call on it.</div>
            <?php } ?>
        </div>

        <?php if ($talk > 0) { ?>
            <div class="form-check form-switch mb-3">
                <input type="checkbox" class="form-check-input" name="count_time" value="1" id="phoneCallCountTime" checked>
                <label class="form-check-label" for="phoneCallCountTime">Count the call as time worked <span class="text-body-secondary">- <?= escapeHtml(phoneSecondsText($talk)) ?>, as <?= escapeHtml(formatDuration(phoneTimeWorked($talk))) ?></span></label>
            </div>
        <?php } ?>

        <div>
            <label class="form-label" for="phoneCallNote">Note</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-sticky-note"></i></span>
                <textarea class="form-control" name="note" id="phoneCallNote" rows="3" maxlength="5000" placeholder="What the call was about"></textarea>
            </div>
            <div class="form-text">An internal note on the ticket says what the call was - this goes with it.</div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-outline-primary me-auto ajax-modal" data-modal-size="lg" data-modal-url="<?= escapeHtml($new_ticket_url) ?>"><i class="fas fa-plus me-2"></i>New Ticket</button>
        <button type="submit" name="phone_call_ticket" class="btn btn-primary fw-bold"><i class="fas fa-check me-2"></i>Add to Ticket</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>
<?php
require_once '../../../includes/modal_footer.php';
