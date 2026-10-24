<?php

/*
 * Edit a ticket status. The fields are ticket_status_form.php - a built-in
 * status shows its fixed answers rather than asking.
 */

require_once '../../includes/modal_header.php';

$ticket_status = ticketStatusRow(intval($_GET['id'] ?? 0));

ob_start();

if (!$ticket_status) { ?>
    <div class="modal-body text-center text-secondary py-5">That status is gone - it may have been deleted.</div>
<?php } else {
    $ticket_status_id = intval($ticket_status['ticket_status_id']); ?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-info-circle me-2"></i>Ticket Status: <strong><?= escapeHtml($ticket_status['ticket_status_name']) ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="ticket_status_id" value="<?= $ticket_status_id ?>">

    <div class="modal-body">
        <?php if (intval($ticket_status['ticket_status_active']) !== 1) { ?>
            <div class="alert alert-secondary small py-2"><i class="fas fa-fw fa-power-off me-1"></i>Switched off - it can't be picked, and isn't on the board. Switch it on from the list.</div>
        <?php } ?>
        <?php require __DIR__ . '/ticket_status_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_ticket_status" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Save changes</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php }
require_once '../../../includes/modal_footer.php';
