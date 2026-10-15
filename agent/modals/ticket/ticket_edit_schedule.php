<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

// The ticket, after the module and client checks - or the modal's error
$row = ticketForModal($_GET['ticket_id'] ?? 0, 2, true);
$ticket_id = intval($row['ticket_id']);

$ticket_prefix = escapeHtml($row['ticket_prefix']);
$ticket_number = intval($row['ticket_number']);
$ticket_scheduled_for = escapeHtml($row['ticket_schedule']);
$ticket_onsite = intval($row['ticket_onsite']);
$client_name = escapeHtml($row['client_name']);
$client_id = intval($row['ticket_client_id']);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title">
        <i class="fa fa-fw fa-calendar-check me-2"></i>Schedule <small class="ms-1 opacity-75"><?= "$ticket_prefix$ticket_number" ?></small>
    </h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="ticket_id" value="<?= $ticket_id ?>">

        <div class="mb-3">
            <label>Date and time</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar-day"></i></span>
                <input type="datetime-local" class="form-control" name="scheduled_date_time" placeholder="Scheduled Date & Time" min="<?= date('Y-m-d\TH:i') ?>" <?php if ($ticket_scheduled_for) { echo "value='$ticket_scheduled_for'"; } ?>>
            </div>
        </div>

        <div class="mb-3">
            <label>Visit</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                <select class="form-select" name="onsite" required>
                    <option value="0" <?php if ($ticket_onsite == 0) echo "selected"; ?>>Remote</option>
                    <option value="1" <?php if ($ticket_onsite == 1) echo "selected"; ?>>Onsite</option>
                </select>
            </div>
        </div>
    </div>

    <div class="modal-footer">
    <?php if ($ticket_scheduled_for) { ?>
        <a href="post.php?cancel_ticket_schedule=<?= $ticket_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="btn btn-outline-danger confirm-link me-auto" data-confirm-title="Remove from the schedule?" data-confirm-text="A cancellation email goes to the assigned agent, and to the contact and watchers if client notifications are on." data-confirm-button="Remove from schedule">
            <i class="fa fa-calendar-times me-2"></i>Remove from schedule
        </a>
    <?php } ?>
        <button type="submit" name="edit_ticket_schedule" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>

</form>

<?php
require_once '../../../includes/modal_footer.php';
