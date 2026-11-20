<?php

require_once '../../../includes/modal_header.php';

/*
 * Quick ticket - the top bar's + (functions/quick_ticket.php): the client, the
 * contact and what the issue is, for a call or a walk-up, with the contact's
 * tickets still open shown first. It posts New Ticket's add_ticket with
 * quick_ticket set - what it starts with is in Admin > Settings > Tickets.
 * Opened on a client's page, it starts on that client. Script:
 * agent/js/ticket_quick.js.
 */

enforceUserPermission('module_support', 2);

if (!quickTicketCan()) {
    echo json_encode(['error' => 'Quick ticket is switched off - New Ticket is on the Tickets page.']);
    exit;
}

$quick = quickTicketSettings();
$client_id = intval($_GET['client_id'] ?? 0);
// From the top bar's timer started before there was a ticket - the new ticket's timer carries on from it
$from_call = !empty($_GET['call']);
if ($client_id && !hasClientAccess($client_id)) {
    $client_id = 0;
}
$assignee = quickTicketAssignee();
$assignee_name = $assignee && $assignee !== intval($session_user_id) ? (string) getFieldById('users', $assignee, 'user_name') : '';
$status = isset(ticketPickableStatuses()[$quick['status']]) ? $quick['status'] : 0;

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-plus-circle me-2"></i>Quick ticket</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="/agent/post.php" method="post" autocomplete="off" id="quickTicketForm" data-timer="<?= escapeHtml($quick['timer']) ?>" data-call="<?= $from_call ? 1 : 0 ?>" data-primary="<?= $quick['primary'] ? 1 : 0 ?>" data-open-tickets="<?= $quick['open_tickets'] ? 1 : 0 ?>">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="quick_ticket" value="1">
    <input type="hidden" name="quick_ticket_timer" value="" id="quickTicketTimer">
    <input type="hidden" name="quick_ticket_paused" value="" id="quickTicketPaused">
    <input type="hidden" name="quick_ticket_call" value="<?= $from_call ? 1 : 0 ?>">
    <input type="hidden" name="status" value="<?= $status ?>">
    <input type="hidden" name="category_id" value="<?= intval($quick['category']) ?>">
    <input type="hidden" name="billable" value="<?= quickTicketBillable() ? 1 : 0 ?>">

    <div class="modal-body">

        <div class="mb-3">
            <label>Client <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                <select class="form-select select2" name="client_id" id="quickTicketClient" required>
                    <option value="">- Select a Client -</option>
                    <?php
                    $sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients
                        WHERE client_archived_at IS NULL AND client_lead = 0 " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
                    while ($row = mysqli_fetch_assoc($sql)) { ?>
                        <option value="<?= intval($row['client_id']) ?>"<?= intval($row['client_id']) === $client_id ? ' selected' : '' ?><?= selectOptionClient($row['client_id'], $row['client_name'], $row['client_abbreviation']) ?>><?= escapeHtml($row['client_name']) ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>

        <div class="mb-3">
            <label>Contact</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                <select class="form-select select2" name="contact_id" id="quickTicketContact" data-placeholder="Pick the client first" disabled>
                    <option value="0">- No One -</option>
                </select>
            </div>
            <div class="form-text">Type a name, an email address or a phone number to find them.</div>
        </div>

        <!-- The contact's tickets still open - ajax.php?ticket_also_open with work -->
        <div id="quickTicketOpen"></div>

        <div class="mb-3">
            <label>What is the issue? <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                <input type="text" class="form-control" name="subject" id="quickTicketSubject" maxlength="500" placeholder="Outlook keeps asking for a password" required>
            </div>
        </div>

        <div class="mb-3">
            <label>More detail</label>
            <textarea class="form-control" name="details" rows="3" maxlength="20000" placeholder="What they told you, any error message, what has been tried"></textarea>
        </div>

        <div class="row align-items-end">
            <div class="col-sm-5">
                <div class="mb-3">
                    <label>Priority</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-thermometer-half"></i></span>
                        <select class="form-select select2" name="priority">
                            <?php foreach (['Low', 'Medium', 'High', 'Urgent'] as $priority) { ?>
                                <option<?= $priority === $quick['priority'] ? ' selected' : '' ?><?= selectOptionPriority($priority) ?>><?= $priority ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="col-sm-7">
                <div class="form-check form-switch mb-3 pb-2">
                    <input type="checkbox" class="form-check-input" role="switch" id="quickTicketAssign" name="assigned_to" value="<?= $assignee ?: intval($session_user_id) ?>"<?= $assignee ? ' checked' : '' ?>>
                    <label class="form-check-label" for="quickTicketAssign">Assign it to <?= $assignee_name !== '' ? escapeHtml($assignee_name) : 'me' ?></label>
                </div>
            </div>
        </div>

        <?php if ($from_call) { ?>
            <div class="small text-body-secondary"><i class="fas fa-fw fa-stopwatch me-1"></i>Its timer carries on from the one you started<span data-call-time></span>.</div>
        <?php } elseif ($quick['timer'] !== 'off') { ?>
            <div class="small text-body-secondary"><i class="fas fa-fw fa-stopwatch me-1"></i>Its timer starts <?= $quick['timer'] === 'open' ? 'from when this window opened' : 'as it is made' ?>.</div>
        <?php } ?>

    </div>

    <div class="modal-footer">
        <button type="submit" name="add_ticket" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Make ticket</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>

</form>

<script src="<?= staticFileUrl('/agent/js/ticket_quick.js') ?>"></script>

<?php
require_once '../../../includes/modal_footer.php';
